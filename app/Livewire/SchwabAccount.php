<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\SchwabTraderAuthService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class SchwabAccount extends Component
{
    public bool $isLoading = true;
    public bool $isAuthenticated = false;
    public ?string $error = null;
    public ?string $traderAuthUrl = null;

    public array $accounts = [];
    public ?string $selectedAccountHash = null;
    public array $positions = [];
    public array $orders = [];
    public array $accountSummary = [];

    public string $activeTab = 'overview';
    public string $ordersFilter = 'ALL';

    public function mount(): void
    {
        $this->traderAuthUrl = route('schwab.trader.redirect');
        $this->loadAccounts();
    }

    public function loadAccounts(): void
    {
        $this->isLoading = true;
        $this->error = null;

        $traderAuth = SchwabTraderAuthService::make();
        $token = $traderAuth->getAccessToken();

        if (!$token) {
            $this->isAuthenticated = false;
            $this->isLoading = false;
            return;
        }

        $this->isAuthenticated = true;

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ])->get('https://api.schwabapi.com/trader/v1/accounts', [
                'fields' => 'positions',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $this->accounts = is_array($data) ? $data : [];

                if (!empty($this->accounts) && !$this->selectedAccountHash) {
                    $first = $this->accounts[0];
                    $this->selectedAccountHash = $first['hashValue'] ?? ($first['securitiesAccount']['accountNumber'] ?? null);
                }

                $this->buildAccountSummary();
                $this->extractPositions();
                $this->loadOrders($token);
            } else {
                $this->error = 'Error fetching accounts: HTTP ' . $response->status();
                if ($response->status() === 401) {
                    $this->isAuthenticated = false;
                    $this->error = 'Session expired. Please re-authenticate with Schwab.';
                }
                Log::error('SchwabAccount: accounts fetch failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            $this->error = 'Connection error: ' . $e->getMessage();
            Log::error('SchwabAccount: exception', ['error' => $e->getMessage()]);
        }

        $this->isLoading = false;
    }

    private function loadOrders(string $token): void
    {
        try {
            $params = [
                'fromEnteredTime' => now()->subDays(60)->format('Y-m-d\TH:i:s.000\Z'),
                'toEnteredTime'   => now()->addDay()->format('Y-m-d\TH:i:s.000\Z'),
                'maxResults'      => 100,
            ];

            if ($this->ordersFilter !== 'ALL') {
                $params['status'] = $this->ordersFilter;
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ])->get('https://api.schwabapi.com/trader/v1/orders', $params);

            if ($response->successful()) {
                $this->orders = $response->json() ?? [];
            } else {
                Log::warning('SchwabAccount: orders fetch failed', ['status' => $response->status()]);
                $this->orders = [];
            }
        } catch (\Exception $e) {
            Log::error('SchwabAccount: orders exception', ['error' => $e->getMessage()]);
            $this->orders = [];
        }
    }

    private function buildAccountSummary(): void
    {
        $totalLiquidationValue = 0;
        $totalCashBalance = 0;
        $totalBuyingPower = 0;
        $totalDayPnl = 0;
        $totalUnrealizedPnl = 0;
        $totalMarginBalance = 0;

        foreach ($this->accounts as $account) {
            $sec = $account['securitiesAccount'] ?? [];
            $currentBalances = $sec['currentBalances'] ?? [];
            $projectedBalances = $sec['projectedBalances'] ?? [];

            $totalLiquidationValue += (float) ($currentBalances['liquidationValue'] ?? $currentBalances['totalCash'] ?? 0);
            $totalCashBalance      += (float) ($currentBalances['cashBalance'] ?? $currentBalances['totalCash'] ?? 0);
            $totalBuyingPower      += (float) ($projectedBalances['buyingPower'] ?? $currentBalances['buyingPower'] ?? 0);
            $totalMarginBalance    += (float) ($currentBalances['marginBalance'] ?? 0);

            foreach ($sec['positions'] ?? [] as $pos) {
                $totalUnrealizedPnl += (float) ($pos['currentDayProfitLoss'] ?? 0);
                $totalDayPnl        += (float) ($pos['currentDayProfitLoss'] ?? 0);
            }
        }

        $this->accountSummary = [
            'totalLiquidationValue' => $totalLiquidationValue,
            'totalCashBalance'      => $totalCashBalance,
            'totalBuyingPower'      => $totalBuyingPower,
            'totalMarginBalance'    => $totalMarginBalance,
            'totalDayPnl'           => $totalDayPnl,
            'totalUnrealizedPnl'    => $totalUnrealizedPnl,
            'accountCount'          => count($this->accounts),
        ];
    }

    private function extractPositions(): void
    {
        $this->positions = [];

        foreach ($this->accounts as $account) {
            $sec = $account['securitiesAccount'] ?? [];
            $accountNumber = $sec['accountNumber'] ?? 'Unknown';

            foreach ($sec['positions'] ?? [] as $pos) {
                $instrument = $pos['instrument'] ?? [];
                $this->positions[] = [
                    'account'           => $accountNumber,
                    'symbol'            => $instrument['symbol'] ?? $instrument['underlyingSymbol'] ?? 'N/A',
                    'description'       => $instrument['description'] ?? '',
                    'assetType'         => $instrument['assetType'] ?? 'EQUITY',
                    'longQuantity'      => (float) ($pos['longQuantity'] ?? 0),
                    'shortQuantity'     => (float) ($pos['shortQuantity'] ?? 0),
                    'averagePrice'      => (float) ($pos['averagePrice'] ?? 0),
                    'currentPrice'      => (float) ($pos['currentDayProfitLossPercentage'] ?? 0),
                    'marketValue'       => (float) ($pos['marketValue'] ?? 0),
                    'dayPnl'            => (float) ($pos['currentDayProfitLoss'] ?? 0),
                    'dayPnlPct'         => (float) ($pos['currentDayProfitLossPercentage'] ?? 0),
                    'settledLongQty'    => (float) ($pos['settledLongQuantity'] ?? 0),
                    'settledShortQty'   => (float) ($pos['settledShortQuantity'] ?? 0),
                    'cusip'             => $instrument['cusip'] ?? '',
                ];
            }
        }

        usort($this->positions, fn ($a, $b) => $b['marketValue'] <=> $a['marketValue']);
    }

    public function selectAccount(string $hash): void
    {
        $this->selectedAccountHash = $hash;
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function setOrdersFilter(string $filter): void
    {
        $this->ordersFilter = $filter;
        $this->refresh();
    }

    public function refresh(): void
    {
        $this->loadAccounts();
    }

    public function getSelectedAccount(): ?array
    {
        foreach ($this->accounts as $account) {
            $sec = $account['securitiesAccount'] ?? [];
            $hash = $account['hashValue'] ?? $sec['accountNumber'] ?? null;
            if ($hash === $this->selectedAccountHash) {
                return $account;
            }
        }
        return $this->accounts[0] ?? null;
    }

    public function render()
    {
        return view('livewire.schwab-account', [
            'selectedAccount' => $this->getSelectedAccount(),
        ]);
    }
}

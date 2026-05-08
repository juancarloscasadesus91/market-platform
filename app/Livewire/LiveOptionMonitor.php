<?php

declare(strict_types=1);

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Services\SchwabAuthService;
use App\Services\SchwabTraderAuthService;

class LiveOptionMonitor extends Component
{
    public string $ticker;
    public ?string $selectedContract = null;
    public ?string $selectedExpiration = null;
    public ?string $selectedType = null; // CALL or PUT
    public ?float $selectedStrike = null;

    // Available options
    public array $expirations = [];
    public array $strikes = [];

    // Streaming credentials
    public ?string $streamerSocketUrl = null;
    public ?string $schwabClientCustomerId = null;
    public ?string $schwabClientCorrelId = null;
    public ?string $schwabClientChannel = null;
    public ?string $schwabClientFunctionId = null;
    public ?string $accessToken = null;

    // Error handling
    public ?string $error = null;
    public bool $hasTraderAccess = false;

    public function mount(string $ticker): void
    {
        $this->ticker = strtoupper($ticker);
        $this->loadStreamingCredentials();
        // Don't load options automatically - user will input contract manually
    }

    private function loadStreamingCredentials(): void
    {
        // Use Trader API for streaming credentials AND streaming token
        $traderAuthService = SchwabTraderAuthService::make();
        $traderToken = $traderAuthService->getAccessToken();

        // Store the trader token for streaming
        $this->accessToken = $traderToken;

        if (!$traderToken) {
            $this->error = 'No Trader API token available. Please authenticate Trader API first.';
            return;
        }

        // Check cache first
        $cached = Cache::get('schwab_streaming_credentials');
        if ($cached) {
            $this->streamerSocketUrl = $cached['streamerSocketUrl'] ?? null;
            $this->schwabClientCustomerId = $cached['schwabClientCustomerId'] ?? null;
            $this->schwabClientCorrelId = $cached['schwabClientCorrelId'] ?? null;
            $this->schwabClientChannel = $cached['schwabClientChannel'] ?? null;
            $this->schwabClientFunctionId = $cached['schwabClientFunctionId'] ?? null;
            $this->hasTraderAccess = true;
            return;
        }

        try {
            // Get streaming credentials from Trader API
            $response = Http::timeout(10)->withHeaders([
                'Authorization' => 'Bearer ' . $traderToken,
                'Accept' => 'application/json',
            ])->get('https://api.schwabapi.com/trader/v1/userPreference');

            \Log::info('LiveOptionMonitor: userPreference response', [
                'status' => $response->status(),
                'success' => $response->successful(),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $streamerInfo = $data['streamerInfo'][0] ?? null;

                if ($streamerInfo) {
                    $this->streamerSocketUrl = $streamerInfo['streamerSocketUrl'] ?? null;
                    $this->schwabClientCustomerId = $streamerInfo['schwabClientCustomerId'] ?? null;
                    $this->schwabClientCorrelId = $streamerInfo['schwabClientCorrelId'] ?? null;
                    $this->schwabClientChannel = $streamerInfo['schwabClientChannel'] ?? null;
                    $this->schwabClientFunctionId = $streamerInfo['schwabClientFunctionId'] ?? null;

                    // Cache the credentials for 30 minutes
                    Cache::put('schwab_streaming_credentials', [
                        'streamerSocketUrl' => $this->streamerSocketUrl,
                        'schwabClientCustomerId' => $this->schwabClientCustomerId,
                        'schwabClientCorrelId' => $this->schwabClientCorrelId,
                        'schwabClientChannel' => $this->schwabClientChannel,
                        'schwabClientFunctionId' => $this->schwabClientFunctionId,
                    ], now()->addMinutes(30));

                    \Log::info('LiveOptionMonitor: Streaming credentials loaded', [
                        'socketUrl' => $this->streamerSocketUrl,
                        'customerId' => $this->schwabClientCustomerId ? 'SET' : 'NULL',
                    ]);
                    $this->hasTraderAccess = true;
                }
            } else {
                if ($response->status() === 401) {
                    $this->error = 'Unauthorized: Token expired or invalid';
                } elseif ($response->status() === 403) {
                    $this->error = 'Forbidden: Missing required scope for Trader API';
                } else {
                    $this->error = 'Failed to load streaming credentials';
                }
            }
        } catch (\Exception $e) {
            $this->error = 'Connection error: ' . $e->getMessage();
        }
    }

    private function loadAvailableOptions(): void
    {
        if (!$this->accessToken) {
            \Log::warning('LiveOptionMonitor: No access token for loading options');
            return;
        }

        try {
            $apiSymbol = $this->getApiSymbol();

            \Log::info('LiveOptionMonitor: Loading options', [
                'ticker' => $this->ticker,
                'apiSymbol' => $apiSymbol,
            ]);

            // Limit to next 60 days to avoid buffer overflow
            $fromDate = now()->format('Y-m-d');
            $toDate = now()->addDays(60)->format('Y-m-d');

            $response = Http::timeout(10)->withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Accept' => 'application/json',
            ])->get('https://api.schwabapi.com/marketdata/v1/chains', [
                'symbol' => $apiSymbol,
                'contractType' => 'ALL',
                'includeUnderlyingQuote' => 'true',
                'fromDate' => $fromDate,
                'toDate' => $toDate,
                'strikeCount' => 50, // Limit strikes around ATM
            ]);

            \Log::info('LiveOptionMonitor: Options response', [
                'status' => $response->status(),
                'success' => $response->successful(),
                'body' => $response->body(),
            ]);

            if ($response->successful()) {
                $data = $response->json();

                \Log::info('LiveOptionMonitor: Parsed data', [
                    'hasCallMap' => isset($data['callExpDateMap']),
                    'hasPutMap' => isset($data['putExpDateMap']),
                ]);

                // Extract unique expiration dates
                $expirations = [];
                if (isset($data['callExpDateMap'])) {
                    foreach ($data['callExpDateMap'] as $expDate => $strikes) {
                        $expirations[] = $expDate;
                    }
                }
                $this->expirations = array_unique($expirations);
                sort($this->expirations);

                \Log::info('LiveOptionMonitor: Expirations loaded', [
                    'count' => count($this->expirations),
                    'first' => $this->expirations[0] ?? 'none',
                    'all' => array_slice($this->expirations, 0, 5),
                ]);
            } else {
                \Log::error('LiveOptionMonitor: Failed to load options', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('LiveOptionMonitor: Exception loading options', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);
        }
    }

    public function updatedSelectedExpiration(): void
    {
        $this->loadStrikesForExpiration();
    }

    private function loadStrikesForExpiration(): void
    {
        if (!$this->selectedExpiration || !$this->accessToken) {
            return;
        }

        try {
            $apiSymbol = $this->getApiSymbol();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Accept' => 'application/json',
            ])->get('https://api.schwabapi.com/marketdata/v1/chains', [
                'symbol' => $apiSymbol, // No $ prefix
                'contractType' => 'ALL',
                'includeUnderlyingQuote' => 'true',
            ]);

            if ($response->successful()) {
                $data = $response->json();

                $strikes = [];
                $expDateKey = $this->selectedExpiration . ':0';

                if (isset($data['callExpDateMap'][$expDateKey])) {
                    foreach ($data['callExpDateMap'][$expDateKey] as $strike => $contracts) {
                        $strikes[] = (float)$strike;
                    }
                }

                $this->strikes = array_unique($strikes);
                sort($this->strikes);
            }
        } catch (\Exception $e) {
            // Silent fail
        }
    }

    public function fetchContractSymbol(string $symbol, string $contractType, int $strike, string $date): void
    {
        try {
            $token = $this->accessToken ?? SchwabAuthService::make()->getAccessToken();

            if (!$token) {
                $this->error = 'No API token available';
                return;
            }

            \Log::info('LiveOptionMonitor: Fetching contract', [
                'symbol' => $symbol,
                'contractType' => $contractType,
                'strike' => $strike,
                'date' => $date,
            ]);

            // Get the full contract symbol from API
            $response = \Illuminate\Support\Facades\Http::withToken($token)
                ->timeout(10)
                ->get('https://api.schwabapi.com/marketdata/v1/chains', [
                    'symbol' => $symbol,
                    'contractType' => $contractType,
                    'strike' => $strike,
                    'fromDate' => $date,
                    'toDate' => $date,
                ]);

            if (!$response->successful()) {
                $this->error = 'Failed to fetch contract from API: ' . $response->status();
                \Log::error('LiveOptionMonitor: API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return;
            }

            $data = $response->json();
            $map = $contractType === 'CALL' ? 'callExpDateMap' : 'putExpDateMap';

            // Find the contract
            $fullSymbol = null;
            if (isset($data[$map])) {
                foreach ($data[$map] as $expDate => $strikes) {
                    foreach ($strikes as $strikeKey => $contracts) {
                        if (!empty($contracts)) {
                            $fullSymbol = $contracts[0]['symbol'];
                            break 2;
                        }
                    }
                }
            }

            if (!$fullSymbol) {
                $this->error = 'Contract not found in API response';
                \Log::warning('LiveOptionMonitor: No contract found', [
                    'response' => $data,
                ]);
                return;
            }

            // Add leading dot for streaming
            $this->selectedContract = '.' . $fullSymbol;

            \Log::info('LiveOptionMonitor: Contract loaded successfully', [
                'fullSymbol' => $fullSymbol,
                'streamingSymbol' => $this->selectedContract,
            ]);

            $this->error = null;

            // Dispatch event to update Alpine.js
            $this->dispatch('contract-loaded', symbol: $this->selectedContract);

        } catch (\Exception $e) {
            $this->error = 'Error: ' . $e->getMessage();
            \Log::error('LiveOptionMonitor: Exception fetching contract', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function selectContract(): void
    {
        if (!$this->selectedExpiration || !$this->selectedType || !$this->selectedStrike) {
            $this->error = 'Please select expiration, type, and strike';
            return;
        }

        try {
            $apiSymbol = $this->getApiSymbol();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Accept' => 'application/json',
            ])->get('https://api.schwabapi.com/marketdata/v1/chains', [
                'symbol' => $apiSymbol, // No $ prefix
                'contractType' => $this->selectedType,
                'includeUnderlyingQuote' => 'true',
            ]);

            if ($response->successful()) {
                $data = $response->json();

                $expDateKey = $this->selectedExpiration . ':0';
                $strikeKey = (string)$this->selectedStrike;

                $map = $this->selectedType === 'CALL' ? 'callExpDateMap' : 'putExpDateMap';

                if (isset($data[$map][$expDateKey][$strikeKey])) {
                    $contracts = $data[$map][$expDateKey][$strikeKey];
                    if (!empty($contracts)) {
                        $contract = $contracts[0];
                        $this->selectedContract = $contract['symbol'] ?? null;
                        $this->error = null;

                        // Dispatch event to start streaming
                        $this->dispatch('contract-selected', [
                            'symbol' => $this->selectedContract,
                            'streamerSocketUrl' => $this->streamerSocketUrl,
                            'schwabClientCustomerId' => $this->schwabClientCustomerId,
                            'schwabClientCorrelId' => $this->schwabClientCorrelId,
                            'schwabClientChannel' => $this->schwabClientChannel,
                            'schwabClientFunctionId' => $this->schwabClientFunctionId,
                            'accessToken' => $this->accessToken,
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->error = 'Failed to load contract: ' . $e->getMessage();
        }
    }

    private function getApiSymbol(): string
    {
        // For option chains, SPX needs the $ prefix
        if ($this->ticker === 'SPX') {
            return '$SPX';
        }
        return $this->ticker;
    }

    public function render()
    {
        return view('livewire.live-option-monitor');
    }
}

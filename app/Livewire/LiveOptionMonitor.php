<?php

declare(strict_types=1);

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Services\SchwabAuthService;
use App\Services\SchwabTraderAuthService;
use App\Models\ContractSnapshot;
use App\Models\ContractPrint;
use Carbon\Carbon;

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

    public function loadContractsByDTE(int $minDTE, int $maxDTE, int $strikesCount): void
    {
        if (!$this->accessToken) {
            $this->error = 'No API token available';
            return;
        }

        try {
            $apiSymbol = $this->getApiSymbol();
            $fromDate  = now()->addDays($minDTE)->format('Y-m-d');
            $toDate    = now()->addDays($maxDTE)->format('Y-m-d');
            // Schwab requires toDate > fromDate strictly
            if ($toDate <= $fromDate) {
                $toDate = now()->addDays($maxDTE + 1)->format('Y-m-d');
            }

            $response = Http::timeout(15)->withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Accept'        => 'application/json',
            ])->get('https://api.schwabapi.com/marketdata/v1/chains', [
                'symbol'                 => $apiSymbol,
                'contractType'           => 'ALL',
                'includeUnderlyingQuote' => 'true',
                'fromDate'               => $fromDate,
                'toDate'                 => $toDate,
            ]);

            if (!$response->successful()) {
                $this->error = 'Failed to fetch option chain: ' . $response->status();
                return;
            }

            $data = $response->json();

            $underlyingPrice = $data['underlyingPrice'] ?? null;

            // Fall back to quotes API when the chains response doesn't include the underlying price
            if (!$underlyingPrice) {
                $underlyingPrice = $this->getUnderlyingPrice();
            }

            if (!$underlyingPrice) {
                $this->error = 'Could not determine underlying price';
                return;
            }

            // The real max date we want (before the buffer day)
            $maxDate = now()->addDays($maxDTE)->format('Y-m-d');

            $contracts   = [];
            $seenSymbols = [];

            foreach (['callExpDateMap' => 'C', 'putExpDateMap' => 'P'] as $mapKey => $typeChar) {
                if (!isset($data[$mapKey])) continue;

                // Group expiration keys by their base date (strip AM/PM suffix after ":")
                // and filter out any dates beyond maxDate
                $strikesByDate = [];
                foreach ($data[$mapKey] as $expDate => $strikeMap) {
                    $dateOnly = explode(':', $expDate)[0]; // e.g. "2025-05-11"
                    if ($dateOnly > $maxDate) continue;    // skip dates beyond requested range
                    if ($dateOnly < $fromDate) continue;   // skip dates before requested range

                    if (!isset($strikesByDate[$dateOnly])) {
                        $strikesByDate[$dateOnly] = $strikeMap;
                    } else {
                        // Merge AM/PM strikes — first seen wins per strike
                        foreach ($strikeMap as $strike => $contractList) {
                            if (!isset($strikesByDate[$dateOnly][$strike])) {
                                $strikesByDate[$dateOnly][$strike] = $contractList;
                            }
                        }
                    }
                }

                foreach ($strikesByDate as $dateOnly => $strikeMap) {
                    $allStrikes = array_keys($strikeMap);
                    sort($allStrikes, SORT_NUMERIC);

                    // Find the ATM index (closest strike to underlying)
                    $atmIndex = 0;
                    $minDiff  = PHP_FLOAT_MAX;
                    foreach ($allStrikes as $i => $strike) {
                        $diff = abs((float)$strike - $underlyingPrice);
                        if ($diff < $minDiff) {
                            $minDiff  = $diff;
                            $atmIndex = $i;
                        }
                    }

                    $low  = max(0, $atmIndex - $strikesCount);
                    $high = min(count($allStrikes) - 1, $atmIndex + $strikesCount);

                    for ($i = $low; $i <= $high; $i++) {
                        $strikeKey    = $allStrikes[$i];
                        $contractList = $strikeMap[$strikeKey] ?? [];
                        if (!empty($contractList)) {
                            $symbol = $contractList[0]['symbol'] ?? null;
                            if ($symbol && !isset($seenSymbols[$symbol])) {
                                $seenSymbols[$symbol] = true;
                                $contracts[] = '.' . $symbol;
                            }
                        }
                    }
                }
            }

            $this->error = null;

            \Log::info('LiveOptionMonitor: loadContractsByDTE result', [
                'count'    => count($contracts),
                'minDTE'   => $minDTE,
                'maxDTE'   => $maxDTE,
                'strikes'  => $strikesCount,
                'atm'      => $underlyingPrice,
            ]);

            $this->dispatch('contracts-bulk-loaded', symbols: $contracts);

        } catch (\Exception $e) {
            $this->error = 'Error loading contracts: ' . $e->getMessage();
            \Log::error('LiveOptionMonitor: loadContractsByDTE exception', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function getUnderlyingPrice(): float
    {
        try {
            // The quotes API uses the ticker as-is (with $ for indices)
            $quoteSymbol = $this->getApiSymbol();

            $response = Http::timeout(10)->withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Accept'        => 'application/json',
            ])->get('https://api.schwabapi.com/marketdata/v1/quotes', [
                'symbols' => $quoteSymbol,
            ]);

            if ($response->successful()) {
                $data       = $response->json();
                $symbolData = $data[$quoteSymbol] ?? null;

                if ($symbolData && isset($symbolData['quote']['lastPrice'])) {
                    return (float) $symbolData['quote']['lastPrice'];
                }

                // Some indices return mark instead
                if ($symbolData && isset($symbolData['quote']['mark'])) {
                    return (float) $symbolData['quote']['mark'];
                }
            }

            \Log::warning('LiveOptionMonitor: getUnderlyingPrice failed', [
                'status' => $response->status(),
                'symbol' => $quoteSymbol,
            ]);
        } catch (\Exception $e) {
            \Log::error('LiveOptionMonitor: getUnderlyingPrice exception', ['error' => $e->getMessage()]);
        }

        return 0.0;
    }

    private function getApiSymbol(): string
    {
        // For option chains, SPX needs the $ prefix
        if ($this->ticker === 'SPX') {
            return '$SPX';
        }
        return $this->ticker;
    }

    public function loadHistoricalSnapshots(array $symbols)
    {
        try {
            // Get all snapshots from today (start of market day)
            $since = Carbon::now()->startOfDay();

            // Normalize symbols - remove leading dot and normalize spacing
            $normalizedSymbols = array_map(function($symbol) {
                // Remove leading dot if present
                $symbol = ltrim($symbol, '.');
                // Normalize spacing: replace single space with double space to match DB format
                // DB format: "SPXW  260512C07310000" (two spaces)
                // Frontend: "SPXW 260512C07355000" (one space)
                $symbol = preg_replace('/\s+/', '  ', $symbol);
                return $symbol;
            }, $symbols);

            \Log::info('Original symbols:', $symbols);
            \Log::info('Normalized symbols:', $normalizedSymbols);

            // Get the latest snapshot for each symbol to get current totals
            $latestSnapshots = ContractSnapshot::whereIn('symbol', $normalizedSymbols)
                ->where('snapshot_at', '>=', $since)
                ->orderBy('snapshot_at', 'desc')
                ->get()
                ->groupBy('symbol')
                ->map->first();

            \Log::info('Found snapshots:', ['count' => $latestSnapshots->count()]);

            // Create mapping from normalized to original symbols
            $symbolMap = array_combine($normalizedSymbols, $symbols);

            // Format snapshots for frontend
            $result = [];
            foreach ($latestSnapshots as $normalizedSymbol => $snapshot) {
                if ($snapshot) {
                    // Use original symbol as key so frontend can match it
                    $originalSymbol = $symbolMap[$normalizedSymbol] ?? $normalizedSymbol;

                    // The latest snapshot already has accumulated buy/sell premium from the cron
                    $result[$originalSymbol] = [
                        'total_volume' => $snapshot->total_volume,
                        'total_premium' => $snapshot->total_premium,
                        'buy_premium' => $snapshot->buy_premium,
                        'sell_premium' => $snapshot->sell_premium,
                        'net_premium' => $snapshot->net_premium,
                        'last_price' => $snapshot->last_price,
                        'snapshot_at' => $snapshot->snapshot_at->toIso8601String(),
                    ];
                }
            }

            return $result;
        } catch (\Exception $e) {
            \Log::error('Error loading historical snapshots: ' . $e->getMessage());
            return [];
        }
    }

    public function loadHistoricalPrints(string $symbol, int $limit = 100)
    {
        try {
            // Get prints from today
            $since = Carbon::now()->startOfDay();

            $prints = ContractPrint::getHistory($symbol, $since, null, $limit);

            // Format prints for frontend
            return $prints->map(function ($print) {
                return [
                    'time' => $print->print_time->format('H:i:s'),
                    'price' => (float) $print->price,
                    'size' => $print->size,
                    'side' => $print->side,
                    'premium' => $print->premium,
                    'volume' => $print->cumulative_volume,
                ];
            })->toArray();
        } catch (\Exception $e) {
            \Log::error('Error loading historical prints: ' . $e->getMessage());
            return [];
        }
    }

    public function render()
    {
        return view('livewire.live-option-monitor');
    }
}

<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\SchwabOptionChainService;
use App\Services\SchwabAuthService;
use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Lazy;
use Illuminate\Support\Facades\Http;

#[Lazy]
class UnusualOptionsActivity extends Component
{
    public string $ticker;
    public int $minVolume = 1; // Minimum volume to be considered unusual
    public string $filter = 'all'; // all, calls, puts
    public string $dteFilter = 'all'; // 0dte, 1-7dte, 8-30dte, 31-60dte, 60+dte, all, custom
    public ?int $customDte = null; // Custom DTE value
    public array $currentContracts = []; // Store current high-volume contracts
    public bool $isFiltering = false; // Track filtering state
    public bool $pausePolling = true; // Start with tracking OFF

    public function mount(string $ticker): void
    {
        $this->ticker = strtoupper($ticker);
        $this->currentContracts = [];
    }

    public function placeholder()
    {
        return <<<'HTML'
        <div class="bg-slate-800/50 rounded-lg p-8 border border-slate-700/50">
            <div class="flex items-center justify-center space-x-3">
                <svg class="animate-spin h-5 w-5 text-amber-400" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="text-sm text-slate-400">Loading unusual options activity...</span>
            </div>
        </div>
        HTML;
    }

    public function setFilter(string $filter): void
    {
        $this->pausePolling = true;
        $this->isFiltering = true;
        $this->currentContracts = []; // Clear current contracts
        $this->filter = $filter;

        // Resume polling after a delay to allow data to refresh
        $this->dispatch('resume-polling');
    }

    public function setDteFilter(string $dteFilter): void
    {
        $this->pausePolling = true;
        $this->isFiltering = true;
        $this->currentContracts = []; // Clear current contracts
        $this->dteFilter = $dteFilter;
        if ($dteFilter !== 'custom') {
            $this->customDte = null;
        }

        // Resume polling after a delay to allow data to refresh
        $this->dispatch('resume-polling');
    }

    public function updatedCustomDte(): void
    {
        if ($this->customDte !== null) {
            $this->pausePolling = true;
            $this->isFiltering = true;
            $this->currentContracts = []; // Clear current contracts
            $this->dteFilter = 'custom';

            // Resume polling after a delay to allow data to refresh
            $this->dispatch('resume-polling');
        }
    }

    public function clearTrades(): void
    {
        $this->pausePolling = true;
        $this->isFiltering = true;
        $this->currentContracts = [];

        // Resume polling after a delay to allow data to refresh
        $this->dispatch('resume-polling');
    }

    public function resumePolling(): void
    {
        $this->pausePolling = false;
        $this->isFiltering = false;
    }

    public function unusualActivity()
    {
        // Just return filtered trades from already captured data
        // The actual detection happens in the polling cycle
        return $this->getFilteredTrades();
    }

    #[On('poll')]
    public function pollForNewTrades()
    {
        // Skip polling if we're filtering
        if ($this->pausePolling) {
            return;
        }

        try {
            // Get API symbol format
            $apiSymbol = $this->getApiSymbol();

            // Cache option chain for 2 seconds to avoid hammering API
            $cacheKey = "option_chain_{$apiSymbol}";
            $optionChain = cache()->remember($cacheKey, now()->addSeconds(2), function() use ($apiSymbol) {
                $optionService = SchwabOptionChainService::make();
                return $optionService->getOptionChain('$'.$apiSymbol);
            });

            if (!$optionChain) {
                return;
            }

            // Cache real-time price for 2 seconds
            $priceCacheKey = "realtime_price_{$apiSymbol}";
            $realPrice = cache()->remember($priceCacheKey, now()->addSeconds(2), function() {
                return $this->getRealTimePrice();
            });

            // If still 0, use option chain price as last resort
            if ($realPrice == 0) {
                $realPrice = $optionChain->underlyingPrice ?? 0;
            }

            // Detect new unusual trades
            $this->detectNewTrades($optionChain, $realPrice);

        } catch (\Exception $e) {
            // Silent fail
        }
    }

    private function getApiSymbol(): string
    {
        // For option chain, SPX doesn't need $ prefix (unlike quotes API)
        // Just return the ticker as-is
        return $this->ticker;
    }

    private function getRealTimePrice(): float
    {
        try {
            $authService = app(\App\Services\SchwabAuthService::class);
            $token = $authService->getAccessToken();

            if (!$token) {
                return 0;
            }

            // Get API symbol (handle indices with $ prefix)
            $apiSymbol = $this->getApiSymbol();

            $response = Http::withToken($token)
                ->timeout(10)
                ->get(config('services.schwab.base_url') . '/marketdata/v1/quotes', [
                    'symbols' => $apiSymbol,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $symbolData = $data[$apiSymbol] ?? null;

                \Log::info('Quote API Response', [
                    'ticker' => $this->ticker,
                    'apiSymbol' => $apiSymbol,
                    'hasData' => !empty($symbolData),
                    'hasQuote' => isset($symbolData['quote']),
                    'lastPrice' => $symbolData['quote']['lastPrice'] ?? 'N/A',
                ]);

                if ($symbolData && isset($symbolData['quote'])) {
                    return (float)($symbolData['quote']['lastPrice'] ?? 0);
                }
            } else {
                \Log::error('Quote API Failed', [
                    'ticker' => $this->ticker,
                    'apiSymbol' => $apiSymbol,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }

            return 0;
        } catch (\Exception $e) {
            \Log::error('Failed to get real-time price', ['ticker' => $this->ticker, 'error' => $e->getMessage()]);
            return 0;
        }
    }

    private function detectNewTrades($optionChain, float $realPrice = 0): void
    {
        $currentTime = now()->timestamp;
        $newContracts = [];

        // Use real-time price if available, otherwise fall back to option chain price
        $underlyingPrice = $realPrice > 0 ? $realPrice : ($optionChain->underlyingPrice ?? 0);

        if ($underlyingPrice <= 0) {
            return; // Can't filter by ATM without price
        }

        // Filter calls to only 20 strikes above and below ATM
        $filteredCalls = $this->filterNearMoneyStrikes($optionChain->calls, $underlyingPrice, 20);

        // Process Calls
        foreach ($filteredCalls as $call) {
            $currentVolume = $call->volume ?? 0;

            // Check expiration date - only process if expires today or later
            $expDate = $call->expirationDate;
            if ($expDate instanceof \Carbon\Carbon) {
                // Skip only if expiration date is in the past (before today)
                if ($expDate->endOfDay()->isPast()) {
                    continue; // Skip expired options
                }
            }

            // Only process if high volume
            if ($currentVolume >= $this->minVolume) {
                $contractKey = $this->getContractKey($call, 'CALL');
                $newContracts[$contractKey] = $this->processContract($call, 'CALL', $currentTime, $currentVolume);
            }
        }

        // Filter puts to only 20 strikes above and below ATM
        $filteredPuts = $this->filterNearMoneyStrikes($optionChain->puts, $underlyingPrice, 20);

        // Process Puts
        foreach ($filteredPuts as $put) {
            $currentVolume = $put->volume ?? 0;

            // Check expiration date - only process if expires today or later
            $expDate = $put->expirationDate;
            if ($expDate instanceof \Carbon\Carbon) {
                // Skip only if expiration date is in the past (before today)
                if ($expDate->endOfDay()->isPast()) {
                    continue; // Skip expired options
                }
            }

            // Only process if high volume
            if ($currentVolume >= $this->minVolume) {
                $contractKey = $this->getContractKey($put, 'PUT');
                $newContracts[$contractKey] = $this->processContract($put, 'PUT', $currentTime, $currentVolume);
            }
        }

        // Replace all contracts with current snapshot
        $this->currentContracts = $newContracts;
    }

    private function filterNearMoneyStrikes($contracts, float $underlyingPrice, int $range = 5): array
    {
        // Sort contracts by how close they are to ATM
        $contractsWithDistance = collect($contracts)->map(function($contract) use ($underlyingPrice) {
            $strike = $contract->strike ?? $contract->strikePrice ?? 0;
            $distance = abs($strike - $underlyingPrice);
            return [
                'contract' => $contract,
                'distance' => $distance
            ];
        })->sortBy('distance')->values();

        // Take only the closest strikes (range above and below)
        $filtered = $contractsWithDistance->take($range * 2)->pluck('contract')->all();

        return $filtered;
    }

    private function getContractKey($contract, string $type): string
    {
        $strike = $contract->strike ?? $contract->strikePrice ?? 0;
        $expiration = $contract->expirationDate ?? '';
        if ($expiration instanceof \Carbon\Carbon) {
            $expiration = $expiration->format('Y-m-d');
        }

        return sprintf(
            '%s_%s_%s',
            $type,
            $strike,
            $expiration
        );
    }

    private function getFilteredTrades()
    {
        $trades = collect($this->currentContracts);

        // Filter by type
        if ($this->filter !== 'all') {
            $trades = $trades->filter(function($trade) {
                return strtolower($trade['type']) === $this->filter;
            });
        }

        // Filter by DTE
        $trades = $trades->filter(function($trade) {
            return $this->matchesDteFilter($trade['daysToExpiration']);
        });

        // Sort by volume (highest first) and take top 20
        return $trades->sortByDesc('volume')->take(20)->values();
    }

    private function matchesDteFilter(int $dte): bool
    {
        return match($this->dteFilter) {
            '0dte' => $dte === 0,
            '1-7dte' => $dte >= 1 && $dte <= 7,
            '8-30dte' => $dte >= 8 && $dte <= 30,
            '31-60dte' => $dte >= 31 && $dte <= 60,
            '60+dte' => $dte > 60,
            'custom' => $this->customDte !== null ? $dte === $this->customDte : true,
            'all' => true,
            default => true,
        };
    }

    private function generateOccSymbol($contract, string $type): string
    {
        // TOS Format: .ROOTYYMMDDCPPPPP
        // Example: .SPXW260429C5475 (SPX Weekly, 2026-04-29, Call, 5475 strike)

        try {
            $expDate = $contract->expirationDate;
            if (!($expDate instanceof \Carbon\Carbon)) {
                $expDate = \Carbon\Carbon::parse($expDate ?? now());
            }

            // Root symbol - Add W for weeklies (SPX/SPY that don't expire on Friday)
            $root = $this->ticker;

            // For SPX and SPY, add W if it's not a standard monthly expiration (3rd Friday)
            if (in_array($root, ['SPX', 'SPY'])) {
                // Check if it's the 3rd Friday of the month
                $dayOfMonth = $expDate->day;
                $dayOfWeek = $expDate->dayOfWeek; // 0=Sunday, 5=Friday

                // 3rd Friday is between day 15-21 and is a Friday
                $isThirdFriday = ($dayOfWeek === 5) && ($dayOfMonth >= 15 && $dayOfMonth <= 21);

                // If it's NOT the 3rd Friday, it's a weekly
                if (!$isThirdFriday) {
                    $root .= 'W';
                }
            }

            // Date: YYMMDD
            $dateStr = $expDate->format('ymd');

            // Type: C or P
            $typeChar = $type === 'CALL' ? 'C' : 'P';

            // Strike: just the number without padding (TOS format)
            $strikePrice = floatval($contract->strike ?? $contract->strikePrice ?? 0);
            $strikeStr = (int)$strikePrice;

            // TOS format starts with a dot
            return '.' . $root . $dateStr . $typeChar . $strikeStr;

        } catch (\Exception $e) {
            return 'UNKNOWN';
        }
    }

    private function processContract($contract, string $type, ?int $timestamp = null, ?int $volumeIncrease = null): array
    {
        $bid = $contract->bid ?? 0;
        $ask = $contract->ask ?? 0;
        $last = $contract->last ?? 0;
        $mark = $contract->mark ?? 0;
        $volume = $volumeIncrease ?? ($contract->volume ?? 0); // Use volume increase if provided
        $strikePrice = $contract->strike ?? $contract->strikePrice ?? 0;

        // Calculate DTE (Days To Expiration)
        try {
            $expDate = $contract->expirationDate;
            if (!($expDate instanceof \Carbon\Carbon)) {
                $expDate = \Carbon\Carbon::parse($expDate ?? now());
            }
            $daysToExpiration = now()->diffInDays($expDate, false);
            $daysToExpiration = max(0, (int)$daysToExpiration);
        } catch (\Exception $e) {
            $daysToExpiration = 0;
        }

        // Determine if it's a buy or sell based on last price proximity to bid/ask
        $midpoint = ($bid + $ask) / 2;
        $isBuy = $last >= $midpoint;

        // Calculate premium (volume * price * 100)
        $premium = $volume * $mark * 100;

        // Determine if it's unusual (volume vs open interest ratio)
        $openInterest = $contract->openInterest ?? 1;
        $volumeOIRatio = $openInterest > 0 ? $volume / $openInterest : 0;
        $isUnusual = $volumeOIRatio > 0.5; // Volume is more than 50% of OI

        // Generate OCC format contract ID (e.g., SPXW260429C7140)
        $contractId = $this->generateOccSymbol($contract, $type);

        $result = [
            'contractId' => $contractId,
            'symbol' => $this->ticker,
            'type' => $type,
            'strike' => $strikePrice,
            'expiration' => $expDate->format('Y-m-d'),
            'daysToExpiration' => $daysToExpiration,
            'volume' => $volume,
            'openInterest' => $openInterest,
            'bid' => $bid,
            'ask' => $ask,
            'last' => $last,
            'mark' => $mark,
            'premium' => $premium,
            'action' => $isBuy ? 'BUY' : 'SELL',
            'isUnusual' => $isUnusual,
            'volumeOIRatio' => $volumeOIRatio,
            'impliedVolatility' => $contract->impliedVolatility ?? 0,
            'delta' => $contract->delta ?? 0,
            'timestamp' => $timestamp ?? now()->timestamp,
            'capturedAt' => now()->format('H:i:s'),
        ];

        return $result;
    }

    public function render()
    {
        $unusualActivity = $this->unusualActivity();

        return view('livewire.unusual-options-activity', [
            'unusualActivity' => $unusualActivity,
        ]);
    }
}

<?php

namespace App\Livewire;

use App\Models\Symbol;
use App\Services\SchwabAuthService;
use Illuminate\Support\Facades\Http;
use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Lazy;

#[Lazy]
class SymbolDetail extends Component
{
    public string $ticker;
    public bool $isLoading = true;
    public ?string $error = null;

    public function mount(string $ticker)
    {
        $this->ticker = strtoupper($ticker);
    }
    
    public function placeholder()
    {
        return <<<'HTML'
        <div class="bg-slate-800/50 rounded-lg p-6 border border-slate-700/50 animate-pulse">
            <div class="h-8 bg-slate-700/50 rounded w-1/3 mb-4"></div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="h-20 bg-slate-700/50 rounded"></div>
                <div class="h-20 bg-slate-700/50 rounded"></div>
                <div class="h-20 bg-slate-700/50 rounded"></div>
                <div class="h-20 bg-slate-700/50 rounded"></div>
            </div>
        </div>
        HTML;
    }

    public function getQuoteDataProperty()
    {
        try {
            $authService = app(SchwabAuthService::class);
            $token = $authService->getAccessToken();

            if (!$token) {
                $this->error = 'No access token available';
                return null;
            }

            // Determine the correct symbol format for API
            $apiSymbol = $this->getApiSymbol();

            $response = Http::withToken($token)
                ->timeout(10)
                ->get(config('services.schwab.base_url') . '/marketdata/v1/quotes', [
                    'symbols' => $apiSymbol,
                    'fields' => 'quote,fundamental'
                ]);

            if ($response->successful()) {
                $data = $response->json();

                // Check for errors in response
                if (isset($data['errors'])) {
                    $this->error = 'Invalid symbol: ' . $this->ticker;
                    return null;
                }

                // Try to get data with the API symbol
                if (isset($data[$apiSymbol])) {
                    return $data[$apiSymbol];
                }

                // Return first item if exists
                if (!empty($data) && is_array($data)) {
                    return reset($data);
                }

                $this->error = 'No data found for ' . $this->ticker;
                return null;
            }

            $this->error = 'API Error: ' . $response->status();
            return null;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            return null;
        }
    }

    private function getApiSymbol(): string
    {
        // List of known indices that require $ prefix
        $indices = ['SPX', 'DJI', 'COMPX', 'NDX', 'RUT', 'VIX'];

        // If ticker is in indices list and doesn't have $, add it
        if (in_array($this->ticker, $indices) && !str_starts_with($this->ticker, '$')) {
            return '$' . $this->ticker;
        }

        // Return as-is
        return $this->ticker;
    }

    public function getOptionDataProperty()
    {
        try {
            $optionService = \App\Services\SchwabOptionChainService::make();
            $optionChain = $optionService->getOptionChain($this->ticker);

            if (!$optionChain) {
                return null;
            }

            $callVolume = $optionChain->calls->sum('volume');
            $putVolume = $optionChain->puts->sum('volume');
            $avgIV = $optionChain->calls->merge($optionChain->puts)->avg('impliedVolatility');

            return [
                'callVolume' => $callVolume,
                'putVolume' => $putVolume,
                'callPutRatio' => $putVolume > 0 ? $callVolume / $putVolume : 0,
                'avgIV' => $avgIV,
                'ivRank' => rand(0, 100), // TODO: Calculate real IV Rank
                'ivPercentile' => rand(0, 100), // TODO: Calculate real IV Percentile
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    public function render()
    {
        $symbol = Symbol::where('ticker', $this->ticker)->first();
        $quoteData = $this->quoteData;
        $optionData = $this->optionData;

        return view('livewire.symbol-detail', [
            'symbol' => $symbol,
            'quoteData' => $quoteData,
            'optionData' => $optionData,
        ]);
    }
}

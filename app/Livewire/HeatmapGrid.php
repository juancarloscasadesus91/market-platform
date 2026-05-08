<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\HeatmapBuilderService;
use Carbon\Carbon;
use Livewire\Component;

class HeatmapGrid extends Component
{
    public array $tickers = ['SPX'];
    public ?string $selectedExpiration = null;
    public string $metric = 'volume'; // volume, premium, iv
    public string $newTicker = '';
    public bool $showTickerInput = false;
    public bool $showSearchResults = false;

    public function mount(): void
    {
        $this->selectedExpiration = now()->next('Friday')->format('Y-m-d');
    }

    public function setMetric(string $metric): void
    {
        $this->metric = $metric;
    }
    
    public function updatedNewTicker(): void
    {
        $this->showSearchResults = strlen($this->newTicker) > 0;
    }
    
    public function addTicker(?string $ticker = null): void
    {
        $tickerToAdd = $ticker ?? strtoupper(trim($this->newTicker));
        
        if ($tickerToAdd && !in_array($tickerToAdd, $this->tickers)) {
            $this->tickers[] = $tickerToAdd;
            $this->newTicker = '';
            $this->showTickerInput = false;
            $this->showSearchResults = false;
        }
    }
    
    public function removeTicker(string $ticker): void
    {
        $this->tickers = array_values(array_filter($this->tickers, fn($t) => $t !== $ticker));
    }
    
    public function toggleTickerInput(): void
    {
        $this->showTickerInput = !$this->showTickerInput;
        $this->newTicker = '';
        $this->showSearchResults = false;
    }

    public function render(HeatmapBuilderService $heatmapService)
    {
        $expiration = $this->selectedExpiration 
            ? Carbon::parse($this->selectedExpiration)
            : now()->next('Friday');

        $heatmapData = $heatmapService->buildMultiSymbolHeatmap(
            $this->tickers,
            $expiration
        );
        
        // Search results for autocomplete - common optionable stocks
        $searchResults = collect();
        if (strlen($this->newTicker) > 0) {
            // List of popular optionable symbols
            $optionableSymbols = [
                'SPX' => 'S&P 500 Index',
                '$SPX.X' => 'S&P 500 Index (Alt)',
                'SPY' => 'SPDR S&P 500 ETF Trust',
                'QQQ' => 'Invesco QQQ Trust',
                'AAPL' => 'Apple Inc.',
                'MSFT' => 'Microsoft Corporation',
                'NVDA' => 'NVIDIA Corporation',
                'TSLA' => 'Tesla, Inc.',
                'AMZN' => 'Amazon.com, Inc.',
                'GOOGL' => 'Alphabet Inc.',
                'META' => 'Meta Platforms, Inc.',
                'AMD' => 'Advanced Micro Devices',
                'NFLX' => 'Netflix, Inc.',
                'DIS' => 'The Walt Disney Company',
                'BA' => 'The Boeing Company',
                'JPM' => 'JPMorgan Chase & Co.',
                'V' => 'Visa Inc.',
                'IWM' => 'iShares Russell 2000 ETF',
                'GLD' => 'SPDR Gold Trust',
                'TLT' => 'iShares 20+ Year Treasury Bond ETF',
            ];
            
            $search = strtoupper($this->newTicker);
            $searchResults = collect($optionableSymbols)
                ->filter(fn($name, $ticker) => 
                    str_contains($ticker, $search) || str_contains(strtoupper($name), $search)
                )
                ->take(8)
                ->map(fn($name, $ticker) => (object)[
                    'ticker' => $ticker,
                    'name' => $name,
                    'quote' => null
                ]);
        }

        return view('livewire.heatmap-grid', [
            'heatmapData' => $heatmapData,
            'searchResults' => $searchResults,
        ]);
    }
}

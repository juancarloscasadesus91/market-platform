<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Symbol;

class MarketOverviewCards extends Component
{
    public function render()
    {
        $tickers = ['SPY', 'QQQ', 'NVDA', 'AAPL'];
        $symbols = Symbol::with('quote')->whereIn('ticker', $tickers)->get()->keyBy('ticker');
        
        return view('livewire.market-overview-cards', [
            'tickers' => $tickers,
            'symbols' => $symbols,
        ]);
    }
}

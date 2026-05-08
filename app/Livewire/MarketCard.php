<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Symbol;
use Livewire\Component;

class MarketCard extends Component
{
    public string $ticker;
    public ?Symbol $symbol = null;

    public function mount(string $ticker): void
    {
        $this->ticker = $ticker;
        $this->loadSymbol();
    }

    public function loadSymbol(): void
    {
        $this->symbol = Symbol::with('quote')
            ->where('ticker', $this->ticker)
            ->first();
    }

    public function refresh(): void
    {
        $this->loadSymbol();
    }

    public function render()
    {
        return view('livewire.market-card');
    }
}

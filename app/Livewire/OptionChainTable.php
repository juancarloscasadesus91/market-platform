<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Symbol;
use Carbon\Carbon;
use Livewire\Component;

class OptionChainTable extends Component
{
    public string $ticker;
    public ?string $selectedExpiration = null;
    public array $expirations = [];
    public string $strikeFilter = 'all'; // all, itm, atm, otm
    public string $sortBy = 'strike';
    public string $sortDirection = 'asc';

    public function mount(string $ticker): void
    {
        $this->ticker = $ticker;
        $this->loadExpirations();
    }

    public function loadExpirations(): void
    {
        // Generate next 8 Fridays
        $this->expirations = [];
        $date = now()->next('Friday');

        for ($i = 0; $i < 8; $i++) {
            $this->expirations[] = $date->format('Y-m-d');
            $date->addWeek();
        }

        if (empty($this->selectedExpiration)) {
            $this->selectedExpiration = $this->expirations[0] ?? null;
        }
    }

    public function selectExpiration(string $date): void
    {
        $this->selectedExpiration = $date;
    }

    public function setStrikeFilter(string $filter): void
    {
        $this->strikeFilter = $filter;
    }

    public function sortBy(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function render()
    {
        $symbol = Symbol::where('ticker', $this->ticker)
            ->with('quote')
            ->first();

        if (!$symbol || !$this->selectedExpiration) {
            return view('livewire.option-chain-table', [
                'symbol' => $symbol,
                'calls' => collect(),
                'puts' => collect(),
                'strikes' => collect(),
            ]);
        }

        $query = $symbol->optionContracts()
            ->where('expiration_date', $this->selectedExpiration);

        // Apply strike filter
        if ($this->strikeFilter !== 'all' && $symbol->quote) {
            $currentPrice = $symbol->quote->last_price;
            
            match($this->strikeFilter) {
                'itm' => $query->where(function ($q) use ($currentPrice) {
                    $q->where(function ($sq) use ($currentPrice) {
                        $sq->where('option_type', 'call')
                           ->where('strike', '<', $currentPrice);
                    })->orWhere(function ($sq) use ($currentPrice) {
                        $sq->where('option_type', 'put')
                           ->where('strike', '>', $currentPrice);
                    });
                }),
                'atm' => $query->whereBetween('strike', [
                    $currentPrice * 0.98,
                    $currentPrice * 1.02
                ]),
                'otm' => $query->where(function ($q) use ($currentPrice) {
                    $q->where(function ($sq) use ($currentPrice) {
                        $sq->where('option_type', 'call')
                           ->where('strike', '>', $currentPrice);
                    })->orWhere(function ($sq) use ($currentPrice) {
                        $sq->where('option_type', 'put')
                           ->where('strike', '<', $currentPrice);
                    });
                }),
                default => null,
            };
        }

        $contracts = $query->get();

        $calls = $contracts->where('option_type', 'call')
            ->sortBy($this->sortBy, SORT_REGULAR, $this->sortDirection === 'desc');

        $puts = $contracts->where('option_type', 'put')
            ->sortBy($this->sortBy, SORT_REGULAR, $this->sortDirection === 'desc');

        $strikes = $contracts->pluck('strike')->unique()->sort()->values();

        return view('livewire.option-chain-table', [
            'symbol' => $symbol,
            'calls' => $calls,
            'puts' => $puts,
            'strikes' => $strikes,
        ]);
    }
}

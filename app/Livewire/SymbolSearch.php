<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Symbol;
use Livewire\Component;

class SymbolSearch extends Component
{
    public string $search = '';
    public bool $showResults = false;

    public function updatedSearch(): void
    {
        $this->showResults = strlen($this->search) > 0;
    }

    public function selectSymbol(string $ticker): void
    {
        $this->redirect(route('symbol.show', $ticker));
    }

    public function render()
    {
        $results = collect();

        if (strlen($this->search) > 0) {
            $results = Symbol::search($this->search)
                ->with('quote')
                ->limit(10)
                ->get();
        }

        return view('livewire.symbol-search', [
            'results' => $results,
        ]);
    }
}

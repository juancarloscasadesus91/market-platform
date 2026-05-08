<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Symbol;
use App\Models\Watchlist;
use Livewire\Attributes\On;
use Livewire\Component;

class WatchlistPanel extends Component
{
    public function addToWatchlist(int $symbolId): void
    {
        $symbol = Symbol::find($symbolId);

        if (!$symbol) {
            return;
        }

        $maxPosition = Watchlist::max('position') ?? 0;

        Watchlist::firstOrCreate(
            ['symbol_id' => $symbolId],
            ['position' => $maxPosition + 1]
        );

        $this->dispatch('watchlist-updated');
    }

    public function removeFromWatchlist(int $watchlistId): void
    {
        Watchlist::find($watchlistId)?->delete();
        $this->dispatch('watchlist-updated');
    }

    #[On('watchlist-updated')]
    public function render()
    {
        $watchlist = Watchlist::with(['symbol.quote'])
            ->ordered()
            ->get();

        return view('livewire.watchlist-panel', [
            'watchlist' => $watchlist,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\MarketMetricsService;
use Livewire\Component;

class MoversList extends Component
{
    public string $type = 'gainers'; // gainers, losers, active

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function render(MarketMetricsService $metricsService)
    {
        $movers = match($this->type) {
            'gainers' => $metricsService->getTopGainers(10),
            'losers' => $metricsService->getTopLosers(10),
            'active' => $metricsService->getMostActive(10),
            default => collect(),
        };

        return view('livewire.movers-list', [
            'movers' => $movers,
        ]);
    }
}

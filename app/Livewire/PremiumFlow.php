<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\SchwabOptionChainService;
use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Lazy;

#[Lazy]
class PremiumFlow extends Component
{
    public string $ticker;
    public string $timeframe = '1m'; // 1m, 5m, 15m, 30m, 1h, 1d
    public bool $isLoading = true;
    public array $premiumHistory = [];
    public int $lastUpdate = 0;

    public function mount(string $ticker): void
    {
        $this->ticker = strtoupper($ticker);
        $this->lastUpdate = time();
    }
    
    public function placeholder()
    {
        return <<<'HTML'
        <div class="bg-slate-800/50 rounded-lg p-6 border border-slate-700/50 animate-pulse">
            <div class="h-6 bg-slate-700/50 rounded w-1/4 mb-4"></div>
            <div class="h-64 bg-slate-700/50 rounded"></div>
        </div>
        HTML;
    }

    public function setTimeframe(string $timeframe): void
    {
        $this->timeframe = $timeframe;
        $this->premiumHistory = []; // Reset history when changing timeframe
    }

    #[On('premium-tick')]
    public function handlePremiumTick($data): void
    {
        // Handle real-time premium updates
        $this->lastUpdate = time();
    }

    public function getPremiumDataProperty()
    {
        try {
            $optionService = SchwabOptionChainService::make();
            $optionChain = $optionService->getOptionChain($this->ticker);

            if (!$optionChain) {
                return [
                    'callPremium' => 0,
                    'putPremium' => 0,
                    'netPremium' => 0,
                    'callContracts' => 0,
                    'putContracts' => 0,
                    'ratio' => 0,
                ];
            }

            $timestamp = time();

            // Calculate premium flow (Price * Volume * 100)
            $totalCallPremium = $optionChain->calls->sum(function($contract) {
                return ($contract->mark ?? 0) * ($contract->volume ?? 0) * 100;
            });

            $totalPutPremium = $optionChain->puts->sum(function($contract) {
                return ($contract->mark ?? 0) * ($contract->volume ?? 0) * 100;
            });

            $callContracts = $optionChain->calls->filter(fn($c) => ($c->volume ?? 0) > 0)->count();
            $putContracts = $optionChain->puts->filter(fn($c) => ($c->volume ?? 0) > 0)->count();

            // Apply timeframe filter by simulating percentage of daily volume
            $timeframeMultiplier = match($this->timeframe) {
                '1m' => 0.002,  // ~0.2% of daily volume (assuming 6.5 hour trading day)
                '5m' => 0.01,   // ~1% of daily volume
                '15m' => 0.04,  // ~4% of daily volume
                '30m' => 0.08,  // ~8% of daily volume
                '1h' => 0.15,   // ~15% of daily volume
                '1d' => 1.0,    // 100% of daily volume
                default => 1.0,
            };

            $callPremium = $totalCallPremium * $timeframeMultiplier;
            $putPremium = $totalPutPremium * $timeframeMultiplier;

            return [
                'callPremium' => $callPremium,
                'putPremium' => $putPremium,
                'netPremium' => $callPremium - $putPremium,
                'callContracts' => (int)($callContracts * $timeframeMultiplier),
                'putContracts' => (int)($putContracts * $timeframeMultiplier),
                'ratio' => $putPremium > 0 ? $callPremium / $putPremium : 0,
                'lastUpdate' => $timestamp,
            ];
        } catch (\Exception $e) {
            return [
                'callPremium' => 0,
                'putPremium' => 0,
                'netPremium' => 0,
                'callContracts' => 0,
                'putContracts' => 0,
                'ratio' => 0,
            ];
        }
    }

    public function render()
    {
        $premiumData = $this->premiumData;
        $this->isLoading = false;

        return view('livewire.premium-flow', [
            'premiumData' => $premiumData,
        ]);
    }
}

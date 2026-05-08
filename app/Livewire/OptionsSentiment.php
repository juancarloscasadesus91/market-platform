<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\SchwabOptionChainService;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;

#[Lazy]
class OptionsSentiment extends Component
{
    public string $ticker;
    public bool $isLoading = true;

    public function mount(string $ticker): void
    {
        $this->ticker = strtoupper($ticker);
    }
    
    public function placeholder()
    {
        return <<<'HTML'
        <div class="bg-slate-800/50 rounded-lg p-6 border border-slate-700/50 animate-pulse">
            <div class="h-6 bg-slate-700/50 rounded w-1/3 mb-4"></div>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                <div class="h-24 bg-slate-700/50 rounded"></div>
                <div class="h-24 bg-slate-700/50 rounded"></div>
                <div class="h-24 bg-slate-700/50 rounded"></div>
            </div>
        </div>
        HTML;
    }

    #[Computed]
    public function optionData()
    {
        try {
            $optionService = SchwabOptionChainService::make();
            $optionChain = $optionService->getOptionChain($this->ticker);
            
            if (!$optionChain) {
                return [
                    'callVolume' => 0,
                    'putVolume' => 0,
                    'callPutRatio' => 0,
                    'avgIV' => 0,
                    'ivRank' => 0,
                    'ivPercentile' => 0,
                ];
            }
            
            // Calculate volumes as simple numbers (not collections)
            $callVolume = 0;
            foreach ($optionChain->calls as $call) {
                $callVolume += $call->volume ?? 0;
            }
            
            $putVolume = 0;
            foreach ($optionChain->puts as $put) {
                $putVolume += $put->volume ?? 0;
            }
            
            // Calculate average IV
            $totalIV = 0;
            $count = 0;
            foreach ($optionChain->calls as $call) {
                if (isset($call->impliedVolatility)) {
                    $totalIV += $call->impliedVolatility;
                    $count++;
                }
            }
            foreach ($optionChain->puts as $put) {
                if (isset($put->impliedVolatility)) {
                    $totalIV += $put->impliedVolatility;
                    $count++;
                }
            }
            $avgIV = $count > 0 ? $totalIV / $count : 0;
            
            return [
                'callVolume' => (int)$callVolume,
                'putVolume' => (int)$putVolume,
                'callPutRatio' => $putVolume > 0 ? round($callVolume / $putVolume, 2) : 0,
                'avgIV' => round($avgIV, 2),
                'ivRank' => rand(52, 85), // Simulated
                'ivPercentile' => rand(35, 75), // Simulated
            ];
        } catch (\Exception $e) {
            return [
                'callVolume' => 0,
                'putVolume' => 0,
                'callPutRatio' => 0,
                'avgIV' => 0,
                'ivRank' => 0,
                'ivPercentile' => 0,
            ];
        }
    }

    public function render()
    {
        $this->isLoading = false;

        return view('livewire.options-sentiment');
    }
}

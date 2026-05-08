<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\HeatmapCellData;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class HeatmapBuilderService
{
    private SchwabOptionChainService $optionChainService;
    
    public function __construct(?SchwabOptionChainService $optionChainService = null)
    {
        $this->optionChainService = $optionChainService ?? SchwabOptionChainService::make();
    }

    /**
     * Build heatmap data for a symbol from real-time API
     */
    public function buildHeatmap(string $ticker, ?Carbon $expirationDate = null): Collection
    {
        $expiration = $expirationDate ?? now()->next('Friday');

        // Get real-time option chain from Schwab API
        $optionChain = $this->optionChainService->getOptionChain($ticker, $expiration);

        if (!$optionChain) {
            return collect();
        }

        // Combine calls and puts
        $allContracts = $optionChain->calls->merge($optionChain->puts);

        // Filter only contracts with volume
        return $allContracts
            ->filter(fn($contract) => ($contract->volume ?? 0) > 0)
            ->map(function ($contract) use ($ticker) {
                return new HeatmapCellData(
                    ticker: $ticker,
                    strike: $contract->strike,
                    optionType: $contract->optionType,
                    volume: $contract->volume ?? 0,
                    premiumFlow: ($contract->mark ?? 0) * ($contract->volume ?? 0) * 100,
                    impliedVolatility: $contract->impliedVolatility ?? 0,
                    delta: $contract->delta,
                    gamma: $contract->gamma,
                );
            });
    }

    /**
     * Build multi-symbol heatmap from real-time API
     */
    public function buildMultiSymbolHeatmap(array $tickers, ?Carbon $expirationDate = null): Collection
    {
        return collect($tickers)->flatMap(function ($ticker) use ($expirationDate) {
            return $this->buildHeatmap($ticker, $expirationDate);
        });
    }

    /**
     * Get gamma exposure zones
     */
    public function getGammaExposureZones(Symbol $symbol): Collection
    {
        $contracts = $symbol->optionContracts()
            ->whereNotNull('gamma')
            ->whereNotNull('open_interest')
            ->get();

        return $contracts->groupBy('strike')->map(function ($group, $strike) {
            $totalGammaExposure = $group->sum(function ($contract) {
                return ($contract->gamma ?? 0) * ($contract->open_interest ?? 0) * 100;
            });

            return [
                'strike' => $strike,
                'gamma_exposure' => $totalGammaExposure,
                'intensity' => $this->calculateIntensity($totalGammaExposure),
            ];
        })->sortBy('strike')->values();
    }

    /**
     * Get call/put premium flow comparison
     */
    public function getPremiumFlowComparison(Symbol $symbol, ?Carbon $expirationDate = null): array
    {
        $expiration = $expirationDate ?? now()->next('Friday');

        $calls = $symbol->optionContracts()
            ->calls()
            ->where('expiration_date', $expiration)
            ->get();

        $puts = $symbol->optionContracts()
            ->puts()
            ->where('expiration_date', $expiration)
            ->get();

        $callFlow = $calls->sum(fn ($c) => ($c->mark ?? 0) * ($c->volume ?? 0) * 100);
        $putFlow = $puts->sum(fn ($c) => ($c->mark ?? 0) * ($c->volume ?? 0) * 100);

        return [
            'call_flow' => round($callFlow, 2),
            'put_flow' => round($putFlow, 2),
            'total_flow' => round($callFlow + $putFlow, 2),
            'call_put_ratio' => $putFlow > 0 ? round($callFlow / $putFlow, 2) : 0,
            'sentiment' => $callFlow > $putFlow ? 'bullish' : 'bearish',
        ];
    }

    /**
     * Calculate intensity level
     */
    private function calculateIntensity(float $value): string
    {
        $absValue = abs($value);

        if ($absValue > 1000000) {
            return 'extreme';
        }

        if ($absValue > 500000) {
            return 'high';
        }

        if ($absValue > 100000) {
            return 'medium';
        }

        return 'low';
    }
}

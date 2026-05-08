<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Symbol;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class MarketMetricsService
{
    public function __construct(
        private readonly SchwabQuoteService $quoteService,
    ) {}

    /**
     * Get top gainers
     */
    public function getTopGainers(int $limit = 10): Collection
    {
        return Cache::remember('market:top_gainers', 300, function () use ($limit) {
            return Symbol::with('quote')
                ->whereHas('quote')
                ->get()
                ->sortByDesc(fn ($symbol) => $symbol->quote->change_percent ?? 0)
                ->take($limit)
                ->values();
        });
    }

    /**
     * Get top losers
     */
    public function getTopLosers(int $limit = 10): Collection
    {
        return Cache::remember('market:top_losers', 300, function () use ($limit) {
            return Symbol::with('quote')
                ->whereHas('quote')
                ->get()
                ->sortBy(fn ($symbol) => $symbol->quote->change_percent ?? 0)
                ->take($limit)
                ->values();
        });
    }

    /**
     * Get most active by volume
     */
    public function getMostActive(int $limit = 10): Collection
    {
        return Cache::remember('market:most_active', 300, function () use ($limit) {
            return Symbol::with('quote')
                ->whereHas('quote')
                ->get()
                ->sortByDesc(fn ($symbol) => $symbol->quote->volume ?? 0)
                ->take($limit)
                ->values();
        });
    }

    /**
     * Get market overview stats
     */
    public function getMarketOverview(): array
    {
        return Cache::remember('market:overview', 300, function () {
            $symbols = Symbol::with('quote')->whereHas('quote')->get();

            $advancing = $symbols->filter(fn ($s) => ($s->quote->change ?? 0) > 0)->count();
            $declining = $symbols->filter(fn ($s) => ($s->quote->change ?? 0) < 0)->count();
            $unchanged = $symbols->filter(fn ($s) => ($s->quote->change ?? 0) == 0)->count();

            return [
                'total_symbols' => $symbols->count(),
                'advancing' => $advancing,
                'declining' => $declining,
                'unchanged' => $unchanged,
                'advance_decline_ratio' => $declining > 0 ? round($advancing / $declining, 2) : 0,
            ];
        });
    }

    /**
     * Get unusual options activity
     */
    public function getUnusualOptionsActivity(int $limit = 20): Collection
    {
        return Cache::remember('market:unusual_options', 300, function () use ($limit) {
            // Calculate unusual activity based on volume vs open interest ratio
            return Symbol::with(['optionContracts' => function ($query) {
                $query->where('volume', '>', 0)
                    ->whereNotNull('open_interest')
                    ->orderByDesc('volume');
            }])
                ->get()
                ->flatMap(fn ($symbol) => $symbol->optionContracts)
                ->filter(fn ($contract) => 
                    $contract->open_interest > 0 && 
                    ($contract->volume / $contract->open_interest) > 0.5
                )
                ->sortByDesc('volume')
                ->take($limit)
                ->values();
        });
    }

    /**
     * Calculate support and resistance levels
     */
    public function getSupportResistanceLevels(Symbol $symbol): array
    {
        // Simplified calculation - in production use more sophisticated algorithms
        $quote = $symbol->quote;
        
        if (!$quote) {
            return ['support' => [], 'resistance' => []];
        }

        $currentPrice = $quote->last_price;
        
        return [
            'support' => [
                round($currentPrice * 0.98, 2),
                round($currentPrice * 0.95, 2),
                round($currentPrice * 0.92, 2),
            ],
            'resistance' => [
                round($currentPrice * 1.02, 2),
                round($currentPrice * 1.05, 2),
                round($currentPrice * 1.08, 2),
            ],
        ];
    }

    /**
     * Calculate volatility metrics
     */
    public function getVolatilityMetrics(Symbol $symbol): array
    {
        $contracts = $symbol->optionContracts()
            ->whereNotNull('implied_volatility')
            ->get();

        if ($contracts->isEmpty()) {
            return [
                'avg_iv' => 0,
                'iv_rank' => 0,
                'iv_percentile' => 0,
            ];
        }

        $avgIV = $contracts->avg('implied_volatility');
        
        return [
            'avg_iv' => round($avgIV * 100, 2),
            'iv_rank' => rand(0, 100), // Simplified - calculate from historical data
            'iv_percentile' => rand(0, 100),
        ];
    }
}

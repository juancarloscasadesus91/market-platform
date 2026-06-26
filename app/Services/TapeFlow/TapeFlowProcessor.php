<?php

declare(strict_types=1);

namespace App\Services\TapeFlow;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

/**
 * Core service for processing option tape flow in real-time
 * Handles trade normalization, classification, and aggregation
 */
class TapeFlowProcessor
{
    private const REDIS_PREFIX = 'tape_flow:';
    private const CONTRACT_PREFIX = 'contract:';
    private const POSITION_PREFIX = 'position:';

    private FlowAggregator $aggregator;
    private PositionEstimator $positionEstimator;

    public function __construct()
    {
        $this->aggregator = new FlowAggregator();
        $this->positionEstimator = new PositionEstimator();
    }

    /**
     * Process incoming trade from WebSocket
     */
    public function processTrade(array $rawTrade): array
    {
        // Normalize trade data
        $trade = $this->normalizeTrade($rawTrade);

        // Calculate aggressiveness
        $trade['aggressiveness'] = $this->calculateAggressiveness(
            $trade['tradePrice'],
            $trade['bidPrice'],
            $trade['askPrice']
        );

        // Classify trade side
        $trade['classification'] = $this->classifyTradeSide($trade['aggressiveness']);

        // Calculate premium
        $trade['premium'] = $trade['tradePrice'] * $trade['size'] * 100;

        // Aggregate trade
        $this->aggregator->addTrade($trade);

        // Update position estimates
        $this->positionEstimator->updatePosition($trade);

        // Store recent tape
        $this->storeRecentTape($trade);

        return $trade;
    }

    /**
     * Normalize raw WebSocket trade data
     */
    private function normalizeTrade(array $raw): array
    {
        return [
            'symbol' => $raw['key'] ?? $raw['symbol'] ?? 'UNKNOWN',
            'type' => $this->extractType($raw),
            'strike' => $this->extractStrike($raw),
            'expiration' => $this->extractExpiration($raw),
            'tradePrice' => (float)($raw['LAST_PRICE'] ?? $raw['lastPrice'] ?? 0),
            'bidPrice' => (float)($raw['BID_PRICE'] ?? $raw['bidPrice'] ?? 0),
            'askPrice' => (float)($raw['ASK_PRICE'] ?? $raw['askPrice'] ?? 0),
            'size' => (int)($raw['LAST_SIZE'] ?? $raw['lastSize'] ?? 0),
            'timestamp' => $raw['QUOTE_TIME'] ?? $raw['timestamp'] ?? now()->timestamp * 1000,
        ];
    }

    /**
     * Calculate trade aggressiveness (0-1)
     */
    private function calculateAggressiveness(float $tradePrice, float $bidPrice, float $askPrice): float
    {
        // Handle edge cases
        if ($askPrice <= $bidPrice || $askPrice == 0 || $bidPrice == 0) {
            return 0.5; // Neutral if invalid spread
        }

        $spread = $askPrice - $bidPrice;
        if ($spread <= 0) {
            return 0.5;
        }

        $aggressiveness = ($tradePrice - $bidPrice) / $spread;

        // Clamp between 0 and 1
        return max(0.0, min(1.0, $aggressiveness));
    }

    /**
     * Classify trade side based on aggressiveness
     */
    private function classifyTradeSide(float $aggressiveness): string
    {
        if ($aggressiveness >= 0.80) {
            return 'BUY';
        } elseif ($aggressiveness >= 0.60) {
            return 'MID_LEAN_BUY';
        } elseif ($aggressiveness >= 0.40) {
            return 'MID';
        } elseif ($aggressiveness >= 0.20) {
            return 'MID_LEAN_SELL';
        } else {
            return 'SELL';
        }
    }

    /**
     * Store recent tape for live feed
     */
    private function storeRecentTape(array $trade): void
    {
        $key = self::REDIS_PREFIX . 'recent_tape';
        $maxTrades = 200;

        try {
            // Add trade to list
            Redis::lpush($key, json_encode($trade));

            // Trim to max size
            Redis::ltrim($key, 0, $maxTrades - 1);

            // Set expiration
            Redis::expire($key, 3600); // 1 hour
        } catch (\Exception $e) {
            Log::error('Failed to store recent tape', [
                'error' => $e->getMessage(),
                'trade' => $trade
            ]);
        }
    }

    /**
     * Get recent tape trades
     */
    public function getRecentTape(int $limit = 100): array
    {
        $key = self::REDIS_PREFIX . 'recent_tape';

        try {
            $trades = Redis::lrange($key, 0, $limit - 1);
            return array_map(fn($t) => json_decode($t, true), $trades);
        } catch (\Exception $e) {
            Log::error('Failed to get recent tape', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get current flow data for a time window
     */
    public function getCurrentFlow(string $window = 'day'): array
    {
        return $this->aggregator->getGlobalData($window);
    }

    /**
     * Get top bullish contracts
     */
    public function getTopBullish(string $window = 'day', int $limit = 10): array
    {
        return $this->aggregator->getTopBullishContracts($window, $limit);
    }

    /**
     * Get top bearish contracts
     */
    public function getTopBearish(string $window = 'day', int $limit = 10): array
    {
        return $this->aggregator->getTopBearishContracts($window, $limit);
    }

    /**
     * Get most aggressive contracts
     */
    public function getMostAggressive(string $window = 'day', int $limit = 10): array
    {
        return $this->aggregator->getMostAggressiveContracts($window, $limit);
    }

    /**
     * Get high MID noise contracts
     */
    public function getHighMidNoise(string $window = 'day', int $limit = 10): array
    {
        return $this->aggregator->getHighMidNoiseContracts($window, $limit);
    }

    /**
     * Get estimated live positions
     */
    public function getActivePositions(float $minPremium = 10000): array
    {
        return $this->positionEstimator->getActivePositions($minPremium);
    }

    /**
     * Get building positions
     */
    public function getBuildingPositions(int $limit = 10): array
    {
        return $this->positionEstimator->getBuildingPositions($limit);
    }

    /**
     * Get exiting positions
     */
    public function getExitingPositions(int $limit = 10): array
    {
        return $this->positionEstimator->getExitingPositions($limit);
    }

    /**
     * Extract option type from symbol
     */
    private function extractType(array $raw): string
    {
        $symbol = $raw['key'] ?? $raw['symbol'] ?? '';

        if (str_contains($symbol, 'C')) {
            return 'CALL';
        } elseif (str_contains($symbol, 'P')) {
            return 'PUT';
        }

        return 'UNKNOWN';
    }

    /**
     * Extract strike price from symbol
     */
    private function extractStrike(array $raw): float
    {
        $symbol = $raw['key'] ?? $raw['symbol'] ?? '';

        // Extract strike from option symbol (e.g., SPXW260505C5100)
        if (preg_match('/[CP](\d+)$/', $symbol, $matches)) {
            return (float)$matches[1];
        }

        return 0.0;
    }

    /**
     * Extract expiration from symbol
     */
    private function extractExpiration(array $raw): string
    {
        $symbol = $raw['key'] ?? $raw['symbol'] ?? '';

        // Extract date from option symbol (e.g., SPXW260505C5100 -> 260505)
        if (preg_match('/(\d{6})[CP]/', $symbol, $matches)) {
            $dateStr = $matches[1];
            // Convert YYMMDD to YYYY-MM-DD
            $year = '20' . substr($dateStr, 0, 2);
            $month = substr($dateStr, 2, 2);
            $day = substr($dateStr, 4, 2);
            return "$year-$month-$day";
        }

        return '';
    }

    /**
     * Reset window data
     */
    public function resetWindow(string $window): void
    {
        $this->aggregator->resetWindow($window);
    }

    /**
     * Create snapshot for persistence
     */
    public function createSnapshot(string $window): array
    {
        return $this->aggregator->createSnapshot($window);
    }
}

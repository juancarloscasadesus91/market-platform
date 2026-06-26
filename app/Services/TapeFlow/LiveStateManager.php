<?php

declare(strict_types=1);

namespace App\Services\TapeFlow;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class LiveStateManager
{
    private const PREFIX = 'tape_flow:';
    private const CONTRACT_PREFIX = 'contract:';
    private const GLOBAL_PREFIX = 'global:';
    private const POSITION_PREFIX = 'position:';
    private const TAPE_PREFIX = 'tape:';
    private const SNAPSHOT_PREFIX = 'snapshot:';

    private const TTL_SECONDS = 3600; // 1 hour
    private const TAPE_TTL_SECONDS = 300; // 5 minutes for tape data
    private const MAX_TAPE_ENTRIES = 1000;

    private FlowAggregator $flowAggregator;
    private PositionEstimator $positionEstimator;

    public function __construct()
    {
        $this->flowAggregator = new FlowAggregator();
        $this->positionEstimator = new PositionEstimator();
    }

    /**
     * Process incoming trade and update all aggregations
     */
    public function processTrade(array $rawTrade): void
    {
        try {
            // Normalize trade
            $trade = TradeNormalizer::normalize($rawTrade);
            $trade['premium'] = TradeNormalizer::calculatePremium($trade);

            if (!TradeNormalizer::validate($trade)) {
                Log::warning('Invalid trade data', ['trade' => $trade]);
                return;
            }

            // Calculate aggressiveness
            $trade['aggressiveness'] = AggressivenessCalculator::calculate(
                $trade['tradePrice'],
                $trade['bidPrice'],
                $trade['askPrice']
            );

            // Classify trade side
            $trade['classification'] = AggressivenessCalculator::classifySide($trade['aggressiveness']);

            // Update aggregations
            $this->flowAggregator->aggregateTrade($trade);
            $this->positionEstimator->processTrade($trade);

            // Store in Redis
            $this->storeTradeInRedis($trade);
            $this->updateAggregationsInRedis();

        } catch (\Exception $e) {
            Log::error('Error processing trade', ['error' => $e->getMessage(), 'trade' => $rawTrade]);
        }
    }

    /**
     * Get current flow data
     */
    public function getCurrentFlowData(string $window = 'day'): array
    {
        $key = self::GLOBAL_PREFIX . $window;

        if (Redis::exists($key)) {
            return json_decode(Redis::get($key), true);
        }

        // Fallback to in-memory aggregator
        $data = $this->flowAggregator->getGlobalData($window);

        // Return empty data if no data exists
        if (empty($data)) {
            return $this->getEmptyGlobalData();
        }

        return $data;
    }

    /**
     * Get contract flow data
     */
    public function getContractFlowData(string $contractKey, string $window = 'day'): array
    {
        $key = self::CONTRACT_PREFIX . $contractKey . ':' . $window;

        if (Redis::exists($key)) {
            return json_decode(Redis::get($key), true);
        }

        // Fallback to in-memory aggregator
        return $this->flowAggregator->getContractData($contractKey, $window);
    }

    /**
     * Get all contracts for a window
     */
    public function getAllContracts(string $window = 'day'): array
    {
        $pattern = self::CONTRACT_PREFIX . '*:' . $window;
        $keys = Redis::keys($pattern);

        $contracts = [];
        foreach ($keys as $key) {
            $contractKey = $this->extractContractKeyFromRedisKey($key, $window);
            $contracts[$contractKey] = $this->getContractFlowData($contractKey, $window);
        }

        // Fallback to in-memory if no Redis data
        if (empty($contracts)) {
            return $this->flowAggregator->getAllContracts($window);
        }

        return $contracts;
    }

    /**
     * Get position data
     */
    public function getPositionData(string $contractKey): array
    {
        $key = self::POSITION_PREFIX . $contractKey;

        if (Redis::exists($key)) {
            return json_decode(Redis::get($key), true);
        }

        // Fallback to in-memory estimator
        return $this->positionEstimator->getPosition($contractKey);
    }

    /**
     * Get all positions
     */
    public function getAllPositions(): array
    {
        $pattern = self::POSITION_PREFIX . '*';
        $keys = Redis::keys($pattern);

        $positions = [];
        foreach ($keys as $key) {
            $contractKey = str_replace(self::POSITION_PREFIX, '', $key);
            $positions[$contractKey] = $this->getPositionData($contractKey);
        }

        // Fallback to in-memory if no Redis data
        if (empty($positions)) {
            return $this->positionEstimator->getAllPositions();
        }

        return $positions;
    }

    /**
     * Get recent tape data
     */
    public function getRecentTape(int $limit = 100): array
    {
        try {
            $key = self::TAPE_PREFIX . 'recent';

            if (Redis::exists($key)) {
                $tape = json_decode(Redis::get($key), true);
                return array_slice($tape, -$limit);
            }

            return [];
        } catch (\Exception $e) {
            Log::error('Error getting recent tape', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get top contracts by directional score
     */
    public function getTopBullishContracts(string $window = 'day', int $limit = 10): array
    {
        try {
            return $this->flowAggregator->getTopBullishContracts($window, $limit);
        } catch (\Exception $e) {
            Log::error('Error getting top bullish contracts', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get top bearish contracts
     */
    public function getTopBearishContracts(string $window = 'day', int $limit = 10): array
    {
        try {
            return $this->flowAggregator->getTopBearishContracts($window, $limit);
        } catch (\Exception $e) {
            Log::error('Error getting top bearish contracts', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get most aggressive contracts
     */
    public function getMostAggressiveContracts(string $window = 'day', int $limit = 10): array
    {
        try {
            return $this->flowAggregator->getMostAggressiveContracts($window, $limit);
        } catch (\Exception $e) {
            Log::error('Error getting most aggressive contracts', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get contracts with high MID noise
     */
    public function getHighMidNoiseContracts(string $window = 'day', int $limit = 10): array
    {
        try {
            return $this->flowAggregator->getHighMidNoiseContracts($window, $limit);
        } catch (\Exception $e) {
            Log::error('Error getting high MID noise contracts', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get active positions (with remaining premium)
     */
    public function getActivePositions(float $minRemainingPremium = 10000): array
    {
        try {
            return $this->positionEstimator->getActivePositions($minRemainingPremium);
        } catch (\Exception $e) {
            Log::error('Error getting active positions', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get building positions
     */
    public function getBuildingPositions(): array
    {
        try {
            return $this->positionEstimator->getBuildingPositions();
        } catch (\Exception $e) {
            Log::error('Error getting building positions', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get exiting positions
     */
    public function getExitingPositions(): array
    {
        try {
            return $this->positionEstimator->getExitingPositions();
        } catch (\Exception $e) {
            Log::error('Error getting exiting positions', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Create snapshots for persistence
     */
    public function createSnapshots(): void
    {
        try {
            $now = now();

            // Create flow snapshots for each window
            foreach (['1m', '5m', '15m', 'day'] as $window) {
                $this->createFlowSnapshot($window, $now);
            }

            // Create position snapshot
            $this->createPositionSnapshot($now);

            Log::info('Tape flow snapshots created', ['timestamp' => $now]);

        } catch (\Exception $e) {
            Log::error('Error creating snapshots', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Reset time window data
     */
    public function resetWindow(string $window): void
    {
        $this->flowAggregator->resetWindow($window);

        // Clear Redis data for this window
        $pattern = self::CONTRACT_PREFIX . '*:' . $window;
        $keys = Redis::keys($pattern);

        if (!empty($keys)) {
            Redis::del($keys);
        }

        Redis::del(self::GLOBAL_PREFIX . $window);
    }

    /**
     * Store trade in Redis for tape feed
     */
    private function storeTradeInRedis(array $trade): void
    {
        $key = self::TAPE_PREFIX . 'recent';

        // Get existing tape data
        $tape = [];
        if (Redis::exists($key)) {
            $tape = json_decode(Redis::get($key), true);
        }

        // Add new trade
        $tape[] = [
            'symbol' => $trade['symbol'],
            'strike' => $trade['strike'],
            'type' => $trade['type'],
            'premium' => $trade['premium'],
            'aggressiveness' => $trade['aggressiveness'],
            'classification' => $trade['classification'],
            'timestamp' => $trade['timestamp'],
            'tradePrice' => $trade['tradePrice'],
            'size' => $trade['size'],
        ];

        // Keep only recent entries
        if (count($tape) > self::MAX_TAPE_ENTRIES) {
            $tape = array_slice($tape, -self::MAX_TAPE_ENTRIES);
        }

        // Store back in Redis
        Redis::setex($key, self::TAPE_TTL_SECONDS, json_encode($tape));
    }

    /**
     * Update aggregations in Redis
     */
    private function updateAggregationsInRedis(): void
    {
        // Update global data for all windows
        foreach (['1m', '5m', '15m', 'day'] as $window) {
            $globalData = $this->flowAggregator->getGlobalData($window);
            Redis::setex(self::GLOBAL_PREFIX . $window, self::TTL_SECONDS, json_encode($globalData));
        }

        // Update contract data
        $allContracts = $this->flowAggregator->getAllContracts('day');
        foreach ($allContracts as $contractKey => $contractData) {
            Redis::setex(
                self::CONTRACT_PREFIX . $contractKey . ':day',
                self::TTL_SECONDS,
                json_encode($contractData)
            );
        }

        // Update position data
        $allPositions = $this->positionEstimator->getAllPositions();
        foreach ($allPositions as $contractKey => $positionData) {
            Redis::setex(
                self::POSITION_PREFIX . $contractKey,
                self::TTL_SECONDS,
                json_encode($positionData)
            );
        }
    }

    /**
     * Create flow snapshot
     */
    private function createFlowSnapshot(string $window, \Carbon\Carbon $timestamp): void
    {
        $contracts = $this->flowAggregator->getAllContracts($window);

        foreach ($contracts as $contractKey => $data) {
            $meta = $this->extractContractMeta($contractKey);

            \DB::table('option_flow_snapshots')->insert([
                'symbol' => $meta['symbol'],
                'strike' => $meta['strike'],
                'option_type' => $meta['type'],
                'expiration_date' => $meta['expiration'],
                'time_window' => $window,
                'window_start' => $this->getWindowStart($timestamp, $window),
                'window_end' => $timestamp,
                'total_premium' => $data['total_premium'],
                'buy_premium' => $data['buy_premium'],
                'sell_premium' => $data['sell_premium'],
                'mid_premium' => $data['mid_premium'],
                'buy_trades' => $data['buy_trades'],
                'sell_trades' => $data['sell_trades'],
                'mid_trades' => $data['mid_trades'],
                'buy_volume' => $data['buy_volume'],
                'sell_volume' => $data['sell_volume'],
                'mid_volume' => $data['mid_volume'],
                'avg_aggressiveness' => $data['avg_aggressiveness'],
                'mid_noise_ratio' => $data['mid_noise_ratio'],
                'directional_score' => $data['directional_score'],
                'confidence_level' => $data['confidence_level'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }
    }

    /**
     * Create position snapshot
     */
    private function createPositionSnapshot(\Carbon\Carbon $timestamp): void
    {
        $positions = $this->positionEstimator->getAllPositions();

        foreach ($positions as $contractKey => $data) {
            \DB::table('option_position_snapshots')->insert([
                'symbol' => $data['symbol'],
                'strike' => $data['strike'],
                'option_type' => $data['type'],
                'expiration_date' => $data['expiration'],
                'snapshot_time' => $timestamp,
                'estimated_open_premium' => $data['estimated_open_premium'],
                'estimated_close_premium' => $data['estimated_close_premium'],
                'estimated_remaining_premium' => $data['estimated_remaining_premium'],
                'avg_entry_price' => $data['avg_entry_price'],
                'avg_exit_price' => $data['avg_exit_price'],
                'current_mark' => $data['current_mark'],
                'unrealized_pnl_estimate' => $data['unrealized_pnl_estimate'],
                'potential_exit_pressure' => $data['potential_exit_pressure'],
                'position_confidence' => $data['position_confidence'],
                'position_status' => $data['position_status'],
                'mid_premium' => $data['mid_premium'],
                'mid_trade_count' => $data['mid_trade_count'],
                'mid_leaning_buy_premium' => $data['mid_leaning_buy_premium'],
                'mid_leaning_sell_premium' => $data['mid_leaning_sell_premium'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }
    }

    /**
     * Extract contract metadata from key
     */
    private function extractContractMeta(string $contractKey): array
    {
        $parts = explode('_', $contractKey);

        return [
            'symbol' => $parts[0] ?? '',
            'strike' => (float) ($parts[1] ?? 0),
            'type' => $parts[2] ?? '',
            'expiration' => $parts[3] ?? '',
        ];
    }

    /**
     * Extract contract key from Redis key
     */
    private function extractContractKeyFromRedisKey(string $redisKey, string $window): string
    {
        $pattern = self::CONTRACT_PREFIX . '(.*):' . $window;
        if (preg_match('/' . str_replace('/', '\/', $pattern) . '/', $redisKey, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * Get window start time based on window type
     */
    private function getWindowStart(\Carbon\Carbon $timestamp, string $window): \Carbon\Carbon
    {
        return match($window) {
            '1m' => $timestamp->copy()->subMinute(),
            '5m' => $timestamp->copy()->subMinutes(5),
            '15m' => $timestamp->copy()->subMinutes(15),
            'day' => $timestamp->copy()->startOfDay(),
            default => $timestamp->copy()->subHour(),
        };
    }

    /**
     * Get empty global data structure
     */
    private function getEmptyGlobalData(): array
    {
        return [
            'total_premium' => 0,
            'buy_premium' => 0,
            'sell_premium' => 0,
            'mid_premium' => 0,
            'total_trades' => 0,
            'buy_trades' => 0,
            'sell_trades' => 0,
            'mid_trades' => 0,
            'total_volume' => 0,
            'buy_volume' => 0,
            'sell_volume' => 0,
            'mid_volume' => 0,
            'total_aggressiveness' => 0,
            'avg_aggressiveness' => 0,
            'mid_noise_ratio' => 0,
            'bullish_score' => 0,
            'bearish_score' => 0,
            'confidence_level' => 'UNKNOWN',
        ];
    }

    /**
     * Get Redis connection
     */
    private function redis(): \Illuminate\Redis\Connections\Connection
    {
        return Redis::connection();
    }
}

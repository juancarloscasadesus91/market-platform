<?php

declare(strict_types=1);

namespace App\Services\TapeFlow;

use Illuminate\Support\Facades\Redis;

/**
 * Aggregates option flow data in real-time
 * Tracks directional premium, aggressiveness, and confidence
 */
class FlowAggregator
{
    private const REDIS_PREFIX = 'tape_flow:agg:';
    private const GLOBAL_PREFIX = 'tape_flow:global:';
    
    /**
     * Add trade to aggregation
     */
    public function addTrade(array $trade): void
    {
        $contractKey = $this->getContractKey($trade);
        
        // Aggregate for all time windows
        foreach (['1m', '5m', '15m', 'day'] as $window) {
            $this->aggregateForWindow($trade, $contractKey, $window);
            $this->updateGlobalForWindow($trade, $window);
        }
    }
    
    /**
     * Aggregate trade for specific time window
     */
    private function aggregateForWindow(array $trade, string $contractKey, string $window): void
    {
        $key = self::REDIS_PREFIX . $window . ':' . $contractKey;
        
        try {
            // Get existing data
            $data = Redis::get($key);
            $agg = $data ? json_decode($data, true) : $this->getEmptyAggregation($trade);
            
            // Update aggregation
            $agg['total_premium'] += $trade['premium'];
            $agg['total_trades']++;
            $agg['total_volume'] += $trade['size'];
            
            // Classify and aggregate by side
            $classification = $trade['classification'];
            
            if ($classification === 'BUY') {
                $agg['buy_premium'] += $trade['premium'];
                $agg['buy_trades']++;
                $agg['buy_volume'] += $trade['size'];
            } elseif ($classification === 'SELL') {
                $agg['sell_premium'] += $trade['premium'];
                $agg['sell_trades']++;
                $agg['sell_volume'] += $trade['size'];
            } else {
                // MID, MID_LEAN_BUY, MID_LEAN_SELL
                $agg['mid_premium'] += $trade['premium'];
                $agg['mid_trades']++;
                $agg['mid_volume'] += $trade['size'];
            }
            
            // Update aggressiveness
            $totalAgg = $agg['total_aggressiveness'] * ($agg['total_trades'] - 1);
            $agg['total_aggressiveness'] = ($totalAgg + $trade['aggressiveness']) / $agg['total_trades'];
            $agg['avg_aggressiveness'] = $agg['total_aggressiveness'];
            
            // Calculate MID noise ratio
            $agg['mid_noise_ratio'] = $agg['total_premium'] > 0 
                ? $agg['mid_premium'] / $agg['total_premium'] 
                : 0;
            
            // Calculate directional score
            $agg['directional_score'] = $this->calculateDirectionalScore($agg, $trade['type']);
            
            // Calculate confidence
            $agg['confidence_level'] = $this->calculateConfidence($agg);
            
            // Update last trade timestamp
            $agg['last_trade_time'] = $trade['timestamp'];
            
            // Store updated aggregation
            Redis::setex($key, $this->getWindowTTL($window), json_encode($agg));
            
        } catch (\Exception $e) {
            \Log::error('Error aggregating trade', [
                'error' => $e->getMessage(),
                'trade' => $trade,
                'window' => $window
            ]);
        }
    }
    
    /**
     * Update global aggregation for window
     */
    private function updateGlobalForWindow(array $trade, string $window): void
    {
        $key = self::GLOBAL_PREFIX . $window;
        
        try {
            $data = Redis::get($key);
            $global = $data ? json_decode($data, true) : $this->getEmptyGlobalData();
            
            // Update global metrics
            $global['total_premium'] += $trade['premium'];
            $global['total_trades']++;
            $global['total_volume'] += $trade['size'];
            
            $classification = $trade['classification'];
            
            if ($classification === 'BUY') {
                $global['buy_premium'] += $trade['premium'];
                $global['buy_trades']++;
                $global['buy_volume'] += $trade['size'];
            } elseif ($classification === 'SELL') {
                $global['sell_premium'] += $trade['premium'];
                $global['sell_trades']++;
                $global['sell_volume'] += $trade['size'];
            } else {
                $global['mid_premium'] += $trade['premium'];
                $global['mid_trades']++;
                $global['mid_volume'] += $trade['size'];
            }
            
            // Update aggressiveness
            $totalAgg = $global['total_aggressiveness'] * ($global['total_trades'] - 1);
            $global['total_aggressiveness'] = ($totalAgg + $trade['aggressiveness']) / $global['total_trades'];
            $global['avg_aggressiveness'] = $global['total_aggressiveness'];
            
            // Calculate MID noise ratio
            $global['mid_noise_ratio'] = $global['total_premium'] > 0 
                ? $global['mid_premium'] / $global['total_premium'] 
                : 0;
            
            // Calculate directional scores
            $global['bullish_score'] = $global['buy_premium'] - $global['sell_premium'];
            $global['bearish_score'] = $global['sell_premium'] - $global['buy_premium'];
            
            // Calculate confidence
            $global['confidence_level'] = $this->calculateConfidence($global);
            
            Redis::setex($key, $this->getWindowTTL($window), json_encode($global));
            
        } catch (\Exception $e) {
            \Log::error('Error updating global aggregation', [
                'error' => $e->getMessage(),
                'window' => $window
            ]);
        }
    }
    
    /**
     * Calculate directional score for contract
     */
    private function calculateDirectionalScore(array $agg, string $type): float
    {
        $buyPremium = $agg['buy_premium'];
        $sellPremium = $agg['sell_premium'];
        
        if ($type === 'CALL') {
            // CALL BUY = bullish, CALL SELL = bearish
            return $buyPremium - $sellPremium;
        } else {
            // PUT BUY = bearish, PUT SELL = bullish
            return $sellPremium - $buyPremium;
        }
    }
    
    /**
     * Calculate confidence level
     */
    private function calculateConfidence(array $agg): string
    {
        $midNoiseRatio = $agg['mid_noise_ratio'];
        $totalPremium = $agg['total_premium'];
        $buyPremium = $agg['buy_premium'];
        $sellPremium = $agg['sell_premium'];
        
        // High MID noise = low confidence
        if ($midNoiseRatio > 0.50) {
            return 'LOW';
        }
        
        // Strong directional imbalance + low MID = high confidence
        if ($midNoiseRatio < 0.25 && $totalPremium > 0) {
            $imbalance = abs($buyPremium - $sellPremium) / $totalPremium;
            if ($imbalance > 0.5) {
                return 'HIGH';
            }
        }
        
        return 'MEDIUM';
    }
    
    /**
     * Get global data for window
     */
    public function getGlobalData(string $window): array
    {
        $key = self::GLOBAL_PREFIX . $window;
        
        try {
            $data = Redis::get($key);
            return $data ? json_decode($data, true) : $this->getEmptyGlobalData();
        } catch (\Exception $e) {
            \Log::error('Error getting global data', ['error' => $e->getMessage()]);
            return $this->getEmptyGlobalData();
        }
    }
    
    /**
     * Get top bullish contracts
     */
    public function getTopBullishContracts(string $window, int $limit): array
    {
        return $this->getTopContracts($window, $limit, 'bullish');
    }
    
    /**
     * Get top bearish contracts
     */
    public function getTopBearishContracts(string $window, int $limit): array
    {
        return $this->getTopContracts($window, $limit, 'bearish');
    }
    
    /**
     * Get most aggressive contracts
     */
    public function getMostAggressiveContracts(string $window, int $limit): array
    {
        return $this->getTopContracts($window, $limit, 'aggressive');
    }
    
    /**
     * Get high MID noise contracts
     */
    public function getHighMidNoiseContracts(string $window, int $limit): array
    {
        return $this->getTopContracts($window, $limit, 'noise');
    }
    
    /**
     * Get top contracts by criteria
     */
    private function getTopContracts(string $window, int $limit, string $criteria): array
    {
        $pattern = self::REDIS_PREFIX . $window . ':*';
        $contracts = [];
        
        try {
            $keys = Redis::keys($pattern);
            
            foreach ($keys as $key) {
                $data = Redis::get($key);
                if ($data) {
                    $agg = json_decode($data, true);
                    $contracts[] = $agg;
                }
            }
            
            // Sort by criteria
            usort($contracts, function ($a, $b) use ($criteria) {
                return match ($criteria) {
                    'bullish' => $b['directional_score'] <=> $a['directional_score'],
                    'bearish' => $a['directional_score'] <=> $b['directional_score'],
                    'aggressive' => $b['avg_aggressiveness'] <=> $a['avg_aggressiveness'],
                    'noise' => $b['mid_noise_ratio'] <=> $a['mid_noise_ratio'],
                    default => 0
                };
            });
            
            return array_slice($contracts, 0, $limit);
            
        } catch (\Exception $e) {
            \Log::error('Error getting top contracts', [
                'error' => $e->getMessage(),
                'criteria' => $criteria
            ]);
            return [];
        }
    }
    
    /**
     * Reset window data
     */
    public function resetWindow(string $window): void
    {
        $pattern = self::REDIS_PREFIX . $window . ':*';
        $globalKey = self::GLOBAL_PREFIX . $window;
        
        try {
            $keys = Redis::keys($pattern);
            foreach ($keys as $key) {
                Redis::del($key);
            }
            Redis::del($globalKey);
        } catch (\Exception $e) {
            \Log::error('Error resetting window', ['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Create snapshot for persistence
     */
    public function createSnapshot(string $window): array
    {
        return [
            'global' => $this->getGlobalData($window),
            'top_bullish' => $this->getTopBullishContracts($window, 10),
            'top_bearish' => $this->getTopBearishContracts($window, 10),
            'most_aggressive' => $this->getMostAggressiveContracts($window, 10),
            'high_mid_noise' => $this->getHighMidNoiseContracts($window, 10),
            'timestamp' => now()->toISOString(),
            'window' => $window
        ];
    }
    
    /**
     * Get contract key
     */
    private function getContractKey(array $trade): string
    {
        return "{$trade['symbol']}_{$trade['strike']}_{$trade['type']}_{$trade['expiration']}";
    }
    
    /**
     * Get empty aggregation structure
     */
    private function getEmptyAggregation(array $trade): array
    {
        return [
            'symbol' => $trade['symbol'],
            'strike' => $trade['strike'],
            'type' => $trade['type'],
            'expiration' => $trade['expiration'],
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
            'directional_score' => 0,
            'confidence_level' => 'UNKNOWN',
            'last_trade_time' => null
        ];
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
            'confidence_level' => 'UNKNOWN'
        ];
    }
    
    /**
     * Get TTL for window
     */
    private function getWindowTTL(string $window): int
    {
        return match ($window) {
            '1m' => 120,      // 2 minutes
            '5m' => 600,      // 10 minutes
            '15m' => 1800,    // 30 minutes
            'day' => 86400,   // 24 hours
            default => 3600
        };
    }
}

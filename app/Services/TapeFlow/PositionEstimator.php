<?php

declare(strict_types=1);

namespace App\Services\TapeFlow;

use Illuminate\Support\Facades\Redis;

/**
 * Estimates live positions and remaining premium
 * Tracks position building, holding, and exiting
 */
class PositionEstimator
{
    private const REDIS_PREFIX = 'tape_flow:position:';
    
    /**
     * Update position estimate based on trade
     */
    public function updatePosition(array $trade): void
    {
        $contractKey = $this->getContractKey($trade);
        $key = self::REDIS_PREFIX . $contractKey;
        
        try {
            // Get existing position
            $data = Redis::get($key);
            $position = $data ? json_decode($data, true) : $this->getEmptyPosition($trade);
            
            $classification = $trade['classification'];
            $premium = $trade['premium'];
            
            // Infer open/close based on classification
            if ($classification === 'BUY') {
                // Aggressive buying - likely opening
                $position['estimated_open_premium'] += $premium;
                $position['open_trades']++;
                $position['total_buy_premium'] += $premium;
                
                // Track entry price
                $totalOpen = $position['estimated_open_premium'];
                $position['avg_entry_price'] = $totalOpen > 0 
                    ? ($position['avg_entry_price'] * ($position['open_trades'] - 1) + $trade['tradePrice']) / $position['open_trades']
                    : $trade['tradePrice'];
                    
            } elseif ($classification === 'SELL') {
                // Aggressive selling - likely closing if there was prior buying
                if ($position['estimated_open_premium'] > 0) {
                    $position['estimated_close_premium'] += $premium;
                    $position['close_trades']++;
                    
                    // Track exit price
                    $position['avg_exit_price'] = $position['close_trades'] > 0
                        ? ($position['avg_exit_price'] * ($position['close_trades'] - 1) + $trade['tradePrice']) / $position['close_trades']
                        : $trade['tradePrice'];
                }
                
                $position['total_sell_premium'] += $premium;
            }
            
            // Calculate remaining premium
            $position['estimated_remaining_premium'] = max(0, 
                $position['estimated_open_premium'] - $position['estimated_close_premium']
            );
            
            // Calculate unrealized P/L estimate
            if ($position['avg_entry_price'] > 0 && $trade['tradePrice'] > 0) {
                $priceDiff = $trade['tradePrice'] - $position['avg_entry_price'];
                $position['unrealized_pnl_estimate'] = $priceDiff * $position['estimated_remaining_premium'] / $trade['tradePrice'];
            }
            
            // Calculate exit pressure
            if ($position['estimated_open_premium'] > 0) {
                $position['potential_exit_pressure'] = $position['estimated_close_premium'] / $position['estimated_open_premium'];
            }
            
            // Calculate position confidence
            $position['position_confidence'] = $this->calculatePositionConfidence($position, $trade);
            
            // Update current mark
            $position['current_mark'] = $trade['tradePrice'];
            $position['last_update'] = $trade['timestamp'];
            
            // Store updated position
            Redis::setex($key, 3600, json_encode($position)); // 1 hour TTL
            
        } catch (\Exception $e) {
            \Log::error('Error updating position', [
                'error' => $e->getMessage(),
                'trade' => $trade
            ]);
        }
    }
    
    /**
     * Calculate position confidence
     */
    private function calculatePositionConfidence(array $position, array $trade): float
    {
        $confidence = 0.5; // Base confidence
        
        // Higher confidence if:
        // - Trade is aggressive (near ask or bid)
        if ($trade['aggressiveness'] > 0.8 || $trade['aggressiveness'] < 0.2) {
            $confidence += 0.2;
        }
        
        // - Premium is large
        if ($trade['premium'] > 50000) {
            $confidence += 0.15;
        }
        
        // - Multiple trades on same contract
        if ($position['open_trades'] > 5) {
            $confidence += 0.1;
        }
        
        // - Low MID noise
        $totalPremium = $position['total_buy_premium'] + $position['total_sell_premium'];
        if ($totalPremium > 0) {
            $buyRatio = $position['total_buy_premium'] / $totalPremium;
            if ($buyRatio > 0.7 || $buyRatio < 0.3) {
                $confidence += 0.15;
            }
        }
        
        return min(1.0, $confidence);
    }
    
    /**
     * Get active positions
     */
    public function getActivePositions(float $minRemainingPremium = 10000): array
    {
        $pattern = self::REDIS_PREFIX . '*';
        $positions = [];
        
        try {
            $keys = Redis::keys($pattern);
            
            foreach ($keys as $key) {
                $data = Redis::get($key);
                if ($data) {
                    $position = json_decode($data, true);
                    
                    if ($position['estimated_remaining_premium'] >= $minRemainingPremium) {
                        $positions[] = $position;
                    }
                }
            }
            
            // Sort by remaining premium
            usort($positions, fn($a, $b) => 
                $b['estimated_remaining_premium'] <=> $a['estimated_remaining_premium']
            );
            
            return $positions;
            
        } catch (\Exception $e) {
            \Log::error('Error getting active positions', ['error' => $e->getMessage()]);
            return [];
        }
    }
    
    /**
     * Get building positions (more opening than closing)
     */
    public function getBuildingPositions(int $limit = 10): array
    {
        $pattern = self::REDIS_PREFIX . '*';
        $positions = [];
        
        try {
            $keys = Redis::keys($pattern);
            
            foreach ($keys as $key) {
                $data = Redis::get($key);
                if ($data) {
                    $position = json_decode($data, true);
                    
                    // Building if open premium > close premium and exit pressure < 0.3
                    if ($position['estimated_open_premium'] > $position['estimated_close_premium'] 
                        && $position['potential_exit_pressure'] < 0.3
                        && $position['estimated_remaining_premium'] > 10000) {
                        $positions[] = $position;
                    }
                }
            }
            
            // Sort by remaining premium
            usort($positions, fn($a, $b) => 
                $b['estimated_remaining_premium'] <=> $a['estimated_remaining_premium']
            );
            
            return array_slice($positions, 0, $limit);
            
        } catch (\Exception $e) {
            \Log::error('Error getting building positions', ['error' => $e->getMessage()]);
            return [];
        }
    }
    
    /**
     * Get exiting positions (more closing than opening)
     */
    public function getExitingPositions(int $limit = 10): array
    {
        $pattern = self::REDIS_PREFIX . '*';
        $positions = [];
        
        try {
            $keys = Redis::keys($pattern);
            
            foreach ($keys as $key) {
                $data = Redis::get($key);
                if ($data) {
                    $position = json_decode($data, true);
                    
                    // Exiting if exit pressure > 0.5
                    if ($position['potential_exit_pressure'] > 0.5
                        && $position['estimated_remaining_premium'] > 5000) {
                        $positions[] = $position;
                    }
                }
            }
            
            // Sort by exit pressure
            usort($positions, fn($a, $b) => 
                $b['potential_exit_pressure'] <=> $a['potential_exit_pressure']
            );
            
            return array_slice($positions, 0, $limit);
            
        } catch (\Exception $e) {
            \Log::error('Error getting exiting positions', ['error' => $e->getMessage()]);
            return [];
        }
    }
    
    /**
     * Get contract key
     */
    private function getContractKey(array $trade): string
    {
        return "{$trade['symbol']}_{$trade['strike']}_{$trade['type']}_{$trade['expiration']}";
    }
    
    /**
     * Get empty position structure
     */
    private function getEmptyPosition(array $trade): array
    {
        return [
            'symbol' => $trade['symbol'],
            'strike' => $trade['strike'],
            'type' => $trade['type'],
            'expiration' => $trade['expiration'],
            'estimated_open_premium' => 0,
            'estimated_close_premium' => 0,
            'estimated_remaining_premium' => 0,
            'avg_entry_price' => 0,
            'avg_exit_price' => 0,
            'current_mark' => 0,
            'unrealized_pnl_estimate' => 0,
            'potential_exit_pressure' => 0,
            'position_confidence' => 0,
            'open_trades' => 0,
            'close_trades' => 0,
            'total_buy_premium' => 0,
            'total_sell_premium' => 0,
            'last_update' => null
        ];
    }
}

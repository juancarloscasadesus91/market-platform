<?php

declare(strict_types=1);

namespace App\Services\TapeFlow;

class AggressivenessCalculator
{
    /**
     * Calculate trade aggressiveness based on price relative to bid/ask
     */
    public static function calculate(float $tradePrice, float $bidPrice, float $askPrice): float
    {
        // Handle edge cases
        if ($askPrice <= $bidPrice || $askPrice - $bidPrice <= 0) {
            return 0.5; // Neutral aggressiveness
        }

        if ($tradePrice <= $bidPrice) {
            return 0.0; // Aggressive sell
        }

        if ($tradePrice >= $askPrice) {
            return 1.0; // Aggressive buy
        }

        // Calculate position within spread
        $spread = $askPrice - $bidPrice;
        $position = $tradePrice - $bidPrice;

        return max(0.0, min(1.0, $position / $spread));
    }

    /**
     * Classify trade side based on aggressiveness
     */
    public static function classifySide(float $aggressiveness): string
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
     * Get color for trade classification
     */
    public static function getColor(string $classification): string
    {
        return match($classification) {
            'BUY' => 'emerald',
            'MID_LEAN_BUY' => 'green',
            'MID' => 'yellow',
            'MID_LEAN_SELL' => 'orange',
            'SELL' => 'red',
            default => 'slate'
        };
    }

    /**
     * Get emoji for trade classification
     */
    public static function getEmoji(string $classification): string
    {
        return match($classification) {
            'BUY' => '🟢',
            'MID_LEAN_BUY' => '🟡',
            'MID' => '⚪',
            'MID_LEAN_SELL' => '🟠',
            'SELL' => '🔴',
            default => '⚫'
        };
    }

    /**
     * Calculate spread width percentage
     */
    public static function calculateSpreadWidth(float $bidPrice, float $askPrice): float
    {
        if ($bidPrice <= 0) {
            return 0;
        }

        $spread = $askPrice - $bidPrice;
        $midPrice = ($bidPrice + $askPrice) / 2;

        return $midPrice > 0 ? ($spread / $midPrice) * 100 : 0;
    }

    /**
     * Determine if spread is tight (good liquidity)
     */
    public static function isTightSpread(float $bidPrice, float $askPrice, float $threshold = 0.5): bool
    {
        return self::calculateSpreadWidth($bidPrice, $askPrice) <= $threshold;
    }

    /**
     * Calculate confidence based on aggressiveness and spread
     */
    public static function calculateConfidence(float $aggressiveness, float $bidPrice, float $askPrice): float
    {
        $spreadWidth = self::calculateSpreadWidth($bidPrice, $askPrice);

        // Higher confidence for trades near bid/ask with tight spreads
        $distanceFromMid = abs($aggressiveness - 0.5) * 2; // 0 at mid, 1 at edges
        $spreadQuality = max(0, 1 - ($spreadWidth / 2.0)); // Penalty for wide spreads

        return $distanceFromMid * $spreadQuality;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\TapeFlow;

class TradeNormalizer
{
    /**
     * Normalize raw websocket trade data into standard format
     */
    public static function normalize(array $rawTrade): array
    {
        return [
            'symbol' => $rawTrade['symbol'] ?? '',
            'type' => self::extractOptionType($rawTrade['symbol'] ?? ''),
            'strike' => self::extractStrike($rawTrade['symbol'] ?? ''),
            'expiration' => self::extractExpiration($rawTrade['symbol'] ?? ''),
            'tradePrice' => (float) ($rawTrade['last'] ?? $rawTrade['price'] ?? 0),
            'bidPrice' => (float) ($rawTrade['bid'] ?? 0),
            'askPrice' => (float) ($rawTrade['ask'] ?? 0),
            'size' => (int) ($rawTrade['size'] ?? $rawTrade['volume'] ?? 1),
            'premium' => 0, // Will be calculated
            'timestamp' => $rawTrade['time'] ?? now()->timestamp * 1000, // milliseconds
            'side' => $rawTrade['side'] ?? null, // If provided by exchange
        ];
    }

    /**
     * Calculate premium for normalized trade
     */
    public static function calculatePremium(array $normalizedTrade): float
    {
        return $normalizedTrade['tradePrice'] * $normalizedTrade['size'] * 100;
    }

    /**
     * Extract option type from symbol
     */
    private static function extractOptionType(string $symbol): string
    {
        // SPXW 260508C07390000 - C indicates CALL, P indicates PUT
        if (preg_match('/C\d{8}/', $symbol)) {
            return 'CALL';
        } elseif (preg_match('/P\d{8}/', $symbol)) {
            return 'PUT';
        }

        // Fallback for other formats
        return strtoupper(substr($symbol, -1)) === 'C' ? 'CALL' : 'PUT';
    }

    /**
     * Extract strike price from symbol
     */
    private static function extractStrike(string $symbol): float
    {
        // SPXW 260508C07390000 - 07390000 = 7390.00
        if (preg_match('/[CP](\d{8})/', $symbol, $matches)) {
            $strikeStr = $matches[1];
            return (float) (substr($strikeStr, 0, -2) . '.' . substr($strikeStr, -2));
        }

        return 0;
    }

    /**
     * Extract expiration date from symbol
     */
    private static function extractExpiration(string $symbol): ?string
    {
        // SPXW 260508C07390000 - 260508 = 2026-05-08
        if (preg_match('/(\d{6})[CP]\d{8}/', $symbol, $matches)) {
            $dateStr = $matches[1];
            $year = '20' . substr($dateStr, 0, 2);
            $month = substr($dateStr, 2, 2);
            $day = substr($dateStr, 4, 2);

            return "{$year}-{$month}-{$day}";
        }

        return null;
    }

    /**
     * Validate normalized trade data
     */
    public static function validate(array $trade): bool
    {
        return !empty($trade['symbol']) &&
               $trade['tradePrice'] > 0 &&
               $trade['size'] > 0 &&
               in_array($trade['type'], ['CALL', 'PUT']) &&
               $trade['strike'] > 0;
    }
}

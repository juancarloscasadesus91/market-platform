<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Support\Collection;

interface MarketDataServiceInterface
{
    /**
     * Return an ordered collection of Candle models for the given range.
     * Fetches from the external API if not already cached in DB.
     */
    public function getCandles(
        string $symbol,
        string $timeframe,
        string $dateFrom,
        string $dateTo,
    ): Collection;

    /**
     * Convert a Collection of Candle models to plain arrays for processing.
     */
    public function toRawArray(Collection $candles): array;

    /**
     * Debug log populated after the last getCandles() / fetchAndStore() call.
     */
    public function getLastFetchLog(): array;
}

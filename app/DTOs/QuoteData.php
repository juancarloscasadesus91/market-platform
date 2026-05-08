<?php

declare(strict_types=1);

namespace App\DTOs;

use Carbon\Carbon;

readonly class QuoteData
{
    public function __construct(
        public string $ticker,
        public float $lastPrice,
        public ?float $bid = null,
        public ?float $ask = null,
        public ?float $open = null,
        public ?float $high = null,
        public ?float $low = null,
        public ?float $close = null,
        public ?float $change = null,
        public ?float $changePercent = null,
        public ?int $volume = null,
        public ?int $avgVolume = null,
        public ?float $marketCap = null,
        public ?float $peRatio = null,
        public ?float $week52High = null,
        public ?float $week52Low = null,
        public ?Carbon $quoteTime = null,
    ) {}

    public function toArray(): array
    {
        return [
            'last_price' => $this->lastPrice,
            'bid' => $this->bid,
            'ask' => $this->ask,
            'open' => $this->open,
            'high' => $this->high,
            'low' => $this->low,
            'close' => $this->close,
            'change' => $this->change,
            'change_percent' => $this->changePercent,
            'volume' => $this->volume,
            'avg_volume' => $this->avgVolume,
            'market_cap' => $this->marketCap,
            'pe_ratio' => $this->peRatio,
            'week_52_high' => $this->week52High,
            'week_52_low' => $this->week52Low,
            'quote_time' => $this->quoteTime,
        ];
    }
}

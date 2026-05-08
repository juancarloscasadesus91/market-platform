<?php

declare(strict_types=1);

namespace App\DTOs;

use Carbon\Carbon;
use Illuminate\Support\Collection;

readonly class OptionChainData
{
    public function __construct(
        public string $ticker,
        public Carbon $expirationDate,
        public Collection $calls,
        public Collection $puts,
        public ?float $underlyingPrice = null,
    ) {}

    public function getStrikes(): Collection
    {
        return $this->calls
            ->pluck('strike')
            ->merge($this->puts->pluck('strike'))
            ->unique()
            ->sort()
            ->values();
    }
}

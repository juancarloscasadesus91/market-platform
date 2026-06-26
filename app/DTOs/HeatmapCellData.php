<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Support\Enums\OptionType;

readonly class HeatmapCellData
{
    public function __construct(
        public string $ticker,
        public float $strike,
        public OptionType $optionType,
        public int $volume,
        public float $premiumFlow,
        public float $impliedVolatility,
        public ?float $delta = null,
        public ?float $gamma = null,
    ) {}

    public function getIntensity(): string
    {
        // Calculate heat intensity based on volume and premium flow
        $score = ($this->volume / 1000) + ($this->premiumFlow / 100000);

        if ($score > 100) {
            return $this->optionType->value === 'call' ? 'bg-emerald-600' : 'bg-rose-600';
        }

        if ($score > 50) {
            return $this->optionType->value === 'call' ? 'bg-emerald-500' : 'bg-rose-500';
        }

        if ($score > 20) {
            return $this->optionType->value === 'call' ? 'bg-emerald-400' : 'bg-rose-400';
        }

        if ($score > 5) {
            return $this->optionType->value === 'call' ? 'bg-emerald-500/50' : 'bg-rose-500/50';
        }

        return 'bg-slate-700/50';
    }

    public function getTextColor(): string
    {
        $score = ($this->volume / 1000) + ($this->premiumFlow / 100000);
        
        // Use white text for high intensity, slate for low
        return $score > 5 ? 'text-white' : 'text-slate-300';
    }
}

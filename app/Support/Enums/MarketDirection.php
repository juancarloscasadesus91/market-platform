<?php

declare(strict_types=1);

namespace App\Support\Enums;

enum MarketDirection: string
{
    case BULLISH = 'bullish';
    case BEARISH = 'bearish';
    case NEUTRAL = 'neutral';

    public function color(): string
    {
        return match($this) {
            self::BULLISH => 'text-emerald-400',
            self::BEARISH => 'text-rose-400',
            self::NEUTRAL => 'text-slate-400',
        };
    }

    public function bgColor(): string
    {
        return match($this) {
            self::BULLISH => 'bg-emerald-500/10',
            self::BEARISH => 'bg-rose-500/10',
            self::NEUTRAL => 'bg-slate-500/10',
        };
    }
}

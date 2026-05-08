<?php

declare(strict_types=1);

namespace App\Support\Enums;

enum OptionType: string
{
    case CALL = 'call';
    case PUT = 'put';

    public function label(): string
    {
        return match($this) {
            self::CALL => 'Call',
            self::PUT => 'Put',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::CALL => 'text-emerald-400',
            self::PUT => 'text-rose-400',
        };
    }
}

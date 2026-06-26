<?php

declare(strict_types=1);

namespace App\Support\Enums;

enum AlertType: string
{
    case UNUSUAL_PREMIUM = 'unusual_premium';
    case VOLUME_SPIKE = 'volume_spike';
    case DELTA_THRESHOLD = 'delta_threshold';
    case PRICE_MOVEMENT = 'price_movement';
    case IV_SPIKE = 'iv_spike';

    public function label(): string
    {
        return match($this) {
            self::UNUSUAL_PREMIUM => 'Unusual Premium',
            self::VOLUME_SPIKE => 'Volume Spike',
            self::DELTA_THRESHOLD => 'Delta Threshold',
            self::PRICE_MOVEMENT => 'Price Movement',
            self::IV_SPIKE => 'IV Spike',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::UNUSUAL_PREMIUM => '💰',
            self::VOLUME_SPIKE => '📊',
            self::DELTA_THRESHOLD => '📈',
            self::PRICE_MOVEMENT => '🎯',
            self::IV_SPIKE => '⚡',
        };
    }
}

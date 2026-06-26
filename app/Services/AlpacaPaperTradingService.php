<?php

declare(strict_types=1);

namespace App\Services;

class AlpacaPaperTradingService extends AlpacaTradingService
{
    public static function make(): AlpacaTradingService
    {
        return AlpacaTradingService::make('paper');
    }
}

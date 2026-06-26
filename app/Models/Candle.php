<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Candle extends Model
{
    protected $fillable = [
        'symbol', 'timeframe', 'opens_at',
        'open', 'high', 'low', 'close', 'volume',
    ];

    protected $casts = [
        'opens_at' => 'datetime',
        'open'     => 'float',
        'high'     => 'float',
        'low'      => 'float',
        'close'    => 'float',
        'volume'   => 'integer',
    ];

    public static function getForRange(string $symbol, string $timeframe, string $from, string $to): \Illuminate\Support\Collection
    {
        return static::where('symbol', $symbol)
            ->where('timeframe', $timeframe)
            ->whereBetween('opens_at', [$from, $to])
            ->orderBy('opens_at')
            ->get();
    }

    public static function hasData(string $symbol, string $timeframe, string $from, string $to): bool
    {
        return static::where('symbol', $symbol)
            ->where('timeframe', $timeframe)
            ->whereBetween('opens_at', [$from, $to])
            ->exists();
    }
}

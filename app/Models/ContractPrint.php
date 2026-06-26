<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ContractPrint extends Model
{
    protected $fillable = [
        'symbol',
        'print_time',
        'price',
        'size',
        'side',
        'premium',
        'cumulative_volume',
    ];

    protected $casts = [
        'print_time' => 'datetime',
        'price' => 'decimal:4',
        'size' => 'integer',
        'premium' => 'integer',
        'cumulative_volume' => 'integer',
    ];

    /**
     * Get prints for a symbol within a time range
     */
    public static function getHistory(string $symbol, ?Carbon $since = null, ?Carbon $until = null, int $limit = 100)
    {
        $query = static::where('symbol', $symbol)
            ->orderBy('print_time', 'desc');

        if ($since) {
            $query->where('print_time', '>=', $since);
        }

        if ($until) {
            $query->where('print_time', '<=', $until);
        }

        return $query->limit($limit)->get();
    }

    /**
     * Clean old prints (older than specified days)
     */
    public static function cleanOld(int $days = 7)
    {
        return static::where('print_time', '<', now()->subDays($days))->delete();
    }
}

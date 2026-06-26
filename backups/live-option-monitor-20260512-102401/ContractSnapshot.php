<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ContractSnapshot extends Model
{
    protected $fillable = [
        'symbol',
        'underlying',
        'expiration_date',
        'dte',
        'strike',
        'type',
        'total_volume',
        'volume_change',
        'total_premium',
        'buy_premium',
        'sell_premium',
        'net_premium',
        'last_price',
        'bid',
        'ask',
        'mark',
        'delta',
        'gamma',
        'theta',
        'vega',
        'iv',
        'total_trades',
        'snapshot_at',
    ];

    protected $casts = [
        'expiration_date' => 'date',
        'snapshot_at' => 'datetime',
        'total_volume' => 'integer',
        'volume_change' => 'integer',
        'total_premium' => 'integer',
        'buy_premium' => 'integer',
        'sell_premium' => 'integer',
        'net_premium' => 'integer',
        'total_trades' => 'integer',
        'dte' => 'integer',
        'last_price' => 'decimal:4',
        'bid' => 'decimal:4',
        'ask' => 'decimal:4',
        'mark' => 'decimal:4',
        'strike' => 'decimal:2',
        'delta' => 'decimal:6',
        'gamma' => 'decimal:6',
        'theta' => 'decimal:6',
        'vega' => 'decimal:6',
        'iv' => 'decimal:4',
    ];

    /**
     * Get the latest snapshot for a symbol
     */
    public static function getLatest(string $symbol, ?Carbon $since = null)
    {
        $query = static::where('symbol', $symbol)
            ->orderBy('snapshot_at', 'desc');

        if ($since) {
            $query->where('snapshot_at', '>=', $since);
        }

        return $query->first();
    }

    /**
     * Get snapshots for multiple symbols
     */
    public static function getLatestBatch(array $symbols, ?Carbon $since = null)
    {
        $query = static::whereIn('symbol', $symbols)
            ->orderBy('snapshot_at', 'desc');

        if ($since) {
            $query->where('snapshot_at', '>=', $since);
        }

        return $query->get()->groupBy('symbol')->map->first();
    }

    /**
     * Clean old snapshots (older than specified days)
     */
    public static function cleanOld(int $days = 7)
    {
        return static::where('snapshot_at', '<', now()->subDays($days))->delete();
    }

    /**
     * Get premium flow summary for a time range
     */
    public static function getPremiumFlow(string $underlying, int $dte, Carbon $from, Carbon $to)
    {
        return static::where('underlying', $underlying)
            ->where('dte', $dte)
            ->whereBetween('snapshot_at', [$from, $to])
            ->selectRaw('
                type,
                SUM(buy_premium) as total_buy,
                SUM(sell_premium) as total_sell,
                SUM(net_premium) as total_net,
                SUM(total_volume) as total_volume
            ')
            ->groupBy('type')
            ->get();
    }
}

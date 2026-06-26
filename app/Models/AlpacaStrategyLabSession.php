<?php

declare(strict_types=1);

namespace App\Models;

use App\Strategies\StrategyRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AlpacaStrategyLabSession extends Model
{
    protected $fillable = [
        'name', 'symbol', 'timeframe', 'strategy_key', 'params', 'status', 'mode',
        'position_size_type', 'position_size_value', 'max_concurrent_trades',
        'stop_loss_pct', 'take_profit_pct', 'started_at', 'stopped_at',
        'last_run_at', 'last_signal_at', 'error_message',
        'total_trades', 'winning_trades', 'losing_trades', 'total_pnl', 'total_pnl_pct',
    ];

    protected $casts = [
        'params' => 'array',
        'position_size_value' => 'float',
        'stop_loss_pct' => 'float',
        'take_profit_pct' => 'float',
        'max_concurrent_trades' => 'integer',
        'started_at' => 'datetime',
        'stopped_at' => 'datetime',
        'last_run_at' => 'datetime',
        'last_signal_at' => 'datetime',
        'total_trades' => 'integer',
        'winning_trades' => 'integer',
        'losing_trades' => 'integer',
        'total_pnl' => 'float',
        'total_pnl_pct' => 'float',
    ];

    public function trades(): HasMany
    {
        return $this->hasMany(AlpacaStrategyLabTrade::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AlpacaStrategyLabLog::class);
    }

    public function strategyLabel(): string
    {
        try {
            return StrategyRegistry::resolve($this->strategy_key)->label();
        } catch (\Throwable) {
            return $this->strategy_key;
        }
    }
}

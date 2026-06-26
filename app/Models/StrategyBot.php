<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StrategyBot extends Model
{
    protected $fillable = [
        'name', 'strategy_key', 'symbol', 'timeframe',
        'trade_type',
        'paper_mode', 'paper_budget', 'paper_balance',
        'position_size_type', 'position_size_value', 'risk_per_trade_pct',
        'max_concurrent_trades', 'max_daily_loss_pct',
        'status', 'started_at', 'stopped_at', 'stop_reason',
        'schwab_account_hash',
        'strategy_params',
        'total_trades', 'winning_trades', 'losing_trades',
        'total_pnl', 'total_pnl_pct', 'max_drawdown',
        'option_delta_target', 'option_delta_tolerance',
        'option_max_dte', 'option_min_dte', 'option_contracts',
        'option_stop_loss_pct', 'option_take_profit_pct',
        'option_order_type', 'option_limit_offset',
    ];

    protected $casts = [
        'paper_mode'              => 'boolean',
        'paper_budget'            => 'float',
        'paper_balance'           => 'float',
        'position_size_value'     => 'float',
        'risk_per_trade_pct'      => 'float',
        'max_daily_loss_pct'      => 'float',
        'max_concurrent_trades'   => 'integer',
        'strategy_params'         => 'array',
        'option_delta_target'     => 'float',
        'option_delta_tolerance'  => 'float',
        'option_max_dte'          => 'integer',
        'option_min_dte'          => 'integer',
        'option_contracts'        => 'integer',
        'option_stop_loss_pct'    => 'float',
        'option_take_profit_pct'  => 'float',
        'option_limit_offset'     => 'float',
        'total_trades'            => 'integer',
        'winning_trades'          => 'integer',
        'losing_trades'           => 'integer',
        'total_pnl'               => 'float',
        'total_pnl_pct'           => 'float',
        'max_drawdown'            => 'float',
        'started_at'              => 'datetime',
        'stopped_at'              => 'datetime',
    ];

    public function trades(): HasMany
    {
        return $this->hasMany(StrategyBotTrade::class);
    }

    public function openTrades(): HasMany
    {
        return $this->hasMany(StrategyBotTrade::class)->where('status', 'open');
    }

    public function getWinRateAttribute(): float
    {
        if ($this->total_trades === 0) return 0.0;
        return round(($this->winning_trades / $this->total_trades) * 100, 1);
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function isPaperMode(): bool
    {
        return (bool) $this->paper_mode;
    }

    public function recalcStats(): void
    {
        $closed = $this->trades()->where('status', 'closed')->get();

        $this->total_trades   = $closed->count();
        $this->winning_trades = $closed->where('pnl', '>', 0)->count();
        $this->losing_trades  = $closed->where('pnl', '<=', 0)->count();
        $this->total_pnl      = (float) $closed->sum('pnl');

        $budget = $this->paper_mode ? $this->paper_budget : 1;
        $this->total_pnl_pct  = $budget > 0 ? round(($this->total_pnl / $budget) * 100, 4) : 0;

        // Running max-drawdown
        $running = 0.0;
        $peak    = 0.0;
        $maxDD   = 0.0;
        foreach ($closed->sortBy('exit_time') as $t) {
            $running += (float) $t->pnl;
            if ($running > $peak) $peak = $running;
            $dd = $peak - $running;
            if ($dd > $maxDD) $maxDD = $dd;
        }
        $this->max_drawdown = $maxDD;

        $this->save();
    }
}

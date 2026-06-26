<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StrategyLabSession extends Model
{
    protected $fillable = [
        'name', 'strategy_key', 'symbols', 'timeframe', 'date_from', 'date_to', 'params',
        'status', 'error_message', 'progress', 'progress_label',
        'total_candles', 'total_signals', 'total_trades',
        'winning_trades', 'losing_trades', 'breakeven_trades',
        'win_rate', 'profit_factor', 'total_pnl_points', 'total_pnl_pct',
        'max_drawdown', 'avg_winner_pts', 'avg_loser_pts',
        'best_hour', 'worst_hour',
    ];

    protected $casts = [
        'symbols'          => 'array',
        'params'           => 'array',
        'date_from'        => 'date',
        'date_to'          => 'date',
        'total_candles'    => 'integer',
        'total_signals'    => 'integer',
        'total_trades'     => 'integer',
        'winning_trades'   => 'integer',
        'losing_trades'    => 'integer',
        'breakeven_trades' => 'integer',
        'win_rate'         => 'float',
        'profit_factor'    => 'float',
        'total_pnl_points' => 'float',
        'total_pnl_pct'    => 'float',
        'max_drawdown'     => 'float',
        'avg_winner_pts'   => 'float',
        'avg_loser_pts'    => 'float',
        'progress'         => 'integer',
    ];

    public function trades(): HasMany
    {
        return $this->hasMany(StrategyLabTrade::class);
    }

    public function isPending(): bool   { return $this->status === 'pending'; }
    public function isRunning(): bool   { return in_array($this->status, ['importing', 'running']); }
    public function isCompleted(): bool { return $this->status === 'completed'; }
    public function isFailed(): bool    { return $this->status === 'failed'; }

    public function markRunning(string $label = ''): void
    {
        $this->update(['status' => 'running', 'progress' => 0, 'progress_label' => $label]);
    }

    public function updateProgress(int $pct, string $label = ''): void
    {
        $this->update(['progress' => $pct, 'progress_label' => $label]);
    }

    public function markCompleted(array $stats): void
    {
        $this->update(array_merge($stats, ['status' => 'completed', 'progress' => 100]));
    }

    public function markFailed(string $error): void
    {
        $this->update(['status' => 'failed', 'error_message' => $error]);
    }

    public function symbolsLabel(): string
    {
        return implode(', ', $this->symbols ?? []);
    }

    public function strategyLabel(): string
    {
        try {
            return \App\Strategies\StrategyRegistry::resolve($this->strategy_key)->label();
        } catch (\Throwable) {
            return $this->strategy_key;
        }
    }
}

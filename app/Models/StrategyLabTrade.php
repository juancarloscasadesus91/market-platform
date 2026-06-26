<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StrategyLabTrade extends Model
{
    protected $fillable = [
        'strategy_lab_session_id', 'symbol', 'direction',
        'entry_time', 'entry_price',
        'exit_time', 'exit_price', 'exit_reason', 'result',
        'pnl_points', 'pnl_pct', 'max_favorable_excursion', 'max_adverse_excursion', 'r_multiple',
        'stop_loss', 'take_profit_1', 'take_profit_2', 'take_profit_3',
        'signal_data',
    ];

    protected $casts = [
        'entry_time'              => 'datetime',
        'exit_time'               => 'datetime',
        'entry_price'             => 'float',
        'exit_price'              => 'float',
        'pnl_points'              => 'float',
        'pnl_pct'                 => 'float',
        'max_favorable_excursion' => 'float',
        'max_adverse_excursion'   => 'float',
        'r_multiple'              => 'float',
        'stop_loss'               => 'float',
        'take_profit_1'           => 'float',
        'take_profit_2'           => 'float',
        'take_profit_3'           => 'float',
        'signal_data'             => 'array',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(StrategyLabSession::class, 'strategy_lab_session_id');
    }

    public function isWin(): bool       { return $this->result === 'win'; }
    public function isLoss(): bool      { return $this->result === 'loss'; }
    public function isBreakeven(): bool { return $this->result === 'breakeven'; }
}

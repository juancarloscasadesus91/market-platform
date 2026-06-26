<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BacktestTrade extends Model
{
    protected $fillable = [
        'backtest_session_id', 'symbol', 'direction',
        'pullback_time', 'pullback_open', 'pullback_high', 'pullback_low', 'pullback_close',
        'confirm_time', 'confirm_open', 'confirm_high', 'confirm_low', 'confirm_close',
        'entry_time', 'entry_price',
        'ema21', 'ema50', 'ema100', 'min_distance',
        'dist_ema21_ema50', 'dist_ema50_ema100',
        'rsi', 'atr', 'bb_upper', 'bb_middle', 'bb_lower',
        'volume', 'rel_volume',
        'stop_loss', 'take_profit_1', 'take_profit_2', 'take_profit_3',
        'exit_price', 'exit_time', 'exit_reason', 'result',
        'pnl_points', 'pnl_pct', 'max_favorable_excursion', 'max_adverse_excursion', 'r_multiple',
    ];

    protected $casts = [
        'pullback_time'           => 'datetime',
        'confirm_time'            => 'datetime',
        'entry_time'              => 'datetime',
        'exit_time'               => 'datetime',
        'pullback_open'           => 'float',
        'pullback_high'           => 'float',
        'pullback_low'            => 'float',
        'pullback_close'          => 'float',
        'confirm_open'            => 'float',
        'confirm_high'            => 'float',
        'confirm_low'             => 'float',
        'confirm_close'           => 'float',
        'entry_price'             => 'float',
        'ema21'                   => 'float',
        'ema50'                   => 'float',
        'ema100'                  => 'float',
        'min_distance'            => 'float',
        'dist_ema21_ema50'        => 'float',
        'dist_ema50_ema100'       => 'float',
        'rsi'                     => 'float',
        'atr'                     => 'float',
        'bb_upper'                => 'float',
        'bb_middle'               => 'float',
        'bb_lower'                => 'float',
        'volume'                  => 'integer',
        'rel_volume'              => 'float',
        'stop_loss'               => 'float',
        'take_profit_1'           => 'float',
        'take_profit_2'           => 'float',
        'take_profit_3'           => 'float',
        'exit_price'              => 'float',
        'pnl_points'              => 'float',
        'pnl_pct'                 => 'float',
        'max_favorable_excursion' => 'float',
        'max_adverse_excursion'   => 'float',
        'r_multiple'              => 'float',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(BacktestSession::class, 'backtest_session_id');
    }

    public function isWin(): bool       { return $this->result === 'win'; }
    public function isLoss(): bool      { return $this->result === 'loss'; }
    public function isBreakeven(): bool { return $this->result === 'breakeven'; }
}

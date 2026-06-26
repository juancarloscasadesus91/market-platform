<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StrategySetting extends Model
{
    protected $fillable = [
        'name',
        'ema_fast', 'ema_mid', 'ema_slow',
        'min_distance_pct', 'max_bars_after_pullback',
        'rsi_period', 'bb_period', 'bb_stddev', 'atr_period', 'volume_avg_period',
        'rsi_max_call', 'rsi_min_put',
        'max_candle_atr_ratio', 'max_price_ema_dist_pct', 'min_bb_dist_pct',
        'min_ema21_ema50_dist', 'max_ema21_ema50_dist',
        'min_ema50_ema100_dist', 'max_ema50_ema100_dist',
        'stop_type', 'stop_atr_mult', 'stop_buffer_pct', 'stop_pct',
        'tp_type', 'tp1_value', 'tp2_value', 'tp3_value', 'quadrant_step_pct',
        'max_trade_duration_minutes',
        'force_exit_time',
        'min_entry_time',
        'max_entry_time',
        'entry_candle_distance_pct',
        'volume_rel_min',
        'volume_rel_max',
    ];

    protected $casts = [
        'ema_fast'                => 'integer',
        'ema_mid'                 => 'integer',
        'ema_slow'                => 'integer',
        'min_distance_pct'        => 'float',
        'max_bars_after_pullback' => 'integer',
        'rsi_period'              => 'integer',
        'bb_period'               => 'integer',
        'bb_stddev'               => 'float',
        'atr_period'              => 'integer',
        'volume_avg_period'       => 'integer',
        'rsi_max_call'            => 'float',
        'rsi_min_put'             => 'float',
        'max_candle_atr_ratio'    => 'float',
        'max_price_ema_dist_pct'  => 'float',
        'min_bb_dist_pct'         => 'float',
        'min_ema21_ema50_dist'    => 'float',
        'max_ema21_ema50_dist'    => 'float',
        'min_ema50_ema100_dist'   => 'float',
        'max_ema50_ema100_dist'   => 'float',
        'stop_atr_mult'           => 'float',
        'stop_buffer_pct'         => 'float',
        'stop_pct'                => 'float',
        'tp1_value'               => 'float',
        'tp2_value'               => 'float',
        'tp3_value'               => 'float',
        'quadrant_step_pct'       => 'float',
        'max_trade_duration_minutes' => 'integer',
        'force_exit_time'         => 'string',
        'min_entry_time'          => 'string',
        'max_entry_time'          => 'string',
        'entry_candle_distance_pct' => 'float',
        'volume_rel_min'          => 'float',
        'volume_rel_max'          => 'float',
    ];

    public function sessions(): HasMany
    {
        return $this->hasMany(BacktestSession::class);
    }

    public static function defaultSettings(): array
    {
        return [
            'name'                    => 'EMA Pullback Default',
            'ema_fast'                => 21,
            'ema_mid'                 => 50,
            'ema_slow'                => 100,
            'min_distance_pct'        => 0.02,
            'max_bars_after_pullback' => 3,
            'rsi_period'              => 14,
            'bb_period'               => 20,
            'bb_stddev'               => 2.0,
            'atr_period'              => 14,
            'volume_avg_period'       => 20,
            'rsi_max_call'            => null,
            'rsi_min_put'             => null,
            'max_candle_atr_ratio'    => null,
            'max_price_ema_dist_pct'  => null,
            'min_bb_dist_pct'         => null,
            'min_ema21_ema50_dist'    => null,
            'max_ema21_ema50_dist'    => null,
            'min_ema50_ema100_dist'   => null,
            'max_ema50_ema100_dist'   => null,
            'stop_type'               => 'pullback',
            'stop_atr_mult'           => 1.5,
            'stop_buffer_pct'         => 0.05,
            'tp_type'                 => 'risk_ratio',
            'tp1_value'               => 1.0,
            'tp2_value'               => 2.0,
            'tp3_value'               => 3.0,
            'quadrant_step_pct'       => 25.0,
            'max_trade_duration_minutes' => 30,
            'force_exit_time'         => '15:45',
            'min_entry_time'          => '09:30',
            'max_entry_time'          => '16:00',
        ];
    }
}

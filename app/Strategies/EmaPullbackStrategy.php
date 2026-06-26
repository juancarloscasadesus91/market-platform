<?php

declare(strict_types=1);

namespace App\Strategies;

use App\Contracts\StrategyInterface;
use App\Services\StrictEmaPullbackStrategyService;

class EmaPullbackStrategy implements StrategyInterface
{
    public function __construct(private StrictEmaPullbackStrategyService $service) {}

    public function detect(array $candles, array $cfg): array
    {
        return $this->service->detect($candles, $cfg);
    }

    public function label(): string
    {
        return 'EMA Pullback';
    }

    public function requiredIndicators(): array
    {
        return ['ema', 'rsi', 'atr', 'bb', 'volume'];
    }

    public function schema(): array
    {
        return [
            // ── Trend / Entry ────────────────────────────────────────────
            ['key' => 'ema_fast',               'label' => 'EMA Fast',                    'type' => 'int',   'default' => 21,    'min' => 2,    'max' => 200,  'step' => 1,    'group' => 'Trend'],
            ['key' => 'ema_mid',                'label' => 'EMA Mid',                     'type' => 'int',   'default' => 50,    'min' => 2,    'max' => 500,  'step' => 1,    'group' => 'Trend'],
            ['key' => 'ema_slow',               'label' => 'EMA Slow',                    'type' => 'int',   'default' => 100,   'min' => 2,    'max' => 500,  'step' => 1,    'group' => 'Trend'],
            ['key' => 'min_distance_pct',       'label' => 'Min Distance %',              'type' => 'float', 'default' => 0.02,  'min' => 0.001,'max' => 5.0,  'step' => 0.001,'group' => 'Trend'],
            ['key' => 'max_bars_after_pullback','label' => 'Max Bars After Pullback',     'type' => 'int',   'default' => 3,     'min' => 1,    'max' => 20,   'step' => 1,    'group' => 'Trend'],
            // ── Indicators ───────────────────────────────────────────────
            ['key' => 'rsi_period',             'label' => 'RSI Period',                  'type' => 'int',   'default' => 14,    'min' => 2,    'max' => 100,  'step' => 1,    'group' => 'Indicators'],
            ['key' => 'bb_period',              'label' => 'BB Period',                   'type' => 'int',   'default' => 20,    'min' => 2,    'max' => 100,  'step' => 1,    'group' => 'Indicators'],
            ['key' => 'bb_stddev',              'label' => 'BB Std Dev',                  'type' => 'float', 'default' => 2.0,   'min' => 0.5,  'max' => 5.0,  'step' => 0.1,  'group' => 'Indicators'],
            ['key' => 'atr_period',             'label' => 'ATR Period',                  'type' => 'int',   'default' => 14,    'min' => 1,    'max' => 100,  'step' => 1,    'group' => 'Indicators'],
            ['key' => 'volume_avg_period',      'label' => 'Volume MA Period',            'type' => 'int',   'default' => 20,    'min' => 1,    'max' => 100,  'step' => 1,    'group' => 'Indicators'],
            // ── Filters ──────────────────────────────────────────────────
            ['key' => 'rsi_max_call',           'label' => 'RSI Max (CALL)',              'type' => 'float', 'default' => null,  'min' => 0,    'max' => 100,  'step' => 1,    'group' => 'Filters'],
            ['key' => 'rsi_min_put',            'label' => 'RSI Min (PUT)',               'type' => 'float', 'default' => null,  'min' => 0,    'max' => 100,  'step' => 1,    'group' => 'Filters'],
            ['key' => 'max_candle_atr_ratio',   'label' => 'Max Candle / ATR Ratio',     'type' => 'float', 'default' => null,  'min' => 0.1,  'max' => 10,   'step' => 0.1,  'group' => 'Filters'],
            ['key' => 'max_price_ema_dist_pct', 'label' => 'Max Price-EMA Dist %',       'type' => 'float', 'default' => null,  'min' => 0,    'max' => 20,   'step' => 0.1,  'group' => 'Filters'],
            ['key' => 'min_bb_dist_pct',        'label' => 'Min BB Dist %',              'type' => 'float', 'default' => null,  'min' => 0,    'max' => 100,  'step' => 1,    'group' => 'Filters'],
            ['key' => 'min_ema21_ema50_dist',   'label' => 'Min EMA21-EMA50 Distance',  'type' => 'float', 'default' => null,  'min' => 0,    'max' => 50,   'step' => 0.1,  'group' => 'Filters'],
            ['key' => 'max_ema21_ema50_dist',   'label' => 'Max EMA21-EMA50 Distance',  'type' => 'float', 'default' => null,  'min' => 0,    'max' => 50,   'step' => 0.1,  'group' => 'Filters'],
            ['key' => 'min_ema50_ema100_dist',  'label' => 'Min EMA50-EMA100 Distance', 'type' => 'float', 'default' => null,  'min' => 0,    'max' => 50,   'step' => 0.1,  'group' => 'Filters'],
            ['key' => 'max_ema50_ema100_dist',  'label' => 'Max EMA50-EMA100 Distance', 'type' => 'float', 'default' => null,  'min' => 0,    'max' => 50,   'step' => 0.1,  'group' => 'Filters'],
            ['key' => 'entry_candle_distance_pct','label'=> 'Entry Candle Range % Min', 'type' => 'float', 'default' => null,  'min' => 0,    'max' => 5,    'step' => 0.01, 'group' => 'Filters'],
            ['key' => 'volume_rel_min',         'label' => 'Relative Volume Min',        'type' => 'float', 'default' => null,  'min' => 0,    'max' => 10,   'step' => 0.1,  'group' => 'Filters'],
            ['key' => 'volume_rel_max',         'label' => 'Relative Volume Max',        'type' => 'float', 'default' => null,  'min' => 0,    'max' => 10,   'step' => 0.1,  'group' => 'Filters'],
            // ── Stop Loss ────────────────────────────────────────────────
            ['key' => 'stop_type',              'label' => 'Stop Type',                  'type' => 'select','default' => 'pullback', 'options' => ['pullback' => 'Pullback Low/High', 'atr' => 'ATR Multiple', 'ema_mid' => 'EMA Mid', 'percent' => 'Percent', 'ema_quadrant_trailing' => 'EMA Quadrant Trailing'], 'group' => 'Stop Loss'],
            ['key' => 'stop_atr_mult',          'label' => 'ATR Multiplier',             'type' => 'float', 'default' => 1.5,   'min' => 0.1,  'max' => 10,   'step' => 0.1,  'group' => 'Stop Loss'],
            ['key' => 'stop_buffer_pct',        'label' => 'Buffer %',                   'type' => 'float', 'default' => 0.05,  'min' => 0,    'max' => 2,    'step' => 0.01, 'group' => 'Stop Loss'],
            ['key' => 'stop_pct',               'label' => 'Stop %',                     'type' => 'float', 'default' => null,  'min' => 0.1,  'max' => 20,   'step' => 0.1,  'group' => 'Stop Loss'],
            // ── Take Profit ───────────────────────────────────────────────
            ['key' => 'tp_type',                'label' => 'TP Type',                    'type' => 'select','default' => 'risk_ratio', 'options' => ['risk_ratio' => 'Risk Ratio', 'atr' => 'ATR Multiple', 'fixed' => 'Fixed Points'], 'group' => 'Take Profit'],
            ['key' => 'tp1_value',              'label' => 'TP1 Value',                  'type' => 'float', 'default' => 1.0,   'min' => 0.1,  'max' => 20,   'step' => 0.1,  'group' => 'Take Profit'],
            ['key' => 'tp2_value',              'label' => 'TP2 Value',                  'type' => 'float', 'default' => 2.0,   'min' => 0.1,  'max' => 20,   'step' => 0.1,  'group' => 'Take Profit'],
            ['key' => 'tp3_value',              'label' => 'TP3 Value',                  'type' => 'float', 'default' => 3.0,   'min' => 0.1,  'max' => 20,   'step' => 0.1,  'group' => 'Take Profit'],
            ['key' => 'quadrant_step_pct',      'label' => 'Quadrant Step %',            'type' => 'float', 'default' => 25.0,  'min' => 5,    'max' => 50,   'step' => 1,    'group' => 'Take Profit'],
            // ── Timing ───────────────────────────────────────────────────
            ['key' => 'min_entry_time',         'label' => 'Min Entry Time (ET)',        'type' => 'time',  'default' => '09:30','group' => 'Timing'],
            ['key' => 'max_entry_time',         'label' => 'Max Entry Time (ET)',        'type' => 'time',  'default' => '16:00','group' => 'Timing'],
            ['key' => 'force_exit_time',        'label' => 'Force Exit Time (ET)',       'type' => 'time',  'default' => '15:45','group' => 'Timing'],
            ['key' => 'max_trade_duration_minutes','label'=> 'Max Duration (min)',       'type' => 'int',   'default' => 30,    'min' => 1,    'max' => 1440, 'step' => 1,    'group' => 'Timing'],
        ];
    }
}

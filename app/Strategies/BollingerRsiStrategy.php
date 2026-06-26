<?php

declare(strict_types=1);

namespace App\Strategies;

use App\Contracts\StrategyInterface;
use Carbon\Carbon;

/**
 * Bollinger Bands + RSI Filter Strategy
 *
 * ThinkScript translation:
 *   PUT  signal: close > UpperBand AND close < close[1] AND RSI > overBought
 *   CALL signal: close < LowerBand AND close > close[1] AND RSI < overSold
 *
 * Entry is the open of the NEXT bar after the signal fires.
 * RSI uses Wilder's EMA (same formula as TOS ExpAverage on net/abs change).
 */
class BollingerRsiStrategy implements StrategyInterface
{
    public function label(): string
    {
        return 'Bollinger Bands + RSI';
    }

    public function requiredIndicators(): array
    {
        return ['atr', 'volume'];
    }

    public function detect(array $candles, array $cfg): array
    {
        $bbLength      = (int)   ($cfg['bb_length']       ?? 30);
        $bbDev         = (float) ($cfg['bb_dev']           ?? 2.0);
        $rsiLength     = (int)   ($cfg['rsi_length']       ?? 13);
        $overBought    = (float) ($cfg['over_bought']      ?? 70.0);
        $overSold      = (float) ($cfg['over_sold']        ?? 30.0);
        $minTime       = $cfg['min_entry_time']   ?? '09:30';
        $maxTime       = $cfg['max_entry_time']   ?? '16:00';
        $atrPeriod     = (int)   ($cfg['atr_period']       ?? 14);
        $emaFilter     = ($cfg['ema_alignment_filter'] ?? '0') === '1';

        $n = count($candles);
        if ($n < max($bbLength, $rsiLength, 100) + 2) return [];

        // ── Pre-compute Bollinger Bands (SMA + population StdDev) ──────────
        $upper  = array_fill(0, $n, null);
        $lower  = array_fill(0, $n, null);
        $middle = array_fill(0, $n, null);

        for ($i = $bbLength - 1; $i < $n; $i++) {
            $slice = array_slice($candles, $i - $bbLength + 1, $bbLength);
            $closes = array_column($slice, 'close');
            $sma = array_sum($closes) / $bbLength;

            $variance = 0.0;
            foreach ($closes as $c) {
                $variance += ($c - $sma) ** 2;
            }
            $stdDev = sqrt($variance / $bbLength);

            $middle[$i] = $sma;
            $upper[$i]  = $sma + $bbDev * $stdDev;
            $lower[$i]  = $sma - $bbDev * $stdDev;
        }

        // ── Pre-compute Wilder RSI (same as TOS ExpAverage) ───────────────
        // k = 2 / (rsiLength + 1)  →  Wilder uses 1/rsiLength; TOS ExpAverage uses 2/(n+1)
        $rsi   = array_fill(0, $n, null);
        $netAvg = null;
        $totAvg = null;
        $k = 2.0 / ($rsiLength + 1);

        for ($i = 1; $i < $n; $i++) {
            $change = (float)$candles[$i]['close'] - (float)$candles[$i - 1]['close'];
            $absChange = abs($change);

            if ($netAvg === null) {
                $netAvg = $change;
                $totAvg = $absChange;
            } else {
                $netAvg = $k * $change    + (1 - $k) * $netAvg;
                $totAvg = $k * $absChange + (1 - $k) * $totAvg;
            }

            $ratio    = $totAvg != 0.0 ? $netAvg / $totAvg : 0.0;
            $rsi[$i]  = 50.0 * ($ratio + 1.0);
        }

        // ── Pre-compute ATR (Wilder's) — reuse from enriched candles ──────
        // IndicatorService already puts atr in each candle via compute(); we use it.

        // ── Pre-compute EMA21, EMA50, EMA100 ──────────────────────────────
        $ema21arr = array_fill(0, $n, null);
        $ema50arr = array_fill(0, $n, null);
        $ema100arr = array_fill(0, $n, null);
        $k21 = 2.0 / (21 + 1);  $k50 = 2.0 / (50 + 1);  $k100 = 2.0 / (100 + 1);
        $e21 = null; $e50 = null; $e100 = null;
        for ($i = 0; $i < $n; $i++) {
            $c = (float) $candles[$i]['close'];
            $e21  = $e21  === null ? $c : $k21  * $c + (1 - $k21)  * $e21;
            $e50  = $e50  === null ? $c : $k50  * $c + (1 - $k50)  * $e50;
            $e100 = $e100 === null ? $c : $k100 * $c + (1 - $k100) * $e100;
            $ema21arr[$i]  = $e21;
            $ema50arr[$i]  = $e50;
            $ema100arr[$i] = $e100;
        }

        // ── Signal detection ──────────────────────────────────────────────
        $signals = [];

        for ($i = $bbLength; $i < $n - 1; $i++) {
            if ($upper[$i] === null || $rsi[$i] === null) continue;

            $close     = (float) $candles[$i]['close'];
            $prevClose = (float) $candles[$i - 1]['close'];
            $high      = (float) $candles[$i]['high'];
            $low       = (float) $candles[$i]['low'];

            $rsiVal = $rsi[$i];
            $up     = $upper[$i];
            $dn     = $lower[$i];
            $mid    = $middle[$i];

            $atrVal  = (float) ($candles[$i]['atr'] ?? ($up - $dn) / 4);
            $ema21v  = $ema21arr[$i];
            $ema50v  = $ema50arr[$i];
            $ema100v = $ema100arr[$i];

            // Validate entry window
            $entryBar    = $candles[$i + 1];
            $entryTimeEt = Carbon::parse($entryBar['dt'], 'UTC')
                ->setTimezone('America/New_York')->format('H:i');

            if ($entryTimeEt < $minTime || $entryTimeEt >= $maxTime) continue;

            $entryPrice = (float) $entryBar['open'];

            // ── PUT signal: price breaks above upper band and reverting ────
            if ($close > $up && $close < $prevClose && $rsiVal > $overBought) {
                if ($emaFilter && !($ema21v < $ema50v && $ema50v < $ema100v)) continue;
                $signals[] = $this->buildSignal(
                    'PUT', $i, $entryBar, $entryPrice,
                    $close, $high, $low, $prevClose,
                    $up, $dn, $mid, $rsiVal, $atrVal, $ema21v, $ema50v, $ema100v
                );
            }

            // ── CALL signal: price breaks below lower band and reverting ───
            if ($close < $dn && $close > $prevClose && $rsiVal < $overSold) {
                if ($emaFilter && !($ema21v > $ema50v && $ema50v > $ema100v)) continue;
                $signals[] = $this->buildSignal(
                    'CALL', $i, $entryBar, $entryPrice,
                    $close, $high, $low, $prevClose,
                    $up, $dn, $mid, $rsiVal, $atrVal, $ema21v, $ema50v, $ema100v
                );
            }
        }

        return $signals;
    }

    private function buildSignal(
        string $direction,
        int    $signalIdx,
        array  $entryBar,
        float  $entryPrice,
        float  $close,
        float  $high,
        float  $low,
        float  $prevClose,
        float  $bbUpper,
        float  $bbLower,
        float  $bbMiddle,
        float  $rsi,
        float  $atr,
        ?float $ema21,
        ?float $ema50,
        ?float $ema100,
    ): array {
        return [
            'direction'   => $direction,
            'entry_idx'   => $signalIdx + 1,
            'entry_time'  => $entryBar['dt'],
            'entry_price' => $entryPrice,
            // Required by TradeSimulatorService
            'atr'         => $atr,
            'ema50'       => $ema50,
            // Extra signal context stored in signal_data
            'signal_time'  => $entryBar['dt'],
            'signal_close' => $close,
            'signal_high'  => $high,
            'signal_low'   => $low,
            'prev_close'   => $prevClose,
            'bb_upper'     => $bbUpper,
            'bb_lower'     => $bbLower,
            'bb_middle'    => $bbMiddle,
            'rsi'          => $rsi,
            'ema21'        => $ema21,
            'ema100'       => $ema100,
            // pullback_* fields needed by TradeSimulatorService 'pullback' stop type
            'pullback_low'  => $low,
            'pullback_high' => $high,
            'pullback_time'  => $entryBar['dt'],
            'pullback_open'  => $close,
            'pullback_close' => $close,
        ];
    }

    public function schema(): array
    {
        return [
            // ── Bollinger Bands ───────────────────────────────────────────
            ['key' => 'bb_length',    'label' => 'BB Length',          'type' => 'int',   'default' => 30,   'min' => 2,   'max' => 200, 'step' => 1,    'group' => 'Bollinger Bands'],
            ['key' => 'bb_dev',       'label' => 'BB Std Deviations',  'type' => 'float', 'default' => 2.0,  'min' => 0.5, 'max' => 5.0, 'step' => 0.1,  'group' => 'Bollinger Bands'],
            // ── RSI ───────────────────────────────────────────────────────
            ['key' => 'rsi_length',   'label' => 'RSI Length',         'type' => 'int',   'default' => 13,   'min' => 2,   'max' => 100, 'step' => 1,    'group' => 'RSI'],
            ['key' => 'over_bought',  'label' => 'Overbought Level',   'type' => 'float', 'default' => 70.0, 'min' => 50,  'max' => 100, 'step' => 1,    'group' => 'RSI'],
            ['key' => 'over_sold',    'label' => 'Oversold Level',     'type' => 'float', 'default' => 30.0, 'min' => 0,   'max' => 50,  'step' => 1,    'group' => 'RSI'],
            // ── ATR ───────────────────────────────────────────────────────
            ['key' => 'atr_period',   'label' => 'ATR Period',         'type' => 'int',   'default' => 14,   'min' => 1,   'max' => 100, 'step' => 1,    'group' => 'ATR'],
            // ── Stop Loss ────────────────────────────────────────────────
            ['key' => 'stop_type',    'label' => 'Stop Type',          'type' => 'select', 'default' => 'percent',
             'options' => [
                 'percent'              => '% del entry price',
                 'atr'                  => 'ATR Multiple',
                 'pullback'             => 'High/Low de la barra señal',
                 'ema_quadrant_trailing'=> 'Cuadrantes EMA50 (trailing)',
             ], 'group' => 'Stop Loss'],
            ['key' => 'stop_pct',     'label' => 'Stop %',             'type' => 'float', 'default' => 1.0,  'min' => 0.1, 'max' => 20,  'step' => 0.1,  'group' => 'Stop Loss', 'show_when' => ['stop_type' => ['percent']]],
            ['key' => 'stop_atr_mult','label' => 'ATR Multiplier',     'type' => 'float', 'default' => 1.5,  'min' => 0.1, 'max' => 10,  'step' => 0.1,  'group' => 'Stop Loss', 'show_when' => ['stop_type' => ['atr']]],
            ['key' => 'stop_buffer_pct','label'=> 'Buffer % (pullback)','type'=> 'float', 'default' => 0.05, 'min' => 0,   'max' => 2,   'step' => 0.01, 'group' => 'Stop Loss', 'show_when' => ['stop_type' => ['pullback']]],
            // ── Take Profit ───────────────────────────────────────────────
            ['key' => 'tp_type',      'label' => 'TP Type',            'type' => 'select', 'default' => 'bb_middle',
             'options' => [
                 'bb_middle'           => 'BB Media móvil (SMA)',
                 'bb_band'             => 'BB Banda opuesta (Upper/Lower)',
                 'risk_ratio'          => 'Risk Ratio (R:R)',
                 'percent'             => '% del entry price',
                 'points'              => 'Puntos fijos',
                 'ema_quadrant_trail'  => 'Cuadrantes EMA50 (trailing)',
             ], 'group' => 'Take Profit'],
            ['key' => 'tp1_value',    'label' => 'TP1 Value',          'type' => 'float', 'default' => 1.0,  'min' => 0.1, 'max' => 50,  'step' => 0.1,  'group' => 'Take Profit', 'show_when' => ['tp_type' => ['risk_ratio','percent','points']]],
            ['key' => 'tp2_value',    'label' => 'TP2 Value',          'type' => 'float', 'default' => 2.0,  'min' => 0.1, 'max' => 50,  'step' => 0.1,  'group' => 'Take Profit', 'show_when' => ['tp_type' => ['percent','points']]],
            ['key' => 'tp3_value',    'label' => 'TP3 Value',          'type' => 'float', 'default' => 3.0,  'min' => 0.1, 'max' => 50,  'step' => 0.1,  'group' => 'Take Profit', 'show_when' => ['tp_type' => ['percent','points']]],
            ['key' => 'quadrant_step_pct','label'=> 'Quadrant Step %', 'type' => 'float', 'default' => 25.0, 'min' => 5,   'max' => 50,  'step' => 5,    'group' => 'Take Profit', 'show_when' => ['tp_type' => ['ema_quadrant_trail']]],
            // ── EMA Filter ───────────────────────────────────────────────
            ['key' => 'ema_alignment_filter', 'label' => 'EMA Alignment Filter',
             'type' => 'select', 'default' => '0',
             'options' => [
                 '0' => 'Desactivado',
                 '1' => 'Activado (EMA21/50/100 alineadas)',
             ], 'group' => 'EMA Filter'],
            // ── Timing ───────────────────────────────────────────────────
            ['key' => 'min_entry_time',  'label' => 'Min Entry Time (ET)', 'type' => 'time', 'default' => '09:30', 'group' => 'Timing'],
            ['key' => 'max_entry_time',  'label' => 'Max Entry Time (ET)', 'type' => 'time', 'default' => '16:00', 'group' => 'Timing'],
            ['key' => 'force_exit_time', 'label' => 'Force Exit Time (ET)','type' => 'time', 'default' => '15:45', 'group' => 'Timing'],
            ['key' => 'max_trade_duration_minutes','label'=> 'Max Duration (min)', 'type' => 'int', 'default' => 60, 'min' => 1, 'max' => 1440, 'step' => 1, 'group' => 'Timing'],
        ];
    }
}

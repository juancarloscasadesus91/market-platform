<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Computes technical indicators on a raw candle array.
 *
 * Each candle in $candles is: ['time','dt','open','high','low','close','volume']
 * Returns the same array enriched with indicator fields (null before warm-up).
 *
 * EMA: recursive k=2/(n+1), seeded from bar 0 (same as ThinkScript ExpAverage).
 * RSI: Wilder's smoothed RSI (same as ThinkorSwim default).
 * ATR: Wilder's ATR.
 * BB:  SMA ± stddev (population std dev, same as ThinkScript default).
 */
class IndicatorService
{
    /**
     * Enrich candles with all required indicators.
     *
     * @param  array $candles   Raw candle array
     * @param  array $cfg       Strategy config (periods, BB std-dev, etc.)
     * @return array            Enriched candle array
     */
    public function compute(array $candles, array $cfg): array
    {
        $n = count($candles);
        if ($n === 0) return [];

        $emaFast  = (int) ($cfg['ema_fast']          ?? 21);
        $emaMid   = (int) ($cfg['ema_mid']            ?? 50);
        $emaSlow  = (int) ($cfg['ema_slow']           ?? 100);
        $rsiP     = (int) ($cfg['rsi_period']         ?? 14);
        $bbP      = (int) ($cfg['bb_period']          ?? 20);
        $bbStd    = (float) ($cfg['bb_stddev']        ?? 2.0);
        $atrP     = (int) ($cfg['atr_period']         ?? 14);
        $volAvgP  = (int) ($cfg['volume_avg_period']  ?? 20);

        // --- Pre-allocate indicator arrays ---
        $ema21  = array_fill(0, $n, null);
        $ema50  = array_fill(0, $n, null);
        $ema100 = array_fill(0, $n, null);
        $rsi    = array_fill(0, $n, null);
        $bbUp   = array_fill(0, $n, null);
        $bbMid  = array_fill(0, $n, null);
        $bbLow  = array_fill(0, $n, null);
        $atr    = array_fill(0, $n, null);
        $volAvg = array_fill(0, $n, null);
        $relVol = array_fill(0, $n, null);

        // --- EMA ---
        $this->calcEma($candles, $ema21,  $emaFast, $n);
        $this->calcEma($candles, $ema50,  $emaMid,  $n);
        $this->calcEma($candles, $ema100, $emaSlow,  $n);

        // --- ATR (Wilder's) ---
        $this->calcAtr($candles, $atr, $atrP, $n);

        // --- RSI (Wilder's) ---
        $this->calcRsi($candles, $rsi, $rsiP, $n);

        // --- Bollinger Bands ---
        $this->calcBollingerBands($candles, $bbUp, $bbMid, $bbLow, $bbP, $bbStd, $n);

        // --- Volume Average & Relative Volume ---
        $this->calcVolumeMetrics($candles, $volAvg, $relVol, $volAvgP, $n);

        // --- Enrich candle array ---
        foreach ($candles as $i => &$c) {
            $c['ema21']   = $ema21[$i];
            $c['ema50']   = $ema50[$i];
            $c['ema100']  = $ema100[$i];
            $c['rsi']     = $rsi[$i];
            $c['bb_upper'] = $bbUp[$i];
            $c['bb_middle'] = $bbMid[$i];
            $c['bb_lower'] = $bbLow[$i];
            $c['atr']     = $atr[$i];
            $c['vol_avg'] = $volAvg[$i];
            $c['rel_vol'] = $relVol[$i];
        }

        return $candles;
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function calcEma(array $candles, array &$out, int $period, int $n): void
    {
        $k = 2.0 / ($period + 1);

        // Seed with first close (ThinkScript ExpAverage behavior)
        $out[0] = $candles[0]['close'];

        for ($i = 1; $i < $n; $i++) {
            $out[$i] = $candles[$i]['close'] * $k + $out[$i - 1] * (1 - $k);
        }

        // Mark bars before warm-up as null so strategy ignores them
        for ($i = 0; $i < min($period - 1, $n); $i++) {
            $out[$i] = null;
        }
    }

    private function calcAtr(array $candles, array &$out, int $period, int $n): void
    {
        if ($n < 2) return;

        $trArr = [];
        $trArr[0] = $candles[0]['high'] - $candles[0]['low'];

        for ($i = 1; $i < $n; $i++) {
            $hl  = $candles[$i]['high'] - $candles[$i]['low'];
            $hpc = abs($candles[$i]['high'] - $candles[$i - 1]['close']);
            $lpc = abs($candles[$i]['low']  - $candles[$i - 1]['close']);
            $trArr[$i] = max($hl, $hpc, $lpc);
        }

        // Initial ATR = simple average of first $period TRs
        if ($n < $period) return;

        $sum = 0;
        for ($i = 0; $i < $period; $i++) $sum += $trArr[$i];
        $out[$period - 1] = $sum / $period;

        // Wilder's smoothing
        for ($i = $period; $i < $n; $i++) {
            $out[$i] = ($out[$i - 1] * ($period - 1) + $trArr[$i]) / $period;
        }
    }

    private function calcRsi(array $candles, array &$out, int $period, int $n): void
    {
        if ($n < $period + 1) return;

        // First RSI: SMA of first $period changes
        $gains = $losses = 0.0;
        for ($i = 1; $i <= $period; $i++) {
            $change = $candles[$i]['close'] - $candles[$i - 1]['close'];
            if ($change > 0) $gains  += $change;
            else             $losses -= $change;
        }

        $avgGain = $gains  / $period;
        $avgLoss = $losses / $period;
        $out[$period] = $avgLoss == 0
            ? 100.0
            : 100.0 - (100.0 / (1.0 + $avgGain / $avgLoss));

        // Wilder's smoothed RSI
        for ($i = $period + 1; $i < $n; $i++) {
            $change  = $candles[$i]['close'] - $candles[$i - 1]['close'];
            $gain    = max(0, $change);
            $loss    = max(0, -$change);
            $avgGain = ($avgGain * ($period - 1) + $gain) / $period;
            $avgLoss = ($avgLoss * ($period - 1) + $loss) / $period;
            $out[$i] = $avgLoss == 0
                ? 100.0
                : 100.0 - (100.0 / (1.0 + $avgGain / $avgLoss));
        }
    }

    private function calcBollingerBands(
        array $candles, array &$up, array &$mid, array &$lo,
        int $period, float $stdMult, int $n
    ): void {
        for ($i = $period - 1; $i < $n; $i++) {
            $slice = [];
            for ($j = $i - $period + 1; $j <= $i; $j++) {
                $slice[] = $candles[$j]['close'];
            }
            $sma     = array_sum($slice) / $period;
            $variance = 0;
            foreach ($slice as $v) $variance += ($v - $sma) ** 2;
            $std     = sqrt($variance / $period);

            $mid[$i] = $sma;
            $up[$i]  = $sma + $stdMult * $std;
            $lo[$i]  = $sma - $stdMult * $std;
        }
    }

    private function calcVolumeMetrics(
        array $candles, array &$avgOut, array &$relOut,
        int $period, int $n
    ): void {
        for ($i = $period - 1; $i < $n; $i++) {
            $sum = 0;
            for ($j = $i - $period + 1; $j <= $i; $j++) {
                $sum += $candles[$j]['volume'];
            }
            $avg = $sum / $period;
            $avgOut[$i] = $avg;
            $relOut[$i] = $avg > 0 ? $candles[$i]['volume'] / $avg : null;
        }
    }
}

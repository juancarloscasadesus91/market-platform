<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;

/**
 * Exact ThinkScript → PHP translation of the EMA Pullback strategy.
 *
 * Bar numbering: 0-indexed array (identical to ThinkScript BarNumber()-1).
 * Previous-bar access: $arr[$i-1]  ↔  ThinkScript value[1].
 *
 * State variables that persist across bars (like ThinkScript global vars):
 *   $bullPullbackBar  / $bullPullbackHigh
 *   $bearPullbackBar  / $bearPullbackLow
 *   $prevBullConfirm  / $prevBearConfirm
 *
 * Returns an array of signal arrays, one per detected entry.
 */
class StrictEmaPullbackStrategyService
{
    /**
     * @param  array $candles  Enriched candle array (from IndicatorService::compute)
     * @param  array $cfg      Strategy config
     * @return array           Detected signals (each is a rich associative array)
     */
    public function detect(array $candles, array $cfg): array
    {
        $emaFast   = (int)   ($cfg['ema_fast']               ?? 21);
        $emaMid    = (int)   ($cfg['ema_mid']                ?? 50);
        $emaSlow   = (int)   ($cfg['ema_slow']               ?? 100);
        $maxBars   = (int)   ($cfg['max_bars_after_pullback'] ?? 3);
        $minDistP  = (float) ($cfg['min_distance_pct']       ?? 0.02);

        // Optional filter limits
        $rsiMaxCall    = (float) ($cfg['rsi_max_call']           ?? 100);
        $rsiMinPut     = (float) ($cfg['rsi_min_put']            ?? 0);
        $maxAtrRatio   = (float) ($cfg['max_candle_atr_ratio']   ?? PHP_INT_MAX);
        $maxEmaDist    = (float) ($cfg['max_price_ema_dist_pct'] ?? PHP_INT_MAX);
        $minBbDist     = (float) ($cfg['min_bb_dist_pct']        ?? 0);

        // State (equivalent to ThinkScript globals updated each bar)
        $bullPullbackBar  = -1;
        $bullPullbackHigh = 0.0;
        $bearPullbackBar  = -1;
        $bearPullbackLow  = PHP_FLOAT_MAX;
        $prevBullConfirm  = false;
        $prevBearConfirm  = false;

        $signals = [];
        $n       = count($candles);

        for ($i = 0; $i < $n; $i++) {
            $c = $candles[$i];

            // Skip until all three EMAs are ready (warm-up guard)
            if ($c['ema21'] === null || $c['ema50'] === null || $c['ema100'] === null) {
                $prevBullConfirm = false;
                $prevBearConfirm = false;
                continue;
            }

            $e21  = (float) $c['ema21'];
            $e50  = (float) $c['ema50'];
            $e100 = (float) $c['ema100'];

            // --- MinDistance = EMA21 * minDistancePercent / 100 ---
            $minDist = $e21 * $minDistP / 100.0;

            // --- EMAsSeparated ---
            $emasSep = (abs($e21 - $e50) >= $minDist) && (abs($e50 - $e100) >= $minDist);

            // --- Trend ---
            $bullTrend = ($e21 > $e50) && ($e50 > $e100);
            $bearTrend = ($e21 < $e50) && ($e50 < $e100);

            // --- EMA slopes (previous-bar access) ---
            $e21Prev  = $i > 0 ? $candles[$i - 1]['ema21']  : null;
            $e50Prev  = $i > 0 ? $candles[$i - 1]['ema50']  : null;

            $ema21Rising  = ($e21Prev !== null) && ($e21  > (float) $e21Prev);
            $ema50Rising  = ($e50Prev !== null) && ($e50  > (float) $e50Prev);
            $ema21Falling = ($e21Prev !== null) && ($e21  < (float) $e21Prev);
            $ema50Falling = ($e50Prev !== null) && ($e50  < (float) $e50Prev);

            $open  = (float) $c['open'];
            $high  = (float) $c['high'];
            $low   = (float) $c['low'];
            $close = (float) $c['close'];

            // ---------------------------------------------------------------
            // BULL PULLBACK
            // BullPullback = BullTrend AND EMAsSeparated AND EMA21Rising
            //             AND EMA50Rising AND low <= EMA21 AND close > EMA21
            // ---------------------------------------------------------------
            $bullPullback = $bullTrend && $emasSep && $ema21Rising && $ema50Rising
                && ($low <= $e21) && ($close > $e21);

            if ($bullPullback) {
                $bullPullbackBar  = $i;
                $bullPullbackHigh = $high;
            }

            // ---------------------------------------------------------------
            // BEAR PULLBACK
            // BearPullback = BearTrend AND EMAsSeparated AND EMA21Falling
            //             AND EMA50Falling AND high >= EMA21 AND close < EMA21
            // ---------------------------------------------------------------
            $bearPullback = $bearTrend && $emasSep && $ema21Falling && $ema50Falling
                && ($high >= $e21) && ($close < $e21);

            if ($bearPullback) {
                $bearPullbackBar = $i;
                $bearPullbackLow = $low;
            }

            // ---------------------------------------------------------------
            // BULL CONFIRMATION
            // BullConfirm = BullTrend AND EMAsSeparated AND EMA21Rising
            //            AND EMA50Rising AND currentBar > bullPullbackBar
            //            AND currentBar <= bullPullbackBar + maxBarsAfterPullback
            //            AND close > bullPullbackHigh AND close > open
            //            AND close >= EMA21 + MinDistance
            // ---------------------------------------------------------------
            $bullConfirm = $bullPullbackBar >= 0
                && $bullTrend && $emasSep && $ema21Rising && $ema50Rising
                && ($i > $bullPullbackBar)
                && ($i <= $bullPullbackBar + $maxBars)
                && ($close > $bullPullbackHigh)
                && ($close > $open)
                && ($close >= $e21 + $minDist);

            // ---------------------------------------------------------------
            // BEAR CONFIRMATION
            // BearConfirm = BearTrend AND EMAsSeparated AND EMA21Falling
            //            AND EMA50Falling AND currentBar > bearPullbackBar
            //            AND currentBar <= bearPullbackBar + maxBarsAfterPullback
            //            AND close < bearPullbackLow AND close < open
            //            AND close <= EMA21 - MinDistance
            // ---------------------------------------------------------------
            $bearConfirm = $bearPullbackBar >= 0
                && $bearTrend && $emasSep && $ema21Falling && $ema50Falling
                && ($i > $bearPullbackBar)
                && ($i <= $bearPullbackBar + $maxBars)
                && ($close < $bearPullbackLow)
                && ($close < $open)
                && ($close <= $e21 - $minDist);

            // ---------------------------------------------------------------
            // ENTRY: signal fires on first confirmation bar (edge: prev=false, now=true)
            // Actual trade entry = next bar's open
            // ---------------------------------------------------------------
            $bullEntry = $bullConfirm && !$prevBullConfirm;
            $bearEntry = $bearConfirm && !$prevBearConfirm;

            if ($bullEntry && $i + 1 < $n) {
                // Only trade within the configured trading window (min_entry_time to max_entry_time)
                $entryEtTime = Carbon::parse($candles[$i + 1]['dt'], 'UTC')
                    ->setTimezone('America/New_York')->format('H:i');
                $minEntryTime = $cfg['min_entry_time'] ?? '09:30';
                $maxEntryTime = $cfg['max_entry_time'] ?? '16:00';
                $entryCandleDistancePct = $cfg['entry_candle_distance_pct'] ?? null;
                $volumeRelMin = $cfg['volume_rel_min'] ?? null;
                $volumeRelMax = $cfg['volume_rel_max'] ?? null;
                $volumeAvgPeriod = $cfg['volume_avg_period'] ?? 20;

                // Normalize min/max entry times to 24h format for comparison
                $normalizedMinTime = Carbon::parse($minEntryTime, 'America/New_York')->format('H:i');
                $normalizedMaxTime = Carbon::parse($maxEntryTime, 'America/New_York')->format('H:i');

                if ($entryEtTime >= $normalizedMinTime && $entryEtTime < $normalizedMaxTime) {

                    // Check entry candle distance (range) if configured
                    if ($entryCandleDistancePct !== null) {
                        $entryCandle = $candles[$i + 1];
                        $candleRange = ($entryCandle['high'] - $entryCandle['low']) / $entryCandle['close'] * 100;
                        if ($candleRange < $entryCandleDistancePct) {
                            // Skip entry if candle range is too small
                            continue;
                        }
                    }

                    // Check relative volume filters if configured
                    if ($volumeRelMin !== null || $volumeRelMax !== null) {
                        $entryCandle = $candles[$i + 1];
                        $entryVolume = $entryCandle['volume'] ?? 0;

                        // Calculate average volume of last N candles
                        $avgVolume = 0;
                        $count = 0;
                        for ($j = max(0, $i + 1 - $volumeAvgPeriod); $j <= $i; $j++) {
                            $avgVolume += $candles[$j]['volume'] ?? 0;
                            $count++;
                        }
                        $avgVolume = $count > 0 ? $avgVolume / $count : 0;

                        // Calculate relative volume
                        $relVolume = $avgVolume > 0 ? $entryVolume / $avgVolume : 0;

                        if ($volumeRelMin !== null && $relVolume < $volumeRelMin) {
                            // Skip entry if relative volume is too low
                            continue;
                        }
                        if ($volumeRelMax !== null && $relVolume > $volumeRelMax) {
                            // Skip entry if relative volume is too high
                            continue;
                        }
                    }

                    $pb = $candles[$bullPullbackBar];
                    $signal = $this->buildSignal(
                        'CALL', $i, $candles, $pb, $c,
                        $e21, $e50, $e100, $minDist, $cfg
                    );

                    // Optional filters
                    if ($this->passesFilters($signal, $cfg, 'CALL')) {
                        $signals[] = $signal;
                    }
                }
            }

            if ($bearEntry && $i + 1 < $n) {
                // Only trade within the configured trading window (min_entry_time to max_entry_time)
                $entryEtTime = Carbon::parse($candles[$i + 1]['dt'], 'UTC')
                    ->setTimezone('America/New_York')->format('H:i');
                $minEntryTime = $cfg['min_entry_time'] ?? '09:30';
                $maxEntryTime = $cfg['max_entry_time'] ?? '16:00';
                $entryCandleDistancePct = $cfg['entry_candle_distance_pct'] ?? null;
                $volumeRelMin = $cfg['volume_rel_min'] ?? null;
                $volumeRelMax = $cfg['volume_rel_max'] ?? null;
                $volumeAvgPeriod = $cfg['volume_avg_period'] ?? 20;

                // Normalize min/max entry times to 24h format for comparison
                $normalizedMinTime = Carbon::parse($minEntryTime, 'America/New_York')->format('H:i');
                $normalizedMaxTime = Carbon::parse($maxEntryTime, 'America/New_York')->format('H:i');

                if ($entryEtTime >= $normalizedMinTime && $entryEtTime < $normalizedMaxTime) {

                    // Check entry candle distance (range) if configured
                    if ($entryCandleDistancePct !== null) {
                        $entryCandle = $candles[$i + 1];
                        $candleRange = ($entryCandle['high'] - $entryCandle['low']) / $entryCandle['close'] * 100;
                        if ($candleRange < $entryCandleDistancePct) {
                            // Skip entry if candle range is too small
                            continue;
                        }
                    }

                    // Check relative volume filters if configured
                    if ($volumeRelMin !== null || $volumeRelMax !== null) {
                        $entryCandle = $candles[$i + 1];
                        $entryVolume = $entryCandle['volume'] ?? 0;

                        // Calculate average volume of last N candles
                        $avgVolume = 0;
                        $count = 0;
                        for ($j = max(0, $i + 1 - $volumeAvgPeriod); $j <= $i; $j++) {
                            $avgVolume += $candles[$j]['volume'] ?? 0;
                            $count++;
                        }
                        $avgVolume = $count > 0 ? $avgVolume / $count : 0;

                        // Calculate relative volume
                        $relVolume = $avgVolume > 0 ? $entryVolume / $avgVolume : 0;

                        if ($volumeRelMin !== null && $relVolume < $volumeRelMin) {
                            // Skip entry if relative volume is too low
                            continue;
                        }
                        if ($volumeRelMax !== null && $relVolume > $volumeRelMax) {
                            // Skip entry if relative volume is too high
                            continue;
                        }
                    }

                    $pb = $candles[$bearPullbackBar];
                    $signal = $this->buildSignal(
                        'PUT', $i, $candles, $pb, $c,
                        $e21, $e50, $e100, $minDist, $cfg
                    );

                    if ($this->passesFilters($signal, $cfg, 'PUT')) {
                        $signals[] = $signal;
                    }
                }
            }

            $prevBullConfirm = $bullConfirm;
            $prevBearConfirm = $bearConfirm;
        }

        return $signals;
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function buildSignal(
        string $direction,
        int    $confirmIdx,
        array  $candles,
        array  $pb,        // pullback candle
        array  $cf,        // confirmation candle
        float  $e21,
        float  $e50,
        float  $e100,
        float  $minDist,
        array  $cfg,
    ): array {
        $entryIdx   = $confirmIdx + 1;
        $entryCandle = $candles[$entryIdx] ?? null;
        $entryPrice  = $entryCandle ? (float) $entryCandle['open'] : null;
        $entryTime   = $entryCandle ? $entryCandle['dt'] : null;

        return [
            'direction'        => $direction,
            'confirm_idx'      => $confirmIdx,
            'entry_idx'        => $entryIdx,
            // Pullback candle
            'pullback_time'    => $pb['dt'],
            'pullback_open'    => (float) $pb['open'],
            'pullback_high'    => (float) $pb['high'],
            'pullback_low'     => (float) $pb['low'],
            'pullback_close'   => (float) $pb['close'],
            // Confirmation candle
            'confirm_time'     => $cf['dt'],
            'confirm_open'     => (float) $cf['open'],
            'confirm_high'     => (float) $cf['high'],
            'confirm_low'      => (float) $cf['low'],
            'confirm_close'    => (float) $cf['close'],
            // Entry
            'entry_time'       => $entryTime,
            'entry_price'      => $entryPrice,
            // Indicators (at confirmation bar)
            'ema21'            => $e21,
            'ema50'            => $e50,
            'ema100'           => $e100,
            'min_distance'     => $minDist,
            'dist_ema21_ema50'    => abs($e21 - $e50),
            'dist_ema50_ema100'   => abs($e50 - $e100),
            'rsi'              => $cf['rsi'],
            'atr'              => $cf['atr'],
            'bb_upper'         => $cf['bb_upper'],
            'bb_middle'        => $cf['bb_middle'],
            'bb_lower'         => $cf['bb_lower'],
            'volume'           => $cf['volume'],
            'rel_volume'       => $cf['rel_vol'],
        ];
    }

    private function passesFilters(array $signal, array $cfg, string $direction): bool
    {
        $rsi         = $signal['rsi'];
        $atr         = $signal['atr'];
        $confirmBody = abs($signal['confirm_close'] - $signal['confirm_open']);
        $e21         = $signal['ema21'];
        $e50         = $signal['ema50'];
        $closePrice  = $signal['confirm_close'];

        // RSI filter
        if ($direction === 'CALL' && $rsi !== null) {
            if ($rsi > (float) ($cfg['rsi_max_call'] ?? 100)) return false;
        }
        if ($direction === 'PUT' && $rsi !== null) {
            if ($rsi < (float) ($cfg['rsi_min_put'] ?? 0)) return false;
        }

        // Confirmation candle size vs ATR
        if ($atr !== null && $atr > 0) {
            $maxRatio = (float) ($cfg['max_candle_atr_ratio'] ?? PHP_INT_MAX);
            if ($confirmBody / $atr > $maxRatio) return false;
        }

        // Price distance from EMA21/EMA50
        $maxEmaDist = (float) ($cfg['max_price_ema_dist_pct'] ?? PHP_INT_MAX);
        if ($e21 > 0) {
            $distE21 = abs($closePrice - $e21) / $e21 * 100;
            $distE50 = abs($closePrice - $e50) / $e50 * 100;
            if ($distE21 > $maxEmaDist || $distE50 > $maxEmaDist) return false;
        }

        // EMA21–EMA50 distance filter (absolute points)
        $d2150 = $signal['dist_ema21_ema50'];
        $min2150 = isset($cfg['min_ema21_ema50_dist']) && $cfg['min_ema21_ema50_dist'] !== null
            ? (float) $cfg['min_ema21_ema50_dist'] : null;
        $max2150 = isset($cfg['max_ema21_ema50_dist']) && $cfg['max_ema21_ema50_dist'] !== null
            ? (float) $cfg['max_ema21_ema50_dist'] : null;
        if ($min2150 !== null && $d2150 < $min2150) return false;
        if ($max2150 !== null && $d2150 > $max2150) return false;

        // EMA50–EMA100 distance filter (absolute points)
        $d50100 = $signal['dist_ema50_ema100'];
        $min50100 = isset($cfg['min_ema50_ema100_dist']) && $cfg['min_ema50_ema100_dist'] !== null
            ? (float) $cfg['min_ema50_ema100_dist'] : null;
        $max50100 = isset($cfg['max_ema50_ema100_dist']) && $cfg['max_ema50_ema100_dist'] !== null
            ? (float) $cfg['max_ema50_ema100_dist'] : null;
        if ($min50100 !== null && $d50100 < $min50100) return false;
        if ($max50100 !== null && $d50100 > $max50100) return false;

        // Bollinger Band proximity filter
        $bbUp  = $signal['bb_upper'];
        $bbLow = $signal['bb_lower'];
        $minBbDist = (float) ($cfg['min_bb_dist_pct'] ?? 0);
        if ($bbUp !== null && $bbLow !== null && $minBbDist > 0) {
            $bbRange = $bbUp - $bbLow;
            if ($bbRange > 0) {
                if ($direction === 'CALL') {
                    $distToUp = ($bbUp - $closePrice) / $bbRange * 100;
                    if ($distToUp < $minBbDist) return false;
                } else {
                    $distToLow = ($closePrice - $bbLow) / $bbRange * 100;
                    if ($distToLow < $minBbDist) return false;
                }
            }
        }

        return true;
    }
}

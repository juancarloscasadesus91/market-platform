<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;

/**
 * Simulates a trade bar-by-bar from entry to exit.
 *
 * Receives a signal (from StrictEmaPullbackStrategyService) and the full
 * enriched candle array.  Returns a completed trade array with all outcome
 * fields filled in.
 */
class TradeSimulatorService
{
    public function simulate(array $signal, array $allCandles, array $cfg): array
    {
        $direction  = $signal['direction'];       // CALL | PUT
        $entryIdx   = $signal['entry_idx'];
        $entryPrice = $signal['entry_price'];
        $e50        = $signal['ema50'];
        $atr        = $signal['atr'];

        // Get EMA values from entry bar (not confirmation bar) for stop calculation
        $entryBar = $allCandles[$entryIdx] ?? null;
        $entryEma21 = $entryBar['ema21'] ?? null;
        $entryEma50 = $entryBar['ema50'] ?? null;

        if ($entryPrice === null || $entryIdx >= count($allCandles)) {
            return array_merge($signal, [
                'stop_loss' => null, 'take_profit_1' => null,
                'take_profit_2' => null, 'take_profit_3' => null,
                'exit_price' => null, 'exit_time' => null,
                'exit_reason' => 'invalidation',
                'result' => 'open',
                'pnl_points' => null, 'pnl_pct' => null,
                'max_favorable_excursion' => null, 'max_adverse_excursion' => null,
                'r_multiple' => null,
            ]);
        }

        // -----------------------------------------------------------------------
        // Stop Loss
        // -----------------------------------------------------------------------
        $stopType   = $cfg['stop_type']       ?? 'pullback';
        $stopAtrMul = (float) ($cfg['stop_atr_mult']      ?? 1.5);
        $stopBuffer = (float) ($cfg['stop_buffer_pct']    ?? 0.05);
        $stopPct    = isset($cfg['stop_pct']) ? (float) $cfg['stop_pct'] : null;

        $stopLoss = null;

        // EMA Quadrant Trailing Stop (as stop_type)
        $isQuadrantTrailStop = ($stopType === 'ema_quadrant_trailing');
        $quadrantNextIdx    = 0;    // index of next profit level to watch for
        $quadrantLevels     = [];   // profit prices at each step
        $quadrantTrailStops = [];   // trailing stop price after each level is cleared

        if ($isQuadrantTrailStop) {
            $dir = $direction === 'CALL' ? 1.0 : -1.0;

            // Full range = absolute distance between entry and EMA50
            // This is always positive and represents the total quadrant span
            $fullRange    = abs($entryPrice - $e50);
            if ($fullRange < $entryPrice * 0.001) $fullRange = $entryPrice * 0.01; // min 1% fallback
            $stepPct      = (float) ($cfg['quadrant_step_pct'] ?? 25.0);
            $stepPct      = max(5.0, min(50.0, $stepPct));   // clamp 5–50%
            $quadrantUnit = $fullRange * ($stepPct / 100.0); // size of one step
            $numLevels    = (int) round(100.0 / $stepPct);   // e.g. 25%→4, 33%→3, 20%→5

            // Initial stop = entry − 1 unit (the −step% level)
            $stopLoss = $entryPrice - $dir * $quadrantUnit;
            $risk     = $quadrantUnit;
            if ($risk <= 0.0) $risk = $entryPrice * 0.005;

            // Profit levels: +1u, +2u … +Nu from entry
            for ($k = 1; $k <= $numLevels; $k++) {
                $quadrantLevels[] = $entryPrice + $dir * $k * $quadrantUnit;
            }

            // Trailing stop after each level (except the last which is the final exit)
            // After breaking level K → stop moves to level K-1 (or entry for K=1)
            for ($k = 0; $k < $numLevels - 1; $k++) {
                $quadrantTrailStops[] = $k === 0
                    ? $entryPrice
                    : $entryPrice + $dir * $k * $quadrantUnit;
            }
        } elseif ($stopType === 'pullback') {
            if ($direction === 'CALL') {
                // Below pullback low minus buffer
                $stopLoss = $signal['pullback_low'] * (1 - $stopBuffer / 100);
            } else {
                // Above pullback high plus buffer
                $stopLoss = $signal['pullback_high'] * (1 + $stopBuffer / 100);
            }
        } elseif ($stopType === 'ema_mid') {
            if ($direction === 'CALL') {
                $stopLoss = $e50 * (1 - $stopBuffer / 100);
            } else {
                $stopLoss = $e50 * (1 + $stopBuffer / 100);
            }
        } elseif ($stopType === 'ema_mid_range') {
            // Stop in the middle between EMA50 and EMA21 at entry bar
            $e21 = $entryEma21;
            $e50Entry = $entryEma50;
            if ($e21 !== null && $e50Entry !== null) {
                if ($direction === 'CALL') {
                    // Stop is between EMA50 and EMA21 (midpoint minus buffer)
                    $midPoint = ($e50Entry + $e21) / 2;
                    $stopLoss = $midPoint * (1 - $stopBuffer / 100);
                } else {
                    // Stop is between EMA50 and EMA21 (midpoint plus buffer)
                    $midPoint = ($e50Entry + $e21) / 2;
                    $stopLoss = $midPoint * (1 + $stopBuffer / 100);
                }
            } else {
                // Fallback to EMA50 if EMA21 not available
                if ($direction === 'CALL') {
                    $stopLoss = $e50Entry * (1 - $stopBuffer / 100);
                } else {
                    $stopLoss = $e50Entry * (1 + $stopBuffer / 100);
                }
            }
        } elseif ($stopType === 'atr' && $atr !== null) {
            if ($direction === 'CALL') {
                $stopLoss = $entryPrice - $atr * $stopAtrMul;
            } else {
                $stopLoss = $entryPrice + $atr * $stopAtrMul;
            }
        } elseif ($stopType === 'percent' && $stopPct !== null) {
            // Stop as percentage of entry price
            if ($direction === 'CALL') {
                $stopLoss = $entryPrice * (1 - $stopPct / 100);
            } else {
                $stopLoss = $entryPrice * (1 + $stopPct / 100);
            }
        }

        if ($stopLoss === null) {
            // Fallback: 0.5% of entry price
            $stopLoss = $direction === 'CALL'
                ? $entryPrice * 0.995
                : $entryPrice * 1.005;
        }

        $risk = abs($entryPrice - $stopLoss);
        if ($risk <= 0) $risk = $entryPrice * 0.005;

        // -----------------------------------------------------------------------
        // Take Profit levels
        // -----------------------------------------------------------------------
        $tpType = $cfg['tp_type'] ?? 'risk_ratio';
        $tp1v   = (float) ($cfg['tp1_value'] ?? 1.0);
        $tp2v   = (float) ($cfg['tp2_value'] ?? 2.0);
        $tp3v   = (float) ($cfg['tp3_value'] ?? 3.0);

        if ($tpType === 'risk_ratio') {
            $dir = $direction === 'CALL' ? 1 : -1;
            $tp1 = $entryPrice + $dir * $risk * $tp1v;
            $tp2 = $entryPrice + $dir * $risk * $tp2v;
            $tp3 = $entryPrice + $dir * $risk * $tp3v;
        } elseif ($tpType === 'points') {
            $dir = $direction === 'CALL' ? 1 : -1;
            $tp1 = $entryPrice + $dir * $tp1v;
            $tp2 = $entryPrice + $dir * $tp2v;
            $tp3 = $entryPrice + $dir * $tp3v;
        } elseif ($tpType === 'bb_middle') {
            // TP1 = Bollinger Band middle (SMA). TP2/TP3 = middle ± tp2/tp3 pts beyond
            $bbMid = (float) ($signal['bb_middle'] ?? 0.0);
            $dir   = $direction === 'CALL' ? 1 : -1;
            $tp1   = $bbMid > 0 ? $bbMid : $entryPrice + $dir * $risk;
            $tp2   = $tp1 + $dir * $tp2v;
            $tp3   = $tp1 + $dir * $tp3v;
        } elseif ($tpType === 'bb_band') {
            // TP1 = opposite Bollinger Band (upper for CALL, lower for PUT)
            // TP2/TP3 = band ± tp2/tp3 pts beyond
            $bbUpper = (float) ($signal['bb_upper'] ?? 0.0);
            $bbLower = (float) ($signal['bb_lower'] ?? 0.0);
            $dir     = $direction === 'CALL' ? 1 : -1;
            $bbTarget = $direction === 'CALL' ? $bbUpper : $bbLower;
            $tp1   = $bbTarget > 0 ? $bbTarget : $entryPrice + $dir * $risk * $tp1v;
            $tp2   = $tp1 + $dir * $tp2v;
            $tp3   = $tp1 + $dir * $tp3v;
        } else { // percent
            $dir = $direction === 'CALL' ? 1 : -1;
            $tp1 = $entryPrice * (1 + $dir * $tp1v / 100);
            $tp2 = $entryPrice * (1 + $dir * $tp2v / 100);
            $tp3 = $entryPrice * (1 + $dir * $tp3v / 100);
        }

        // -----------------------------------------------------------------------
        // EMA Quadrant Trailing Stop  (tp_type = 'ema_quadrant_trail')
        //
        // NOTE: This is now also available as stop_type = 'ema_quadrant_trailing'
        // The logic is shared between both options.
        //
        // Quadrant unit  = |entry − EMA50| / 2
        // Levels (CALL example, mirrored for PUT):
        //   −25% = entry − 1u  ← initial stop loss
        //    0%  = entry        ← breakeven (stop after 25% broken)
        //   +25% = entry + 1u   ← profit L1  → stop → entry
        //   +50% = entry + 2u   ← profit L2  → stop → 25%
        //   +75% = entry + 3u   ← profit L3  → stop → 50%
        //  +100% = entry + 4u   ← final TP exit
        // -----------------------------------------------------------------------
        $isQuadrantTrail    = ($tpType === 'ema_quadrant_trail' || $isQuadrantTrailStop);

        if ($isQuadrantTrail && !$isQuadrantTrailStop) {
            // Initialize quadrant trail if using tp_type (not stop_type)
            $dir = $direction === 'CALL' ? 1.0 : -1.0;

            // Full range = absolute distance between entry and EMA50
            $fullRange    = abs($entryPrice - $e50);
            if ($fullRange < $entryPrice * 0.001) $fullRange = $entryPrice * 0.01;
            $stepPct      = (float) ($cfg['quadrant_step_pct'] ?? 25.0);
            $stepPct      = max(5.0, min(50.0, $stepPct));
            $quadrantUnit = $fullRange * ($stepPct / 100.0);
            $numLevels    = (int) round(100.0 / $stepPct);

            // Initial stop = entry − 1 unit
            $stopLoss = $entryPrice - $dir * $quadrantUnit;
            $risk     = $quadrantUnit;
            if ($risk <= 0.0) $risk = $entryPrice * 0.005;

            // Profit levels
            for ($k = 1; $k <= $numLevels; $k++) {
                $quadrantLevels[] = $entryPrice + $dir * $k * $quadrantUnit;
            }

            // Trailing stops
            for ($k = 0; $k < $numLevels - 1; $k++) {
                $quadrantTrailStops[] = $k === 0
                    ? $entryPrice
                    : $entryPrice + $dir * $k * $quadrantUnit;
            }

            // Override tp1/tp2/tp3 for reporting (first 3 levels)
            $tp1 = $quadrantLevels[0] ?? $entryPrice;
            $tp2 = $quadrantLevels[1] ?? $entryPrice;
            $tp3 = $quadrantLevels[2] ?? $entryPrice;
        } elseif ($isQuadrantTrailStop) {
            // Set TP levels for reporting when using stop_type
            $tp1 = $quadrantLevels[0] ?? $entryPrice;
            $tp2 = $quadrantLevels[1] ?? $entryPrice;
            $tp3 = $quadrantLevels[2] ?? $entryPrice;
        }

        // -----------------------------------------------------------------------
        // Simulate bar by bar from entry bar onwards
        // -----------------------------------------------------------------------
        $exitPrice  = null;
        $exitTime   = null;
        $exitReason = null;
        $mfe = 0.0;  // max favorable excursion (in price points from entry)
        $mae = 0.0;  // max adverse excursion

        // Close at 4 PM ET (20:00 UTC) or at configured force_exit_time
        $forceExitTime = $cfg['force_exit_time'] ?? null; // "HH:MM" ET or null
        $marketCloseEt = '16:00'; // 4 PM ET
        $maxTradeDurationMinutes = $cfg['max_trade_duration_minutes'] ?? null; // minutes or null

        for ($i = $entryIdx; $i < count($allCandles); $i++) {
            $bar = $allCandles[$i];

            // Convert bar time to ET for comparison
            $barTimeEt = Carbon::parse($bar['dt'], 'UTC')
                ->setTimezone('America/New_York')
                ->format('H:i');

            // Check max trade duration
            if ($maxTradeDurationMinutes !== null) {
                $entryTime = Carbon::parse($allCandles[$entryIdx]['dt'], 'UTC');
                $barTime = Carbon::parse($bar['dt'], 'UTC');
                $minutesInTrade = $entryTime->diffInMinutes($barTime);
                if ($minutesInTrade >= $maxTradeDurationMinutes) {
                    $exitPrice  = (float) $bar['close'];
                    $exitTime   = $bar['dt'];
                    $exitReason = 'max_duration';
                    break;
                }
            }

            $h = (float) $bar['high'];
            $l = (float) $bar['low'];

            if ($direction === 'CALL') {
                $favorable = $h - $entryPrice;
                $adverse   = $entryPrice - $l;
                $mfe = max($mfe, $favorable);
                $mae = max($mae, $adverse);

                if ($isQuadrantTrail) {
                    // Advance through as many profit levels as this bar reaches
                    $lastLevel = count($quadrantLevels) - 1;
                    while ($quadrantNextIdx <= $lastLevel) {
                        if ($h < $quadrantLevels[$quadrantNextIdx]) break;
                        if ($quadrantNextIdx === $lastLevel) {
                            // Final level hit → take profit exit
                            $exitPrice  = $quadrantLevels[$lastLevel];
                            $exitReason = 'take_profit_' . ($lastLevel + 1);
                            break 2;
                        }
                        // Trail the stop up to the previous level
                        $stopLoss = $quadrantTrailStops[$quadrantNextIdx];
                        $quadrantNextIdx++;
                    }
                    if ($exitReason === null && $l <= $stopLoss) {
                        $exitPrice = $stopLoss; $exitReason = 'stop_loss'; break;
                    }
                } else {
                    // Check TP3 first (price can hit multiple levels in same bar)
                    if ($h >= $tp3) {
                        $exitPrice = $tp3; $exitReason = 'take_profit_3'; break;
                    }
                    if ($h >= $tp2) {
                        $exitPrice = $tp2; $exitReason = 'take_profit_2'; break;
                    }
                    if ($h >= $tp1) {
                        $exitPrice = $tp1; $exitReason = 'take_profit_1'; break;
                    }
                    if ($l <= $stopLoss) {
                        $exitPrice = $stopLoss; $exitReason = 'stop_loss'; break;
                    }
                }
            } else { // PUT
                $favorable = $entryPrice - $l;
                $adverse   = $h - $entryPrice;
                $mfe = max($mfe, $favorable);
                $mae = max($mae, $adverse);

                if ($isQuadrantTrail) {
                    // Advance through as many profit levels as this bar reaches
                    $lastLevel = count($quadrantLevels) - 1;
                    while ($quadrantNextIdx <= $lastLevel) {
                        if ($l > $quadrantLevels[$quadrantNextIdx]) break;
                        if ($quadrantNextIdx === $lastLevel) {
                            // Final level hit → take profit exit
                            $exitPrice  = $quadrantLevels[$lastLevel];
                            $exitReason = 'take_profit_' . ($lastLevel + 1);
                            break 2;
                        }
                        // Trail the stop down to the previous level
                        $stopLoss = $quadrantTrailStops[$quadrantNextIdx];
                        $quadrantNextIdx++;
                    }
                    if ($exitReason === null && $h >= $stopLoss) {
                        $exitPrice = $stopLoss; $exitReason = 'stop_loss'; break;
                    }
                } else {
                    if ($l <= $tp3) {
                        $exitPrice = $tp3; $exitReason = 'take_profit_3'; break;
                    }
                    if ($l <= $tp2) {
                        $exitPrice = $tp2; $exitReason = 'take_profit_2'; break;
                    }
                    if ($l <= $tp1) {
                        $exitPrice = $tp1; $exitReason = 'take_profit_1'; break;
                    }
                    if ($h >= $stopLoss) {
                        $exitPrice = $stopLoss; $exitReason = 'stop_loss'; break;
                    }
                }
            }

            // Force exit at configured time (e.g. "15:45" ET) — user's rule:
            // never hold a trade into the final minutes of the session.
            // This check happens AFTER TP/SL checks for the current bar.
            if ($forceExitTime !== null && $exitReason === null) {
                if ($barTimeEt >= $forceExitTime) {
                    $exitPrice  = (float) $bar['close'];
                    $exitTime   = $bar['dt'];
                    $exitReason = 'time_exit';
                    break;
                }
            }

            // Close at market close (4 PM ET) if no force_exit_time set
            if ($forceExitTime === null && $barTimeEt >= $marketCloseEt && $exitReason === null) {
                $exitPrice  = (float) $bar['close'];
                $exitTime   = $bar['dt'];
                $exitReason = 'market_close';
                break;
            }
        }

        if ($exitPrice === null) {
            // Reached end of candles without exit (single-day dataset)
            $lastBar    = $allCandles[count($allCandles) - 1];
            $exitPrice  = (float) $lastBar['close'];
            $exitTime   = $lastBar['dt'];
            $exitReason = 'end_of_session';
        } else {
            $exitTime = $exitTime ?? $allCandles[min($i, count($allCandles) - 1)]['dt'];
        }

        // -----------------------------------------------------------------------
        // Outcome
        // -----------------------------------------------------------------------
        $pnlPoints = $direction === 'CALL'
            ? $exitPrice - $entryPrice
            : $entryPrice - $exitPrice;

        $pnlPct    = $entryPrice > 0 ? ($pnlPoints / $entryPrice) * 100 : 0;
        $rMult     = $risk > 0 ? $pnlPoints / $risk : null;

        $result = match(true) {
            $pnlPoints > 0  => 'win',
            $pnlPoints < 0  => 'loss',
            default         => 'breakeven',
        };

        return array_merge($signal, [
            'stop_loss'               => round($stopLoss, 4),
            'take_profit_1'           => round($tp1, 4),
            'take_profit_2'           => round($tp2, 4),
            'take_profit_3'           => round($tp3, 4),
            'exit_price'              => round($exitPrice, 4),
            'exit_time'               => $exitTime,
            'exit_reason'             => $exitReason,
            'result'                  => $result,
            'pnl_points'              => round($pnlPoints, 4),
            'pnl_pct'                 => round($pnlPct, 4),
            'max_favorable_excursion' => round($mfe, 4),
            'max_adverse_excursion'   => round($mae, 4),
            'r_multiple'              => $rMult !== null ? round($rMult, 4) : null,
        ]);
    }
}

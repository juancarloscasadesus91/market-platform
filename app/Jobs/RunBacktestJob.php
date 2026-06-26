<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\BacktestSession;
use App\Models\BacktestTrade;
use App\Contracts\MarketDataServiceInterface;
use App\Services\IndicatorService;
use App\Services\StrictEmaPullbackStrategyService;
use App\Services\TradeSimulatorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RunBacktestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries   = 1;

    public function __construct(
        public readonly int    $sessionId,
        public readonly string $symbol,
    ) {}

    public function handle(
        MarketDataServiceInterface     $market,
        IndicatorService               $indicators,
        StrictEmaPullbackStrategyService $strategy,
        TradeSimulatorService          $simulator,
    ): void {
        Log::info("RunBacktestJob started for session {$this->sessionId}, symbol {$this->symbol}");
        $session = BacktestSession::with('strategy')->find($this->sessionId);
        if (!$session) {
            Log::error("RunBacktestJob: Session {$this->sessionId} not found");
            return;
        }

        try {
            $session->update([
                'status'         => 'running',
                'progress_label' => "Analysing {$this->symbol}…",
            ]);

            $cfg = $session->strategy->toArray();

            // --- 1. Load candles from DB (already imported) ---
            $candleModels = $market->getCandles(
                $this->symbol,
                $session->timeframe,
                $session->date_from->toDateString(),
                $session->date_to->toDateString(),
            );

            if ($candleModels->isEmpty()) {
                $session->markFailed(
                    "No se encontraron candles para {$this->symbol} ({$session->timeframe}) "
                    . "en el rango {$session->date_from->toDateString()} – {$session->date_to->toDateString()}. "
                    . "Posibles causas: mercado cerrado, festivo, token de Schwab inválido o símbolo incorrecto."
                );
                Cache::forget("backtest:{$this->sessionId}:done");
                return;
            }

            $raw = $market->toRawArray($candleModels);

            // --- 2. Compute indicators ---
            $enriched = $indicators->compute($raw, $cfg);

            $session->increment('total_candles', count($enriched));

            // --- 3. Detect signals ---
            $signals = $strategy->detect($enriched, $cfg);
            $session->increment('total_signals', count($signals));

            Log::info("RunBacktestJob: {$this->symbol} → " . count($signals) . " signals");

            // --- 4. Simulate each trade ---
            $tradesToInsert = [];

            foreach ($signals as $signal) {
                $trade = $simulator->simulate($signal, $enriched, $cfg);

                $tradesToInsert[] = [
                    'backtest_session_id' => $this->sessionId,
                    'symbol'              => $this->symbol,
                    'direction'           => $trade['direction'],
                    'pullback_time'       => $trade['pullback_time'],
                    'pullback_open'       => $trade['pullback_open'],
                    'pullback_high'       => $trade['pullback_high'],
                    'pullback_low'        => $trade['pullback_low'],
                    'pullback_close'      => $trade['pullback_close'],
                    'confirm_time'        => $trade['confirm_time'],
                    'confirm_open'        => $trade['confirm_open'],
                    'confirm_high'        => $trade['confirm_high'],
                    'confirm_low'         => $trade['confirm_low'],
                    'confirm_close'       => $trade['confirm_close'],
                    'entry_time'          => $trade['entry_time'],
                    'entry_price'         => $trade['entry_price'],
                    'ema21'               => $trade['ema21'],
                    'ema50'               => $trade['ema50'],
                    'ema100'              => $trade['ema100'],
                    'min_distance'        => $trade['min_distance'],
                    'dist_ema21_ema50'    => $trade['dist_ema21_ema50'],
                    'dist_ema50_ema100'   => $trade['dist_ema50_ema100'],
                    'rsi'                 => $trade['rsi'],
                    'atr'                 => $trade['atr'],
                    'bb_upper'            => $trade['bb_upper'],
                    'bb_middle'           => $trade['bb_middle'],
                    'bb_lower'            => $trade['bb_lower'],
                    'volume'              => $trade['volume'],
                    'rel_volume'          => $trade['rel_volume'],
                    'stop_loss'           => $trade['stop_loss'],
                    'take_profit_1'       => $trade['take_profit_1'],
                    'take_profit_2'       => $trade['take_profit_2'],
                    'take_profit_3'       => $trade['take_profit_3'],
                    'exit_price'          => $trade['exit_price'],
                    'exit_time'           => $trade['exit_time'],
                    'exit_reason'         => $trade['exit_reason'],
                    'result'              => $trade['result'],
                    'pnl_points'          => $trade['pnl_points'],
                    'pnl_pct'             => $trade['pnl_pct'],
                    'max_favorable_excursion' => $trade['max_favorable_excursion'],
                    'max_adverse_excursion'   => $trade['max_adverse_excursion'],
                    'r_multiple'          => $trade['r_multiple'],
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ];
            }

            if (!empty($tradesToInsert)) {
                foreach (array_chunk($tradesToInsert, 200) as $chunk) {
                    BacktestTrade::insert($chunk);
                }
                $session->increment('total_trades', count($tradesToInsert));
            }

            // --- 5. Mark this symbol done & update progress ---
            $allSymbols  = $session->symbols;
            $totalSyms   = count($allSymbols);
            $cacheKey    = "backtest:{$this->sessionId}:done";
            $done        = Cache::get($cacheKey, []);
            $done[]      = $this->symbol;
            $done        = array_unique($done);
            Cache::put($cacheKey, $done, now()->addDay());

            $doneCount = count($done);
            $pct       = $totalSyms > 0 ? (int) round($doneCount / $totalSyms * 90) : 90;

            $session->update([
                'progress'       => $pct,
                'progress_label' => "Completado {$this->symbol} ({$doneCount}/{$totalSyms})",
            ]);

            // --- 6. Finalize when all symbols are processed ---
            if ($doneCount >= $totalSyms) {
                Cache::forget($cacheKey);
                $this->computeAndMarkCompleted($session);
            }

        } catch (\Throwable $e) {
            Log::error("RunBacktestJob failed for {$this->symbol}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            $session->markFailed("Backtest failed for {$this->symbol}: " . $e->getMessage());
        }
    }

    private function computeAndMarkCompleted(BacktestSession $session): void
    {
        $session->refresh();

        $trades = BacktestTrade::where('backtest_session_id', $session->id)->get();

        $winners   = $trades->where('result', 'win');
        $losers    = $trades->where('result', 'loss');
        $total     = $trades->count();
        $winCount  = $winners->count();
        $lossCount = $losers->count();

        $grossWin  = $winners->sum('pnl_points');
        $grossLoss = abs($losers->sum('pnl_points'));
        $profitFactor = $grossLoss > 0 ? round($grossWin / $grossLoss, 4) : null;

        // Max drawdown (running sum of pnl_points)
        $maxDd = 0.0;
        $peak  = 0.0;
        $equity = 0.0;
        foreach ($trades->sortBy('entry_time') as $t) {
            $equity += (float) $t->pnl_points;
            if ($equity > $peak) $peak = $equity;
            $dd = $peak - $equity;
            if ($dd > $maxDd) $maxDd = $dd;
        }

        // Best/worst hour (by average pnl)
        $byHour = $trades->groupBy(fn ($t) => $t->entry_time?->format('H'));
        $hourAvg = $byHour->map(fn ($g) => $g->avg('pnl_points'))->toArray();
        arsort($hourAvg);
        $bestHour  = !empty($hourAvg) ? array_key_first($hourAvg) . ':00' : null;
        krsort($hourAvg);
        $worstHour = !empty($hourAvg) ? array_key_first($hourAvg) . ':00' : null;

        $session->markCompleted([
            'total_trades'     => $total,
            'winning_trades'   => $winCount,
            'losing_trades'    => $lossCount,
            'breakeven_trades' => $trades->where('result', 'breakeven')->count(),
            'win_rate'         => $total > 0 ? round($winCount / $total * 100, 2) : null,
            'profit_factor'    => $profitFactor,
            'total_pnl_points' => round($trades->sum('pnl_points'), 4),
            'total_pnl_pct'    => round($trades->avg('pnl_pct') ?? 0, 4),
            'max_drawdown'     => round($maxDd, 4),
            'avg_winner_pts'   => $winCount  > 0 ? round($winners->avg('pnl_points'), 4) : null,
            'avg_loser_pts'    => $lossCount > 0 ? round($losers->avg('pnl_points'), 4)  : null,
            'best_hour'        => $bestHour,
            'worst_hour'       => $worstHour,
        ]);
    }

}

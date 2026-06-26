<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\MarketDataServiceInterface;
use App\Models\StrategyLabSession;
use App\Models\StrategyLabTrade;
use App\Services\IndicatorService;
use App\Services\TradeSimulatorService;
use App\Strategies\StrategyRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RunStrategyLabJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries   = 1;

    public function __construct(
        public readonly int    $sessionId,
        public readonly string $symbol,
    ) {}

    public function handle(
        MarketDataServiceInterface $market,
        IndicatorService           $indicators,
        TradeSimulatorService      $simulator,
    ): void {
        Log::info("RunStrategyLabJob: session={$this->sessionId} symbol={$this->symbol}");

        $session = StrategyLabSession::find($this->sessionId);
        if (!$session) return;

        try {
            $session->update([
                'status'         => 'running',
                'progress_label' => "Analysing {$this->symbol}…",
            ]);

            $cfg      = $session->params ?? [];
            $strategy = StrategyRegistry::resolve($session->strategy_key);

            // 1. Load candles
            $candleModels = $market->getCandles(
                $this->symbol,
                $session->timeframe,
                $session->date_from->toDateString(),
                $session->date_to->toDateString(),
            );

            if ($candleModels->isEmpty()) {
                $session->markFailed("No candles for {$this->symbol} ({$session->timeframe}) "
                    . "{$session->date_from->toDateString()} – {$session->date_to->toDateString()}");
                return;
            }

            $raw = $market->toRawArray($candleModels);

            // 2. Compute indicators
            $enriched = $indicators->compute($raw, $cfg);
            $session->increment('total_candles', count($enriched));

            // 3. Detect signals via the pluggable strategy
            $signals = $strategy->detect($enriched, $cfg);
            $session->increment('total_signals', count($signals));

            Log::info("RunStrategyLabJob: {$this->symbol} → " . count($signals) . " signals");

            // 4. Simulate trades bar-by-bar
            $rows = [];
            foreach ($signals as $signal) {
                $trade = $simulator->simulate($signal, $enriched, $cfg);

                // Core fields always present; extras go into signal_data JSON
                $coreKeys = [
                    'direction', 'entry_time', 'entry_price',
                    'stop_loss', 'take_profit_1', 'take_profit_2', 'take_profit_3',
                    'exit_price', 'exit_time', 'exit_reason', 'result',
                    'pnl_points', 'pnl_pct', 'max_favorable_excursion',
                    'max_adverse_excursion', 'r_multiple',
                ];

                $signalData = array_diff_key($trade, array_flip($coreKeys));

                $rows[] = [
                    'strategy_lab_session_id' => $this->sessionId,
                    'symbol'                  => $this->symbol,
                    'direction'               => $trade['direction'],
                    'entry_time'              => $trade['entry_time'],
                    'entry_price'             => $trade['entry_price'],
                    'stop_loss'               => $trade['stop_loss'],
                    'take_profit_1'           => $trade['take_profit_1'],
                    'take_profit_2'           => $trade['take_profit_2'],
                    'take_profit_3'           => $trade['take_profit_3'],
                    'exit_price'              => $trade['exit_price'],
                    'exit_time'               => $trade['exit_time'],
                    'exit_reason'             => $trade['exit_reason'],
                    'result'                  => $trade['result'],
                    'pnl_points'              => $trade['pnl_points'],
                    'pnl_pct'                 => $trade['pnl_pct'],
                    'max_favorable_excursion' => $trade['max_favorable_excursion'],
                    'max_adverse_excursion'   => $trade['max_adverse_excursion'],
                    'r_multiple'              => $trade['r_multiple'],
                    'signal_data'             => json_encode($signalData),
                    'created_at'              => now(),
                    'updated_at'              => now(),
                ];
            }

            if (!empty($rows)) {
                foreach (array_chunk($rows, 200) as $chunk) {
                    StrategyLabTrade::insert($chunk);
                }
                $session->increment('total_trades', count($rows));
            }

            // 5. Track progress across multiple symbols
            $allSymbols = $session->symbols;
            $totalSyms  = count($allSymbols);
            $cacheKey   = "lab:{$this->sessionId}:done";
            $done       = Cache::get($cacheKey, []);
            $done[]     = $this->symbol;
            $done       = array_unique($done);
            Cache::put($cacheKey, $done, now()->addDay());

            $doneCount = count($done);
            $pct       = $totalSyms > 0 ? (int) round($doneCount / $totalSyms * 90) : 90;

            $session->update([
                'progress'       => $pct,
                'progress_label' => "Done {$this->symbol} ({$doneCount}/{$totalSyms})",
            ]);

            // 6. Finalize when all symbols done
            if ($doneCount >= $totalSyms) {
                Cache::forget($cacheKey);
                $this->finalize($session);
            }

        } catch (\Throwable $e) {
            Log::error("RunStrategyLabJob failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $session->markFailed("Backtest failed for {$this->symbol}: " . $e->getMessage());
        }
    }

    private function finalize(StrategyLabSession $session): void
    {
        $session->refresh();
        $trades = StrategyLabTrade::where('strategy_lab_session_id', $session->id)->get();

        $winners   = $trades->where('result', 'win');
        $losers    = $trades->where('result', 'loss');
        $total     = $trades->count();
        $winCount  = $winners->count();
        $lossCount = $losers->count();

        $grossWin  = $winners->sum('pnl_points');
        $grossLoss = abs($losers->sum('pnl_points'));
        $pf        = $grossLoss > 0 ? round($grossWin / $grossLoss, 4) : null;

        // Max drawdown
        $maxDd = 0.0; $peak = 0.0; $equity = 0.0;
        foreach ($trades->sortBy('entry_time') as $t) {
            $equity += (float) $t->pnl_points;
            if ($equity > $peak) $peak = $equity;
            $dd = $peak - $equity;
            if ($dd > $maxDd) $maxDd = $dd;
        }

        // Best/worst hour
        $byHour  = $trades->groupBy(fn ($t) => $t->entry_time?->format('H'));
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
            'profit_factor'    => $pf,
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

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BacktestSession;
use App\Models\BacktestTrade;

class AnalyzeGridSearch extends Command
{
    protected $signature = 'backtest:analyze-grid
                            {--name=Grid Search : Filter by session name pattern}
                            {--top=5 : Number of top results to show per metric}';

    protected $description = 'Analyze grid search results and show best performers by metric';

    public function handle(): int
    {
        $namePattern = $this->option('name');
        $top = (int) $this->option('top');

        $this->info('📊 Grid Search Analysis');
        $this->newLine();

        // Get all completed sessions matching the pattern
        $sessions = BacktestSession::where('name', 'like', "%{$namePattern}%")
            ->where('status', 'completed')
            ->with('strategy')
            ->get();

        if ($sessions->isEmpty()) {
            $this->warn('No completed sessions found matching pattern: ' . $namePattern);
            return self::FAILURE;
        }

        $this->line("  Found {$sessions->count()} completed sessions");
        $this->newLine();

        // Calculate metrics for each session
        $results = [];
        foreach ($sessions as $session) {
            $trades = BacktestTrade::where('backtest_session_id', $session->id)->get();

            if ($trades->isEmpty()) {
                continue;
            }

            $winners = $trades->where('pnl', '>', 0);
            $losers = $trades->where('pnl', '<', 0);

            $totalPnl = $trades->sum('pnl');
            $avgWinner = $winners->avg('pnl') ?? 0;
            $avgLoser = $losers->avg('pnl') ?? 0;
            $winRate = $winners->count() / $trades->count() * 100;
            $profitFactor = abs($winners->sum('pnl') / ($losers->sum('pnl') ?: 1));

            // Calculate max drawdown from equity curve
            $equity = 0;
            $peak = 0;
            $maxDrawdown = 0;
            foreach ($trades->sortBy('entry_time') as $trade) {
                $equity += $trade->pnl;
                $peak = max($peak, $equity);
                $dd = ($peak - $equity) / ($peak ?: 1) * 100;
                $maxDrawdown = max($maxDrawdown, $dd);
            }

            $results[] = [
                'session' => $session,
                'trades' => $trades->count(),
                'total_pnl' => $totalPnl,
                'win_rate' => $winRate,
                'profit_factor' => $profitFactor,
                'avg_winner' => $avgWinner,
                'avg_loser' => $avgLoser,
                'avg_winner_gt_avg_loser' => $avgWinner > abs($avgLoser),
                'max_drawdown' => $maxDrawdown,
                'params' => [
                    'min_distance_pct' => $session->strategy->min_distance_pct,
                    'max_bars_after_pullback' => $session->strategy->max_bars_after_pullback,
                    'stop_atr_mult' => $session->strategy->stop_atr_mult,
                    'tp1_value' => $session->strategy->tp1_value,
                ],
            ];
        }

        if (empty($results)) {
            $this->warn('No sessions with trades found');
            return self::FAILURE;
        }

        // Sort by different metrics
        $this->line('🏆 Top performers by metric:');
        $this->newLine();

        // Best profit factor
        $this->line('  <fg=green>Best Profit Factor:</>');
        $sorted = collect($results)->sortByDesc('profit_factor')->take($top);
        foreach ($sorted as $r) {
            $this->line(sprintf(
                "    PF: %.2f | Win: %.1f%% | PnL: $%.2f | %s",
                $r['profit_factor'],
                $r['win_rate'],
                $r['total_pnl'],
                $this->formatParams($r['params'])
            ));
        }
        $this->newLine();

        // Best win rate
        $this->line('  <fg=green>Best Win Rate:</>');
        $sorted = collect($results)->sortByDesc('win_rate')->take($top);
        foreach ($sorted as $r) {
            $this->line(sprintf(
                "    Win: %.1f%% | PF: %.2f | PnL: $%.2f | %s",
                $r['win_rate'],
                $r['profit_factor'],
                $r['total_pnl'],
                $this->formatParams($r['params'])
            ));
        }
        $this->newLine();

        // Best avg winner
        $this->line('  <fg=green>Best Avg Winner:</>');
        $sorted = collect($results)->sortByDesc('avg_winner')->take($top);
        foreach ($sorted as $r) {
            $this->line(sprintf(
                "    Avg Win: $%.2f | PF: %.2f | Win: %.1f%% | %s",
                $r['avg_winner'],
                $r['profit_factor'],
                $r['win_rate'],
                $this->formatParams($r['params'])
            ));
        }
        $this->newLine();

        // Avg winner > avg loser
        $this->line('  <fg=green>Avg Winner > Avg Loser:</>');
        $filtered = collect($results)->where('avg_winner_gt_avg_loser', true)
            ->sortByDesc('avg_winner')->take($top);
        foreach ($filtered as $r) {
            $this->line(sprintf(
                "    Avg Win: $%.2f > Avg Loss: $%.2f | PF: %.2f | %s",
                $r['avg_winner'],
                abs($r['avg_loser']),
                $r['profit_factor'],
                $this->formatParams($r['params'])
            ));
        }
        $this->newLine();

        // Best max drawdown (lowest)
        $this->line('  <fg=green>Best Max Drawdown (lowest):</>');
        $sorted = collect($results)->sortBy('max_drawdown')->take($top);
        foreach ($sorted as $r) {
            $this->line(sprintf(
                "    Max DD: %.2f%% | PnL: $%.2f | PF: %.2f | %s",
                $r['max_drawdown'],
                $r['total_pnl'],
                $r['profit_factor'],
                $this->formatParams($r['params'])
            ));
        }
        $this->newLine();

        // Best total PnL
        $this->line('  <fg=green>Best Total PnL:</>');
        $sorted = collect($results)->sortByDesc('total_pnl')->take($top);
        foreach ($sorted as $r) {
            $this->line(sprintf(
                "    PnL: $%.2f | PF: %.2f | Win: %.1f%% | DD: %.2f%% | %s",
                $r['total_pnl'],
                $r['profit_factor'],
                $r['win_rate'],
                $r['max_drawdown'],
                $this->formatParams($r['params'])
            ));
        }

        return self::SUCCESS;
    }

    private function formatParams(array $params): string
    {
        return sprintf(
            "dist:%.2f bars:%d stop:%.1f tp:%.1f",
            $params['min_distance_pct'],
            $params['max_bars_after_pullback'],
            $params['stop_atr_mult'],
            $params['tp1_value']
        );
    }
}

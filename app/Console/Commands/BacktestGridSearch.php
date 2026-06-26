<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BacktestGridSearch extends Command
{
    protected $signature = 'backtest:grid-search
                            {--run : Execute the grid search (create sessions and dispatch jobs)}
                            {--symbol=SPY : Symbol to test}
                            {--from=2024-01-01 : Start date}
                            {--to=2024-12-31 : End date}
                            {--tf=1m : Timeframe}';

    protected $description = 'Calculate or run a grid search of strategy parameter combinations';

    public function handle(): int
    {
        $this->info('📊 Grid Search Calculator — EMA Pullback Strategy');
        $this->newLine();

        // Parameter ranges for grid search (user-specified)
        $ranges = [
            // EMAs: fixed at 21, 50, 100 — not varied
            'min_distance_pct'        => [0.005, 0.01, 0.015, 0.02], // 0.5% - 2% for 1m
            'max_bars_after_pullback' => [2, 3],
            'stop_atr_mult'           => [1.0, 1.5, 2.0],
            'tp1_value'               => [0.5, 1.0, 1.5],
            'force_exit_time'         => ['15:00'], // 3:00 PM max
        ];

        $total = 1;
        foreach ($ranges as $param => $values) {
            $total *= count($values);
            $this->line(sprintf(
                "  %-25s %3d values: %s",
                $param,
                count($values),
                implode(', ', array_map(fn($v) => is_float($v) ? number_format($v, 2) : $v, $values))
            ));
        }

        $this->newLine();
        $this->line("  <fg=yellow>Total combinations: {$total}</>");
        $this->newLine();

        // Time estimates (1m timeframe = 5x more data than 5m)
        $this->line('⏱  Time estimates (per backtest, 1m timeframe):');
        $estimates = [
            '1 month  data (1m)' => 150,
            '3 months data (1m)' => 450,
            '6 months data (1m)' => 900,
            '1 year   data (1m)' => 1500,
            '5 years  data (1m)' => 7500,
        ];

        foreach ($estimates as $label => $seconds) {
            $totalSeconds = $total * $seconds;
            $hours = $totalSeconds / 3600;
            $days = $hours / 24;

            $this->line(sprintf(
                "  %-20s → %s",
                $label,
                $days >= 1
                    ? sprintf('%.1f days (%.1f hours)', $days, $hours)
                    : sprintf('%.1f hours (%.0f min)', $hours, $totalSeconds / 60)
            ));
        }

        $this->newLine();
        $this->line('💡 Parallel processing estimate (with 8 workers):');
        foreach ($estimates as $label => $seconds) {
            $totalSeconds = ($total * $seconds) / 8;
            $hours = $totalSeconds / 3600;
            $days = $hours / 24;

            $this->line(sprintf(
                "  %-20s → %s",
                $label,
                $days >= 1
                    ? sprintf('%.1f days (%.1f hours)', $days, $hours)
                    : sprintf('%.1f hours (%.0f min)', $hours, $totalSeconds / 60)
            ));
        }

        $this->newLine();
        $this->warn('⚠  This is a conservative grid. Full parameter space would be exponential.');
        $this->line('   To reduce: focus on 2-3 core parameters (EMAs + stop/TP).');

        // If --run flag, execute the grid search
        if ($this->option('run')) {
            $this->newLine();
            $this->info('🚀 Executing grid search...');
            $this->newLine();

            $symbol    = $this->option('symbol');
            $dateFrom  = $this->option('from');
            $dateTo    = $this->option('to');
            $timeframe = $this->option('tf');

            $this->line("  Symbol:    {$symbol}");
            $this->line("  Timeframe: {$timeframe}");
            $this->line("  Date range: {$dateFrom} → {$dateTo}");
            $this->newLine();

            // Generate all combinations
            $combinations = $this->generateCombinations($ranges);
            $this->line("  Generated {$total} parameter combinations");
            $this->newLine();

            // Create sessions for each combination and dispatch jobs
            $created = 0;
            foreach ($combinations as $params) {
                $strategy = \App\Models\StrategySetting::create(array_merge(
                    \App\Models\StrategySetting::defaultSettings(),
                    $params,
                    ['name' => "Grid Search #{$created}"]
                ));

                $session = \App\Models\BacktestSession::create([
                    'name'                => "Grid Search #{$created}",
                    'symbols'             => [$symbol],
                    'timeframe'           => $timeframe,
                    'date_from'           => $dateFrom,
                    'date_to'             => $dateTo,
                    'strategy_setting_id' => $strategy->id,
                    'status'              => 'pending',
                ]);

                // Dispatch candle import job for this session
                $job = \App\Jobs\CandleImporterJob::dispatch(
                    $session->id,
                    $symbol,
                    $timeframe,
                    $dateFrom,
                    $dateTo,
                );
                $this->line("  Dispatched job for session {$session->id}, queue: " . config('queue.default'));

                $created++;
                if ($created % 10 === 0) {
                    $this->line("  Created {$created} sessions + dispatched jobs...");
                }
            }

            $this->newLine();
            $this->line("  <fg=green>✓ Created {$created} backtest sessions</>");
            $this->line("  Dispatch candle import jobs with:");
            $this->line("    php artisan queue:work --queue=default --tries=1 --timeout=600");
        }

        return self::SUCCESS;
    }

    private function generateCombinations(array $ranges): array
    {
        $keys = array_keys($ranges);
        $values = array_values($ranges);

        $result = [[]];
        foreach ($values as $i => $valueArray) {
            $temp = [];
            foreach ($result as $item) {
                foreach ($valueArray as $value) {
                    $temp[] = array_merge($item, [$keys[$i] => $value]);
                }
            }
            $result = $temp;
        }

        return $result;
    }
}

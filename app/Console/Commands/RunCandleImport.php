<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\CandleImporterJob;
use App\Models\BacktestSession;
use App\Models\StrategySetting;
use Illuminate\Console\Command;

class RunCandleImport extends Command
{
    protected $signature = 'backtest:import-candles
                            {symbol   : Ticker symbol, e.g. SPY}
                            {from     : Start date YYYY-MM-DD}
                            {to       : End date YYYY-MM-DD}
                            {--tf=5m  : Timeframe (1m, 5m, 15m, 30m)}
                            {--sync   : Run synchronously instead of dispatching to queue}';

    protected $description = 'Import candles for a symbol/timeframe/date range (creates a throw-away BacktestSession for debug)';

    public function handle(): int
    {
        $symbol    = strtoupper($this->argument('symbol'));
        $dateFrom  = $this->argument('from');
        $dateTo    = $this->argument('to');
        $timeframe = $this->option('tf');
        $sync      = $this->option('sync');

        $this->info("📥 Importing candles for {$symbol} [{$timeframe}] {$dateFrom} → {$dateTo}");

        // Create a temporary strategy + session so the job has a valid session_id
        $strategy = StrategySetting::create(StrategySetting::defaultSettings());

        $session = BacktestSession::create([
            'name'                => "debug-import-{$symbol}-{$dateFrom}",
            'symbols'             => [$symbol],
            'timeframe'           => $timeframe,
            'date_from'           => $dateFrom,
            'date_to'             => $dateTo,
            'strategy_setting_id' => $strategy->id,
            'status'              => 'pending',
        ]);

        $this->line("  → Session ID: <comment>{$session->id}</comment>");

        if ($sync) {
            $this->line("  → Running <info>synchronously</info>…");
            $job = new CandleImporterJob($session->id, $symbol, $timeframe, $dateFrom, $dateTo);
            app()->call([$job, 'handle']);
        } else {
            CandleImporterJob::dispatch($session->id, $symbol, $timeframe, $dateFrom, $dateTo);
            $this->line("  → Job dispatched to queue. Check session #{$session->id} in the UI.");
        }

        $session->refresh();
        $this->newLine();
        $this->line("  Status : <comment>{$session->status}</comment>");

        if ($session->error_message) {
            $this->newLine();
            $this->line("<fg=yellow>🐛 Debug log:</>");
            $this->line($session->error_message);
        }

        return self::SUCCESS;
    }
}

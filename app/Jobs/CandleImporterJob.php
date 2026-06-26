<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\MarketDataServiceInterface;
use App\Models\BacktestSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CandleImporterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries   = 2;

    public function __construct(
        public readonly int    $sessionId,
        public readonly string $symbol,
        public readonly string $timeframe,
        public readonly string $dateFrom,
        public readonly string $dateTo,
    ) {}

    public function handle(MarketDataServiceInterface $market): void
    {
        $session = BacktestSession::find($this->sessionId);
        if (!$session) return;

        try {
            $session->update([
                'status'         => 'importing',
                'progress_label' => "Importing {$this->symbol} candles…",
            ]);

            $candles = $market->getCandles(
                $this->symbol,
                $this->timeframe,
                $this->dateFrom,
                $this->dateTo,
            );

            $debugLog = $market->getLastFetchLog();
            $debugJson = json_encode($debugLog, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            if ($candles->isEmpty()) {
                $session->update([
                    'status'        => 'failed',
                    'error_message' => "[DEBUG] No candles for {$this->symbol} ({$this->timeframe}) "
                        . "{$this->dateFrom} – {$this->dateTo}\n\n{$debugJson}",
                ]);
                return;
            }

            $session->update(['error_message' => "[DEBUG]\n{$debugJson}"]);
            Log::info("CandleImporterJob: {$candles->count()} candles for {$this->symbol}");

            // Dispatch the backtest runner for this symbol
            Log::info("Dispatching RunBacktestJob for session {$this->sessionId}, symbol {$this->symbol}");
            try {
                RunBacktestJob::dispatch($this->sessionId, $this->symbol);
                Log::info("RunBacktestJob dispatched successfully, queue connection: " . config('queue.default'));
            } catch (\Throwable $e) {
                Log::error("Failed to dispatch RunBacktestJob: " . $e->getMessage());
            }

        } catch (\Throwable $e) {
            Log::error("CandleImporterJob failed for {$this->symbol}: " . $e->getMessage());
            $session->markFailed("Import failed for {$this->symbol}: " . $e->getMessage());
        }
    }
}

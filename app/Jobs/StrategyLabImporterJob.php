<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\MarketDataServiceInterface;
use App\Models\StrategyLabSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class StrategyLabImporterJob implements ShouldQueue
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
        $session = StrategyLabSession::find($this->sessionId);
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

            if ($candles->isEmpty()) {
                $debugLog  = $market->getLastFetchLog();
                $debugJson = json_encode($debugLog, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                $session->markFailed("[No candles] {$this->symbol} ({$this->timeframe}) "
                    . "{$this->dateFrom} – {$this->dateTo}\n\n{$debugJson}");
                return;
            }

            Log::info("StrategyLabImporterJob: {$candles->count()} candles for {$this->symbol}");

            RunStrategyLabJob::dispatch($this->sessionId, $this->symbol);

        } catch (\Throwable $e) {
            Log::error("StrategyLabImporterJob failed for {$this->symbol}: " . $e->getMessage());
            $session->markFailed("Import failed for {$this->symbol}: " . $e->getMessage());
        }
    }
}

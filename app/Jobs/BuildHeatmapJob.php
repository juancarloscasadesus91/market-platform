<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Symbol;
use App\Services\HeatmapBuilderService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BuildHeatmapJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    public function __construct(
        public readonly array $tickers,
        public readonly ?Carbon $expirationDate = null,
    ) {}

    public function handle(HeatmapBuilderService $heatmapService): void
    {
        try {
            $heatmapData = $heatmapService->buildMultiSymbolHeatmap(
                $this->tickers,
                $this->expirationDate
            );

            $cacheKey = 'heatmap:' . implode(',', $this->tickers) . ':' . 
                        ($this->expirationDate?->format('Y-m-d') ?? 'next');

            Cache::put($cacheKey, $heatmapData, now()->addMinutes(15));

            Log::info("Heatmap built for symbols: " . implode(', ', $this->tickers));
        } catch (\Exception $e) {
            Log::error("Failed to build heatmap: {$e->getMessage()}");
            throw $e;
        }
    }
}

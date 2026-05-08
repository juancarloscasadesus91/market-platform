<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Symbol;
use App\Services\SchwabOptionChainService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RefreshOptionChainJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 180;

    public function __construct(
        public readonly string $ticker,
        public readonly ?Carbon $expirationDate = null,
    ) {}

    public function handle(SchwabOptionChainService $optionChainService): void
    {
        $symbol = Symbol::where('ticker', $this->ticker)->first();

        if (!$symbol) {
            Log::warning("Symbol not found: {$this->ticker}");
            return;
        }

        try {
            $chainData = $optionChainService->getOptionChain(
                $this->ticker,
                $this->expirationDate
            );

            if ($chainData) {
                $optionChainService->storeOptionChain($symbol, $chainData);
                Log::info("Option chain refreshed for {$this->ticker}");
            }
        } catch (\Exception $e) {
            Log::error("Failed to refresh option chain for {$this->ticker}: {$e->getMessage()}");
            throw $e;
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Symbol;
use App\Services\SchwabQuoteService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RefreshQuotesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public readonly ?array $tickers = null,
    ) {}

    public function handle(SchwabQuoteService $quoteService): void
    {
        $symbols = $this->tickers 
            ? Symbol::whereIn('ticker', $this->tickers)->get()
            : Symbol::active()->get();

        foreach ($symbols as $symbol) {
            try {
                $quoteData = $quoteService->getQuote($symbol->ticker);

                if ($quoteData) {
                    $quoteService->storeQuote($symbol, $quoteData);
                    Log::info("Quote refreshed for {$symbol->ticker}");
                }
            } catch (\Exception $e) {
                Log::error("Failed to refresh quote for {$symbol->ticker}: {$e->getMessage()}");
            }
        }
    }
}

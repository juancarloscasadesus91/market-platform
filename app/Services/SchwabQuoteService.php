<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\QuoteData;
use App\Models\Quote;
use App\Models\Symbol;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SchwabQuoteService
{
    public function __construct(
        private readonly SchwabAuthService $authService,
        private readonly string $baseUrl,
    ) {}

    /**
     * Fetch quote for a single symbol
     */
    public function getQuote(string $ticker): ?QuoteData
    {
        $token = $this->authService->getAccessToken();

        if (!$token) {
            return $this->getFakeQuote($ticker);
        }

        $response = Http::withToken($token)
            ->get("{$this->baseUrl}/marketdata/v1/quotes/{$ticker}");

        if ($response->successful()) {
            return $this->transformQuoteData($ticker, $response->json());
        }

        return $this->getFakeQuote($ticker);
    }

    /**
     * Fetch quotes for multiple symbols
     */
    public function getQuotes(array $tickers): Collection
    {
        return collect($tickers)->map(fn ($ticker) => $this->getQuote($ticker));
    }

    /**
     * Store quote in database
     */
    public function storeQuote(Symbol $symbol, QuoteData $quoteData): Quote
    {
        return $symbol->quotes()->create($quoteData->toArray());
    }

    /**
     * Get cached quote or fetch fresh
     */
    public function getCachedQuote(string $ticker, int $ttl = 60): ?QuoteData
    {
        return Cache::remember(
            "quote:{$ticker}",
            now()->addSeconds($ttl),
            fn () => $this->getQuote($ticker)
        );
    }

    /**
     * Transform API response to QuoteData DTO
     */
    private function transformQuoteData(string $ticker, array $data): QuoteData
    {
        $quote = $data[$ticker]['quote'] ?? $data;

        return new QuoteData(
            ticker: $ticker,
            lastPrice: (float) ($quote['lastPrice'] ?? 0),
            bid: isset($quote['bidPrice']) ? (float) $quote['bidPrice'] : null,
            ask: isset($quote['askPrice']) ? (float) $quote['askPrice'] : null,
            open: isset($quote['openPrice']) ? (float) $quote['openPrice'] : null,
            high: isset($quote['highPrice']) ? (float) $quote['highPrice'] : null,
            low: isset($quote['lowPrice']) ? (float) $quote['lowPrice'] : null,
            close: isset($quote['closePrice']) ? (float) $quote['closePrice'] : null,
            change: isset($quote['netChange']) ? (float) $quote['netChange'] : null,
            changePercent: isset($quote['netPercentChange']) ? (float) $quote['netPercentChange'] : null,
            volume: isset($quote['totalVolume']) ? (int) $quote['totalVolume'] : null,
            avgVolume: isset($quote['avgVolume']) ? (int) $quote['avgVolume'] : null,
            marketCap: isset($quote['marketCap']) ? (float) $quote['marketCap'] : null,
            peRatio: isset($quote['peRatio']) ? (float) $quote['peRatio'] : null,
            week52High: isset($quote['52WeekHigh']) ? (float) $quote['52WeekHigh'] : null,
            week52Low: isset($quote['52WeekLow']) ? (float) $quote['52WeekLow'] : null,
            quoteTime: isset($quote['quoteTime']) ? Carbon::parse($quote['quoteTime']) : now(),
        );
    }

    /**
     * Generate fake quote data for development
     */
    private function getFakeQuote(string $ticker): QuoteData
    {
        $basePrice = match($ticker) {
            'SPX' => 5200.00,
            'SPY' => 520.00,
            'QQQ' => 450.00,
            'NVDA' => 875.00,
            'AAPL' => 185.00,
            'TSLA' => 245.00,
            default => 100.00,
        };

        $change = $basePrice * (rand(-300, 300) / 10000);
        $changePercent = ($change / $basePrice) * 100;

        return new QuoteData(
            ticker: $ticker,
            lastPrice: round($basePrice + $change, 2),
            bid: round($basePrice + $change - 0.05, 2),
            ask: round($basePrice + $change + 0.05, 2),
            open: round($basePrice + (rand(-100, 100) / 100), 2),
            high: round($basePrice + (rand(0, 200) / 100), 2),
            low: round($basePrice - (rand(0, 200) / 100), 2),
            close: round($basePrice, 2),
            change: round($change, 2),
            changePercent: round($changePercent, 2),
            volume: rand(1000000, 50000000),
            avgVolume: rand(5000000, 30000000),
            marketCap: $basePrice * rand(100000000, 3000000000),
            peRatio: rand(15, 35) + (rand(0, 99) / 100),
            week52High: round($basePrice * 1.25, 2),
            week52Low: round($basePrice * 0.75, 2),
            quoteTime: now(),
        );
    }
}

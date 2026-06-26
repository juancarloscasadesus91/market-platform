<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AlpacaTradingService
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $apiSecret,
        private readonly string $baseUrl,
        private readonly string $dataBaseUrl,
        public readonly string $mode = 'paper',
    ) {}

    public static function make(string $mode = 'paper'): self
    {
        $mode = $mode === 'live' ? 'live' : 'paper';
        $prefix = "services.alpaca.{$mode}";

        return new self(
            apiKey: (string) config("{$prefix}.key", ''),
            apiSecret: (string) config("{$prefix}.secret", ''),
            baseUrl: self::normalizeBaseUrl((string) config("{$prefix}.base_url")),
            dataBaseUrl: self::normalizeBaseUrl((string) config('services.alpaca.data_base_url', 'https://data.alpaca.markets')),
            mode: $mode,
        );
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->apiSecret !== '' && $this->baseUrl !== '';
    }

    public function account(): array
    {
        return $this->request('get', '/v2/account')->json() ?? [];
    }

    public function positions(): array
    {
        return $this->request('get', '/v2/positions')->json() ?? [];
    }

    public function openOrders(): array
    {
        return $this->orders([
            'status' => 'open',
            'limit' => 100,
            'nested' => 'true',
            'direction' => 'desc',
        ]);
    }

    public function latestOrders(int $limit = 25): array
    {
        return $this->orders([
            'status' => 'all',
            'limit' => max(1, min(100, $limit)),
            'nested' => 'true',
            'direction' => 'desc',
        ]);
    }

    public function orders(array $params = []): array
    {
        return $this->request('get', '/v2/orders', $params)->json() ?? [];
    }

    public function order(string $orderId): array
    {
        return $this->request('get', "/v2/orders/{$orderId}")->json() ?? [];
    }

    public function submitOrder(array $payload): array
    {
        return $this->request('post', '/v2/orders', $payload)->json() ?? [];
    }

    public function optionContracts(array $params): array
    {
        return $this->request('get', '/v2/options/contracts', $params)->json() ?? [];
    }

    public function latestOptionQuotes(array $symbols, ?string $feed = null): array
    {
        $symbols = array_values(array_filter(array_unique($symbols)));
        if (empty($symbols)) {
            return [];
        }

        $response = $this->dataRequest('get', '/v1beta1/options/quotes/latest', [
            'symbols' => implode(',', $symbols),
            'feed' => $feed ?? (string) config('services.alpaca.options_feed', 'indicative'),
        ], throw: false);

        return $response->successful() ? ($response->json('quotes') ?? []) : [];
    }

    public function latestStockTrade(string $symbol, ?string $feed = null): ?array
    {
        $response = $this->dataRequest('get', '/v2/stocks/' . strtoupper($symbol) . '/trades/latest', [
            'feed' => $feed ?? (string) config('services.alpaca.market_data_feed', 'iex'),
        ], throw: false);

        return $response->successful() ? ($response->json('trade') ?? null) : null;
    }

    public function latestStockQuote(string $symbol, ?string $feed = null): ?array
    {
        $response = $this->dataRequest('get', '/v2/stocks/' . strtoupper($symbol) . '/quotes/latest', [
            'feed' => $feed ?? (string) config('services.alpaca.market_data_feed', 'iex'),
        ], throw: false);

        return $response->successful() ? ($response->json('quote') ?? null) : null;
    }

    public function cancelOrder(string $orderId): bool
    {
        $response = $this->request('delete', "/v2/orders/{$orderId}", throw: false);

        return in_array($response->status(), [200, 204], true);
    }

    public function closePosition(string $symbol, ?float $qty = null, ?float $percentage = null): array
    {
        $params = [];
        if ($qty !== null && $qty > 0) {
            $params['qty'] = $this->formatNumber($qty);
        } elseif ($percentage !== null && $percentage > 0) {
            $params['percentage'] = $this->formatNumber(min(100, $percentage));
        }

        return $this->request('delete', '/v2/positions/' . strtoupper($symbol), $params)->json() ?? [];
    }

    private function request(string $method, string $path, array $data = [], bool $throw = true): Response
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException("Alpaca {$this->mode} API keys are not configured.");
        }

        $request = Http::withHeaders([
            'APCA-API-KEY-ID' => $this->apiKey,
            'APCA-API-SECRET-KEY' => $this->apiSecret,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->timeout(15);

        $url = $this->baseUrl . $path;
        $response = match (strtolower($method)) {
            'get' => $request->get($url, $data),
            'post' => $request->post($url, $data),
            'delete' => $request->delete($url, $data),
            default => throw new \InvalidArgumentException("Unsupported Alpaca method [{$method}]."),
        };

        if (!$response->successful()) {
            Log::warning('Alpaca trading API request failed', [
                'mode' => $this->mode,
                'method' => strtoupper($method),
                'path' => $path,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if ($throw) {
                $message = $response->json('message')
                    ?? $response->json('error')
                    ?? $response->body()
                    ?: 'Alpaca request failed.';

                throw new \RuntimeException($message);
            }
        }

        return $response;
    }

    private function dataRequest(string $method, string $path, array $data = [], bool $throw = true): Response
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException("Alpaca {$this->mode} API keys are not configured.");
        }

        $request = Http::withHeaders([
            'APCA-API-KEY-ID' => $this->apiKey,
            'APCA-API-SECRET-KEY' => $this->apiSecret,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->timeout(15);

        $url = $this->dataBaseUrl . $path;
        $response = match (strtolower($method)) {
            'get' => $request->get($url, $data),
            default => throw new \InvalidArgumentException("Unsupported Alpaca data method [{$method}]."),
        };

        if (!$response->successful()) {
            Log::warning('Alpaca market data API request failed', [
                'mode' => $this->mode,
                'method' => strtoupper($method),
                'path' => $path,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if ($throw) {
                $message = $response->json('message')
                    ?? $response->json('error')
                    ?? $response->body()
                    ?: 'Alpaca market data request failed.';

                throw new \RuntimeException($message);
            }
        }

        return $response;
    }

    private function formatNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 8, '.', ''), '0'), '.');
    }

    private static function normalizeBaseUrl(string $baseUrl): string
    {
        $baseUrl = rtrim($baseUrl, '/');

        return str_ends_with($baseUrl, '/v2')
            ? substr($baseUrl, 0, -3)
            : $baseUrl;
    }
}

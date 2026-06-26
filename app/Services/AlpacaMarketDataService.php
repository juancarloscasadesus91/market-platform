<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\MarketDataServiceInterface;
use App\Models\Candle;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AlpacaMarketDataService implements MarketDataServiceInterface
{
    public array $lastFetchLog = [];

    private const BASE      = 'https://data.alpaca.markets';
    private const BAR_LIMIT = 10_000;

    /** Alpaca timeframe strings */
    private const TIMEFRAME_MAP = [
        '1m'  => '1Min',
        '5m'  => '5Min',
        '15m' => '15Min',
        '30m' => '30Min',
        '1h'  => '1Hour',
        '1d'  => '1Day',
    ];

    /** Days per chunk (keep requests under BAR_LIMIT bars) */
    private const CHUNK_DAYS = [
        '1m'  => 20,
        '5m'  => 90,
        '15m' => 180,
        '30m' => 365,
        '1h'  => 365,
        '1d'  => 1825,
    ];

    public function __construct(
        private readonly string $apiKey,
        private readonly string $apiSecret,
    ) {}

    public function getCandles(
        string $symbol,
        string $timeframe,
        string $dateFrom,
        string $dateTo,
    ): Collection {
        $from = Carbon::parse($dateFrom)->startOfDay();
        $to   = Carbon::parse($dateTo)->endOfDay();

        $cached = Candle::where('symbol', $symbol)
            ->where('timeframe', $timeframe)
            ->whereBetween('opens_at', [$from, $to])
            ->orderBy('opens_at')
            ->get();

        $lastCached  = $cached->last();
        $fullyCovers = $lastCached
            && $lastCached->opens_at->greaterThanOrEqualTo($to->copy()->subDays(5));

        if ($cached->isNotEmpty() && $fullyCovers) {
            Log::info("Alpaca: using {$cached->count()} cached candles for {$symbol} {$timeframe}");
            $this->lastFetchLog = ['source' => 'cache', 'candles' => $cached->count()];
            return $cached;
        }

        $fetchFrom = $lastCached
            ? $lastCached->opens_at->copy()->addDay()->startOfDay()
            : $from;

        if ($fetchFrom->lte($to)) {
            $this->fetchAndStore($symbol, $timeframe, $fetchFrom, $to);
        }

        return Candle::where('symbol', $symbol)
            ->where('timeframe', $timeframe)
            ->whereBetween('opens_at', [$from, $to])
            ->orderBy('opens_at')
            ->get();
    }

    public function toRawArray(Collection $candles): array
    {
        return $candles->map(fn ($c) => [
            'time'   => $c->opens_at->getTimestamp(),
            'dt'     => $c->opens_at->toDateTimeString(),
            'open'   => $c->open,
            'high'   => $c->high,
            'low'    => $c->low,
            'close'  => $c->close,
            'volume' => $c->volume,
        ])->values()->toArray();
    }

    public function getLastFetchLog(): array
    {
        return $this->lastFetchLog;
    }

    public function fetchAndStore(
        string $symbol,
        string $timeframe,
        Carbon $from,
        Carbon $to,
    ): Collection {
        $alpacaTf  = self::TIMEFRAME_MAP[$timeframe]  ?? '5Min';
        $chunkDays = self::CHUNK_DAYS[$timeframe]      ?? 90;

        $cursor    = $from->copy()->startOfDay();
        $totalRows = 0;

        $this->lastFetchLog = [
            'provider'       => 'Alpaca',
            'endpoint'       => self::BASE . '/v2/stocks/{symbol}/bars',
            'symbol'         => $symbol,
            'timeframe'      => $timeframe,
            'alpaca_tf'      => $alpacaTf,
            'requested_from' => $from->toDateString(),
            'requested_to'   => $to->toDateString(),
            'chunk_days'     => $chunkDays,
            'chunks'         => [],
        ];

        while ($cursor->lte($to)) {
            $chunkEnd = $cursor->copy()->addDays($chunkDays - 1)->endOfDay();
            if ($chunkEnd->gt($to)) {
                $chunkEnd = $to->copy()->endOfDay();
            }

            $chunkEntry = [
                'from'    => $cursor->toDateString(),
                'to'      => $chunkEnd->toDateString(),
                'http'    => null,
                'candles' => 0,
                'pages'   => 0,
                'error'   => null,
            ];

            $rows      = [];
            $pageToken = null;

            do {
                $params = [
                    'timeframe'  => $alpacaTf,
                    'start'      => $cursor->toIso8601String(),
                    'end'        => $chunkEnd->toIso8601String(),
                    'limit'      => self::BAR_LIMIT,
                    'adjustment' => 'split',
                    'feed'       => (string) config('services.alpaca.market_data_feed', 'iex'),
                ];

                if ($pageToken) {
                    $params['page_token'] = $pageToken;
                }

                $response = Http::withHeaders([
                    'APCA-API-KEY-ID'     => $this->apiKey,
                    'APCA-API-SECRET-KEY' => $this->apiSecret,
                ])->timeout(30)->get(self::BASE . "/v2/stocks/{$symbol}/bars", $params);

                $chunkEntry['http'] = $response->status();

                if (! $response->successful()) {
                    $chunkEntry['error'] = substr($response->body(), 0, 300);
                    Log::error("Alpaca bars error [{$cursor->toDateString()}–{$chunkEnd->toDateString()}]: "
                        . "{$response->status()} " . $response->body());
                    break;
                }

                $data      = $response->json();
                $bars      = $data['bars'] ?? [];
                $pageToken = $data['next_page_token'] ?? null;
                $now       = now();
                $chunkEntry['pages']++;

                foreach ($bars as $b) {
                    $rows[] = [
                        'symbol'     => $symbol,
                        'timeframe'  => $timeframe,
                        'opens_at'   => Carbon::parse($b['t'])->toDateTimeString(),
                        'open'       => $b['o'],
                        'high'       => $b['h'],
                        'low'        => $b['l'],
                        'close'      => $b['c'],
                        'volume'     => (int) ($b['v'] ?? 0),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            } while ($pageToken);

            if (! empty($rows)) {
                foreach (array_chunk($rows, 500) as $chunk) {
                    Candle::upsert($chunk, ['symbol', 'timeframe', 'opens_at'], [
                        'open', 'high', 'low', 'close', 'volume', 'updated_at',
                    ]);
                }
                $chunkEntry['candles'] = count($rows);
                $totalRows += count($rows);
                Log::info("Alpaca: {$symbol} {$timeframe} [{$cursor->toDateString()}–{$chunkEnd->toDateString()}] → " . count($rows) . " candles");
            } else {
                Log::info("Alpaca: {$symbol} {$timeframe} [{$cursor->toDateString()}–{$chunkEnd->toDateString()}] → 0 candles");
            }

            $this->lastFetchLog['chunks'][] = $chunkEntry;
            $cursor = $chunkEnd->copy()->addDay()->startOfDay();
        }

        $this->lastFetchLog['total_candles_fetched'] = $totalRows;
        $this->lastFetchLog['total_chunks']          = count($this->lastFetchLog['chunks']);

        return Candle::where('symbol', $symbol)
            ->where('timeframe', $timeframe)
            ->whereBetween('opens_at', [$from->startOfDay(), $to->endOfDay()])
            ->orderBy('opens_at')
            ->get();
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\MarketDataServiceInterface;
use App\Models\Candle;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SchwabMarketDataService implements MarketDataServiceInterface
{
    public array $lastFetchLog = [];
    private const BASE = 'https://api.schwabapi.com';

    /** Schwab frequencyType/frequency mapping */
    private const TIMEFRAME_MAP = [
        '1m'  => ['frequencyType' => 'minute', 'frequency' => 1],
        '5m'  => ['frequencyType' => 'minute', 'frequency' => 5],
        '15m' => ['frequencyType' => 'minute', 'frequency' => 15],
        '30m' => ['frequencyType' => 'minute', 'frequency' => 30],
        '1h'  => ['frequencyType' => 'minute', 'frequency' => 60],
        '1d'  => ['frequencyType' => 'daily',  'frequency' => 1],
    ];

    public function __construct(private readonly SchwabAuthService $auth) {}

    public function getLastFetchLog(): array
    {
        return $this->lastFetchLog;
    }

    /**
     * Get candles from DB cache or fetch from Schwab.
     * Returns an ordered Collection of Candle models.
     */
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
            Log::info("Backtest: using {$cached->count()} cached candles for {$symbol} {$timeframe}");
            return $cached;
        }

        // Fetch only the missing window (from the day after the last cached candle)
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

    /**
     * Fetch from Schwab API and persist to DB.
     *
     * Schwab silently truncates intraday responses when a request spans too many
     * bars (~2 500–3 000 for minute data).  We chunk the range into 20-calendar-day
     * windows so every request stays well under that limit.
     */
    public function fetchAndStore(
        string $symbol,
        string $timeframe,
        Carbon $from,
        Carbon $to,
    ): Collection {
        $token = 'I0.b2F1dGgyLmNkYy5zY2h3YWIuY29t.-3LSbQ56TLHzGEhIu_qp3_vuZcAdVVU7gXFXqDrrcgo@';

        if (!$token) {
            Log::warning("SchwabMarketDataService: no token available");
            return collect();
        }

        $map = self::TIMEFRAME_MAP[$timeframe] ?? self::TIMEFRAME_MAP['1m'];

        // Intraday data: Schwab caps responses; chunk into 20-day windows
        $chunkDays = in_array($timeframe, ['1m', '5m', '15m', '30m', '1h']) ? 20 : 365;

        $cursor    = $from->copy()->startOfDay();
        $totalRows = 0;
        $this->lastFetchLog = [
            'endpoint'     => self::BASE . '/marketdata/v1/pricehistory',
            'symbol'       => $symbol,
            'timeframe'    => $timeframe,
            'requested_from' => $from->toDateString(),
            'requested_to'   => $to->toDateString(),
            'chunk_days'   => $chunkDays,
            'token_present' => (bool) $token,
            'chunks'       => [],
        ];

        while ($cursor->lte($to)) {
            $chunkEnd = $cursor->copy()->addDays($chunkDays - 1)->endOfDay();
            if ($chunkEnd->gt($to)) {
                $chunkEnd = $to->copy()->endOfDay();
            }

            $response = Http::withToken($token)
                ->timeout(30)
                ->get(self::BASE . '/marketdata/v1/pricehistory', [
                    'symbol'                => $symbol,
                    'periodType'            => 'day',
                    'frequencyType'         => $map['frequencyType'],
                    'frequency'             => $map['frequency'],
                    'startDate'             => $cursor->getTimestampMs(),
                    'endDate'               => $chunkEnd->getTimestampMs(),
                    'needExtendedHoursData' => false,
                ]);

            $chunkEntry = [
                'from'    => $cursor->toDateString(),
                'to'      => $chunkEnd->toDateString(),
                'http'    => $response->status(),
                'candles' => 0,
                'error'   => null,
            ];

            if (!$response->successful()) {
                $chunkEntry['error'] = substr($response->body(), 0, 200);
                $this->lastFetchLog['chunks'][] = $chunkEntry;
                Log::error("Schwab price history error [{$cursor->toDateString()}–{$chunkEnd->toDateString()}]: "
                    . "{$response->status()} " . $response->body());
                $cursor = $chunkEnd->copy()->addDay()->startOfDay();
                continue;
            }

            $data = $response->json();

            if (!empty($data['candles'])) {
                $rows = [];
                $now  = now();

                foreach ($data['candles'] as $c) {
                    $rows[] = [
                        'symbol'     => $symbol,
                        'timeframe'  => $timeframe,
                        'opens_at'   => Carbon::createFromTimestampMs($c['datetime'])->toDateTimeString(),
                        'open'       => $c['open'],
                        'high'       => $c['high'],
                        'low'        => $c['low'],
                        'close'      => $c['close'],
                        'volume'     => (int) $c['volume'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                foreach (array_chunk($rows, 500) as $chunk) {
                    Candle::upsert($chunk, ['symbol', 'timeframe', 'opens_at'], [
                        'open', 'high', 'low', 'close', 'volume', 'updated_at',
                    ]);
                }

                $chunkEntry['candles'] = count($rows);
                $totalRows += count($rows);
                Log::info("Schwab: {$symbol} {$timeframe} [{$cursor->toDateString()}–{$chunkEnd->toDateString()}] → " . count($rows) . " candles");
            } else {
                Log::info("Schwab: {$symbol} {$timeframe} [{$cursor->toDateString()}–{$chunkEnd->toDateString()}] → 0 candles (market closed / weekend)");
            }

            $this->lastFetchLog['chunks'][] = $chunkEntry;
            $cursor = $chunkEnd->copy()->addDay()->startOfDay();
        }

        $this->lastFetchLog['total_candles_fetched'] = $totalRows;
        $this->lastFetchLog['total_chunks'] = count($this->lastFetchLog['chunks']);
        Log::info("Schwab: total {$totalRows} candles stored for {$symbol} {$timeframe} [{$from->toDateString()}–{$to->toDateString()}]");

        return Candle::where('symbol', $symbol)
            ->where('timeframe', $timeframe)
            ->whereBetween('opens_at', [$from->startOfDay(), $to->endOfDay()])
            ->orderBy('opens_at')
            ->get();
    }

    /**
     * Convert a Collection of Candle models to plain arrays for processing.
     */
    public function toRawArray(Collection $candles): array
    {
        return $candles->map(fn (Candle $c) => [
            'time'   => $c->opens_at->getTimestamp(),
            'dt'     => $c->opens_at->toDateTimeString(),
            'open'   => $c->open,
            'high'   => $c->high,
            'low'    => $c->low,
            'close'  => $c->close,
            'volume' => $c->volume,
        ])->values()->toArray();
    }
}

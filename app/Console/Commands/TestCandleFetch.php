<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestCandleFetch extends Command
{
    protected $signature = 'backtest:test-fetch
                            {symbol        : Ticker, e.g. SPY}
                            {from          : Start date YYYY-MM-DD}
                            {to            : End date YYYY-MM-DD}
                            {--tf=5m       : Timeframe (1m,5m,15m,30m,1d)}
                            {--chunk=20    : Days per API chunk}
                            {--dump        : Dump full raw JSON of first non-empty chunk}';

    protected $description = 'Fetch candles from Schwab API and show results — does NOT save to DB';

    private const BASE = 'https://api.schwabapi.com';

    private const TIMEFRAME_MAP = [
        '1m'  => ['frequencyType' => 'minute', 'frequency' => 1],
        '5m'  => ['frequencyType' => 'minute', 'frequency' => 5],
        '15m' => ['frequencyType' => 'minute', 'frequency' => 15],
        '30m' => ['frequencyType' => 'minute', 'frequency' => 30],
        '1h'  => ['frequencyType' => 'minute', 'frequency' => 60],
        '1d'  => ['frequencyType' => 'daily',  'frequency' => 1],
    ];

    public function handle(): int
    {
        $symbol    = strtoupper($this->argument('symbol'));
        $from      = Carbon::parse($this->argument('from'))->startOfDay();
        $to        = Carbon::parse($this->argument('to'))->endOfDay();
        $timeframe = $this->option('tf');
        $chunkDays = (int) $this->option('chunk');
        $dumpRaw   = $this->option('dump');

        $token = config('services.schwab.access_token')
            ?: app(\App\Services\SchwabAuthService::class)->getAccessToken();

        if (! $token) {
            $this->error('No Schwab token available.');
            return self::FAILURE;
        }

        $map = self::TIMEFRAME_MAP[$timeframe] ?? self::TIMEFRAME_MAP['5m'];

        $this->info("🔍 Schwab dry-fetch: {$symbol} [{$timeframe}]  {$from->toDateString()} → {$to->toDateString()}");
        $this->line("   Endpoint : " . self::BASE . '/marketdata/v1/pricehistory');
        $this->line("   Chunk    : {$chunkDays} days");
        $this->newLine();

        $cursor      = $from->copy();
        $totalChunks = 0;
        $totalCandles = 0;
        $firstDump   = true;

        $rows = [];

        while ($cursor->lte($to)) {
            $chunkEnd = $cursor->copy()->addDays($chunkDays - 1)->endOfDay();
            if ($chunkEnd->gt($to)) {
                $chunkEnd = $to->copy()->endOfDay();
            }

            $totalChunks++;

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

            $status   = $response->status();
            $data     = $response->json();
            $candles  = $data['candles'] ?? [];
            $count    = count($candles);
            $totalCandles += $count;

            $first = $count > 0 ? Carbon::createFromTimestampMs($candles[0]['datetime'])->toDateTimeString() : '—';
            $last  = $count > 0 ? Carbon::createFromTimestampMs(end($candles)['datetime'])->toDateTimeString() : '—';

            $statusColor = $status === 200 ? 'info' : 'error';
            $candleColor = $count > 0 ? 'comment' : 'fg=gray';

            $this->line(sprintf(
                "  [%s → %s]  HTTP <{$statusColor}>%d</>  candles: <{$candleColor}>%d</>  (%s … %s)%s",
                $cursor->toDateString(),
                $chunkEnd->toDateString(),
                $status,
                $count,
                $first,
                $last,
                $status !== 200 ? '  ← ' . substr($response->body(), 0, 120) : ''
            ));

            if ($dumpRaw && $count > 0 && $firstDump) {
                $this->newLine();
                $this->line('<fg=yellow>── RAW JSON (first non-empty chunk) ──</>');
                $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                $firstDump = false;
            }

            $cursor = $chunkEnd->copy()->addDay()->startOfDay();
        }

        $this->newLine();
        $this->line("  Total chunks : <comment>{$totalChunks}</comment>");
        $this->line("  Total candles: <comment>{$totalCandles}</comment>");

        if ($totalCandles === 0) {
            $this->newLine();
            $this->warn('⚠  0 candles returned for the entire range.');
            $this->warn('   Schwab intraday history is typically limited to the last ~30 days.');
        }

        return self::SUCCESS;
    }
}

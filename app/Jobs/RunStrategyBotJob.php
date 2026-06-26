<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\StrategyBot;
use App\Models\StrategyBotTrade;
use App\Services\OptionContractSelector;
use App\Services\PaperOptionPricingService;
use App\Services\SchwabOrderService;
use App\Services\IndicatorService;
use App\Strategies\StrategyRegistry;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * RunStrategyBotJob
 *
 * Fetches the latest candles for the bot's symbol/timeframe, runs the
 * configured strategy, and — if a new signal fires — opens a position
 * via SchwabOrderService.
 *
 * Paper mode  → SchwabOrderService simulates internally, no HTTP to Schwab.
 * Live mode   → SchwabOrderService sends real orders to Schwab Trader API.
 *
 * This job is dispatched:
 *   - When the user presses "Start" (immediately, then re-dispatched by the scheduler).
 *   - By the Laravel scheduler every minute (or per bot timeframe).
 *
 * The job also monitors open trades for stop-loss / take-profit exits.
 */
class RunStrategyBotJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries   = 1;

    public function __construct(
        public readonly int $botId,
    ) {}

    public function handle(
        IndicatorService  $indicators,
        SchwabOrderService $orderService,
    ): void {
        $bot = StrategyBot::find($this->botId);

        if (!$bot) {
            Log::warning("RunStrategyBotJob: bot #{$this->botId} not found");
            return;
        }

        if (!in_array($bot->status, ['running'])) {
            Log::info("RunStrategyBotJob: bot #{$bot->id} is {$bot->status}, skipping");
            return;
        }

        Log::info("RunStrategyBotJob: running bot #{$bot->id} [{$bot->name}]", [
            'paper'    => $bot->paper_mode,
            'symbol'   => $bot->symbol,
            'timeframe'=> $bot->timeframe,
        ]);

        try {
            // ── 1. Kill-switch: max daily loss ────────────────────────────
            if ($this->maxDailyLossHit($bot)) {
                $bot->update([
                    'status'      => 'stopped',
                    'stopped_at'  => now(),
                    'stop_reason' => 'Max daily loss reached',
                ]);
                Log::warning("RunStrategyBotJob: bot #{$bot->id} stopped — max daily loss hit");
                return;
            }

            // ── 2. Monitor open trades (check SL/TP against current price) ─
            $this->monitorOpenTrades($bot, $orderService);

            // ── 3. Check concurrent trade limit ───────────────────────────
            $openCount = $bot->openTrades()->count();
            if ($openCount >= $bot->max_concurrent_trades) {
                Log::info("RunStrategyBotJob: bot #{$bot->id} at max concurrent trades ({$openCount})");
                return;
            }

            // ── 4. Fetch latest candles ────────────────────────────────────
            $candles = $this->fetchLatestCandles($bot);
            if (count($candles) < 50) {
                Log::warning("RunStrategyBotJob: not enough candles for bot #{$bot->id}", [
                    'got' => count($candles),
                ]);
                return;
            }

            // ── 5. Enrich with indicators ──────────────────────────────────
            $candles = $indicators->compute($candles, $bot->strategy_params ?? []);

            // ── 6. Detect signals ──────────────────────────────────────────
            $strategy = StrategyRegistry::resolve($bot->strategy_key);
            $params   = $bot->strategy_params ?? [];
            $signals  = $strategy->detect($candles, $params);

            if (empty($signals)) {
                Log::info("RunStrategyBotJob: bot #{$bot->id} — no signal on latest bar");
                return;
            }

            // Use only the LAST signal (most recent bar)
            $signal = end($signals);

            // ── 7. Dedup — avoid opening the same bar twice ────────────────
            $lastTrade = $bot->trades()->latest('entry_time')->first();
            if ($lastTrade) {
                $lastEntryTime = Carbon::parse($lastTrade->entry_time);
                $signalTime    = Carbon::parse($signal['entry_time']);
                if ($lastEntryTime->gte($signalTime)) {
                    Log::info("RunStrategyBotJob: bot #{$bot->id} — signal already traded, skipping");
                    return;
                }
            }

            // ── 8. Calculate position size ────────────────────────────────
            $entryPrice = (float) $signal['entry_price'];
            $quantity   = $this->calcQuantity($bot, $entryPrice);

            if ($quantity <= 0) {
                Log::warning("RunStrategyBotJob: bot #{$bot->id} — calculated qty=0, skipping", [
                    'balance' => $bot->paper_balance,
                    'price'   => $entryPrice,
                ]);
                return;
            }

            // ── 9. Calculate SL / TP ──────────────────────────────────────
            [$stopLoss, $tp1, $tp2, $tp3] = $this->calcRiskLevels($signal, $params);

            // ── 10. For options bots: select the contract ─────────────────
            $optionContract = null;
            if ($bot->trade_type === 'options') {
                $selector       = OptionContractSelector::make();
                $optionContract = $selector->selectContract($bot, $signal['direction']);
                if (!$optionContract && $bot->paper_mode) {
                    $optionContract = app(PaperOptionPricingService::class)
                        ->makeSyntheticContract($bot, $signal['direction'], $entryPrice);

                    Log::info("RunStrategyBotJob: bot #{$bot->id} — using synthetic paper contract {$optionContract->contractSymbol}", [
                        'delta' => $optionContract->delta,
                        'strike' => $optionContract->strike,
                        'mark' => $optionContract->mark,
                    ]);
                }

                if (!$optionContract) {
                    Log::warning("RunStrategyBotJob: bot #{$bot->id} — no option contract found matching criteria, skipping");
                    return;
                }
                Log::info("RunStrategyBotJob: bot #{$bot->id} — selected contract {$optionContract->contractSymbol}", [
                    'delta'  => $optionContract->delta,
                    'strike' => $optionContract->strike,
                    'mark'   => $optionContract->mark,
                    'expiry' => $optionContract->expirationDate->toDateString(),
                ]);
            }

            // ── 11. Open position ─────────────────────────────────────────
            $orderService->openPosition(
                bot:            $bot,
                direction:      $signal['direction'],
                entryPrice:     $entryPrice,
                quantity:       $quantity,
                stopLoss:       $stopLoss,
                takeProfit1:    $tp1,
                takeProfit2:    $tp2,
                takeProfit3:    $tp3,
                signalData:     $signal,
                optionContract: $optionContract,
            );

            Log::info("RunStrategyBotJob: bot #{$bot->id} — position opened", [
                'direction' => $signal['direction'],
                'price'     => $entryPrice,
                'qty'       => $quantity,
                'sl'        => $stopLoss,
                'tp1'       => $tp1,
            ]);

        } catch (\Throwable $e) {
            Log::error("RunStrategyBotJob: bot #{$bot->id} exception: {$e->getMessage()}", [
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TRADE MONITORING
    // ─────────────────────────────────────────────────────────────────────────

    private function monitorOpenTrades(StrategyBot $bot, SchwabOrderService $orderService): void
    {
        $openTrades = $bot->openTrades()->get();
        if ($openTrades->isEmpty()) return;

        $currentPrice = $this->fetchCurrentPrice($bot);
        if ($currentPrice === null) {
            Log::warning("RunStrategyBotJob: could not fetch current price for {$bot->symbol}");
            return;
        }

        $recentCandles = $this->fetchRecentMinuteCandles($bot);

        foreach ($openTrades as $trade) {
            $this->checkTradeExit($bot, $trade, $currentPrice, $orderService, $recentCandles);
        }
    }

    private function checkTradeExit(
        StrategyBot      $bot,
        StrategyBotTrade $trade,
        float            $currentPrice,
        SchwabOrderService $orderService,
        array            $recentCandles = [],
    ): void {
        $isLong = in_array($trade->direction, ['CALL', 'LONG']);
        $params = $bot->strategy_params ?? [];
        $rangeSinceEntry = $this->priceRangeSinceEntry($trade, $recentCandles);
        $highestSinceEntry = $rangeSinceEntry['high'] ?? $currentPrice;
        $lowestSinceEntry = $rangeSinceEntry['low'] ?? $currentPrice;

        // ── Options: check contract value % stop/TP ────────────────────────
        if ($bot->trade_type === 'options' && $trade->option_contract_symbol) {
            $selector    = OptionContractSelector::make();
            $optQuote    = $selector->getContractQuote($trade->option_contract_symbol);
            $currentMark = $optQuote ? (float) $optQuote['mark'] : null;
            $entryMark   = (float) ($trade->option_entry_price ?? 0);

            if ($currentMark === null && $bot->paper_mode) {
                $currentMark = app(PaperOptionPricingService::class)
                    ->estimateExitMark($trade, $currentPrice);
            }

            if ($currentMark !== null && $entryMark > 0) {
                $changePct = (($currentMark - $entryMark) / $entryMark) * 100;

                // Contract stop-loss %
                if ($bot->option_stop_loss_pct !== null && $changePct <= -abs($bot->option_stop_loss_pct)) {
                    $orderService->closePosition($trade, $currentPrice, 'option_stop_loss', $currentMark);
                    return;
                }
                // Contract take-profit %
                if ($bot->option_take_profit_pct !== null && $changePct >= abs($bot->option_take_profit_pct)) {
                    $orderService->closePosition($trade, $currentPrice, 'option_take_profit', $currentMark);
                    return;
                }
            }
        }

        // Stop Loss hit
        if ($trade->stop_loss !== null) {
            $slHit = $isLong
                ? $lowestSinceEntry <= $trade->stop_loss
                : $highestSinceEntry >= $trade->stop_loss;
            if ($slHit) {
                $orderService->closePosition(
                    $trade,
                    $trade->stop_loss,
                    'stop_loss',
                    $this->paperOptionExitMark($bot, $trade, (float) $trade->stop_loss),
                );
                return;
            }
        }

        // Check the furthest targets first. If price gaps through TP3, record TP3.
        if ($trade->take_profit_3 !== null) {
            $tp3Hit = $isLong
                ? $highestSinceEntry >= $trade->take_profit_3
                : $lowestSinceEntry <= $trade->take_profit_3;
            if ($tp3Hit) {
                $orderService->closePosition(
                    $trade,
                    $trade->take_profit_3,
                    'tp3',
                    $this->paperOptionExitMark($bot, $trade, (float) $trade->take_profit_3),
                );
                return;
            }
        }

        if ($trade->take_profit_2 !== null) {
            $tp2Hit = $isLong
                ? $highestSinceEntry >= $trade->take_profit_2
                : $lowestSinceEntry <= $trade->take_profit_2;
            if ($tp2Hit) {
                $orderService->closePosition(
                    $trade,
                    $trade->take_profit_2,
                    'tp2',
                    $this->paperOptionExitMark($bot, $trade, (float) $trade->take_profit_2),
                );
                return;
            }
        }

        if ($trade->take_profit_1 !== null) {
            $tp1Hit = $isLong
                ? $highestSinceEntry >= $trade->take_profit_1
                : $lowestSinceEntry <= $trade->take_profit_1;
            if ($tp1Hit) {
                $orderService->closePosition(
                    $trade,
                    $trade->take_profit_1,
                    'tp1',
                    $this->paperOptionExitMark($bot, $trade, (float) $trade->take_profit_1),
                );
                return;
            }
        }

        // Max trade duration
        $maxMinutes = (int) ($params['max_trade_duration_minutes'] ?? 60);
        if ($trade->entry_time && now()->diffInMinutes($trade->entry_time) >= $maxMinutes) {
            $orderService->closePosition(
                $trade,
                $currentPrice,
                'max_duration',
                $this->paperOptionExitMark($bot, $trade, $currentPrice),
            );
            return;
        }

        // Force exit time
        $forceExitTime = $params['force_exit_time'] ?? '15:45';
        $nowET = now()->setTimezone('America/New_York')->format('H:i');
        $entryET = $trade->entry_time
            ? $trade->entry_time->copy()->setTimezone('America/New_York')->format('H:i')
            : null;

        if ($entryET !== null && $entryET < $forceExitTime && $nowET >= $forceExitTime) {
            $orderService->closePosition(
                $trade,
                $currentPrice,
                'time_exit',
                $this->paperOptionExitMark($bot, $trade, $currentPrice),
            );
            return;
        }
    }

    private function paperOptionExitMark(StrategyBot $bot, StrategyBotTrade $trade, float $underlyingPrice): ?float
    {
        if (!$bot->paper_mode || $bot->trade_type !== 'options' || !$trade->option_contract_symbol) {
            return null;
        }

        return app(PaperOptionPricingService::class)->estimateExitMark($trade, $underlyingPrice);
    }

    private function priceRangeSinceEntry(StrategyBotTrade $trade, array $candles): ?array
    {
        if (!$trade->entry_time || empty($candles)) {
            return null;
        }

        $entryTime = $trade->entry_time->copy()->subMinute();
        $high = null;
        $low = null;

        foreach ($candles as $candle) {
            $dt = $candle['carbon_dt'] ?? null;
            if (!$dt instanceof Carbon || $dt->lt($entryTime)) {
                continue;
            }

            $candleHigh = (float) ($candle['high'] ?? 0);
            $candleLow = (float) ($candle['low'] ?? 0);

            if ($candleHigh > 0) {
                $high = $high === null ? $candleHigh : max($high, $candleHigh);
            }
            if ($candleLow > 0) {
                $low = $low === null ? $candleLow : min($low, $candleLow);
            }
        }

        if ($high === null || $low === null) {
            return null;
        }

        return ['high' => $high, 'low' => $low];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // MARKET DATA HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Fetch the latest N candles from the Schwab Market Data API.
     * Returns array of ['dt'=>..., 'open'=>..., 'high'=>..., 'low'=>..., 'close'=>..., 'volume'=>...]
     */
    private function fetchLatestCandles(StrategyBot $bot): array
    {
        // Map timeframe → Schwab API parameters
        $tfMap = [
            '1m'  => ['periodType' => 'day',   'period' => 1,  'frequencyType' => 'minute', 'frequency' => 1],
            '5m'  => ['periodType' => 'day',   'period' => 2,  'frequencyType' => 'minute', 'frequency' => 5],
            '15m' => ['periodType' => 'day',   'period' => 5,  'frequencyType' => 'minute', 'frequency' => 15],
            '30m' => ['periodType' => 'day',   'period' => 10, 'frequencyType' => 'minute', 'frequency' => 30],
            '1h'  => ['periodType' => 'month', 'period' => 1,  'frequencyType' => 'minute', 'frequency' => 60],
            '1d'  => ['periodType' => 'year',  'period' => 1,  'frequencyType' => 'daily',  'frequency' => 1],
        ];

        $tf = $tfMap[$bot->timeframe] ?? $tfMap['5m'];

        // Get Schwab Market Data token (not Trader token)
        $authService = \App\Services\SchwabAuthService::make();
        $token = $authService->getAccessToken();
        if (!$token) {
            Log::warning("RunStrategyBotJob: no market data token");
            return [];
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept'        => 'application/json',
        ])->get('https://api.schwabapi.com/marketdata/v1/pricehistory', [
            'symbol'        => strtoupper($bot->symbol),
            'periodType'    => $tf['periodType'],
            'period'        => $tf['period'],
            'frequencyType' => $tf['frequencyType'],
            'frequency'     => $tf['frequency'],
        ]);

        if (!$response->successful()) {
            Log::error("RunStrategyBotJob: price history failed", [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return [];
        }

        $raw = $response->json('candles') ?? [];

        return array_map(fn($c) => [
            'dt'     => date('Y-m-d H:i:s', intdiv((int) $c['datetime'], 1000)),
            'open'   => (float) $c['open'],
            'high'   => (float) $c['high'],
            'low'    => (float) $c['low'],
            'close'  => (float) $c['close'],
            'volume' => (int)   $c['volume'],
        ], $raw);
    }

    /**
     * Fetch recent 1-minute candles with extended-hours data for missed-touch
     * TP/SL checks between scheduler ticks.
     */
    private function fetchRecentMinuteCandles(StrategyBot $bot): array
    {
        $authService = \App\Services\SchwabAuthService::make();
        $token = $authService->getAccessToken();
        if (!$token) {
            return [];
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept'        => 'application/json',
        ])->timeout(5)->get('https://api.schwabapi.com/marketdata/v1/pricehistory', [
            'symbol'                => strtoupper($bot->symbol),
            'periodType'            => 'day',
            'period'                => 1,
            'frequencyType'         => 'minute',
            'frequency'             => 1,
            'needExtendedHoursData' => 'true',
        ]);

        if (!$response->successful()) {
            Log::warning("RunStrategyBotJob: recent minute candles failed", [
                'symbol' => $bot->symbol,
                'status' => $response->status(),
            ]);
            return [];
        }

        $raw = $response->json('candles') ?? [];

        return array_map(fn($c) => [
            'carbon_dt' => Carbon::createFromTimestampMs((int) $c['datetime']),
            'dt'        => Carbon::createFromTimestampMs((int) $c['datetime'])->toDateTimeString(),
            'open'      => (float) $c['open'],
            'high'      => (float) $c['high'],
            'low'       => (float) $c['low'],
            'close'     => (float) $c['close'],
            'volume'    => (int)   $c['volume'],
        ], $raw);
    }

    /**
     * Fetch the last trade price for the symbol (used for SL/TP monitoring).
     */
    private function fetchCurrentPrice(StrategyBot $bot): ?float
    {
        $authService = \App\Services\SchwabAuthService::make();
        $token = $authService->getAccessToken();
        if (!$token) {
            Log::warning("RunStrategyBotJob: no Schwab token for fetchCurrentPrice");
            return null;
        }

        $symbol = strtoupper(trim($bot->symbol));

        // Retry up to 2 times on failure
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Accept'        => 'application/json',
            ])->timeout(5)->get('https://api.schwabapi.com/marketdata/v1/quotes', [
                'symbols' => $symbol,
                'fields'  => 'quote',
            ]);

            if ($response->successful()) {
                $data  = $response->json() ?? [];
                $price = (float) ($data[$symbol]['quote']['lastPrice']
                    ?? $data[$symbol]['quote']['mark']
                    ?? $data[$symbol]['quote']['closePrice']
                    ?? 0);

                if ($price > 0) {
                    Log::debug("RunStrategyBotJob: fetchCurrentPrice {$symbol} = {$price}");
                    return $price;
                }
            }

            if ($attempt < 2) usleep(300_000); // 300ms before retry
        }

        Log::warning("RunStrategyBotJob: fetchCurrentPrice failed for {$symbol}", [
            'http_status' => $response->status() ?? null,
        ]);
        return null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // RISK HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    private function calcQuantity(StrategyBot $bot, float $price): float
    {
        // Options: quantity = contracts, independent of index price
        if ($bot->trade_type === 'options') {
            return max(1, (int) ($bot->option_contracts ?? 1));
        }

        if ($price <= 0) return 0;
        $balance = $bot->paper_mode ? $bot->paper_balance : $bot->paper_budget;
        return match ($bot->position_size_type) {
            'fixed_shares'  => $bot->position_size_value,
            'fixed_dollars' => floor($bot->position_size_value / $price),
            'risk_pct'      => $bot->risk_per_trade_pct
                ? floor(($balance * ($bot->risk_per_trade_pct / 100)) / $price)
                : 1,
            default => 1,
        };
    }

    /**
     * Derive stop-loss and take-profit prices from the signal data and strategy params.
     * Returns [$stopLoss, $tp1, $tp2, $tp3]
     */
    private function calcRiskLevels(array $signal, array $params): array
    {
        $direction  = $signal['direction'];
        $entry      = (float) $signal['entry_price'];
        $atr        = (float) ($signal['atr'] ?? 0);
        $ema50      = (float) ($signal['ema50'] ?? $entry);
        $isLong     = in_array($direction, ['CALL', 'LONG']);

        // ── Stop Loss ──────────────────────────────────────────────────────
        $stopType     = $params['stop_type'] ?? 'pullback';
        $stopAtrMult  = (float) ($params['stop_atr_mult'] ?? 1.5);
        $stopBufPct   = (float) ($params['stop_buffer_pct'] ?? 0.05);
        $stopPct      = (float) ($params['stop_pct'] ?? 1.0);

        $stopLoss = match ($stopType) {
            'atr'     => $isLong
                ? $entry - ($atr * $stopAtrMult)
                : $entry + ($atr * $stopAtrMult),
            'ema_mid' => $isLong
                ? $ema50 * (1 - $stopBufPct / 100)
                : $ema50 * (1 + $stopBufPct / 100),
            'percent' => $isLong
                ? $entry * (1 - $stopPct / 100)
                : $entry * (1 + $stopPct / 100),
            default   => $isLong  // pullback
                ? (float) ($signal['pullback_low'] ?? $entry * (1 - $stopBufPct / 100)) * (1 - $stopBufPct / 100)
                : (float) ($signal['pullback_high'] ?? $entry * (1 + $stopBufPct / 100)) * (1 + $stopBufPct / 100),
        };

        // ── Take Profit ────────────────────────────────────────────────────
        $tpType   = $params['tp_type'] ?? 'risk_ratio';
        $tp1Val   = (float) ($params['tp1_value'] ?? 1.0);
        $tp2Val   = (float) ($params['tp2_value'] ?? 2.0);
        $tp3Val   = (float) ($params['tp3_value'] ?? 3.0);
        $risk     = abs($entry - $stopLoss);

        [$tp1, $tp2, $tp3] = match ($tpType) {
            'risk_ratio' => $isLong
                ? [$entry + $risk * $tp1Val, $entry + $risk * $tp2Val, $entry + $risk * $tp3Val]
                : [$entry - $risk * $tp1Val, $entry - $risk * $tp2Val, $entry - $risk * $tp3Val],
            'atr' => $isLong
                ? [$entry + $atr * $tp1Val, $entry + $atr * $tp2Val, $entry + $atr * $tp3Val]
                : [$entry - $atr * $tp1Val, $entry - $atr * $tp2Val, $entry - $atr * $tp3Val],
            default => $isLong
                ? [$entry * (1 + $tp1Val / 100), $entry * (1 + $tp2Val / 100), $entry * (1 + $tp3Val / 100)]
                : [$entry * (1 - $tp1Val / 100), $entry * (1 - $tp2Val / 100), $entry * (1 - $tp3Val / 100)],
        };

        return [
            round($stopLoss, 4),
            round($tp1, 4),
            round($tp2, 4),
            round($tp3, 4),
        ];
    }

    private function maxDailyLossHit(StrategyBot $bot): bool
    {
        if (!$bot->max_daily_loss_pct) return false;

        $todayPnl = $bot->trades()
            ->where('status', 'closed')
            ->whereDate('exit_time', today())
            ->sum('pnl');

        $budget = $bot->paper_mode ? $bot->paper_budget : $bot->paper_budget;
        $lossPct = $budget > 0 ? (abs(min(0, $todayPnl)) / $budget) * 100 : 0;

        return $lossPct >= $bot->max_daily_loss_pct;
    }
}

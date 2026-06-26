<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AlpacaStrategyLabLog;
use App\Models\AlpacaStrategyLabSession;
use App\Models\AlpacaStrategyLabTrade;
use App\Services\AlpacaMarketDataService;
use App\Services\AlpacaTradingService;
use App\Services\IndicatorService;
use App\Strategies\StrategyRegistry;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunAlpacaStrategyLabJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries = 1;

    public function __construct(
        public readonly int $sessionId,
        public readonly bool $syncOnly = false,
    ) {}

    public function handle(IndicatorService $indicators): void
    {
        $session = AlpacaStrategyLabSession::find($this->sessionId);
        if (!$session || (!$this->syncOnly && $session->status !== 'running')) {
            return;
        }

        $trader = AlpacaTradingService::make($session->mode === 'live' ? 'live' : 'paper');

        try {
            $this->syncTrades($session, $trader);
            if (!$this->syncOnly) {
                $this->evaluateEntry($session, $trader, $indicators);
            }

            $session->update([
                'last_run_at' => now(),
                'error_message' => null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Alpaca Strategy Lab tick failed', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);

            $session->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'last_run_at' => now(),
            ]);

            $this->log($session, 'error', 'tick_failed', $e->getMessage());
        }
    }

    private function evaluateEntry(
        AlpacaStrategyLabSession $session,
        AlpacaTradingService $trader,
        IndicatorService $indicators,
    ): void {
        $openCount = $session->trades()
            ->whereIn('status', ['pending', 'open', 'closing'])
            ->count();

        if ($openCount >= $session->max_concurrent_trades) {
            return;
        }

        if (
            $session->strategy_key === 'price_trigger'
            && ($session->params['one_shot'] ?? 'yes') === 'yes'
            && $session->trades()->exists()
        ) {
            if (!$session->logs()->where('event', 'one_shot_already_used')->where('created_at', '>=', now()->subMinutes(5))->exists()) {
                $this->log($session, 'info', 'one_shot_already_used', 'This Price Trigger session already placed its one allowed entry. Set One Entry Only to No or create a new session to allow another entry.');
            }
            return;
        }

        $market = new AlpacaMarketDataService(
            (string) config('services.alpaca.paper.key', ''),
            (string) config('services.alpaca.paper.secret', ''),
        );

        $from = now('America/New_York')->subDays($this->lookbackDays($session->timeframe))->toDateString();
        $to = now('America/New_York')->toDateString();
        $candles = $market->getCandles($session->symbol, $session->timeframe, $from, $to);

        if ($candles->isEmpty()) {
            $this->log($session, 'warning', 'no_candles', 'No candles returned for entry evaluation.');
            return;
        }

        $raw = $market->toRawArray($candles);
        $currentPriceData = null;
        if ($session->strategy_key === 'price_trigger') {
            $currentPriceData = $this->latestTriggerPrice($trader, $session);
            $currentPrice = (float) ($currentPriceData['price'] ?? 0.0);
            if ($currentPrice > 0) {
                $raw[] = [
                    'time' => now('UTC')->getTimestamp(),
                    'dt' => now('UTC')->toIso8601String(),
                    'open' => $currentPrice,
                    'high' => $currentPrice,
                    'low' => $currentPrice,
                    'close' => $currentPrice,
                    'volume' => 0,
                ];
            } else {
                $this->log($session, 'warning', 'no_trigger_price', 'No current trigger price was available from Alpaca market data.', $currentPriceData);
            }
        }

        $enriched = $indicators->compute($raw, $session->params ?? []);
        $signals = StrategyRegistry::resolve($session->strategy_key)->detect($enriched, $session->params ?? []);

        if (empty($signals)) {
            if ($session->strategy_key === 'price_trigger') {
                $this->log($session, 'info', 'trigger_not_met', 'Price trigger condition was not met.', [
                    'trigger_price' => $session->params['trigger_price'] ?? null,
                    'trigger_operator' => $session->params['trigger_operator'] ?? null,
                    'price' => $currentPriceData,
                ]);
            }
            return;
        }

        $signal = end($signals);
        $signalTime = Carbon::parse($signal['entry_time'] ?? now(), 'UTC');

        if ($session->last_signal_at && $signalTime->lessThanOrEqualTo($session->last_signal_at)) {
            return;
        }

        if ($signalTime->lt(now('UTC')->subMinutes($this->staleSignalMinutes($session->timeframe)))) {
            return;
        }

        $direction = strtoupper((string) ($signal['direction'] ?? 'LONG'));
        $isOptionTrade = ($session->params['trade_asset'] ?? null) === 'option';
        $side = (!$isOptionTrade && in_array($direction, ['PUT', 'SHORT'], true)) ? 'sell' : 'buy';
        $symbol = strtoupper($session->symbol);
        $latestPrice = $this->latestPrice($trader, $symbol);

        $contract = null;
        if ($isOptionTrade) {
            $contract = $this->selectOptionContract($session, $trader, $direction);
            if ($contract === null) {
                $this->log($session, 'warning', 'option_contract_not_found', 'No Alpaca option contract matched the configured price/delta.');
                return;
            }

            $symbol = $contract['symbol'];
            $latestPrice = (float) ($contract['price'] ?? 0.0);
        }

        $payload = [
            'symbol' => $symbol,
            'side' => $side,
        ] + $this->entryOrderTypeFields($side, $latestPrice, $session, $contract);

        if ($isOptionTrade) {
            $payload['qty'] = $this->formatNumber($this->optionQuantity($session, $latestPrice));
        } elseif ($session->position_size_type === 'fixed_notional' && $side === 'buy' && ($payload['type'] ?? null) === 'market') {
            $payload['notional'] = $this->formatNumber($session->position_size_value);
        } elseif ($session->position_size_type === 'fixed_notional' && $side === 'buy' && $latestPrice > 0) {
            $payload['qty'] = $this->formatNumber(max(0.000001, $session->position_size_value / $latestPrice));
        } else {
            $payload['qty'] = $this->formatNumber(max(0.000001, $session->position_size_value));
        }

        try {
            $order = $trader->submitOrder($payload);
        } catch (\RuntimeException $e) {
            if (str_contains(strtolower($e->getMessage()), 'wash trade')) {
                $this->log($session, 'warning', 'entry_blocked_by_opposite_order', 'Alpaca blocked the entry because an opposite open order already exists for this symbol. Cancel or resolve the open order, then the bot can submit a new entry.', [
                    'symbol' => $symbol,
                    'side' => $side,
                    'alpaca_message' => $e->getMessage(),
                    'payload' => $payload,
                ]);
                return;
            }

            throw $e;
        }

        $trade = AlpacaStrategyLabTrade::create([
            'alpaca_strategy_lab_session_id' => $session->id,
            'symbol' => $symbol,
            'direction' => $direction,
            'side' => $side,
            'status' => 'pending',
            'entry_order_id' => $order['id'] ?? null,
            'quantity' => isset($payload['qty']) ? (float) $payload['qty'] : null,
            'notional' => isset($payload['notional']) ? (float) $payload['notional'] : null,
            'signal_data' => $contract ? ($signal + ['selected_contract' => $contract]) : $signal,
            'entry_order_payload' => $order,
        ]);

        $session->update(['last_signal_at' => $signalTime]);
        $this->log($session, 'info', 'entry_order_submitted', 'Entry order sent to Alpaca ' . strtoupper($session->mode) . '.', [
            'trade_id' => $trade->id,
            'order_id' => $trade->entry_order_id,
            'side' => $side,
            'symbol' => $symbol,
        ], $trade);
    }

    private function syncTrades(AlpacaStrategyLabSession $session, AlpacaTradingService $trader): void
    {
        $trades = $session->trades()
            ->whereIn('status', ['pending', 'open', 'closing'])
            ->oldest()
            ->get();

        foreach ($trades as $trade) {
            if ($trade->status === 'pending') {
                $this->syncEntryOrder($session, $trade, $trader);
            }

            if ($trade->fresh()?->status === 'open') {
                $this->monitorOpenTrade($session, $trade->fresh(), $trader);
            }

            if ($trade->fresh()?->status === 'closing') {
                $this->syncExitOrder($session, $trade->fresh(), $trader);
            }
        }

        $this->refreshStats($session);
    }

    private function syncEntryOrder(
        AlpacaStrategyLabSession $session,
        AlpacaStrategyLabTrade $trade,
        AlpacaTradingService $trader,
    ): void {
        if (!$trade->entry_order_id) {
            $trade->update(['status' => 'failed', 'error_message' => 'Entry order id missing.']);
            return;
        }

        $order = $trader->order($trade->entry_order_id);
        $status = strtolower((string) ($order['status'] ?? ''));

        if (in_array($status, ['canceled', 'cancelled', 'rejected', 'expired'], true)) {
            $trade->update([
                'status' => $status === 'rejected' ? 'failed' : 'cancelled',
                'entry_order_payload' => $order,
                'error_message' => $order['reject_reason'] ?? null,
                'last_sync_at' => now(),
            ]);
            $this->log($session, 'warning', 'entry_not_filled', "Entry order status: {$status}.", [], $trade);
            return;
        }

        if ($status !== 'filled') {
            $trade->update(['entry_order_payload' => $order, 'last_sync_at' => now()]);
            return;
        }

        $entryPrice = (float) ($order['filled_avg_price'] ?? $trade->entry_price ?? 0);
        $qty = (float) ($order['filled_qty'] ?? $trade->quantity ?? 0);

        $trade->update([
            'status' => 'open',
            'entry_price' => $entryPrice,
            'quantity' => $qty > 0 ? $qty : $trade->quantity,
            'entry_time' => isset($order['filled_at']) ? Carbon::parse($order['filled_at']) : now(),
            'stop_loss' => $this->configuredStopPrice($session, $trade->side, $entryPrice),
            'take_profit' => $this->configuredTakeProfitPrice($session, $trade->side, $entryPrice),
            'entry_order_payload' => $order,
            'last_sync_at' => now(),
        ]);

        $this->log($session, 'info', 'entry_filled', 'Entry order filled.', [
            'price' => $entryPrice,
            'qty' => $qty,
        ], $trade);
    }

    private function monitorOpenTrade(
        AlpacaStrategyLabSession $session,
        AlpacaStrategyLabTrade $trade,
        AlpacaTradingService $trader,
    ): void {
        $price = $this->tradeMarketPrice($trader, $trade);

        if (!$price || !$trade->quantity) {
            return;
        }

        $exitReason = null;
        if ($trade->side === 'buy') {
            if ($trade->stop_loss !== null && $price <= $trade->stop_loss) {
                $exitReason = 'stop_loss';
            } elseif ($trade->take_profit !== null && $price >= $trade->take_profit) {
                $exitReason = 'take_profit';
            }
        } else {
            if ($trade->stop_loss !== null && $price >= $trade->stop_loss) {
                $exitReason = 'stop_loss';
            } elseif ($trade->take_profit !== null && $price <= $trade->take_profit) {
                $exitReason = 'take_profit';
            }
        }

        if (!$exitReason) {
            $this->logOpenTradeSnapshot($session, $trade, $price);
            $trade->update(['last_sync_at' => now()]);
            return;
        }

        $exitSide = $trade->side === 'buy' ? 'sell' : 'buy';
        $payload = [
            'symbol' => $trade->symbol,
            'qty' => $this->formatNumber($trade->quantity),
            'side' => $exitSide,
        ] + $this->orderTypeFields($exitSide, $price);

        $order = $trader->submitOrder($payload);
        $trade->update([
            'status' => 'closing',
            'exit_order_id' => $order['id'] ?? null,
            'exit_reason' => $exitReason,
            'exit_order_payload' => $order,
            'last_sync_at' => now(),
        ]);

        $this->log($session, 'info', 'exit_order_submitted', "Exit order sent for {$exitReason}.", [
            'price' => $price,
            'order_id' => $trade->exit_order_id,
        ], $trade);
    }

    private function logOpenTradeSnapshot(
        AlpacaStrategyLabSession $session,
        AlpacaStrategyLabTrade $trade,
        float $price,
    ): void {
        $alreadyLogged = $session->logs()
            ->where('alpaca_strategy_lab_trade_id', $trade->id)
            ->where('event', 'monitor_snapshot')
            ->where('created_at', '>=', now()->subMinute())
            ->exists();

        if ($alreadyLogged) {
            return;
        }

        $entry = (float) ($trade->entry_price ?? 0);
        $qty = (float) ($trade->quantity ?? 0);
        $multiplier = $trade->side === 'buy' ? 1 : -1;
        $pnl = $entry > 0 && $qty > 0 ? ($price - $entry) * $qty * $multiplier : null;
        $pnlPct = $entry > 0 ? (($price - $entry) / $entry) * 100 * $multiplier : null;

        $this->log($session, $pnl === null || $pnl >= 0 ? 'info' : 'warning', 'monitor_snapshot', 'Open trade monitored.', [
            'current_price' => $price,
            'entry_price' => $entry ?: null,
            'quantity' => $qty ?: null,
            'stop_loss' => $trade->stop_loss,
            'take_profit' => $trade->take_profit,
            'unrealized_pnl' => $pnl !== null ? round($pnl, 2) : null,
            'unrealized_pnl_pct' => $pnlPct !== null ? round($pnlPct, 4) : null,
        ], $trade);
    }

    private function syncExitOrder(
        AlpacaStrategyLabSession $session,
        AlpacaStrategyLabTrade $trade,
        AlpacaTradingService $trader,
    ): void {
        if (!$trade->exit_order_id) {
            return;
        }

        $order = $trader->order($trade->exit_order_id);
        $status = strtolower((string) ($order['status'] ?? ''));

        if ($status !== 'filled') {
            $trade->update(['exit_order_payload' => $order, 'last_sync_at' => now()]);
            return;
        }

        $exitPrice = (float) ($order['filled_avg_price'] ?? 0);
        $entryPrice = (float) $trade->entry_price;
        $qty = (float) $trade->quantity;
        $multiplier = $trade->side === 'buy' ? 1 : -1;
        $pnl = ($exitPrice - $entryPrice) * $qty * $multiplier;
        $pnlPct = $entryPrice > 0 ? (($exitPrice - $entryPrice) / $entryPrice) * 100 * $multiplier : null;

        $trade->update([
            'status' => 'closed',
            'exit_price' => $exitPrice,
            'exit_time' => isset($order['filled_at']) ? Carbon::parse($order['filled_at']) : now(),
            'pnl' => round($pnl, 4),
            'pnl_pct' => $pnlPct !== null ? round($pnlPct, 4) : null,
            'exit_order_payload' => $order,
            'last_sync_at' => now(),
        ]);

        $this->log($session, 'info', 'exit_filled', 'Exit order filled.', [
            'price' => $exitPrice,
            'pnl' => round($pnl, 4),
        ], $trade);
    }

    private function refreshStats(AlpacaStrategyLabSession $session): void
    {
        $closed = $session->trades()->where('status', 'closed')->get();
        $session->update([
            'total_trades' => $session->trades()->count(),
            'winning_trades' => $closed->where('pnl', '>', 0)->count(),
            'losing_trades' => $closed->where('pnl', '<', 0)->count(),
            'total_pnl' => round((float) $closed->sum('pnl'), 4),
            'total_pnl_pct' => round((float) $closed->avg('pnl_pct'), 4),
        ]);
    }

    private function stopPrice(string $side, float $entry, ?float $pct): ?float
    {
        if (!$pct || $pct <= 0) {
            return null;
        }

        return round($side === 'buy' ? $entry * (1 - $pct / 100) : $entry * (1 + $pct / 100), 4);
    }

    private function takeProfitPrice(string $side, float $entry, ?float $pct): ?float
    {
        if (!$pct || $pct <= 0) {
            return null;
        }

        return round($side === 'buy' ? $entry * (1 + $pct / 100) : $entry * (1 - $pct / 100), 4);
    }

    private function configuredStopPrice(AlpacaStrategyLabSession $session, string $side, float $entry): ?float
    {
        $fixed = $session->params['stop_loss_value'] ?? null;
        if ($fixed !== null && $fixed !== '' && (float) $fixed > 0) {
            return round((float) $fixed, 4);
        }

        return $this->stopPrice($side, $entry, $session->stop_loss_pct);
    }

    private function configuredTakeProfitPrice(AlpacaStrategyLabSession $session, string $side, float $entry): ?float
    {
        $fixed = $session->params['take_profit_value'] ?? null;
        if ($fixed !== null && $fixed !== '' && (float) $fixed > 0) {
            return round((float) $fixed, 4);
        }

        return $this->takeProfitPrice($side, $entry, $session->take_profit_pct);
    }

    private function selectOptionContract(AlpacaStrategyLabSession $session, AlpacaTradingService $trader, string $direction): ?array
    {
        $params = $session->params ?? [];
        $targetPrice = isset($params['option_target_price']) ? (float) $params['option_target_price'] : 0.0;
        $targetDelta = isset($params['option_target_delta']) ? abs((float) $params['option_target_delta']) : 0.0;
        $minDte = max(0, (int) ($params['option_min_dte'] ?? 0));
        $maxDte = max($minDte, (int) ($params['option_max_dte'] ?? 14));
        $type = in_array(strtoupper($direction), ['PUT', 'SHORT'], true) ? 'put' : 'call';

        $contractsResponse = $trader->optionContracts([
            'underlying_symbols' => strtoupper($session->symbol),
            'status' => 'active',
            'type' => $type,
            'expiration_date_gte' => now('America/New_York')->addDays($minDte)->toDateString(),
            'expiration_date_lte' => now('America/New_York')->addDays($maxDte)->toDateString(),
            'limit' => 10000,
        ]);

        $contracts = $contractsResponse['option_contracts']
            ?? $contractsResponse['contracts']
            ?? $contractsResponse
            ?? [];

        if (!is_array($contracts) || empty($contracts)) {
            return null;
        }

        $symbols = [];
        foreach ($contracts as $contract) {
            $symbol = $contract['symbol'] ?? $contract['contract_symbol'] ?? null;
            if ($symbol) {
                $symbols[] = $symbol;
            }
        }

        $quotes = [];
        foreach (array_chunk($symbols, 100) as $chunk) {
            $quotes += $this->normalizeOptionQuotes($trader->latestOptionQuotes($chunk));
        }

        $best = null;
        $bestScore = PHP_FLOAT_MAX;

        foreach ($contracts as $contract) {
            $symbol = $contract['symbol'] ?? $contract['contract_symbol'] ?? null;
            if (!$symbol) {
                continue;
            }

            $quote = $quotes[$symbol] ?? [];
            $price = $this->optionQuotePrice($quote, $contract);
            $delta = $this->optionDelta($quote, $contract);

            if ($price <= 0) {
                continue;
            }

            $priceScore = $targetPrice > 0 ? abs($price - $targetPrice) / max($targetPrice, 0.01) : 0.0;
            $deltaScore = $targetDelta > 0 && $delta !== null ? abs(abs($delta) - $targetDelta) : 0.0;
            $missingDeltaPenalty = $targetDelta > 0 && $delta === null ? 10.0 : 0.0;
            $score = $priceScore + $deltaScore + $missingDeltaPenalty;

            if ($score >= $bestScore) {
                continue;
            }

            $bestScore = $score;
            $best = [
                'symbol' => $symbol,
                'type' => $type,
                'price' => $price,
                'delta' => $delta,
                'target_price' => $targetPrice,
                'target_delta' => $targetDelta,
                'expiration_date' => $contract['expiration_date'] ?? null,
                'strike_price' => isset($contract['strike_price']) ? (float) $contract['strike_price'] : null,
                'quote' => $quote,
                'contract' => $contract,
            ];
        }

        return $best;
    }

    private function optionQuotePrice(array $quote, array $contract = []): float
    {
        $bid = (float) ($quote['bp'] ?? $quote['bid_price'] ?? $quote['bid'] ?? $contract['bid'] ?? 0);
        $ask = (float) ($quote['ap'] ?? $quote['ask_price'] ?? $quote['ask'] ?? $contract['ask'] ?? 0);
        if ($bid > 0 && $ask > 0) {
            return round(($bid + $ask) / 2, 4);
        }

        return (float) ($quote['p'] ?? $quote['last_price'] ?? $quote['last'] ?? $quote['mark'] ?? $contract['last_price'] ?? $contract['close_price'] ?? 0);
    }

    private function normalizeOptionQuotes(array $quotes): array
    {
        $normalized = [];
        foreach ($quotes as $key => $quote) {
            if (!is_array($quote)) {
                continue;
            }

            $symbol = is_string($key)
                ? $key
                : ($quote['symbol'] ?? $quote['S'] ?? null);

            if ($symbol) {
                $normalized[$symbol] = $quote;
            }
        }

        return $normalized;
    }

    private function optionDelta(array $quote, array $contract = []): ?float
    {
        $delta = $quote['delta']
            ?? $quote['greeks']['delta']
            ?? $contract['delta']
            ?? $contract['greeks']['delta']
            ?? null;

        return $delta !== null ? (float) $delta : null;
    }

    private function optionQuantity(AlpacaStrategyLabSession $session, float $contractPrice): float
    {
        if ($session->position_size_type === 'fixed_notional' && $contractPrice > 0) {
            return max(1, floor($session->position_size_value / ($contractPrice * 100)));
        }

        return max(1, floor($session->position_size_value));
    }

    private function tradeMarketPrice(AlpacaTradingService $trader, AlpacaStrategyLabTrade $trade): float
    {
        if (($trade->signal_data['trade_asset'] ?? null) === 'option' || ($trade->signal_data['selected_contract'] ?? null)) {
            $quotes = $this->normalizeOptionQuotes($trader->latestOptionQuotes([$trade->symbol]));
            return $this->optionQuotePrice($quotes[$trade->symbol] ?? []);
        }

        return $this->latestPrice($trader, $trade->symbol);
    }

    private function lookbackDays(string $timeframe): int
    {
        return match ($timeframe) {
            '1m' => 5,
            '5m', '15m' => 15,
            '30m', '1h' => 45,
            default => 180,
        };
    }

    private function staleSignalMinutes(string $timeframe): int
    {
        return match ($timeframe) {
            '1m' => 3,
            '5m' => 10,
            '15m' => 30,
            '30m' => 60,
            '1h' => 120,
            default => 1440,
        };
    }

    private function latestPrice(AlpacaTradingService $trader, string $symbol): float
    {
        $last = $trader->latestStockTrade($symbol);
        $tradePrice = isset($last['p']) ? (float) $last['p'] : 0.0;
        $tradeTime = isset($last['t']) ? Carbon::parse($last['t']) : null;

        if ($tradePrice > 0 && $tradeTime && $tradeTime->gte(now('UTC')->subMinutes(15))) {
            return $tradePrice;
        }

        $quote = $trader->latestStockQuote($symbol);
        $bid = (float) ($quote['bp'] ?? 0);
        $ask = (float) ($quote['ap'] ?? 0);

        if ($bid > 0 && $ask > 0) {
            return round(($bid + $ask) / 2, 4);
        }

        return $tradePrice;
    }

    private function latestTriggerPrice(AlpacaTradingService $trader, AlpacaStrategyLabSession $session): array
    {
        $source = (string) ($session->params['trigger_price_source'] ?? 'auto');
        if ($source === 'last') {
            $last = $trader->latestStockTrade($session->symbol);
            return [
                'price' => isset($last['p']) ? (float) $last['p'] : 0.0,
                'source' => 'last',
                'timestamp' => $last['t'] ?? null,
            ];
        }

        $quote = $trader->latestStockQuote($session->symbol);
        $bid = (float) ($quote['bp'] ?? 0);
        $ask = (float) ($quote['ap'] ?? 0);
        $timestamp = $quote['t'] ?? null;

        if ($source === 'bid') {
            return ['price' => $bid, 'source' => 'bid', 'timestamp' => $timestamp, 'bid' => $bid, 'ask' => $ask];
        }

        if ($source === 'ask') {
            return ['price' => $ask, 'source' => 'ask', 'timestamp' => $timestamp, 'bid' => $bid, 'ask' => $ask];
        }

        if ($source === 'mid') {
            return [
                'price' => $bid > 0 && $ask > 0 ? round(($bid + $ask) / 2, 4) : $this->latestPrice($trader, $session->symbol),
                'source' => 'mid',
                'timestamp' => $timestamp,
                'bid' => $bid,
                'ask' => $ask,
            ];
        }

        $direction = strtoupper((string) ($session->params['trade_direction'] ?? 'LONG'));
        $operator = (string) ($session->params['trigger_operator'] ?? 'at_or_above');
        $isUpsideTrigger = in_array($operator, ['at_or_above', 'cross_above'], true);
        $isBuyLike = in_array($direction, ['LONG', 'CALL'], true);

        if ($isBuyLike && $isUpsideTrigger && $ask > 0) {
            return ['price' => $ask, 'source' => 'auto_ask', 'timestamp' => $timestamp, 'bid' => $bid, 'ask' => $ask];
        }

        if (!$isBuyLike && !$isUpsideTrigger && $bid > 0) {
            return ['price' => $bid, 'source' => 'auto_bid', 'timestamp' => $timestamp, 'bid' => $bid, 'ask' => $ask];
        }

        return [
            'price' => $bid > 0 && $ask > 0 ? round(($bid + $ask) / 2, 4) : $this->latestPrice($trader, $session->symbol),
            'source' => 'auto_mid',
            'timestamp' => $timestamp,
            'bid' => $bid,
            'ask' => $ask,
        ];
    }

    private function entryOrderTypeFields(
        string $side,
        float $referencePrice,
        AlpacaStrategyLabSession $session,
        ?array $contract = null,
    ): array {
        if ($contract !== null) {
            $mode = (string) ($session->params['option_order_price'] ?? 'market');
            if ($mode === 'limit_mid' || $mode === 'limit_target') {
                $limitPrice = $mode === 'limit_target'
                    ? (float) ($session->params['option_target_price'] ?? $referencePrice)
                    : $referencePrice;

                return [
                    'type' => 'limit',
                    'limit_price' => $this->formatNumber(round(max(0.01, $limitPrice), 2)),
                    'time_in_force' => 'day',
                ];
            }

            return [
                'type' => 'market',
                'time_in_force' => 'day',
            ];
        }

        return $this->orderTypeFields($side, $referencePrice);
    }

    private function orderTypeFields(string $side, float $referencePrice): array
    {
        if ($this->isRegularMarketHours() || $referencePrice <= 0) {
            return [
                'type' => 'market',
                'time_in_force' => 'day',
            ];
        }

        $limitPrice = $side === 'buy'
            ? $referencePrice * 1.0025
            : $referencePrice * 0.9975;

        return [
            'type' => 'limit',
            'limit_price' => $this->formatNumber(round($limitPrice, 2)),
            'time_in_force' => 'day',
            'extended_hours' => true,
        ];
    }

    private function isRegularMarketHours(): bool
    {
        $now = now('America/New_York');
        if ($now->isWeekend()) {
            return false;
        }

        $time = $now->format('H:i');

        return $time >= '09:30' && $time < '16:00';
    }

    private function log(
        AlpacaStrategyLabSession $session,
        string $level,
        string $event,
        string $message,
        array $context = [],
        ?AlpacaStrategyLabTrade $trade = null,
    ): void {
        AlpacaStrategyLabLog::create([
            'alpaca_strategy_lab_session_id' => $session->id,
            'alpaca_strategy_lab_trade_id' => $trade?->id,
            'level' => $level,
            'event' => $event,
            'message' => $message,
            'context' => $context ?: null,
        ]);
    }

    private function formatNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 8, '.', ''), '0'), '.');
    }
}

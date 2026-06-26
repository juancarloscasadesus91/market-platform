<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\OptionContractData;
use App\Models\StrategyBot;
use App\Models\StrategyBotTrade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Handles placing, monitoring and cancelling orders on the Schwab Trader API.
 *
 * When bot->paper_mode === true  → simulates everything locally, no HTTP calls.
 * When bot->paper_mode === false → sends real orders to Schwab.
 *
 * Schwab Trader API base: https://api.schwabapi.com/trader/v1
 */
class SchwabOrderService
{
    private const BASE = 'https://api.schwabapi.com/trader/v1';

    public function __construct(
        private readonly SchwabTraderAuthService $auth,
    ) {}

    public static function make(): self
    {
        return new self(SchwabTraderAuthService::make());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUBLIC API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Open a new position for the bot.
     * Routes to paper or live depending on bot->paper_mode.
     *
     * @param  StrategyBot  $bot
     * @param  string       $direction   CALL | PUT | LONG | SHORT
     * @param  float        $entryPrice  Expected fill price
     * @param  float        $quantity    Shares / contracts
     * @param  float|null   $stopLoss
     * @param  float|null   $takeProfit1
     * @param  float|null   $takeProfit2
     * @param  float|null   $takeProfit3
     * @param  array        $signalData  Raw signal context to store
     * @return StrategyBotTrade
     */
    public function openPosition(
        StrategyBot           $bot,
        string                $direction,
        float                 $entryPrice,
        float                 $quantity,
        ?float                $stopLoss         = null,
        ?float                $takeProfit1      = null,
        ?float                $takeProfit2      = null,
        ?float                $takeProfit3      = null,
        array                 $signalData       = [],
        ?OptionContractData   $optionContract   = null,
    ): StrategyBotTrade {
        $tradeData = [
            'strategy_bot_id' => $bot->id,
            'symbol'          => $bot->symbol,
            'direction'       => $direction,
            'status'          => 'open',
            'entry_time'      => now(),
            'entry_price'     => $entryPrice,
            'quantity'        => $quantity,
            'entry_value'     => $entryPrice * $quantity,
            'stop_loss'       => $stopLoss,
            'take_profit_1'   => $takeProfit1,
            'take_profit_2'   => $takeProfit2,
            'take_profit_3'   => $takeProfit3,
            'signal_data'     => $signalData,
        ];

        // Options: store contract details
        if ($optionContract) {
            $tradeData['option_contract_symbol'] = $optionContract->contractSymbol;
            $tradeData['option_entry_price']     = $optionContract->mark;
            $tradeData['option_delta']           = $optionContract->delta;
            $tradeData['option_gamma']           = $optionContract->gamma;
            $tradeData['option_theta']           = $optionContract->theta;
            $tradeData['option_iv']              = $optionContract->impliedVolatility;
            $tradeData['option_strike']          = $optionContract->strike;
            $tradeData['option_expiry']          = $optionContract->expirationDate->toDateString();
            $tradeData['option_contracts']       = (int) $bot->option_contracts;
            // entry_value for options = premium * contracts * 100
            $tradeData['entry_value']            = ($optionContract->mark ?? 0) * ((int) $bot->option_contracts) * 100;
        }

        $trade = StrategyBotTrade::create($tradeData);

        if ($bot->paper_mode) {
            Log::info("StrategyBot #{$bot->id} [PAPER] opened {$direction} {$bot->symbol} @ {$entryPrice} x{$quantity}", [
                'trade_id' => $trade->id,
                'sl'  => $stopLoss,
                'tp1' => $takeProfit1,
            ]);
            return $trade;
        }

        // ── LIVE: send order to Schwab ─────────────────────────────────────
        $instruction = $this->directionToInstruction($direction);

        if ($bot->trade_type === 'options' && $optionContract) {
            $orderId = $this->placeSchwabOptionOrder(
                accountHash:      $bot->schwab_account_hash,
                contractSymbol:   $optionContract->contractSymbol,
                instruction:      'BUY_TO_OPEN',
                contracts:        (int) $bot->option_contracts,
                price:            $optionContract->mark,
                orderType:        $bot->option_order_type ?? 'mid',
                limitOffset:      (float) ($bot->option_limit_offset ?? 0.05),
            );
        } else {
            $orderId = $this->placeSchwabOrder(
                accountHash: $bot->schwab_account_hash,
                symbol:      $bot->symbol,
                instruction: $instruction,
                quantity:    $quantity,
                price:       $entryPrice,
            );
        }

        if ($orderId) {
            $trade->update(['schwab_order_id' => $orderId]);
            Log::info("StrategyBot #{$bot->id} [LIVE] order placed", [
                'schwab_order_id' => $orderId,
                'direction' => $direction,
                'price'     => $entryPrice,
                'qty'       => $quantity,
            ]);

            // Place bracket orders (stop + TP) if provided
            if ($stopLoss && $takeProfit1) {
                $this->placeBracketOrders($bot, $trade, $direction, $quantity, $stopLoss, $takeProfit1);
            }
        } else {
            Log::error("StrategyBot #{$bot->id} [LIVE] failed to place order", [
                'symbol'    => $bot->symbol,
                'direction' => $direction,
                'price'     => $entryPrice,
            ]);
            $trade->update(['status' => 'cancelled', 'exit_reason' => 'order_rejected']);
        }

        return $trade;
    }

    /**
     * Close an open position.
     * Paper: calculates P&L and updates balance.
     * Live: sends a closing order to Schwab.
     */
    public function closePosition(
        StrategyBotTrade $trade,
        float            $exitPrice,
        string           $reason = 'manual',
        ?float           $optionExitPrice = null,
    ): void {
        $bot = $trade->bot;

        // For options trades, P&L is based on premium difference × contracts × 100
        if ($bot->trade_type === 'options' && $trade->option_contract_symbol && $optionExitPrice !== null) {
            $pnl    = ($optionExitPrice - ($trade->option_entry_price ?? 0)) * ($trade->option_contracts ?? 1) * 100;
            $pnlPct = ($trade->entry_value > 0) ? ($pnl / $trade->entry_value) * 100 : 0.0;
        } else {
            $pnl    = $this->calcPnl($trade, $exitPrice);
            $pnlPct = $trade->entry_value > 0 ? ($pnl / $trade->entry_value) * 100 : 0.0;
        }

        if ($bot->paper_mode) {
            $update = [
                'status'     => 'closed',
                'exit_time'  => now(),
                'exit_price' => $exitPrice,
                'exit_reason'=> $reason,
                'pnl'        => $pnl,
                'pnl_pct'    => $pnlPct,
            ];
            if ($optionExitPrice !== null) {
                $update['option_exit_price'] = $optionExitPrice;
            }
            $trade->update($update);

            $bot->paper_balance = $bot->paper_balance + $pnl;
            $bot->save();
            $bot->recalcStats();

            Log::info("StrategyBot #{$bot->id} [PAPER] closed {$trade->direction} @ {$exitPrice} P&L={$pnl}", [
                'trade_id' => $trade->id,
                'reason'   => $reason,
            ]);
            return;
        }

        // ── LIVE: send closing order ───────────────────────────────────────
        if ($bot->trade_type === 'options' && $trade->option_contract_symbol) {
            $optQuote  = (new OptionContractSelector(SchwabAuthService::make()))->getContractQuote($trade->option_contract_symbol);
            $optClose  = $optQuote ? (float) $optQuote['mark'] : ($optionExitPrice ?? 0);
            $orderId   = $this->placeSchwabOptionOrder(
                accountHash:    $bot->schwab_account_hash,
                contractSymbol: $trade->option_contract_symbol,
                instruction:    'SELL_TO_CLOSE',
                contracts:      (int) ($trade->option_contracts ?? 1),
                price:          $optClose,
                orderType:      $bot->option_order_type ?? 'mid',
                limitOffset:    (float) ($bot->option_limit_offset ?? 0.05),
            );
            $optionExitPrice = $optClose;
        } else {
            $orderId = $this->placeSchwabOrder(
                accountHash: $bot->schwab_account_hash,
                symbol:      $bot->symbol,
                instruction: $this->oppositeInstruction($trade->direction),
                quantity:    $trade->quantity,
                price:       $exitPrice,
            );
        }

        $update = [
            'status'              => 'closed',
            'exit_time'           => now(),
            'exit_price'          => $exitPrice,
            'exit_reason'         => $reason,
            'pnl'                 => $pnl,
            'pnl_pct'             => $pnlPct,
            'schwab_exit_order_id'=> $orderId,
        ];
        if ($optionExitPrice !== null) {
            $update['option_exit_price'] = $optionExitPrice;
        }
        $trade->update($update);

        $bot->recalcStats();

        Log::info("StrategyBot #{$bot->id} [LIVE] close order sent", [
            'schwab_exit_order_id' => $orderId,
            'exit_price' => $exitPrice,
            'pnl'        => $pnl,
        ]);
    }

    /**
     * Check the fill status of a Schwab order.
     * Returns 'FILLED' | 'WORKING' | 'CANCELED' | 'REJECTED' | 'UNKNOWN'
     */
    public function getOrderStatus(string $accountHash, string $orderId): string
    {
        $token = $this->auth->getAccessToken();
        if (!$token) return 'UNKNOWN';

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept'        => 'application/json',
        ])->get(self::BASE . "/accounts/{$accountHash}/orders/{$orderId}");

        if ($response->successful()) {
            return $response->json('status') ?? 'UNKNOWN';
        }

        Log::warning("SchwabOrderService: getOrderStatus failed", [
            'order_id' => $orderId,
            'status'   => $response->status(),
            'body'     => $response->body(),
        ]);

        return 'UNKNOWN';
    }

    /**
     * Cancel a Schwab order.
     */
    public function cancelOrder(string $accountHash, string $orderId): bool
    {
        $token = $this->auth->getAccessToken();
        if (!$token) return false;

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->delete(self::BASE . "/accounts/{$accountHash}/orders/{$orderId}");

        if ($response->successful()) {
            Log::info("SchwabOrderService: order {$orderId} cancelled");
            return true;
        }

        Log::warning("SchwabOrderService: cancel failed for {$orderId}", [
            'status' => $response->status(),
            'body'   => $response->body(),
        ]);

        return false;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Place a single-leg options order (BUY_TO_OPEN or SELL_TO_CLOSE).
     */
    private function placeSchwabOptionOrder(
        string $accountHash,
        string $contractSymbol,
        string $instruction,    // BUY_TO_OPEN | SELL_TO_CLOSE
        int    $contracts,
        float  $price,
        string $orderType   = 'mid',
        float  $limitOffset = 0.05,
    ): ?string {
        $token = $this->auth->getAccessToken();
        if (!$token) return null;

        // Determine limit price
        $limitPrice = match ($orderType) {
            'market' => null,
            'mid'    => round($price, 2),
            'limit'  => $instruction === 'BUY_TO_OPEN'
                ? round($price + $limitOffset, 2)
                : round($price - $limitOffset, 2),
            default  => round($price, 2),
        };

        $payload = [
            'orderType'         => $orderType === 'market' ? 'MARKET' : 'LIMIT',
            'session'           => 'NORMAL',
            'duration'          => 'DAY',
            'orderStrategyType' => 'SINGLE',
            'orderLegCollection' => [
                [
                    'instruction' => $instruction,
                    'quantity'    => $contracts,
                    'instrument'  => [
                        'symbol'    => $contractSymbol,
                        'assetType' => 'OPTION',
                    ],
                ],
            ],
        ];

        if ($limitPrice !== null) {
            $payload['price'] = $limitPrice;
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Content-Type'  => 'application/json',
        ])->post(self::BASE . "/accounts/{$accountHash}/orders", $payload);

        if ($response->successful() || $response->status() === 201) {
            $location = $response->header('Location') ?? '';
            $orderId  = basename($location) ?: ($response->json('orderId') ?? null);
            Log::info("SchwabOrderService: option order placed [{$instruction}]", [
                'contract' => $contractSymbol,
                'contracts' => $contracts,
                'price'    => $limitPrice,
                'order_id' => $orderId,
            ]);
            return (string) $orderId;
        }

        Log::error("SchwabOrderService: option order rejected", [
            'contract'   => $contractSymbol,
            'instruction'=> $instruction,
            'status'     => $response->status(),
            'body'       => $response->body(),
        ]);
        return null;
    }

    /**
     * Send a single-leg equity/ETF market or limit order to Schwab.
     * Returns the Schwab order ID on success, null on failure.
     */
    private function placeSchwabOrder(
        string $accountHash,
        string $symbol,
        string $instruction,   // BUY | SELL | SELL_SHORT | BUY_TO_COVER
        float  $quantity,
        float  $price,
    ): ?string {
        $token = $this->auth->getAccessToken();
        if (!$token) {
            Log::error('SchwabOrderService: no access token available');
            return null;
        }

        $payload = [
            'orderType'          => 'LIMIT',
            'session'            => 'NORMAL',
            'price'              => round($price, 2),
            'duration'           => 'DAY',
            'orderStrategyType'  => 'SINGLE',
            'orderLegCollection' => [
                [
                    'instruction' => $instruction,
                    'quantity'    => (int) $quantity,
                    'instrument'  => [
                        'symbol'    => strtoupper($symbol),
                        'assetType' => 'EQUITY',
                    ],
                ],
            ],
        ];

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Content-Type'  => 'application/json',
        ])->post(self::BASE . "/accounts/{$accountHash}/orders", $payload);

        if ($response->successful() || $response->status() === 201) {
            // Schwab returns the order ID in the Location header
            $location = $response->header('Location') ?? '';
            $orderId = basename($location) ?: null;

            if (!$orderId) {
                // Fallback: try body
                $orderId = $response->json('orderId') ?? null;
            }

            Log::info("SchwabOrderService: order placed", [
                'symbol'      => $symbol,
                'instruction' => $instruction,
                'price'       => $price,
                'qty'         => $quantity,
                'order_id'    => $orderId,
            ]);

            return (string) $orderId;
        }

        Log::error("SchwabOrderService: order rejected", [
            'symbol'      => $symbol,
            'instruction' => $instruction,
            'price'       => $price,
            'qty'         => $quantity,
            'http_status' => $response->status(),
            'body'        => $response->body(),
        ]);

        return null;
    }

    /**
     * Place a One-Cancels-Other (OCO) bracket for stop + take-profit.
     * This creates a child OTA (Order Triggers Another) order linked to the parent fill.
     */
    private function placeBracketOrders(
        StrategyBot      $bot,
        StrategyBotTrade $trade,
        string           $direction,
        float            $quantity,
        float            $stopLoss,
        float            $takeProfit,
    ): void {
        $token = $this->auth->getAccessToken();
        if (!$token) return;

        $closeInstruction = $this->oppositeInstruction($direction);

        $payload = [
            'orderStrategyType' => 'OCO',
            'childOrderStrategies' => [
                // Stop Loss
                [
                    'orderType'          => 'STOP',
                    'session'            => 'NORMAL',
                    'stopPrice'          => round($stopLoss, 2),
                    'duration'           => 'DAY',
                    'orderStrategyType'  => 'SINGLE',
                    'orderLegCollection' => [
                        [
                            'instruction' => $closeInstruction,
                            'quantity'    => (int) $quantity,
                            'instrument'  => [
                                'symbol'    => strtoupper($bot->symbol),
                                'assetType' => 'EQUITY',
                            ],
                        ],
                    ],
                ],
                // Take Profit
                [
                    'orderType'          => 'LIMIT',
                    'session'            => 'NORMAL',
                    'price'              => round($takeProfit, 2),
                    'duration'           => 'DAY',
                    'orderStrategyType'  => 'SINGLE',
                    'orderLegCollection' => [
                        [
                            'instruction' => $closeInstruction,
                            'quantity'    => (int) $quantity,
                            'instrument'  => [
                                'symbol'    => strtoupper($bot->symbol),
                                'assetType' => 'EQUITY',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Content-Type'  => 'application/json',
        ])->post(self::BASE . "/accounts/{$bot->schwab_account_hash}/orders", $payload);

        if ($response->successful() || $response->status() === 201) {
            Log::info("SchwabOrderService: OCO bracket placed for trade #{$trade->id}");
        } else {
            Log::warning("SchwabOrderService: OCO bracket failed for trade #{$trade->id}", [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UTILITIES
    // ─────────────────────────────────────────────────────────────────────────

    private function directionToInstruction(string $direction): string
    {
        return match ($direction) {
            'CALL', 'LONG'  => 'BUY',
            'PUT', 'SHORT'  => 'SELL_SHORT',
            default         => 'BUY',
        };
    }

    private function oppositeInstruction(string $direction): string
    {
        return match ($direction) {
            'CALL', 'LONG'  => 'SELL',
            'PUT', 'SHORT'  => 'BUY_TO_COVER',
            default         => 'SELL',
        };
    }

    private function calcPnl(StrategyBotTrade $trade, float $exitPrice): float
    {
        return match ($trade->direction) {
            'CALL', 'LONG'  => ($exitPrice - $trade->entry_price) * $trade->quantity,
            'PUT', 'SHORT'  => ($trade->entry_price - $exitPrice) * $trade->quantity,
            default         => 0.0,
        };
    }
}

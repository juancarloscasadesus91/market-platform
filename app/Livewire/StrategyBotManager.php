<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Jobs\RunStrategyBotJob;
use App\Models\StrategyBot;
use App\Models\StrategyBotTrade;
use App\Services\PaperOptionPricingService;
use App\Services\SchwabOrderService;
use App\Services\SchwabTraderAuthService;
use App\Strategies\StrategyRegistry;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class StrategyBotManager extends Component
{
    // ── List view ───────────────────────────────────────────────────────
    public string $view = 'list';      // list | create | detail | edit
    public ?int   $selectedBotId = null;

    // ── Create / Edit form ───────────────────────────────────────────────
    public string $formName          = '';
    public string $formStrategyKey   = 'ema_pullback';
    public string $formSymbol        = 'SPY';
    public string $formTimeframe     = '5m';
    public bool   $formPaperMode     = true;
    public float  $formPaperBudget   = 10000.00;
    public string $formPositionSizeType  = 'fixed_dollars';
    public float  $formPositionSizeValue = 1000.00;
    public ?float $formRiskPerTradePct   = null;
    public int    $formMaxConcurrent     = 1;
    public ?float $formMaxDailyLossPct   = null;
    public string $formSchwabAccountHash = '';
    public array  $formParams            = [];

    // ── Options config ───────────────────────────────────────────────────
    public string $formTradeType          = 'equity';  // equity | options
    public float  $formOptionDeltaTarget  = 0.40;
    public float  $formOptionDeltaTol     = 0.05;
    public int    $formOptionMaxDte       = 7;
    public int    $formOptionMinDte       = 1;
    public int    $formOptionContracts    = 1;
    public ?float $formOptionStopLossPct  = 50.0;    // exit if contract loses 50%
    public ?float $formOptionTpPct        = 100.0;   // exit if contract gains 100%
    public string $formOptionOrderType    = 'mid';   // mid | limit | market
    public float  $formOptionLimitOffset  = 0.05;

    // ── Detail view state ────────────────────────────────────────────────
    public string $detailTab      = 'trades';
    public string $tradesFilter   = 'all';   // all | open | closed

    // ── Schwab accounts for dropdown ────────────────────────────────────
    public array $schwabAccounts = [];

    // ── Alerts ──────────────────────────────────────────────────────────
    public ?string $successMessage = null;
    public ?string $errorMessage   = null;

    public function mount(): void
    {
        $this->loadSchwabAccounts();
        $this->loadStrategyDefaults();
    }

    // ── Strategy param defaults ──────────────────────────────────────────

    public function updatedFormStrategyKey(): void
    {
        $this->loadStrategyDefaults();
    }

    private function loadStrategyDefaults(): void
    {
        try {
            $strategy = StrategyRegistry::resolve($this->formStrategyKey);
            $this->formParams = [];
            foreach ($strategy->schema() as $field) {
                $this->formParams[$field['key']] = $field['default'];
            }
        } catch (\Throwable) {
            $this->formParams = [];
        }
    }

    // ── Schwab accounts loader ───────────────────────────────────────────

    private function loadSchwabAccounts(): void
    {
        $traderAuth = SchwabTraderAuthService::make();
        $token = $traderAuth->getAccessToken();
        if (!$token) {
            $this->schwabAccounts = [];
            return;
        }
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ])->get('https://api.schwabapi.com/trader/v1/accounts');

            if ($response->successful()) {
                $data = $response->json() ?? [];
                $this->schwabAccounts = collect($data)->map(function ($a) {
                    $sec = $a['securitiesAccount'] ?? [];
                    return [
                        'hash'   => $a['hashValue'] ?? $sec['accountNumber'] ?? '',
                        'number' => $sec['accountNumber'] ?? 'N/A',
                        'type'   => $sec['type'] ?? '',
                    ];
                })->toArray();
            }
        } catch (\Exception $e) {
            Log::warning('StrategyBotManager: could not load Schwab accounts', ['err' => $e->getMessage()]);
        }
    }

    // ── Navigation ───────────────────────────────────────────────────────

    public function showCreate(): void
    {
        $this->resetForm();
        $this->view = 'create';
        $this->clearMessages();
    }

    public function showList(): void
    {
        $this->view = 'list';
        $this->selectedBotId = null;
        $this->clearMessages();
    }

    public function showDetail(int $id): void
    {
        $this->selectedBotId = $id;
        $this->view = 'detail';
        $this->detailTab = 'trades';
        $this->clearMessages();
    }

    public function showEdit(int $id): void
    {
        $bot = StrategyBot::findOrFail($id);
        $this->selectedBotId = $id;
        $this->formName             = $bot->name;
        $this->formStrategyKey      = $bot->strategy_key;
        $this->formSymbol           = $bot->symbol;
        $this->formTimeframe        = $bot->timeframe;
        $this->formPaperMode        = $bot->paper_mode;
        $this->formPaperBudget      = $bot->paper_budget;
        $this->formPositionSizeType = $bot->position_size_type;
        $this->formPositionSizeValue= $bot->position_size_value;
        $this->formRiskPerTradePct  = $bot->risk_per_trade_pct;
        $this->formMaxConcurrent    = $bot->max_concurrent_trades;
        $this->formMaxDailyLossPct  = $bot->max_daily_loss_pct;
        $this->formSchwabAccountHash= $bot->schwab_account_hash ?? '';
        $this->formParams           = $bot->strategy_params ?? [];
        $this->formTradeType         = $bot->trade_type ?? 'equity';
        $this->formOptionDeltaTarget = (float) ($bot->option_delta_target ?? 0.40);
        $this->formOptionDeltaTol    = (float) ($bot->option_delta_tolerance ?? 0.05);
        $this->formOptionMaxDte      = (int)   ($bot->option_max_dte ?? 7);
        $this->formOptionMinDte      = (int)   ($bot->option_min_dte ?? 1);
        $this->formOptionContracts   = (int)   ($bot->option_contracts ?? 1);
        $this->formOptionStopLossPct = $bot->option_stop_loss_pct;
        $this->formOptionTpPct       = $bot->option_take_profit_pct;
        $this->formOptionOrderType   = $bot->option_order_type ?? 'mid';
        $this->formOptionLimitOffset = (float) ($bot->option_limit_offset ?? 0.05);
        $this->view = 'edit';
        $this->clearMessages();
    }

    // ── CRUD ────────────────────────────────────────────────────────────

    public function createBot(): void
    {
        $this->validateForm();

        $bot = StrategyBot::create([
            'name'                    => $this->formName,
            'strategy_key'            => $this->formStrategyKey,
            'symbol'                  => strtoupper(trim($this->formSymbol)),
            'timeframe'               => $this->formTimeframe,
            'trade_type'              => $this->formTradeType,
            'paper_mode'              => $this->formPaperMode,
            'paper_budget'            => $this->formPaperBudget,
            'paper_balance'           => $this->formPaperBudget,
            'position_size_type'      => $this->formPositionSizeType,
            'position_size_value'     => $this->formPositionSizeValue,
            'risk_per_trade_pct'      => $this->formRiskPerTradePct,
            'max_concurrent_trades'   => $this->formMaxConcurrent,
            'max_daily_loss_pct'      => $this->formMaxDailyLossPct,
            'schwab_account_hash'     => $this->formPaperMode ? null : ($this->formSchwabAccountHash ?: null),
            'strategy_params'         => $this->formParams,
            'status'                  => 'idle',
            'option_delta_target'     => $this->formTradeType === 'options' ? $this->formOptionDeltaTarget : null,
            'option_delta_tolerance'  => $this->formOptionDeltaTol,
            'option_max_dte'          => $this->formOptionMaxDte,
            'option_min_dte'          => $this->formOptionMinDte,
            'option_contracts'        => $this->formOptionContracts,
            'option_stop_loss_pct'    => $this->formTradeType === 'options' ? $this->formOptionStopLossPct : null,
            'option_take_profit_pct'  => $this->formTradeType === 'options' ? $this->formOptionTpPct : null,
            'option_order_type'       => $this->formOptionOrderType,
            'option_limit_offset'     => $this->formOptionLimitOffset,
        ]);

        $this->successMessage = "Bot \"{$bot->name}\" created successfully!";
        $this->showDetail($bot->id);
    }

    public function updateBot(): void
    {
        $this->validateForm();
        $bot = StrategyBot::findOrFail($this->selectedBotId);

        $bot->update([
            'name'                    => $this->formName,
            'strategy_key'            => $this->formStrategyKey,
            'symbol'                  => strtoupper(trim($this->formSymbol)),
            'timeframe'               => $this->formTimeframe,
            'trade_type'              => $this->formTradeType,
            'paper_mode'              => $this->formPaperMode,
            'paper_budget'            => $this->formPaperBudget,
            'position_size_type'      => $this->formPositionSizeType,
            'position_size_value'     => $this->formPositionSizeValue,
            'risk_per_trade_pct'      => $this->formRiskPerTradePct,
            'max_concurrent_trades'   => $this->formMaxConcurrent,
            'max_daily_loss_pct'      => $this->formMaxDailyLossPct,
            'schwab_account_hash'     => $this->formPaperMode ? null : ($this->formSchwabAccountHash ?: null),
            'strategy_params'         => $this->formParams,
            'option_delta_target'     => $this->formTradeType === 'options' ? $this->formOptionDeltaTarget : null,
            'option_delta_tolerance'  => $this->formOptionDeltaTol,
            'option_max_dte'          => $this->formOptionMaxDte,
            'option_min_dte'          => $this->formOptionMinDte,
            'option_contracts'        => $this->formOptionContracts,
            'option_stop_loss_pct'    => $this->formTradeType === 'options' ? $this->formOptionStopLossPct : null,
            'option_take_profit_pct'  => $this->formTradeType === 'options' ? $this->formOptionTpPct : null,
            'option_order_type'       => $this->formOptionOrderType,
            'option_limit_offset'     => $this->formOptionLimitOffset,
        ]);

        $this->successMessage = "Bot updated successfully!";
        $this->showDetail($bot->id);
    }

    public function deleteBot(int $id): void
    {
        $bot = StrategyBot::findOrFail($id);
        if ($bot->status === 'running') {
            $this->errorMessage = "Stop the bot before deleting it.";
            return;
        }
        $name = $bot->name;
        $bot->delete();
        $this->successMessage = "Bot \"{$name}\" deleted.";
        $this->showList();
    }

    // ── Bot Controls ────────────────────────────────────────────────────

    public function startBot(int $id): void
    {
        $bot = StrategyBot::findOrFail($id);

        if (!$bot->paper_mode && empty($bot->schwab_account_hash)) {
            $this->errorMessage = "Set a Schwab account before running in live mode.";
            return;
        }

        $bot->update([
            'status'     => 'running',
            'started_at' => now(),
            'stopped_at' => null,
            'stop_reason'=> null,
        ]);

        // Run paper bots immediately even when a local queue worker is not running.
        if ($bot->paper_mode) {
            RunStrategyBotJob::dispatchSync($bot->id);
        } else {
            RunStrategyBotJob::dispatch($bot->id);
        }

        Log::info("StrategyBot #{$bot->id} \"{$bot->name}\" started", [
            'paper'  => $bot->paper_mode,
            'symbol' => $bot->symbol,
        ]);

        $this->successMessage = "Bot \"{$bot->name}\" is now running" . ($bot->paper_mode ? ' (paper mode)' : ' (LIVE)') . '.';
    }

    public function pauseBot(int $id): void
    {
        $bot = StrategyBot::findOrFail($id);
        $bot->update(['status' => 'paused']);
        $this->successMessage = "Bot \"{$bot->name}\" paused.";
    }

    public function resumeBot(int $id): void
    {
        $bot = StrategyBot::findOrFail($id);
        $bot->update(['status' => 'running']);
        if ($bot->paper_mode) {
            RunStrategyBotJob::dispatchSync($bot->id);
        } else {
            RunStrategyBotJob::dispatch($bot->id);
        }
        $this->successMessage = "Bot \"{$bot->name}\" resumed.";
    }

    public function stopBot(int $id): void
    {
        $bot = StrategyBot::findOrFail($id);
        $bot->update([
            'status'      => 'stopped',
            'stopped_at'  => now(),
            'stop_reason' => 'Manual stop',
        ]);
        $this->successMessage = "Bot \"{$bot->name}\" stopped.";
    }

    public function resetPaperBalance(int $id): void
    {
        $bot = StrategyBot::findOrFail($id);
        if (!$bot->paper_mode) {
            $this->errorMessage = "Reset only available in paper mode.";
            return;
        }
        if ($bot->status === 'running') {
            $this->errorMessage = "Stop the bot first.";
            return;
        }
        $bot->trades()->delete();
        $bot->update([
            'paper_balance' => $bot->paper_budget,
            'total_trades'  => 0,
            'winning_trades'=> 0,
            'losing_trades' => 0,
            'total_pnl'     => 0,
            'total_pnl_pct' => 0,
            'max_drawdown'  => 0,
        ]);
        $this->successMessage = "Paper balance reset to \${$bot->paper_budget}.";
    }

    // ── Manual paper trade simulation ───────────────────────────────────

    public function simulatePaperSignal(int $id, string $direction, float $entryPrice): void
    {
        $bot = StrategyBot::findOrFail($id);
        if (!$bot->paper_mode) {
            $this->errorMessage = "Manual simulation only in paper mode.";
            return;
        }

        // ── Calculate index SL / TP levels (same logic as RunStrategyBotJob) ─
        $params  = $bot->strategy_params ?? [];
        $stopPct = (float) ($params['stop_pct'] ?? $params['stop_buffer_pct'] ?? 1.0);
        $tp1Val  = (float) ($params['tp1_value'] ?? 1.0);
        $tp2Val  = (float) ($params['tp2_value'] ?? $tp1Val * 2);
        $tp3Val  = (float) ($params['tp3_value'] ?? $tp1Val * 3);
        $isRR    = ($params['tp_type'] ?? 'risk_ratio') === 'risk_ratio';
        $isLong  = in_array($direction, ['CALL', 'LONG']);

        $sl = $isLong
            ? $entryPrice * (1 - $stopPct / 100)
            : $entryPrice * (1 + $stopPct / 100);
        $risk = abs($entryPrice - $sl);

        $calcTp = function (float $mult) use ($isLong, $entryPrice, $risk, $isRR, $stopPct): float {
            if ($isRR) {
                return $isLong ? $entryPrice + $risk * $mult : $entryPrice - $risk * $mult;
            }
            return $isLong
                ? $entryPrice * (1 + $mult / 100)
                : $entryPrice * (1 - $mult / 100);
        };

        $tp1Price = $calcTp($tp1Val);
        $tp2Price = $calcTp($tp2Val);
        $tp3Price = $calcTp($tp3Val);

        // ── Options: select contract exactly like the job ─────────────────
        $optionContract = null;
        if ($bot->trade_type === 'options') {
            try {
                $selector       = \App\Services\OptionContractSelector::make();
                $optionContract = $selector->selectContract($bot, $direction);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("simulatePaperSignal: contract selector failed", ['error' => $e->getMessage()]);
            }

            if (!$optionContract) {
                $optionContract = app(PaperOptionPricingService::class)
                    ->makeSyntheticContract($bot, $direction, $entryPrice);

                Log::info("simulatePaperSignal: using synthetic paper contract", [
                    'bot_id' => $bot->id,
                    'contract' => $optionContract->contractSymbol,
                    'mark' => $optionContract->mark,
                    'delta' => $optionContract->delta,
                ]);
            }

            if (!$optionContract) {
                $this->errorMessage = "No option contract found matching your delta target ({$bot->option_delta_target}) within ±{$bot->option_delta_tolerance}. Try wider tolerance or different DTE range.";
                return;
            }
        }

        // ── Position sizing ───────────────────────────────────────────────
        $qty = $this->calcQuantity($bot, $entryPrice);
        if ($qty <= 0) {
            $this->errorMessage = "Insufficient paper balance.";
            return;
        }

        // ── Open via SchwabOrderService (same as job) ─────────────────────
        $orderService = SchwabOrderService::make();
        $trade = $orderService->openPosition(
            bot:            $bot,
            direction:      $direction,
            entryPrice:     $entryPrice,
            quantity:       $qty,
            stopLoss:       $sl,
            takeProfit1:    $tp1Price,
            takeProfit2:    $tp2Price,
            takeProfit3:    $tp3Price,
            signalData:     ['source' => 'manual_paper_sim'],
            optionContract: $optionContract,
        );

        if ($bot->trade_type === 'options' && $optionContract) {
            $this->successMessage = "Paper {$direction} opened — contract: {$optionContract->contractSymbol} @ \${$optionContract->mark} (Δ " . number_format(abs($optionContract->delta ?? 0), 2) . ") · {$bot->option_contracts} contract(s)";
        } else {
            $this->successMessage = "Paper {$direction} opened @ \${$entryPrice} ×{$qty}";
        }
    }

    public function closePaperTrade(int $tradeId, float $exitPrice, string $reason = 'manual'): void
    {
        $trade = StrategyBotTrade::with('bot')->findOrFail($tradeId);
        $bot   = $trade->bot;

        $orderService = SchwabOrderService::make();
        $optionExitPrice = null;
        if ($bot->paper_mode && $bot->trade_type === 'options' && $trade->option_contract_symbol) {
            $optionExitPrice = app(PaperOptionPricingService::class)->estimateExitMark($trade, $exitPrice);
        }

        $orderService->closePosition($trade, $exitPrice, $reason, $optionExitPrice);

        $pnl = ($trade->direction === 'CALL' || $trade->direction === 'LONG')
            ? ($exitPrice - $trade->entry_price) * $trade->quantity
            : ($trade->entry_price - $exitPrice) * $trade->quantity;

        $this->successMessage = "Trade closed @ \${$exitPrice}. P&L: " . ($pnl >= 0 ? '+$' : '-$') . number_format(abs($pnl), 2);
    }

    // ── Position sizing ─────────────────────────────────────────────────

    private function calcQuantity(StrategyBot $bot, float $price): float
    {
        // Options: quantity = number of contracts (not shares of the index)
        if ($bot->trade_type === 'options') {
            return max(1, (int) ($bot->option_contracts ?? 1));
        }

        if ($price <= 0) return 0;
        return match ($bot->position_size_type) {
            'fixed_shares'  => $bot->position_size_value,
            'fixed_dollars' => floor($bot->position_size_value / $price),
            'risk_pct'      => $bot->risk_per_trade_pct
                ? floor(($bot->paper_balance * $bot->risk_per_trade_pct / 100) / $price)
                : 1,
            default => 1,
        };
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    private function validateForm(): void
    {
        $this->validate([
            'formName'       => 'required|min:2',
            'formSymbol'     => 'required|min:1',
            'formTimeframe'  => 'required',
            'formPaperBudget'=> 'required|numeric|min:100',
        ], [
            'formName.required'  => 'Bot name is required.',
            'formSymbol.required'=> 'Symbol is required.',
            'formPaperBudget.min'=> 'Minimum budget is $100.',
        ]);
    }

    private function resetForm(): void
    {
        $this->formName             = '';
        $this->formStrategyKey      = 'ema_pullback';
        $this->formSymbol           = 'SPY';
        $this->formTimeframe        = '5m';
        $this->formPaperMode        = true;
        $this->formPaperBudget      = 10000.00;
        $this->formPositionSizeType = 'fixed_dollars';
        $this->formPositionSizeValue= 1000.00;
        $this->formRiskPerTradePct  = null;
        $this->formMaxConcurrent    = 1;
        $this->formMaxDailyLossPct  = null;
        $this->formSchwabAccountHash= '';
        $this->formTradeType         = 'equity';
        $this->formOptionDeltaTarget = 0.40;
        $this->formOptionDeltaTol    = 0.05;
        $this->formOptionMaxDte      = 7;
        $this->formOptionMinDte      = 1;
        $this->formOptionContracts   = 1;
        $this->formOptionStopLossPct = 50.0;
        $this->formOptionTpPct       = 100.0;
        $this->formOptionOrderType   = 'mid';
        $this->formOptionLimitOffset = 0.05;
        $this->loadStrategyDefaults();
    }

    private function clearMessages(): void
    {
        $this->successMessage = null;
        $this->errorMessage   = null;
    }

    public function setDetailTab(string $tab): void
    {
        $this->detailTab = $tab;
    }

    public function setTradesFilter(string $f): void
    {
        $this->tradesFilter = $f;
    }

    public function render()
    {
        $bots = StrategyBot::withCount(['trades', 'openTrades'])
            ->latest()
            ->get();

        $selectedBot = $this->selectedBotId
            ? StrategyBot::find($this->selectedBotId)
            : null;

        $botTrades = collect();
        if ($selectedBot) {
            $q = $selectedBot->trades()->latest('entry_time');
            if ($this->tradesFilter === 'open')   $q->where('status', 'open');
            if ($this->tradesFilter === 'closed')  $q->where('status', 'closed');
            $botTrades = $q->limit(200)->get();
        }

        // Fetch live prices for all symbols with open trades
        $livePrices       = [];
        $liveOptionQuotes = [];
        if ($selectedBot && $this->view === 'detail') {
            $openTrades = $botTrades->where('status', 'open');

            $openSymbols = $openTrades->pluck('symbol')->unique()->values()->toArray();
            if (!empty($openSymbols)) {
                $livePrices = $this->fetchLivePrices($openSymbols);
            }

            // Fetch live option contract quotes (mark, delta, theta, IV)
            $optionContracts = $openTrades
                ->whereNotNull('option_contract_symbol')
                ->pluck('option_contract_symbol')
                ->filter()
                ->unique()
                ->values()
                ->toArray();
            if (!empty($optionContracts)) {
                $liveOptionQuotes = $this->fetchLiveOptionQuotes($optionContracts);
            }

            foreach ($openTrades as $trade) {
                if (
                    $selectedBot->paper_mode
                    && $selectedBot->trade_type === 'options'
                    && $trade->option_contract_symbol
                    && !isset($liveOptionQuotes[$trade->option_contract_symbol])
                ) {
                    $underlyingPrice = $livePrices[strtoupper($trade->symbol)] ?? null;
                    if ($underlyingPrice) {
                        $estimatedMark = app(PaperOptionPricingService::class)
                            ->estimateExitMark($trade, (float) $underlyingPrice);

                        if ($estimatedMark !== null) {
                            $liveOptionQuotes[$trade->option_contract_symbol] = [
                                'bid' => max(0.01, $estimatedMark - 0.05),
                                'ask' => $estimatedMark + 0.05,
                                'mark' => $estimatedMark,
                                'last' => $estimatedMark,
                                'delta' => $trade->option_delta,
                                'gamma' => $trade->option_gamma,
                                'theta' => $trade->option_theta,
                                'iv' => $trade->option_iv,
                            ];
                        }
                    }
                }
            }
        }

        $strategyOptions = StrategyRegistry::options();
        $strategySchema  = [];
        try {
            $strategy = StrategyRegistry::resolve($this->formStrategyKey);
            $strategySchema = $strategy->schema();
        } catch (\Throwable) {}

        // Group schema by group
        $schemaGroups = collect($strategySchema)->groupBy('group');

        return view('livewire.strategy-bot-manager', [
            'bots'             => $bots,
            'selectedBot'      => $selectedBot,
            'botTrades'        => $botTrades,
            'livePrices'       => $livePrices,
            'liveOptionQuotes' => $liveOptionQuotes,
            'strategyOptions'  => $strategyOptions,
            'schemaGroups'     => $schemaGroups,
        ]);
    }

    /**
     * Fetch last price for multiple symbols in a single Schwab quotes request.
     * Returns ['SPY' => 591.23, 'QQQ' => 480.10, ...]
     */
    private function fetchLivePrices(array $symbols): array
    {
        try {
            $authService = \App\Services\SchwabAuthService::make();
            $token = $authService->getAccessToken();
            if (!$token) return [];

            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Accept'        => 'application/json',
            ])->get('https://api.schwabapi.com/marketdata/v1/quotes', [
                'symbols' => implode(',', array_map('strtoupper', $symbols)),
                'fields'  => 'quote',
            ]);

            if (!$response->successful()) return [];

            $data   = $response->json() ?? [];
            $prices = [];
            foreach ($symbols as $sym) {
                $key = strtoupper($sym);
                $prices[$key] = (float) ($data[$key]['quote']['lastPrice']
                    ?? $data[$key]['quote']['mark']
                    ?? $data[$key]['quote']['closePrice']
                    ?? 0);
            }
            return $prices;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Fetch live quotes for option contracts.
     * Returns ['SPY 250620C590' => ['bid'=>..., 'ask'=>..., 'mark'=>..., 'delta'=>..., ...]]
     */
    private function fetchLiveOptionQuotes(array $contractSymbols): array
    {
        try {
            $selector = \App\Services\OptionContractSelector::make();
            $quotes   = [];
            foreach ($contractSymbols as $sym) {
                $q = $selector->getContractQuote($sym);
                if ($q) $quotes[$sym] = $q;
            }
            return $quotes;
        } catch (\Throwable) {
            return [];
        }
    }
}

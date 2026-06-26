<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\AlpacaTradingService;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class AlpacaPaperAccount extends Component
{
    public array $account = [];
    public array $positions = [];
    public array $openOrders = [];
    public array $latestOrders = [];

    public ?string $successMessage = null;
    public ?string $errorMessage = null;
    public bool $configured = false;
    public string $mode = 'paper';
    public bool $confirmModalOpen = false;
    public ?string $confirmAction = null;
    public array $confirmPayload = [];
    public string $confirmTitle = '';
    public string $confirmMessage = '';
    public string $confirmButton = 'Confirm';
    public string $confirmTone = 'blue';

    public string $symbol = 'SPY';
    public string $assetClass = 'equity';
    public string $side = 'buy';
    public string $orderType = 'market';
    public string $timeInForce = 'day';
    public ?float $qty = 1.0;
    public ?float $notional = null;
    public ?float $limitPrice = null;
    public ?float $stopPrice = null;
    public bool $extendedHours = false;
    public string $optionType = 'call';
    public ?string $optionExpirationDate = null;
    public ?float $optionMinStrike = null;
    public ?float $optionMaxStrike = null;
    public ?string $selectedOptionSymbol = null;
    public array $optionContracts = [];
    public array $optionQuotes = [];

    public function mount(): void
    {
        $this->mode = session('alpaca_trading_mode', 'paper') === 'live' ? 'live' : 'paper';
        $this->refreshData();
    }

    public function refreshData(): void
    {
        $service = $this->service();
        $this->configured = $service->isConfigured();

        if (!$this->configured) {
            $this->account = [];
            $this->positions = [];
            $this->openOrders = [];
            $this->latestOrders = [];
            $this->errorMessage = $this->mode === 'live'
                ? 'Configure ALPACA_LIVE_API_KEY and ALPACA_LIVE_API_SECRET in .env to use Alpaca Live.'
                : 'Configure ALPACA_PAPER_API_KEY and ALPACA_PAPER_API_SECRET in .env to use Alpaca Paper.';
            return;
        }

        try {
            $this->account = $service->account();
            $this->positions = $service->positions();
            $this->openOrders = $service->openOrders();
            $this->latestOrders = $service->latestOrders();
        } catch (\Throwable $e) {
            Log::warning('AlpacaPaperAccount: refresh failed', ['error' => $e->getMessage()]);
            $this->errorMessage = $e->getMessage();
        }
    }

    public function submitOrder(): void
    {
        $this->clearMessages();
        if (!$this->validateOrderForDisplay()) {
            return;
        }

        if ($this->mode === 'live') {
            $this->openConfirmModal(
                action: 'submit_order',
                title: 'Submit Live Order',
                message: 'This order will be sent to your real Alpaca account.',
                button: 'Submit Live Order',
                tone: 'rose',
            );
            return;
        }

        $this->executeSubmitOrder();
    }

    public function executeSubmitOrder(): void
    {
        $this->clearMessages();
        if (!$this->validateOrderForDisplay()) {
            return;
        }

        $payload = [
            'symbol' => $this->assetClass === 'option'
                ? (string) $this->selectedOptionSymbol
                : strtoupper(trim($this->symbol)),
            'side' => $this->side,
            'type' => $this->orderType,
            'time_in_force' => $this->timeInForce,
        ];

        if ($this->assetClass === 'equity' && $this->notional !== null && $this->notional > 0) {
            $payload['notional'] = $this->formatNumber($this->notional);
        } else {
            $payload['qty'] = $this->formatNumber((float) $this->qty);
        }

        if (in_array($this->orderType, ['limit', 'stop_limit'], true)) {
            $payload['limit_price'] = $this->formatNumber((float) $this->limitPrice);
        }

        if (in_array($this->orderType, ['stop', 'stop_limit'], true)) {
            $payload['stop_price'] = $this->formatNumber((float) $this->stopPrice);
        }

        if ($this->assetClass === 'equity' && $this->extendedHours) {
            $payload['extended_hours'] = true;
        }

        try {
            $order = $this->service()->submitOrder($payload);
            $this->successMessage = strtoupper($this->mode) . ' order submitted: ' . strtoupper((string) ($order['symbol'] ?? $payload['symbol']))
                . ' ' . strtoupper((string) ($order['side'] ?? $this->side))
                . ' ' . ((string) ($order['qty'] ?? $order['notional'] ?? ''));
            $this->refreshData();
        } catch (\Throwable $e) {
            Log::warning('AlpacaPaperAccount: submit order failed', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
            $this->errorMessage = $e->getMessage();
        }
    }

    public function cancelOrder(string $orderId): void
    {
        $this->openConfirmModal(
            action: 'cancel_order',
            title: 'Cancel Order',
            message: 'This will request cancellation for the selected Alpaca order.',
            button: 'Cancel Order',
            tone: 'amber',
            payload: ['order_id' => $orderId],
        );
    }

    public function executeCancelOrder(string $orderId): void
    {
        $this->clearMessages();

        try {
            $cancelled = $this->service()->cancelOrder($orderId);
            $this->successMessage = $cancelled ? 'Order cancelled.' : 'Order was not cancelled.';
            $this->refreshData();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function closePosition(string $symbol): void
    {
        $this->openConfirmModal(
            action: 'close_position',
            title: 'Close Position',
            message: 'This will submit a close order for ' . strtoupper($symbol) . ($this->mode === 'live' ? ' in LIVE mode.' : '.'),
            button: 'Close Position',
            tone: 'rose',
            payload: ['symbol' => $symbol],
        );
    }

    public function executeClosePosition(string $symbol): void
    {
        $this->clearMessages();

        try {
            $this->service()->closePosition($symbol);
            $this->successMessage = 'Close order submitted for ' . strtoupper($symbol) . '.';
            $this->refreshData();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function setMode(string $mode): void
    {
        $mode = $mode === 'live' ? 'live' : 'paper';

        if ($mode === 'live' && $this->mode !== 'live') {
            $this->openConfirmModal(
                action: 'set_mode',
                title: 'Switch To Live',
                message: 'Live mode uses your real Alpaca account. Orders and position closes can use real money.',
                button: 'Switch To Live',
                tone: 'rose',
                payload: ['mode' => 'live'],
            );
            return;
        }

        $this->applyMode($mode);
    }

    public function setAssetClass(string $assetClass): void
    {
        $this->assetClass = $assetClass === 'option' ? 'option' : 'equity';
        $this->clearMessages();

        if ($this->assetClass === 'option') {
            $this->notional = null;
            $this->extendedHours = false;
            $this->timeInForce = in_array($this->timeInForce, ['day', 'gtc'], true) ? $this->timeInForce : 'day';
            $this->qty = $this->qty && $this->qty > 0 ? (float) max(1, (int) $this->qty) : 1.0;
            $this->orderType = 'limit';
        }
    }

    public function searchOptionContracts(): void
    {
        $this->clearMessages();
        $this->selectedOptionSymbol = null;
        $this->optionContracts = [];
        $this->optionQuotes = [];

        $underlying = strtoupper(trim($this->symbol));
        if ($underlying === '') {
            $this->errorMessage = 'Enter an underlying symbol.';
            return;
        }

        $params = [
            'underlying_symbols' => $underlying,
            'status' => 'active',
            'type' => $this->optionType,
            'limit' => 100,
        ];

        if ($this->optionExpirationDate) {
            $params['expiration_date'] = $this->optionExpirationDate;
        } else {
            $params['expiration_date_gte'] = now('America/New_York')->toDateString();
            $params['expiration_date_lte'] = now('America/New_York')->addDays(14)->toDateString();
        }

        if ($this->optionMinStrike !== null && $this->optionMinStrike > 0) {
            $params['strike_price_gte'] = $this->formatNumber($this->optionMinStrike);
        }
        if ($this->optionMaxStrike !== null && $this->optionMaxStrike > 0) {
            $params['strike_price_lte'] = $this->formatNumber($this->optionMaxStrike);
        }

        try {
            $response = $this->service()->optionContracts($params);
            $contracts = $response['option_contracts'] ?? [];
            $symbols = array_values(array_filter(array_map(fn ($contract) => $contract['symbol'] ?? null, $contracts)));

            $this->optionContracts = $contracts;
            $this->optionQuotes = $this->service()->latestOptionQuotes($symbols);

            if (empty($contracts)) {
                $this->errorMessage = 'No option contracts found for the selected filters.';
            }
        } catch (\Throwable $e) {
            Log::warning('AlpacaPaperAccount: option contract search failed', [
                'error' => $e->getMessage(),
                'params' => $params,
            ]);
            $this->errorMessage = $e->getMessage();
        }
    }

    public function selectOptionContract(string $contractSymbol): void
    {
        $this->selectedOptionSymbol = $contractSymbol;
        $quote = $this->optionQuotes[$contractSymbol] ?? null;
        $ask = (float) ($quote['ap'] ?? $quote['ask_price'] ?? 0);
        $bid = (float) ($quote['bp'] ?? $quote['bid_price'] ?? 0);

        if ($this->orderType === 'limit' && (!$this->limitPrice || $this->limitPrice <= 0)) {
            if ($ask > 0 && $this->side === 'buy') {
                $this->limitPrice = $ask;
            } elseif ($bid > 0 && $this->side === 'sell') {
                $this->limitPrice = $bid;
            }
        }
    }

    public function confirmModalAction(): void
    {
        $action = $this->confirmAction;
        $payload = $this->confirmPayload;
        $this->closeConfirmModal();

        match ($action) {
            'set_mode' => $this->applyMode((string) ($payload['mode'] ?? 'paper')),
            'submit_order' => $this->executeSubmitOrder(),
            'cancel_order' => $this->executeCancelOrder((string) ($payload['order_id'] ?? '')),
            'close_position' => $this->executeClosePosition((string) ($payload['symbol'] ?? '')),
            default => null,
        };
    }

    public function closeConfirmModal(): void
    {
        $this->confirmModalOpen = false;
        $this->confirmAction = null;
        $this->confirmPayload = [];
        $this->confirmTitle = '';
        $this->confirmMessage = '';
        $this->confirmButton = 'Confirm';
        $this->confirmTone = 'blue';
    }

    private function applyMode(string $mode): void
    {
        $this->mode = $mode === 'live' ? 'live' : 'paper';
        session(['alpaca_trading_mode' => $this->mode]);
        $this->clearMessages();
        $this->refreshData();
    }

    public function updatedOrderType(): void
    {
        if ($this->orderType === 'market') {
            $this->limitPrice = null;
            $this->stopPrice = null;
        }
    }

    public function updatedOptionType(): void
    {
        $this->selectedOptionSymbol = null;
        $this->optionContracts = [];
        $this->optionQuotes = [];
    }

    public function updatedNotional($value): void
    {
        if ($value !== null && (float) $value > 0) {
            $this->qty = null;
        }
    }

    public function updatedQty($value): void
    {
        if ($value !== null && (float) $value > 0) {
            $this->notional = null;
        }
    }

    public function render()
    {
        return view('livewire.alpaca-paper-account');
    }

    private function validateOrder(): void
    {
        $this->symbol = strtoupper(trim($this->symbol));

        $this->validate([
            'symbol' => 'required|string|min:1|max:10',
            'assetClass' => 'required|in:equity,option',
            'side' => 'required|in:buy,sell',
            'orderType' => 'required|in:market,limit,stop,stop_limit',
            'timeInForce' => 'required|in:day,gtc,opg,cls,ioc,fok',
            'qty' => 'nullable|numeric|min:0.000001',
            'notional' => 'nullable|numeric|min:1',
            'limitPrice' => 'nullable|numeric|min:0.01',
            'stopPrice' => 'nullable|numeric|min:0.01',
        ]);

        if ($this->assetClass === 'option') {
            if (!$this->selectedOptionSymbol) {
                throw new \InvalidArgumentException('Select an option contract first.');
            }
            if ($this->notional !== null && $this->notional > 0) {
                throw new \InvalidArgumentException('Options orders must use quantity, not notional.');
            }
            if (!$this->qty || $this->qty <= 0 || floor($this->qty) != $this->qty) {
                throw new \InvalidArgumentException('Options quantity must be a whole number.');
            }
            if (!in_array($this->timeInForce, ['day', 'gtc'], true)) {
                throw new \InvalidArgumentException('Options time in force must be DAY or GTC.');
            }
            if ($this->extendedHours) {
                throw new \InvalidArgumentException('Options orders cannot use extended hours.');
            }
            return;
        }

        if (($this->qty === null || $this->qty <= 0) && ($this->notional === null || $this->notional <= 0)) {
            throw new \InvalidArgumentException('Enter either quantity or notional amount.');
        }

        if ($this->notional !== null && $this->notional > 0 && $this->qty !== null && $this->qty > 0) {
            throw new \InvalidArgumentException('Use quantity or notional, not both.');
        }

        if (in_array($this->orderType, ['limit', 'stop_limit'], true) && (!$this->limitPrice || $this->limitPrice <= 0)) {
            throw new \InvalidArgumentException('Limit price is required for limit orders.');
        }

        if (in_array($this->orderType, ['stop', 'stop_limit'], true) && (!$this->stopPrice || $this->stopPrice <= 0)) {
            throw new \InvalidArgumentException('Stop price is required for stop orders.');
        }

        if ($this->extendedHours && ($this->orderType !== 'limit' || !in_array($this->timeInForce, ['day', 'gtc'], true))) {
            throw new \InvalidArgumentException('Extended-hours orders must be limit orders with DAY or GTC time in force.');
        }
    }

    private function validateOrderForDisplay(): bool
    {
        try {
            $this->validateOrder();
            return true;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            return false;
        }
    }

    private function clearMessages(): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;
    }

    private function service(): AlpacaTradingService
    {
        return AlpacaTradingService::make($this->mode);
    }

    private function openConfirmModal(
        string $action,
        string $title,
        string $message,
        string $button,
        string $tone = 'blue',
        array $payload = [],
    ): void {
        $this->confirmAction = $action;
        $this->confirmPayload = $payload;
        $this->confirmTitle = $title;
        $this->confirmMessage = $message;
        $this->confirmButton = $button;
        $this->confirmTone = $tone;
        $this->confirmModalOpen = true;
    }

    private function formatNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 8, '.', ''), '0'), '.');
    }
}

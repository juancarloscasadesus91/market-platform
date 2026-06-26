<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Jobs\RunAlpacaStrategyLabJob;
use App\Models\AlpacaStrategyLabLog;
use App\Models\AlpacaStrategyLabSession;
use App\Models\AlpacaStrategyLabTrade;
use App\Services\AlpacaTradingService;
use App\Strategies\StrategyRegistry;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class AlpacaStrategyLab extends Component
{
    use WithPagination;

    public string $name = '';
    public string $symbol = 'SPY';
    public string $timeframe = '5m';
    public string $strategyKey = 'ema_pullback';
    public string $mode = 'paper';
    public array $params = [];
    public string $positionSizeType = 'fixed_qty';
    public float $positionSizeValue = 1.0;
    public int $maxConcurrentTrades = 1;
    public ?float $stopLossPct = 0.5;
    public ?float $takeProfitPct = 1.0;
    public ?int $selectedSession = null;
    public ?int $selectedTradeId = null;
    public ?int $editingSessionId = null;
    public bool $showForm = false;

    public function mount(): void
    {
        $this->loadStrategyDefaults();
        $this->selectedSession = AlpacaStrategyLabSession::latest()->value('id');
    }

    public function updatedStrategyKey(): void
    {
        $this->loadStrategyDefaults();
    }

    public function saveSession(): void
    {
        $this->validate([
            'symbol' => 'required|string|max:20',
            'timeframe' => 'required|in:1m,5m,15m,30m,1h,1d',
            'strategyKey' => 'required|string',
            'mode' => 'required|in:paper,live',
            'positionSizeType' => 'required|in:fixed_qty,fixed_notional',
            'positionSizeValue' => 'required|numeric|min:0.000001',
            'maxConcurrentTrades' => 'required|integer|min:1|max:5',
            'stopLossPct' => 'nullable|numeric|min:0.01|max:99',
            'takeProfitPct' => 'nullable|numeric|min:0.01|max:500',
        ]);

        $data = [
            'name' => $this->name ?: null,
            'symbol' => strtoupper(trim($this->symbol)),
            'timeframe' => $this->timeframe,
            'strategy_key' => $this->strategyKey,
            'params' => array_map(fn ($value) => $value === '' ? null : $value, $this->params),
            'mode' => $this->mode,
            'position_size_type' => $this->positionSizeType,
            'position_size_value' => $this->positionSizeValue,
            'max_concurrent_trades' => $this->maxConcurrentTrades,
            'stop_loss_pct' => $this->stopLossPct,
            'take_profit_pct' => $this->takeProfitPct,
        ];

        if ($this->editingSessionId) {
            $session = AlpacaStrategyLabSession::findOrFail($this->editingSessionId);
            $session->update($data);
        } else {
            $session = AlpacaStrategyLabSession::create($data + ['status' => 'idle']);
        }

        $this->selectedSession = $session->id;
        $this->editingSessionId = null;
        $this->showForm = false;
        $this->resetPage();
    }

    public function createNew(): void
    {
        $this->editingSessionId = null;
        $this->name = '';
        $this->symbol = 'SPY';
        $this->timeframe = '5m';
        $this->strategyKey = 'ema_pullback';
        $this->mode = 'paper';
        $this->positionSizeType = 'fixed_qty';
        $this->positionSizeValue = 1.0;
        $this->maxConcurrentTrades = 1;
        $this->stopLossPct = 0.5;
        $this->takeProfitPct = 1.0;
        $this->loadStrategyDefaults();
        $this->showForm = true;
    }

    public function editSession(int $id): void
    {
        $session = AlpacaStrategyLabSession::findOrFail($id);

        $this->selectedSession = $session->id;
        $this->editingSessionId = $session->id;
        $this->name = (string) ($session->name ?? '');
        $this->symbol = $session->symbol;
        $this->timeframe = $session->timeframe;
        $this->strategyKey = $session->strategy_key;
        $this->mode = $session->mode === 'live' ? 'live' : 'paper';
        $this->params = $this->paramsWithSchemaDefaults($session->params ?? []);
        $this->positionSizeType = $session->position_size_type;
        $this->positionSizeValue = (float) $session->position_size_value;
        $this->maxConcurrentTrades = (int) $session->max_concurrent_trades;
        $this->stopLossPct = $session->stop_loss_pct;
        $this->takeProfitPct = $session->take_profit_pct;
        $this->showForm = true;
    }

    public function cancelEdit(): void
    {
        $this->editingSessionId = null;
        $this->showForm = false;
    }

    public function start(int $id): void
    {
        $session = AlpacaStrategyLabSession::findOrFail($id);
        $session->update([
            'status' => 'running',
            'started_at' => $session->started_at ?? now(),
            'stopped_at' => null,
            'error_message' => null,
        ]);

        RunAlpacaStrategyLabJob::dispatchSync($session->id);
        $this->selectedSession = $session->id;
    }

    public function runNow(int $id): void
    {
        $session = AlpacaStrategyLabSession::findOrFail($id);
        if ($session->status !== 'running') {
            $session->update(['status' => 'running', 'started_at' => $session->started_at ?? now()]);
        }

        RunAlpacaStrategyLabJob::dispatchSync($session->id);
        $this->selectedSession = $session->id;
    }

    public function pause(int $id): void
    {
        AlpacaStrategyLabSession::findOrFail($id)->update(['status' => 'paused']);
    }

    public function stop(int $id): void
    {
        AlpacaStrategyLabSession::findOrFail($id)->update([
            'status' => 'stopped',
            'stopped_at' => now(),
        ]);
    }

    public function deleteSession(int $id): void
    {
        AlpacaStrategyLabSession::find($id)?->delete();
        if ($this->selectedSession === $id) {
            $this->selectedSession = AlpacaStrategyLabSession::latest()->value('id');
            $this->selectedTradeId = null;
        }
        if ($this->editingSessionId === $id) {
            $this->editingSessionId = null;
            $this->showForm = false;
        }
    }

    public function selectSession(int $id): void
    {
        $this->selectedSession = $id;
        $this->selectedTradeId = null;
    }

    public function selectTrade(int $id): void
    {
        $trade = AlpacaStrategyLabTrade::findOrFail($id);
        $this->selectedSession = $trade->alpaca_strategy_lab_session_id;
        $this->selectedTradeId = $trade->id;
    }

    public function refreshActiveSession(): void
    {
        if (!$this->selectedSession || $this->showForm) {
            return;
        }

        $session = AlpacaStrategyLabSession::withCount([
            'trades as active_trades_count' => fn ($query) => $query->whereIn('status', ['pending', 'open', 'closing']),
        ])->find($this->selectedSession);

        if (!$session || $session->active_trades_count < 1) {
            return;
        }

        try {
            RunAlpacaStrategyLabJob::dispatchSync($session->id, true);
        } catch (\Throwable $e) {
            Log::warning('Alpaca Strategy Lab live refresh failed', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function closeTrade(int $id): void
    {
        $trade = AlpacaStrategyLabTrade::with('session')->findOrFail($id);
        $session = $trade->session;

        $this->selectedSession = $trade->alpaca_strategy_lab_session_id;
        $this->selectedTradeId = $trade->id;

        if (!$session || !in_array($trade->status, ['open'], true)) {
            session()->flash('alpaca_error', 'Only open trades can be closed manually.');
            return;
        }

        if (!$trade->quantity || (float) $trade->quantity <= 0) {
            session()->flash('alpaca_error', 'This trade has no filled quantity to close.');
            return;
        }

        try {
            $trader = AlpacaTradingService::make($session->mode === 'live' ? 'live' : 'paper');
            $exitSide = $trade->side === 'buy' ? 'sell' : 'buy';
            $order = $trader->submitOrder([
                'symbol' => $trade->symbol,
                'qty' => $this->formatNumber((float) $trade->quantity),
                'side' => $exitSide,
                'type' => 'market',
                'time_in_force' => 'day',
            ]);

            $trade->update([
                'status' => 'closing',
                'exit_order_id' => $order['id'] ?? null,
                'exit_reason' => 'manual_close',
                'exit_order_payload' => $order,
                'last_sync_at' => now(),
            ]);

            AlpacaStrategyLabLog::create([
                'alpaca_strategy_lab_session_id' => $session->id,
                'alpaca_strategy_lab_trade_id' => $trade->id,
                'level' => 'warning',
                'event' => 'manual_close_submitted',
                'message' => 'Manual close order submitted.',
                'context' => [
                    'order_id' => $trade->exit_order_id,
                    'side' => $exitSide,
                    'qty' => (float) $trade->quantity,
                ],
            ]);

            RunAlpacaStrategyLabJob::dispatchSync($session->id, true);
            session()->flash('alpaca_success', 'Manual close order submitted.');
        } catch (\Throwable $e) {
            Log::error('Alpaca Strategy Lab manual close failed', [
                'trade_id' => $trade->id,
                'error' => $e->getMessage(),
            ]);

            $trade->update([
                'error_message' => $e->getMessage(),
                'last_sync_at' => now(),
            ]);

            AlpacaStrategyLabLog::create([
                'alpaca_strategy_lab_session_id' => $session->id,
                'alpaca_strategy_lab_trade_id' => $trade->id,
                'level' => 'error',
                'event' => 'manual_close_failed',
                'message' => $e->getMessage(),
                'context' => [],
            ]);

            session()->flash('alpaca_error', 'Manual close failed: '.$e->getMessage());
        }
    }

    #[Computed]
    public function strategyOptions(): array
    {
        return StrategyRegistry::options();
    }

    #[Computed]
    public function strategySchema(): array
    {
        try {
            return StrategyRegistry::resolve($this->strategyKey)->schema();
        } catch (\Throwable) {
            return [];
        }
    }

    #[Computed]
    public function schemaByGroup(): array
    {
        $groups = [];
        foreach ($this->strategySchema as $field) {
            $groups[$field['group'] ?? 'General'][] = $field;
        }

        return $groups;
    }

    #[Computed]
    public function sessions()
    {
        return AlpacaStrategyLabSession::latest()->paginate(10);
    }

    #[Computed]
    public function selectedSessionModel(): ?AlpacaStrategyLabSession
    {
        return $this->selectedSession
            ? AlpacaStrategyLabSession::with(['trades' => fn ($q) => $q->latest(), 'logs' => fn ($q) => $q->latest()->limit(50)])
                ->find($this->selectedSession)
            : null;
    }

    #[Computed]
    public function selectedTradeModel(): ?AlpacaStrategyLabTrade
    {
        return $this->selectedTradeId
            ? AlpacaStrategyLabTrade::with(['logs' => fn ($q) => $q->latest()->limit(100)])
                ->where('alpaca_strategy_lab_session_id', $this->selectedSession)
                ->find($this->selectedTradeId)
            : null;
    }

    private function loadStrategyDefaults(): void
    {
        try {
            $this->params = $this->paramsWithSchemaDefaults();
        } catch (\Throwable) {
            $this->params = [];
        }
    }

    private function paramsWithSchemaDefaults(array $saved = []): array
    {
        $params = [];
        foreach (StrategyRegistry::resolve($this->strategyKey)->schema() as $field) {
            $key = $field['key'];
            $params[$key] = array_key_exists($key, $saved) ? $saved[$key] : ($field['default'] ?? null);
        }

        return $params;
    }

    private function formatNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 8, '.', ''), '0'), '.');
    }

    public function render()
    {
        return view('livewire.alpaca-strategy-lab');
    }
}

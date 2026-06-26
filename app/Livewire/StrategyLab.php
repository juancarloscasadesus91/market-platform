<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Jobs\StrategyLabImporterJob;
use App\Models\StrategyLabSession;
use App\Models\StrategyLabTrade;
use App\Strategies\StrategyRegistry;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class StrategyLab extends Component
{
    use WithPagination;

    // ── Form ────────────────────────────────────────────────────────────
    public string  $sessionName  = '';
    public string  $strategyKey  = 'ema_pullback';
    public string  $symbolsInput = 'SPY';
    public string  $timeframe    = '5m';
    public string  $dateFrom     = '';
    public string  $dateTo       = '';

    // Dynamic params (keyed by strategy schema 'key')
    public array $params = [];

    // ── UI ───────────────────────────────────────────────────────────────
    public bool   $showForm          = false;
    public bool   $showParamsSection = true;
    public ?int   $selectedSession   = null;
    public string $activeTab         = 'trades';
    public string $tradesFilter      = '';
    public string $tradesSort        = 'entry_time';
    public string $tradesSortDir     = 'asc';
    public ?int   $selectedTradeId   = null;

    // ── Sim Profit ───────────────────────────────────────────────────────
    public bool   $showSimProfit        = false;
    public float  $simDollarPerContract = 300.0;
    public float  $simDelta             = 0.30;
    public float  $simGamma             = 0.0;
    public float  $simTheta             = 0.0;
    public bool   $simSpxMode           = false;
    public float  $simSpxMultiplier     = 10.02;
    public float  $simCommission        = 0.80;
    public bool   $simHasResults        = false;
    public string $simSort              = 'entry_time';
    public string $simSortDir           = 'asc';
    public int    $simPage              = 1;
    public int    $simPerPage           = 15;

    public function mount(): void
    {
        $this->dateFrom = now()->subDays(30)->toDateString();
        $this->dateTo   = now()->subDay()->toDateString();
        $this->loadStrategyDefaults();
    }

    // ── Strategy switching ───────────────────────────────────────────────

    public function updatedStrategyKey(): void
    {
        $this->loadStrategyDefaults();
        $this->resetPage();
    }

    private function loadStrategyDefaults(): void
    {
        try {
            $strategy = StrategyRegistry::resolve($this->strategyKey);
            $this->params = [];
            foreach ($strategy->schema() as $field) {
                $this->params[$field['key']] = $field['default'];
            }
        } catch (\Throwable) {
            $this->params = [];
        }
    }

    // ── Computed ─────────────────────────────────────────────────────────

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
            $g = $field['group'] ?? 'General';
            $groups[$g][] = $field;
        }
        return $groups;
    }

    #[Computed]
    public function sessions()
    {
        return StrategyLabSession::latest()->paginate(15);
    }

    #[Computed]
    public function selectedSessionModel(): ?StrategyLabSession
    {
        return $this->selectedSession
            ? StrategyLabSession::find($this->selectedSession)
            : null;
    }

    #[Computed]
    public function sessionTrades()
    {
        if (!$this->selectedSession) return collect();

        $q = StrategyLabTrade::where('strategy_lab_session_id', $this->selectedSession);

        if ($this->tradesFilter) {
            $q->where(function ($q2) {
                $q2->where('direction', 'like', "%{$this->tradesFilter}%")
                   ->orWhere('result',    'like', "%{$this->tradesFilter}%")
                   ->orWhere('symbol',    'like', "%{$this->tradesFilter}%");
            });
        }

        $allowedSorts = ['entry_time', 'exit_time', 'pnl_points', 'r_multiple', 'result', 'direction'];
        $col = in_array($this->tradesSort, $allowedSorts) ? $this->tradesSort : 'entry_time';
        $dir = $this->tradesSortDir === 'desc' ? 'desc' : 'asc';

        return $q->orderBy($col, $dir)->get();
    }

    // ── Actions ──────────────────────────────────────────────────────────

    public function runBacktest(): void
    {
        $this->validate([
            'symbolsInput' => 'required|string',
            'timeframe'    => 'required|in:1m,5m,15m,30m,1h,4h,1d',
            'dateFrom'     => 'required|date',
            'dateTo'       => 'required|date|after_or_equal:dateFrom',
            'strategyKey'  => 'required|string',
        ]);

        $symbols = array_map('strtoupper', array_filter(
            array_map('trim', explode(',', $this->symbolsInput))
        ));

        if (empty($symbols)) {
            $this->addError('symbolsInput', 'At least one symbol required.');
            return;
        }

        // Merge nulls: treat empty string params as null
        $cleanParams = array_map(fn($v) => ($v === '' ? null : $v), $this->params);

        $session = StrategyLabSession::create([
            'name'         => $this->sessionName ?: null,
            'strategy_key' => $this->strategyKey,
            'symbols'      => $symbols,
            'timeframe'    => $this->timeframe,
            'date_from'    => $this->dateFrom,
            'date_to'      => $this->dateTo,
            'params'       => $cleanParams,
            'status'       => 'pending',
        ]);

        foreach ($symbols as $symbol) {
            StrategyLabImporterJob::dispatch(
                $session->id,
                $symbol,
                $this->timeframe,
                $this->dateFrom,
                $this->dateTo,
            );
        }

        $this->showForm = false;
        $this->sessionName = '';
        $this->selectedSession = $session->id;
        $this->resetPage();
    }

    public function deleteSession(int $id): void
    {
        StrategyLabSession::find($id)?->delete();
        if ($this->selectedSession === $id) {
            $this->selectedSession = null;
        }
        $this->resetPage();
    }

    public function selectSession(int $id): void
    {
        $this->selectedSession = ($this->selectedSession === $id) ? null : $id;
        $this->activeTab = 'trades';
        $this->tradesFilter = '';
        $this->tradesSort = 'entry_time';
        $this->tradesSortDir = 'asc';
    }

    public function sortTrades(string $col): void
    {
        if ($this->tradesSort === $col) {
            $this->tradesSortDir = $this->tradesSortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->tradesSort = $col;
            $this->tradesSortDir = 'asc';
        }
    }

    public function loadConfig(int $sessionId): void
    {
        $session = StrategyLabSession::find($sessionId);
        if (!$session) return;

        $this->strategyKey  = $session->strategy_key;
        $this->symbolsInput = implode(',', $session->symbols ?? []);
        $this->timeframe    = $session->timeframe;
        $this->dateFrom     = $session->date_from?->toDateString() ?? $this->dateFrom;
        $this->dateTo       = $session->date_to?->toDateString()   ?? $this->dateTo;
        $this->sessionName  = '';

        // Load saved params, falling back to strategy defaults for any missing keys
        $this->loadStrategyDefaults();
        foreach ($session->params ?? [] as $key => $value) {
            $this->params[$key] = $value;
        }

        $this->showForm = true;
    }

    public function viewTrade(int $id): void
    {
        $this->selectedTradeId = $id;
    }

    public function closeTrade(): void
    {
        $this->selectedTradeId = null;
    }

    #[Computed]
    public function selectedTradeData(): ?array
    {
        if (!$this->selectedTradeId) return null;

        $trade = StrategyLabTrade::find($this->selectedTradeId);
        if (!$trade) return null;

        $session    = StrategyLabSession::find($trade->strategy_lab_session_id);
        $tf         = $session?->timeframe ?? '5m';
        $minsPerBar = match ($tf) {
            '1m' => 1, '5m' => 5, '15m' => 15, '30m' => 30,
            '1h' => 60, '4h' => 240, '1d' => 1440, default => 5,
        };

        $entryTime = $trade->entry_time;
        $exitTime  = $trade->exit_time ?? $entryTime;

        $signalData  = $trade->signal_data ?? [];
        $strategyKey = $session?->strategy_key ?? '';
        $bbLength    = (int) ($session?->params['bb_length'] ?? 30);

        $warmupBars = $strategyKey === 'bollinger_rsi' ? max(90, $bbLength * 3) : 150;

        $candles = \App\Models\Candle::where('symbol', $trade->symbol)
            ->where('timeframe', $tf)
            ->where('opens_at', '>=', $entryTime->copy()->subMinutes($minsPerBar * $warmupBars))
            ->where('opens_at', '<=', $exitTime->copy()->addMinutes($minsPerBar * 20))
            ->orderBy('opens_at')
            ->get()
            ->map(fn ($c) => [
                'time'   => $c->opens_at->timestamp,
                'open'   => (float) $c->open,
                'high'   => (float) $c->high,
                'low'    => (float) $c->low,
                'close'  => (float) $c->close,
                'volume' => (int)   $c->volume,
            ])
            ->values()
            ->toArray();

        return [
            'trade' => array_merge($trade->toArray(), [
                'entry_time_ts' => $entryTime?->timestamp,
                'exit_time_ts'  => $exitTime?->timestamp,
                'bb_upper'      => $signalData['bb_upper']  ?? null,
                'bb_lower'      => $signalData['bb_lower']  ?? null,
                'bb_middle'     => $signalData['bb_middle'] ?? null,
                'rsi'           => $signalData['rsi']       ?? null,
                'strategy_key'  => $strategyKey,
            ]),
            'candles'        => $candles,
            'timeframe'      => $tf,
            'mins_per_bar'   => $minsPerBar,
            'view_from_ts'   => $entryTime->copy()->subMinutes($minsPerBar * 40)->timestamp,
            'view_to_ts'     => $exitTime->copy()->addMinutes($minsPerBar * 20)->timestamp,
            'session_params' => $session?->params ?? [],
            'strategy_key'   => $strategyKey,
        ];
    }

    public function refreshRunningSessions(): void
    {
        // Triggered by poll — just re-renders
    }

    // ── Sim Profit ───────────────────────────────────────────────────────

    private function simCacheKey(): string
    {
        return 'sim_profit_' . $this->getId();
    }

    public function openSimProfitModal(): void
    {
        if (!$this->selectedSession) return;
        $this->simHasResults = false;
        Cache::forget($this->simCacheKey());
        $this->showSimProfit = true;
    }

    public function calculateSimProfit(): void
    {
        if (!$this->selectedSession) return;

        $trades = StrategyLabTrade::where('strategy_lab_session_id', $this->selectedSession)
            ->orderBy('entry_time')
            ->get();

        $results  = [];
        $totalPnl = 0.0;
        $spxMult  = $this->simSpxMode ? $this->simSpxMultiplier : 1.0;

        foreach ($trades as $trade) {
            $rawPnlPoints = (float) ($trade->pnl_points ?? 0);

            // Standard options P&L per contract:
            // Delta:  pnl_pts × delta × 100  (100 = options multiplier for 1 contract)
            // e.g.    1.42 × 0.30 × 100 = $42.60 (SPY)
            //         with SPX: × 10.02 additional
            $estimatedPnl = $rawPnlPoints * $this->simDelta * 100.0 * $spxMult;

            // Gamma: ½ × gamma × move² × 100
            if ($this->simGamma != 0.0) {
                $estimatedPnl += 0.5 * $this->simGamma * ($rawPnlPoints ** 2) * 100.0;
            }

            // Theta: $/day × days held (negative theta = time decay cost)
            if ($this->simTheta != 0.0 && $trade->entry_time && $trade->exit_time) {
                $daysHeld      = $trade->entry_time->diffInMinutes($trade->exit_time) / 1440;
                $estimatedPnl += $this->simTheta * $daysHeld;
            }

            // Commission per trade (round-trip: open + close)
            $commission     = $this->simSpxMode ? 1.80 : $this->simCommission;
            $profit         = round($estimatedPnl - $commission, 2);
            $contractPrice  = round($this->simDollarPerContract, 2);
            $totalEstimated = round($contractPrice + $profit, 2);
            $totalPnl      += $profit;

            $results[] = [
                'id'             => $trade->id,
                'symbol'         => $trade->symbol,
                'direction'      => $trade->direction,
                'result'         => $trade->result,
                'exit_reason'    => $trade->exit_reason,
                'entry_time'     => $trade->entry_time?->format('M j H:i'),
                'exit_time'      => $trade->exit_time?->format('M j H:i'),
                'entry_ts'       => $trade->entry_time?->timestamp ?? 0,
                'exit_ts'        => $trade->exit_time?->timestamp ?? 0,
                'pnl_points'     => round($rawPnlPoints, 2),
                'pnl_points_spx' => round($rawPnlPoints * $spxMult, 2),
                'contract_price' => $contractPrice,
                'commission'     => $commission,
                'profit'         => $profit,
                'estimated_pnl'  => $totalEstimated,
            ];
        }

        $tradeCount = count($results);
        $payload = [
            'trades'           => $results,
            'total_pnl'        => round($totalPnl, 2),
            'total_invested'   => round($this->simDollarPerContract * $tradeCount, 2),
            'total_estimated'  => round(($this->simDollarPerContract * $tradeCount) + $totalPnl, 2),
            'wins'             => collect($results)->where('profit', '>', 0)->count(),
            'losses'           => collect($results)->where('profit', '<', 0)->count(),
            'spx_mode'         => $this->simSpxMode,
        ];

        Cache::put($this->simCacheKey(), $payload, now()->addHours(2));
        $this->simHasResults = true;
    }

    public function toggleSpxMode(): void
    {
        $this->simSpxMode = !$this->simSpxMode;
        if ($this->simHasResults) {
            $this->simPage = 1;
            $this->calculateSimProfit();
        }
    }

    public function sortSimTrades(string $col): void
    {
        if ($this->simSort === $col) {
            $this->simSortDir = $this->simSortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->simSort    = $col;
            $this->simSortDir = 'asc';
        }
        $this->simPage = 1;
    }

    public function simNextPage(): void
    {
        $this->simPage++;
    }

    public function simPrevPage(): void
    {
        if ($this->simPage > 1) $this->simPage--;
    }

    public function closeSimProfit(): void
    {
        $this->showSimProfit = false;
        $this->simHasResults = false;
        Cache::forget($this->simCacheKey());
    }

    public function render()
    {
        $simResults   = [];
        $simPagedRows = [];
        $simTotalPages = 1;

        if ($this->simHasResults) {
            $simResults = Cache::get($this->simCacheKey(), []);

            if (!empty($simResults['trades'])) {
                $allowed = ['entry_time', 'exit_time', 'result', 'direction', 'pnl_points', 'estimated_pnl', 'exit_reason', 'profit', 'contract_price'];
                $sortKey = in_array($this->simSort, $allowed) ? $this->simSort : 'entry_time';

                $sorted = collect($simResults['trades'])->sortBy(
                    fn ($r) => match($sortKey) {
                        'entry_time'    => $r['entry_ts'],
                        'exit_time'     => $r['exit_ts'],
                        'estimated_pnl' => $r['estimated_pnl'],
                        'pnl_points'    => $r['pnl_points'],
                        default         => $r[$sortKey] ?? '',
                    },
                    SORT_REGULAR,
                    $this->simSortDir === 'desc'
                );

                $total          = $sorted->count();
                $simTotalPages  = max(1, (int) ceil($total / $this->simPerPage));
                $this->simPage  = min($this->simPage, $simTotalPages);
                $simPagedRows   = $sorted->slice(($this->simPage - 1) * $this->simPerPage, $this->simPerPage)->values();
            }
        }

        return view('livewire.strategy-lab', compact('simResults', 'simPagedRows', 'simTotalPages'));
    }
}

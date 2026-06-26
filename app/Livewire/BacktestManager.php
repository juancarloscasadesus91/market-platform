<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Jobs\CandleImporterJob;
use App\Models\BacktestSession;
use App\Models\BacktestTrade;
use App\Models\Candle;
use App\Models\StrategySetting;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class BacktestManager extends Component
{
    use WithPagination;

    // ── Form state ────────────────────────────────────────────────────────
    public string $sessionName  = '';
    public string $symbolsInput = 'SPY,QQQ';
    public string $timeframe    = '5m';
    public string $dateFrom     = '';
    public string $dateTo       = '';

    // Strategy config
    public int    $emaFast              = 21;
    public int    $emaMid               = 50;
    public int    $emaSlow              = 100;
    public float  $minDistancePct       = 0.02;
    public int    $maxBarsAfterPullback = 3;
    public int    $rsiPeriod            = 14;
    public int    $bbPeriod             = 20;
    public float  $bbStddev             = 2.0;
    public int    $atrPeriod            = 14;
    public int    $volumeAvgPeriod      = 20;
    public ?float $rsiMaxCall           = null;
    public ?float $rsiMinPut            = null;
    public ?float $maxCandleAtrRatio    = null;
    public ?float $maxPriceEmaDistPct   = null;
    public ?float $minBbDistPct         = null;
    public mixed  $minEma21Ema50Dist    = null;
    public mixed  $maxEma21Ema50Dist    = null;
    public mixed  $minEma50Ema100Dist   = null;
    public mixed  $maxEma50Ema100Dist   = null;
    public bool   $ignoreIndicatorFilters = false;
    public string $stopType             = 'pullback';
    public float  $stopAtrMult          = 1.5;
    public float  $stopBufferPct        = 0.05;
    public ?float $stopPct              = null;
    public string $tpType               = 'risk_ratio';
    public float  $tp1Value             = 1.0;
    public float  $tp2Value             = 2.0;
    public float  $tp3Value             = 3.0;
    public float  $quadrantStepPct      = 25.0;
    public ?int   $maxTradeDurationMinutes = 30;
    public string $forceExitTime        = '15:45';
    public string $minEntryTime         = '09:30';
    public string $maxEntryTime         = '16:00';
    public ?float $entryCandleDistancePct = null;
    public ?float $volumeRelMin         = null;
    public ?float $volumeRelMax         = null;

    // ── UI state ──────────────────────────────────────────────────────────
    public bool  $showForm        = false;
    public bool  $showAdvanced    = false;
    public bool  $showAnalysisModal = false;
    public ?int  $selectedSession = null;
    public ?int  $selectedTradeId = null;
    public string $activeTab     = 'trades';

    // Sim Profit modal state
    public bool  $showSimProfitModal = false;
    public ?int  $simProfitTradeId = null;
    public string $simUnderlying = 'SPY'; // SPY or SPX
    public string $simResult = 'auto'; // auto, win, loss, breakeven
    public float $simContractPrice = 0.00;
    public float $simDelta = 0.50;
    public float $simGamma = 0.05;
    public float $simTheta = -0.02;
    public float $simPriceMove = 1.0;
    public float $simContracts = 1;
    public string $tradesSort    = 'entry_time';
    public string $tradesSortDir = 'asc';
    public string $tradesFilter  = '';

    protected $listeners = ['echo:backtests,BacktestUpdated' => 'refreshRunningSessions'];

    public function mount(): void
    {
        $this->dateFrom = now()->subDays(30)->toDateString();
        $this->dateTo   = now()->subDay()->toDateString();
    }

    // ── Lifecycle polling for running sessions ────────────────────────────
    public function getListeners(): array
    {
        return [];
    }

    public function refreshRunningSessions(): void
    {
        // This method is called by polling
        // Livewire will automatically re-render after this method completes
        // With caching, this is inexpensive even when there are no running sessions
    }

    // ── Actions ───────────────────────────────────────────────────────────

    public function launch(): void
    {
        $this->validate([
            'symbolsInput' => 'required|string',
            'timeframe'    => 'required|in:1m,5m,15m,30m',
            'dateFrom'     => 'required|date',
            'dateTo'       => 'required|date|after_or_equal:dateFrom',
        ]);

        $symbols = collect(explode(',', $this->symbolsInput))
            ->map(fn ($s) => strtoupper(trim($s)))
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (empty($symbols)) {
            $this->addError('symbolsInput', 'Enter at least one symbol.');
            return;
        }

        // Save strategy settings
        $strategy = StrategySetting::create([
            'name'                    => $this->sessionName ?: ('Run ' . now()->format('Y-m-d H:i')),
            'ema_fast'                => $this->emaFast,
            'ema_mid'                 => $this->emaMid,
            'ema_slow'                => $this->emaSlow,
            'min_distance_pct'        => $this->minDistancePct,
            'max_bars_after_pullback' => $this->maxBarsAfterPullback,
            'rsi_period'              => $this->rsiPeriod,
            'bb_period'               => $this->bbPeriod,
            'bb_stddev'               => $this->bbStddev,
            'atr_period'              => $this->atrPeriod,
            'volume_avg_period'       => $this->volumeAvgPeriod,
            'rsi_max_call'            => $this->ignoreIndicatorFilters ? null : (is_numeric($this->rsiMaxCall) ? (float) $this->rsiMaxCall : null),
            'rsi_min_put'             => $this->ignoreIndicatorFilters ? null : (is_numeric($this->rsiMinPut) ? (float) $this->rsiMinPut : null),
            'max_candle_atr_ratio'    => $this->ignoreIndicatorFilters ? null : (is_numeric($this->maxCandleAtrRatio) ? (float) $this->maxCandleAtrRatio : null),
            'max_price_ema_dist_pct'  => $this->ignoreIndicatorFilters ? null : (is_numeric($this->maxPriceEmaDistPct) ? (float) $this->maxPriceEmaDistPct : null),
            'min_bb_dist_pct'         => $this->ignoreIndicatorFilters ? null : (is_numeric($this->minBbDistPct) ? (float) $this->minBbDistPct : null),
            'min_ema21_ema50_dist'    => is_numeric($this->minEma21Ema50Dist) ? (float) $this->minEma21Ema50Dist : null,
            'max_ema21_ema50_dist'    => is_numeric($this->maxEma21Ema50Dist) ? (float) $this->maxEma21Ema50Dist : null,
            'min_ema50_ema100_dist'   => is_numeric($this->minEma50Ema100Dist) ? (float) $this->minEma50Ema100Dist : null,
            'max_ema50_ema100_dist'   => is_numeric($this->maxEma50Ema100Dist) ? (float) $this->maxEma50Ema100Dist : null,
            'stop_type'               => $this->stopType,
            'stop_atr_mult'           => $this->stopAtrMult,
            'stop_buffer_pct'         => $this->stopBufferPct,
            'stop_pct'                => is_numeric($this->stopPct) ? (float) $this->stopPct : null,
            'tp_type'                 => $this->tpType,
            'tp1_value'               => $this->tp1Value,
            'tp2_value'               => $this->tp2Value,
            'tp3_value'               => $this->tp3Value,
            'quadrant_step_pct'       => $this->quadrantStepPct,
            'max_trade_duration_minutes' => $this->maxTradeDurationMinutes,
            'force_exit_time'         => $this->forceExitTime ?: null,
            'min_entry_time'          => $this->minEntryTime ?: null,
            'max_entry_time'          => $this->maxEntryTime ?: null,
            'entry_candle_distance_pct' => is_numeric($this->entryCandleDistancePct) ? (float) $this->entryCandleDistancePct : null,
            'volume_rel_min'          => is_numeric($this->volumeRelMin) ? (float) $this->volumeRelMin : null,
            'volume_rel_max'          => is_numeric($this->volumeRelMax) ? (float) $this->volumeRelMax : null,
        ]);

        $session = BacktestSession::create([
            'name'                => $this->sessionName ?: ('Run ' . now()->format('Y-m-d H:i')),
            'symbols'             => $symbols,
            'timeframe'           => $this->timeframe,
            'date_from'           => $this->dateFrom,
            'date_to'             => $this->dateTo,
            'strategy_setting_id' => $strategy->id,
            'status'              => 'pending',
        ]);

        // Dispatch one import job per symbol (they run in sequence or parallel depending on queue workers)
        foreach ($symbols as $symbol) {
            CandleImporterJob::dispatch(
                $session->id,
                $symbol,
                $this->timeframe,
                $this->dateFrom,
                $this->dateTo,
            );
        }

        // Clear cache when new session is created
        cache()->forget('backtest:grid-analysis');

        $this->showForm      = false;
        $this->selectedSession = $session->id;
        session()->flash('success', "Backtest #{$session->id} queued for " . implode(', ', $symbols));
    }

    public function deleteSession(int $id): void
    {
        BacktestSession::find($id)?->delete();
        if ($this->selectedSession === $id) {
            $this->selectedSession = null;
        }
        // Clear cache when session is deleted
        cache()->forget('backtest:grid-analysis');
        cache()->forget("backtest:pattern-analysis:{$id}");
    }

    public function selectSession(int $id): void
    {
        $this->selectedSession = $id;
        $this->resetPage();
    }

    public function viewTrade(int $id): void
    {
        $this->selectedTradeId = $id;
    }

    public function closeTrade(): void
    {
        $this->selectedTradeId = null;
    }

    public function openSimProfit(int $tradeId): void
    {
        $trade = BacktestTrade::find($tradeId);
        if (!$trade) return;

        $this->simProfitTradeId = $tradeId;
        $this->showSimProfitModal = true;

        // Load trade entry price as initial contract price
        $this->simContractPrice = $trade->entry_price ?? 0.00;

        // Calculate price move based on actual trade movement (exit - entry)
        // This represents the actual underlying movement during the trade
        $entry = $trade->entry_price ?? 0;
        $exit = $trade->exit_price ?? 0;
        $this->simPriceMove = abs($exit - $entry);

        // Load example SPY option greeks (0 DTE ATM)
        // These are typical values for a near-ATM SPY option
        $this->simDelta = 0.50;
        $this->simGamma = 0.10;
        $this->simTheta = -0.05;
        $this->simContracts = 1;
    }

    public function closeSimProfit(): void
    {
        $this->showSimProfitModal = false;
        $this->simProfitTradeId = null;
    }

    public function switchUnderlying(): void
    {
        $conversionFactor = 10.04;

        if ($this->simUnderlying === 'SPY') {
            // Convert SPY to SPX
            $this->simUnderlying = 'SPX';
            $this->simContractPrice = round($this->simContractPrice * $conversionFactor, 2);
            $this->simPriceMove = round($this->simPriceMove * $conversionFactor, 2);
        } else {
            // Convert SPX to SPY
            $this->simUnderlying = 'SPY';
            $this->simContractPrice = round($this->simContractPrice / $conversionFactor, 2);
            $this->simPriceMove = round($this->simPriceMove / $conversionFactor, 2);
        }
    }

    #[Computed]
    public function simProfitResult(): array
    {
        if (!$this->simProfitTradeId) return [];

        $trade = BacktestTrade::find($this->simProfitTradeId);
        if (!$trade) return [];

        $direction = $trade->direction; // CALL or PUT
        $result = $trade->result; // win, loss, breakeven

        // Validate and sanitize inputs with fallbacks
        $contractPrice = is_numeric($this->simContractPrice) && $this->simContractPrice > 0
            ? (float) $this->simContractPrice
            : ($trade->entry_price ?? 1.00);

        $priceMove = is_numeric($this->simPriceMove) ? (float) $this->simPriceMove : 0.0;
        $delta = is_numeric($this->simDelta) ? max(0, min(1, (float) $this->simDelta)) : 0.50;
        $gamma = is_numeric($this->simGamma) ? max(0, (float) $this->simGamma) : 0.10;
        $theta = is_numeric($this->simTheta) ? (float) $this->simTheta : -0.05;
        $contracts = is_numeric($this->simContracts) && $this->simContracts > 0
            ? (int) $this->simContracts
            : 1;

        // Calculate option price change using greeks approximation
        // Δprice ≈ delta * ΔS + 0.5 * gamma * (ΔS)² + theta * Δt
        // Assuming 1 day time decay for theta
        // The sign of price change depends on the simulated result

        $basePriceChange = ($delta * $priceMove) + (0.5 * $gamma * $priceMove * $priceMove) + $theta;

        // Use simulated result if set, otherwise use actual trade result
        $effectiveResult = $this->simResult === 'auto' ? $result : $this->simResult;

        // If trade was a winner, price change should be positive
        // If trade was a loser, price change should be negative
        if ($effectiveResult === 'win') {
            $priceChange = abs($basePriceChange);
        } elseif ($effectiveResult === 'loss') {
            $priceChange = -abs($basePriceChange);
        } else {
            // breakeven or unknown
            $priceChange = 0;
        }

        $newPrice = $contractPrice + $priceChange;
        $perContractPnl = $priceChange * 100; // Options are $100 per point
        $totalPnl = $perContractPnl * $contracts;

        return [
            'direction' => $direction,
            'result' => $result,
            'contract_price' => $contractPrice,
            'price_move' => $priceMove,
            'price_change' => $priceChange,
            'new_price' => $newPrice,
            'per_contract_pnl' => $perContractPnl,
            'total_pnl' => $totalPnl,
            'pnl_pct' => $contractPrice > 0 ? ($priceChange / $contractPrice) * 100 : 0,
        ];
    }

    public function showAnalysis(): void
    {
        $this->showAnalysisModal = true;
    }

    public function closeAnalysis(): void
    {
        $this->showAnalysisModal = false;
    }

    public function loadConfig(int $sessionId): void
    {
        $session = BacktestSession::with('strategy')->find($sessionId);
        if (!$session || !$session->strategy) {
            return;
        }

        $cfg = $session->strategy;

        // Load strategy settings into form
        $this->emaFast = $cfg->ema_fast;
        $this->emaMid = $cfg->ema_mid;
        $this->emaSlow = $cfg->ema_slow;
        $this->minDistancePct = $cfg->min_distance_pct;
        $this->maxBarsAfterPullback = $cfg->max_bars_after_pullback;
        $this->rsiPeriod = $cfg->rsi_period;
        $this->bbPeriod = $cfg->bb_period;
        $this->bbStddev = $cfg->bb_stddev;
        $this->atrPeriod = $cfg->atr_period;
        $this->volumeAvgPeriod = $cfg->volume_avg_period;
        $this->rsiMaxCall = $cfg->rsi_max_call;
        $this->rsiMinPut = $cfg->rsi_min_put;
        $this->maxCandleAtrRatio = $cfg->max_candle_atr_ratio;
        $this->maxPriceEmaDistPct = $cfg->max_price_ema_dist_pct;
        $this->minBbDistPct = $cfg->min_bb_dist_pct;
        $this->minEma21Ema50Dist = $cfg->min_ema21_ema50_dist;
        $this->maxEma21Ema50Dist = $cfg->max_ema21_ema50_dist;
        $this->minEma50Ema100Dist = $cfg->min_ema50_ema100_dist;
        $this->maxEma50Ema100Dist = $cfg->max_ema50_ema100_dist;
        $this->stopType = $cfg->stop_type;
        $this->stopAtrMult = $cfg->stop_atr_mult;
        $this->stopBufferPct = $cfg->stop_buffer_pct;
        $this->stopPct = $cfg->stop_pct;
        $this->tpType = $cfg->tp_type;
        $this->tp1Value = $cfg->tp1_value;
        $this->tp2Value = $cfg->tp2_value;
        $this->tp3Value = $cfg->tp3_value;
        $this->maxTradeDurationMinutes = $cfg->max_trade_duration_minutes;
        $this->forceExitTime = $cfg->force_exit_time ?? '15:45';
        $this->minEntryTime = $cfg->min_entry_time ?? '09:30';
        $this->maxEntryTime = $cfg->max_entry_time ?? '16:00';
        $this->entryCandleDistancePct = $cfg->entry_candle_distance_pct;
        $this->volumeRelMin = $cfg->volume_rel_min;
        $this->volumeRelMax = $cfg->volume_rel_max;

        // Load session parameters
        $this->symbolsInput = implode(',', $session->symbols);
        $this->timeframe = $session->timeframe;
        $this->dateFrom = $session->date_from?->format('Y-m-d') ?? '';
        $this->dateTo = $session->date_to?->format('Y-m-d') ?? '';
        $this->sessionName = '';

        // Show form
        $this->showForm = true;
        $this->showAdvanced = true;
    }

    public function selectSessionFromAnalysis(int $sessionId): void
    {
        $this->showAnalysisModal = false;
        $this->selectedSession = $sessionId;
        $this->resetPage();
        $this->dispatch('scrollToSession', sessionId: $sessionId);
    }

    #[Computed]
    public function gridAnalysis(): ?array
    {
        return cache()->remember('backtest:grid-analysis', 300, function () {
            $sessions = BacktestSession::where('status', 'completed')
                ->with('strategy')
                ->latest()
                ->limit(100)
                ->get();

            if ($sessions->isEmpty()) {
                return null;
            }

            // Single query to get all trade stats for all sessions
            $sessionIds = $sessions->pluck('id');
            $allTradeStats = BacktestTrade::whereIn('backtest_session_id', $sessionIds)
                ->selectRaw('
                    backtest_session_id,
                    COUNT(*) as total_trades,
                    SUM(CASE WHEN pnl_points > 0 THEN 1 ELSE 0 END) as win_count,
                    SUM(CASE WHEN pnl_points < 0 THEN 1 ELSE 0 END) as loss_count,
                    SUM(pnl_points) as total_pnl,
                    AVG(CASE WHEN pnl_points > 0 THEN pnl_points END) as avg_winner,
                    AVG(CASE WHEN pnl_points < 0 THEN pnl_points END) as avg_loser,
                    SUM(CASE WHEN pnl_points > 0 THEN pnl_points ELSE 0 END) as gross_win,
                    SUM(CASE WHEN pnl_points < 0 THEN pnl_points ELSE 0 END) as gross_loss
                ')
                ->groupBy('backtest_session_id')
                ->get()
                ->keyBy('backtest_session_id');

            // Get equity curve data in single query
            $equityData = BacktestTrade::whereIn('backtest_session_id', $sessionIds)
                ->orderBy('backtest_session_id')
                ->orderBy('entry_time')
                ->select('backtest_session_id', 'pnl_points', 'entry_time')
                ->get()
                ->groupBy('backtest_session_id');

            $results = [];
            foreach ($sessions as $session) {
                $stats = $allTradeStats->get($session->id);
                if (!$stats) continue;

                $totalTrades = $stats->total_trades ?? 0;
                $winCount = $stats->win_count ?? 0;
                $totalPnl = (float) ($stats->total_pnl ?? 0);
                $avgWinner = (float) ($stats->avg_winner ?? 0);
                $avgLoser = (float) ($stats->avg_loser ?? 0);
                $grossWin = (float) ($stats->gross_win ?? 0);
                $grossLoss = abs((float) ($stats->gross_loss ?? 0));

                $winRate = $totalTrades > 0 ? ($winCount / $totalTrades) * 100 : 0;
                $profitFactor = $grossLoss > 0 ? $grossWin / $grossLoss : 0;

                // Calculate max drawdown from pre-loaded equity data
                $trades = $equityData->get($session->id, collect());
                $equity = 0;
                $peak = 0;
                $maxDrawdown = 0;
                foreach ($trades as $trade) {
                    $equity += $trade->pnl_points;
                    $peak = max($peak, $equity);
                    $dd = ($peak - $equity) / ($peak ?: 1) * 100;
                    $maxDrawdown = max($maxDrawdown, $dd);
                }

                $results[] = [
                    'session' => $session,
                    'trades' => $totalTrades,
                    'total_pnl' => $totalPnl,
                    'win_rate' => $winRate,
                    'profit_factor' => $profitFactor,
                    'avg_winner' => $avgWinner,
                    'avg_loser' => $avgLoser,
                    'avg_winner_gt_avg_loser' => $avgWinner > abs((float) $avgLoser),
                    'max_drawdown' => $maxDrawdown,
                    'params' => [
                        'min_distance_pct' => $session->strategy->min_distance_pct,
                        'max_bars_after_pullback' => $session->strategy->max_bars_after_pullback,
                        'stop_atr_mult' => $session->strategy->stop_atr_mult,
                        'tp1_value' => $session->strategy->tp1_value,
                    ],
                ];
            }

            // Sort by different metrics
            return [
                'best_profit_factor' => collect($results)->sortByDesc('profit_factor')->take(5)->values(),
                'best_win_rate' => collect($results)->sortByDesc('win_rate')->take(5)->values(),
                'best_avg_winner' => collect($results)->sortByDesc('avg_winner')->take(5)->values(),
                'avg_winner_gt_avg_loser' => collect($results)->where('avg_winner_gt_avg_loser', true)
                    ->sortByDesc('avg_winner')->take(5)->values(),
                'best_max_drawdown' => collect($results)->sortBy('max_drawdown')->take(5)->values(),
                'best_total_pnl' => collect($results)->sortByDesc('total_pnl')->take(5)->values(),
            ];
        });
    }

    public function sortTrades(string $col): void
    {
        if ($this->tradesSort === $col) {
            $this->tradesSortDir = $this->tradesSortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->tradesSort    = $col;
            $this->tradesSortDir = 'asc';
        }
        $this->resetPage();
    }

    // ── Computed ──────────────────────────────────────────────────────────

    #[Computed]
    public function sessions(): Collection
    {
        return BacktestSession::latest()->limit(50)->get();
    }

    #[Computed]
    public function currentSession(): ?BacktestSession
    {
        return $this->selectedSession
            ? BacktestSession::with('strategy')->find($this->selectedSession)
            : null;
    }

    #[Computed]
    public function selectedTradeData(): ?array
    {
        if (!$this->selectedTradeId) return null;

        $trade = BacktestTrade::find($this->selectedTradeId);
        if (!$trade) return null;

        $session    = BacktestSession::find($trade->backtest_session_id);
        $tf         = $session?->timeframe ?? '5m';
        $minsPerBar = match ($tf) {
            '1m' => 1, '5m' => 5, '15m' => 15, '30m' => 30, default => 5,
        };

        $entryTime = $trade->entry_time;
        $exitTime  = $trade->exit_time ?? $entryTime;

        $candles = Candle::where('symbol', $trade->symbol)
            ->where('timeframe', $tf)
            ->where('opens_at', '>=', $entryTime->copy()->subMinutes($minsPerBar * 150))
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

        $arr = $trade->toArray();
        $arr['entry_time_ts'] = $entryTime?->timestamp;
        $arr['exit_time_ts']  = $exitTime?->timestamp;
        $arr['pullback_ts']   = $trade->pullback_time?->timestamp;
        $arr['confirm_ts']    = $trade->confirm_time?->timestamp;

        return [
            'trade'       => $arr,
            'candles'     => $candles,
            'timeframe'   => $tf,
            'mins_per_bar' => $minsPerBar,
            'view_from_ts' => $entryTime->copy()->subMinutes($minsPerBar * 40)->timestamp,
            'view_to_ts'   => $exitTime->copy()->addMinutes($minsPerBar * 20)->timestamp,
        ];
    }

    #[Computed]
    public function trades()
    {
        if (!$this->selectedSession) return null;

        $q = BacktestTrade::where('backtest_session_id', $this->selectedSession);

        if ($this->tradesFilter) {
            $q->where(function ($sub) {
                $sub->where('symbol', 'like', "%{$this->tradesFilter}%")
                    ->orWhere('direction', 'like', "%{$this->tradesFilter}%")
                    ->orWhere('result', 'like', "%{$this->tradesFilter}%");
            });
        }

        return $q->orderBy($this->tradesSort, $this->tradesSortDir)->paginate(25);
    }

    public function paginationView(): string
    {
        return 'livewire.pagination';
    }


    #[Computed]
    public function patternAnalysis(): ?array
    {
        if (!$this->selectedSession) return null;

        $session = BacktestSession::find($this->selectedSession);
        if (!$session?->isCompleted()) return null;

        // Use SQL aggregation instead of loading all trades
        $totalTrades = BacktestTrade::where('backtest_session_id', $this->selectedSession)->count();
        if ($totalTrades < 2) return null;

        $cacheKey = "backtest:pattern-analysis:{$this->selectedSession}";
        return cache()->remember($cacheKey, 300, function () use ($session, $totalTrades) {
            // Get parameter stats using SQL aggregation
            $paramDefs = [
                'rsi'               => 'RSI',
                'rel_volume'        => 'Rel Volume',
                'atr'               => 'ATR',
                'min_distance'      => 'Min EMA Dist',
                'dist_ema21_ema50'  => 'EMA21–50 Dist',
                'dist_ema50_ema100' => 'EMA50–100 Dist',
            ];

            $paramStats = [];
            foreach ($paramDefs as $key => $label) {
                $winStats = BacktestTrade::where('backtest_session_id', $this->selectedSession)
                    ->where('result', 'win')
                    ->whereNotNull($key)
                    ->selectRaw('
                        COUNT(*) as count,
                        AVG(' . $key . ') as mean,
                        MIN(' . $key . ') as min,
                        MAX(' . $key . ') as max
                    ')
                    ->first();

                $lossStats = BacktestTrade::where('backtest_session_id', $this->selectedSession)
                    ->where('result', 'loss')
                    ->whereNotNull($key)
                    ->selectRaw('
                        COUNT(*) as count,
                        AVG(' . $key . ') as mean,
                        MIN(' . $key . ') as min,
                        MAX(' . $key . ') as max
                    ')
                    ->first();

                $wMean = $winStats->mean !== null ? (float) $winStats->mean : null;
                $lMean = $lossStats->mean !== null ? (float) $lossStats->mean : null;
                $divergence = null;
                if ($wMean !== null && $lMean !== null) {
                    $scale = (abs($wMean) + abs($lMean)) / 2;
                    if ($scale > 0.0001) {
                        $divergence = round(($wMean - $lMean) / $scale * 100, 1);
                    }
                }

                $paramStats[] = [
                    'key'        => $key,
                    'label'      => $label,
                    'win'        => [
                        'count' => (int) ($winStats->count ?? 0),
                        'mean'  => $wMean !== null ? round($wMean, 4) : null,
                        'min'   => $winStats->min !== null ? round((float) $winStats->min, 4) : null,
                        'max'   => $winStats->max !== null ? round((float) $winStats->max, 4) : null,
                    ],
                    'loss'       => [
                        'count' => (int) ($lossStats->count ?? 0),
                        'mean'  => $lMean !== null ? round($lMean, 4) : null,
                        'min'   => $lossStats->min !== null ? round((float) $lossStats->min, 4) : null,
                        'max'   => $lossStats->max !== null ? round((float) $lossStats->max, 4) : null,
                    ],
                    'divergence' => $divergence,
                ];
            }

            usort($paramStats, fn ($a, $b) => abs($b['divergence'] ?? 0) <=> abs($a['divergence'] ?? 0));

            // By hour using SQL aggregation
            // Use a fixed -4h UTC offset (EDT). For EST (winter) it would be -5.
            // CONVERT_TZ requires MySQL timezone tables which may not be populated,
            // so we subtract the ET offset directly (ET = UTC-5 standard, UTC-4 DST).
            // We use -4 (EDT) as most US trading sessions fall in DST months.
            $hourStats = BacktestTrade::where('backtest_session_id', $this->selectedSession)
                ->selectRaw("
                    HOUR(DATE_SUB(entry_time, INTERVAL 4 HOUR)) as hour,
                    COUNT(*) as total,
                    SUM(CASE WHEN result = 'win' THEN 1 ELSE 0 END) as wins,
                    AVG(pnl_points) as avg_pnl
                ")
                ->groupBy('hour')
                ->havingRaw('COUNT(*) >= 3')
                ->orderBy('hour')
                ->get()
                ->map(fn ($row) => [
                    'hour'     => (int) $row->hour,
                    'total'    => (int) $row->total,
                    'wins'     => (int) $row->wins,
                    'win_rate' => $row->total > 0 ? round((int) $row->wins / (int) $row->total * 100, 1) : 0,
                    'avg_pnl'  => round((float) $row->avg_pnl, 2),
                ])
                ->keyBy('hour')
                ->toArray();

            // By direction using SQL aggregation
            $dirStats = BacktestTrade::where('backtest_session_id', $this->selectedSession)
                ->selectRaw("
                    direction,
                    COUNT(*) as total,
                    SUM(CASE WHEN result = 'win' THEN 1 ELSE 0 END) as wins,
                    AVG(pnl_points) as avg_pnl
                ")
                ->groupBy('direction')
                ->get()
                ->keyBy('direction')
                ->map(fn ($row) => [
                    'total'    => (int) $row->total,
                    'wins'     => (int) $row->wins,
                    'win_rate' => $row->total > 0 ? round((int) $row->wins / (int) $row->total * 100, 1) : 0,
                    'avg_pnl'  => round((float) $row->avg_pnl, 2),
                ])
                ->toArray();

            // Ensure both CALL and PUT are present
            if (!isset($dirStats['CALL'])) {
                $dirStats['CALL'] = ['total' => 0, 'wins' => 0, 'win_rate' => 0, 'avg_pnl' => 0];
            }
            if (!isset($dirStats['PUT'])) {
                $dirStats['PUT'] = ['total' => 0, 'wins' => 0, 'win_rate' => 0, 'avg_pnl' => 0];
            }

            // By exit reason using SQL aggregation
            $exitStats = BacktestTrade::where('backtest_session_id', $this->selectedSession)
                ->selectRaw("
                    COALESCE(exit_reason, 'unknown') as reason,
                    COUNT(*) as total,
                    SUM(CASE WHEN result = 'win' THEN 1 ELSE 0 END) as wins,
                    AVG(pnl_points) as avg_pnl
                ")
                ->groupBy('reason')
                ->orderByDesc('total')
                ->get()
                ->keyBy('reason')
                ->map(fn ($row) => [
                    'total'    => (int) $row->total,
                    'wins'     => (int) $row->wins,
                    'win_rate' => $row->total > 0 ? round((int) $row->wins / (int) $row->total * 100, 1) : 0,
                    'avg_pnl'  => round((float) $row->avg_pnl, 2),
                ])
                ->toArray();

            // Get win/loss counts
            $winCount = BacktestTrade::where('backtest_session_id', $this->selectedSession)
                ->where('result', 'win')->count();
            $lossCount = BacktestTrade::where('backtest_session_id', $this->selectedSession)
                ->where('result', 'loss')->count();

            return [
                'params'     => $paramStats,
                'by_hour'    => $hourStats,
                'by_dir'     => $dirStats,
                'by_exit'    => $exitStats,
                'win_count'  => $winCount,
                'loss_count' => $lossCount,
                'total'      => $totalTrades,
            ];
        });
    }

    private function distributionStats(\Illuminate\Support\Collection $values): array
    {
        if ($values->isEmpty()) {
            return ['count' => 0, 'mean' => null, 'median' => null,
                    'p25' => null, 'p75' => null, 'min' => null, 'max' => null];
        }
        $sorted = $values->sort()->values();
        return [
            'count'  => $sorted->count(),
            'mean'   => round((float) $values->avg(), 4),
            'median' => $this->percentile($sorted, 50),
            'p25'    => $this->percentile($sorted, 25),
            'p75'    => $this->percentile($sorted, 75),
            'min'    => round((float) $sorted->first(), 4),
            'max'    => round((float) $sorted->last(), 4),
        ];
    }

    private function percentile(\Illuminate\Support\Collection $sorted, int $p): float
    {
        $n = $sorted->count();
        if ($n === 0) return 0.0;
        $idx   = ($p / 100) * ($n - 1);
        $lower = (int) floor($idx);
        $upper = (int) ceil($idx);
        if ($lower === $upper) return round((float) $sorted[$lower], 4);
        return round(
            (float) $sorted[$lower] + ($idx - $lower) * ((float) $sorted[$upper] - (float) $sorted[$lower]),
            4
        );
    }

    // ── Render ────────────────────────────────────────────────────────────

    public function render()
    {
        return view('livewire.backtest-manager', [
            'sessions'          => $this->sessions,
            'currentSession'    => $this->currentSession,
            'trades'            => $this->trades,
            'selectedTradeData' => $this->selectedTradeData,
            'patternAnalysis'   => $this->patternAnalysis,
            'gridAnalysis'      => $this->gridAnalysis,
            'simProfitResult'   => $this->simProfitResult,
        ]);
    }
}

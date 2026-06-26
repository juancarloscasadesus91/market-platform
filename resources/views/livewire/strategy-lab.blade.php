<div class="space-y-6" @if(!$selectedTradeId) wire:poll.5s="refreshRunningSessions" @endif>

    {{-- ── Header ── --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-100">🧪 Strategy Lab</h1>
            <p class="text-sm text-slate-400 mt-0.5">Pluggable backtest engine · any strategy, same infrastructure</p>
        </div>
        <button wire:click="$set('showForm', true)"
                class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            New Run
        </button>
    </div>

    {{-- ── New Session Modal ── --}}
    @if($showForm)
    <div class="fixed inset-0 z-50 flex items-start justify-center bg-black/70 backdrop-blur-sm p-4 overflow-y-auto">
        <div class="w-full max-w-3xl bg-[#111318] border border-slate-700/60 rounded-2xl shadow-2xl my-8">

            {{-- Modal header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-700/50">
                <h2 class="text-lg font-bold text-slate-100">New Strategy Lab Run</h2>
                <button wire:click="$set('showForm', false)" class="text-slate-400 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="p-6 space-y-6 max-h-[80vh] overflow-y-auto">

                {{-- Session basics --}}
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="block text-xs font-medium text-slate-400 mb-1">Session Name (optional)</label>
                        <input wire:model="sessionName" type="text" placeholder="e.g. SPY 5m test #1"
                               class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-100 focus:border-blue-500 focus:outline-none">
                    </div>

                    <div class="col-span-2">
                        <label class="block text-xs font-medium text-slate-400 mb-1">Strategy</label>
                        <select wire:model.live="strategyKey"
                                class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-100 focus:border-blue-500 focus:outline-none">
                            @foreach($this->strategyOptions as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-span-2">
                        <label class="block text-xs font-medium text-slate-400 mb-1">Symbols (comma-separated)</label>
                        <input wire:model="symbolsInput" type="text" placeholder="SPY,QQQ"
                               class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-100 focus:border-blue-500 focus:outline-none">
                        @error('symbolsInput') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Timeframe</label>
                        <select wire:model="timeframe"
                                class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-100 focus:border-blue-500 focus:outline-none">
                            @foreach(['1m'=>'1 min','5m'=>'5 min','15m'=>'15 min','30m'=>'30 min','1h'=>'1 hour','4h'=>'4 hours','1d'=>'Daily'] as $v => $l)
                                <option value="{{ $v }}">{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div></div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Date From</label>
                        <input wire:model="dateFrom" type="date"
                               class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-100 focus:border-blue-500 focus:outline-none">
                        @error('dateFrom') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Date To</label>
                        <input wire:model="dateTo" type="date"
                               class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-100 focus:border-blue-500 focus:outline-none">
                        @error('dateTo') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Strategy Parameters (dynamic from schema) --}}
                @if(!empty($this->schemaByGroup))
                <div>
                    <button wire:click="$toggle('showParamsSection')"
                            class="flex items-center gap-2 text-sm font-semibold text-slate-300 hover:text-white mb-3">
                        <svg class="w-4 h-4 transition-transform {{ $showParamsSection ? 'rotate-90' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                        Strategy Parameters
                    </button>

                    @if($showParamsSection)
                    <div class="space-y-6">
                        @foreach($this->schemaByGroup as $group => $fields)
                        <div>
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3 pb-1 border-b border-slate-700/50">{{ $group }}</p>
                            <div class="grid grid-cols-2 gap-3">
                                @foreach($fields as $field)
                                @php
                                    $visible = true;
                                    if (!empty($field['show_when'])) {
                                        foreach ($field['show_when'] as $depKey => $allowedVals) {
                                            $currentVal = $this->params[$depKey] ?? null;
                                            if (!in_array($currentVal, $allowedVals)) {
                                                $visible = false;
                                                break;
                                            }
                                        }
                                    }
                                @endphp
                                @if($visible)
                                <div>
                                    <label class="block text-xs font-medium text-slate-400 mb-1">{{ $field['label'] }}</label>

                                    @if($field['type'] === 'select')
                                        <select wire:model.live="params.{{ $field['key'] }}"
                                                class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-100 focus:border-blue-500 focus:outline-none">
                                            @foreach($field['options'] as $val => $lbl)
                                                <option value="{{ $val }}">{{ $lbl }}</option>
                                            @endforeach
                                        </select>

                                    @elseif($field['type'] === 'bool')
                                        <div class="flex items-center gap-2 mt-2">
                                            <input wire:model="params.{{ $field['key'] }}" type="checkbox"
                                                   class="w-4 h-4 rounded border-slate-600 bg-slate-800 text-blue-500 focus:ring-blue-500">
                                            <span class="text-xs text-slate-400">Enable</span>
                                        </div>

                                    @elseif($field['type'] === 'time')
                                        <input wire:model="params.{{ $field['key'] }}" type="time"
                                               class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-100 focus:border-blue-500 focus:outline-none">

                                    @else
                                        <input wire:model="params.{{ $field['key'] }}"
                                               type="number"
                                               step="{{ $field['step'] ?? ($field['type'] === 'int' ? 1 : 0.01) }}"
                                               min="{{ $field['min'] ?? '' }}"
                                               max="{{ $field['max'] ?? '' }}"
                                               placeholder="{{ $field['default'] ?? '' }}"
                                               class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-100 focus:border-blue-500 focus:outline-none">
                                    @endif
                                </div>
                                @endif
                                @endforeach
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>
                @endif

            </div>

            <div class="px-6 py-4 border-t border-slate-700/50 flex justify-end gap-3">
                <button wire:click="$set('showForm', false)"
                        class="px-4 py-2 text-sm text-slate-400 hover:text-white transition-colors">
                    Cancel
                </button>
                <button wire:click="runBacktest" wire:loading.attr="disabled"
                        class="px-5 py-2 bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white text-sm font-semibold rounded-lg transition-colors">
                    <span wire:loading.remove wire:target="runBacktest">🚀 Run Backtest</span>
                    <span wire:loading wire:target="runBacktest">Running…</span>
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- ── Sessions list ── --}}
    <div class="bg-[#111318] border border-slate-700/50 rounded-xl overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-700/50">
            <h3 class="text-sm font-semibold text-slate-300">Sessions</h3>
        </div>

        @if($this->sessions->isEmpty())
            <div class="py-16 text-center text-slate-500 text-sm">
                No sessions yet. Click <strong class="text-slate-400">New Run</strong> to start.
            </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="bg-slate-800/40 text-slate-400 uppercase tracking-wide">
                        <th class="px-4 py-2 text-left">Name / Strategy</th>
                        <th class="px-4 py-2 text-left">Symbols</th>
                        <th class="px-4 py-2 text-left">TF / Range</th>
                        <th class="px-4 py-2 text-center">Status</th>
                        <th class="px-4 py-2 text-right">Trades</th>
                        <th class="px-4 py-2 text-right">Win%</th>
                        <th class="px-4 py-2 text-right">PnL pts</th>
                        <th class="px-4 py-2 text-right">PF</th>
                        <th class="px-4 py-2 text-right">DD</th>
                        <th class="px-4 py-2 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/30">
                    @foreach($this->sessions as $session)
                    <tr wire:key="lab-{{ $session->id }}"
                        wire:click="selectSession({{ $session->id }})"
                        class="cursor-pointer transition-colors hover:bg-slate-800/30 {{ $selectedSession === $session->id ? 'bg-blue-500/10 border-l-2 border-l-blue-500' : '' }}">

                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-200">{{ $session->name ?: 'Lab #'.$session->id }}</p>
                            <p class="text-slate-500 mt-0.5">{{ $session->strategyLabel() }}</p>
                        </td>
                        <td class="px-4 py-3 text-slate-300">{{ $session->symbolsLabel() }}</td>
                        <td class="px-4 py-3 text-slate-400">
                            <span class="uppercase font-mono">{{ $session->timeframe }}</span>
                            <span class="block text-slate-500">{{ $session->date_from->format('M j') }} – {{ $session->date_to->format('M j, Y') }}</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($session->isRunning())
                                <div class="flex items-center justify-center gap-1.5">
                                    <div class="w-2 h-2 rounded-full bg-blue-400 animate-pulse"></div>
                                    <span class="text-blue-400 font-medium">{{ $session->progress }}%</span>
                                </div>
                                <p class="text-slate-500 text-[10px] mt-0.5 truncate max-w-[120px]">{{ $session->progress_label }}</p>
                            @elseif($session->isCompleted())
                                <span class="px-2 py-0.5 bg-emerald-500/20 text-emerald-400 rounded-full font-medium">Done</span>
                            @elseif($session->isFailed())
                                <span class="px-2 py-0.5 bg-red-500/20 text-red-400 rounded-full font-medium">Failed</span>
                            @else
                                <span class="px-2 py-0.5 bg-slate-600/40 text-slate-400 rounded-full font-medium">Pending</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right text-slate-300">{{ $session->total_trades ?: '–' }}</td>
                        <td class="px-4 py-3 text-right {{ ($session->win_rate ?? 0) >= 50 ? 'text-emerald-400' : 'text-red-400' }}">
                            {{ $session->win_rate !== null ? number_format($session->win_rate, 1).'%' : '–' }}
                        </td>
                        <td class="px-4 py-3 text-right {{ ($session->total_pnl_points ?? 0) >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                            {{ $session->total_pnl_points !== null ? number_format($session->total_pnl_points, 2) : '–' }}
                        </td>
                        <td class="px-4 py-3 text-right {{ ($session->profit_factor ?? 0) >= 1 ? 'text-emerald-400' : 'text-red-400' }}">
                            {{ $session->profit_factor !== null ? number_format($session->profit_factor, 2) : '–' }}
                        </td>
                        <td class="px-4 py-3 text-right text-red-400">
                            {{ $session->max_drawdown !== null ? number_format($session->max_drawdown, 2) : '–' }}
                        </td>
                        <td class="px-4 py-3 text-center" wire:click.stop="">
                            <button wire:click="deleteSession({{ $session->id }})"
                                    wire:confirm="Delete this session and all its trades?"
                                    class="text-slate-500 hover:text-red-400 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-700/30">
            {{ $this->sessions->links() }}
        </div>
        @endif
    </div>

    {{-- ── Session Detail Panel ── --}}
    @if($selectedSession && $this->selectedSessionModel)
    @php $s = $this->selectedSessionModel; @endphp
    <div class="bg-[#111318] border border-slate-700/50 rounded-xl overflow-hidden">

        {{-- Tabs --}}
        <div class="flex items-center gap-0 border-b border-slate-700/50">
            @foreach(['trades' => 'Trades', 'stats' => 'Stats', 'params' => 'Params', 'error' => 'Debug'] as $tab => $label)
            <button wire:click="$set('activeTab', '{{ $tab }}')"
                    class="px-5 py-3 text-sm font-medium transition-colors border-b-2 {{ $activeTab === $tab ? 'text-blue-400 border-blue-500' : 'text-slate-400 hover:text-white border-transparent' }}">
                {{ $label }}
            </button>
            @endforeach
            <div class="ml-auto pr-4">
                <button wire:click="openSimProfitModal"
                        class="flex items-center gap-2 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold rounded-lg transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Sim Profit
                </button>
            </div>
        </div>

        {{-- ── Trades Tab ── --}}
        @if($activeTab === 'trades')
        <div class="p-4 space-y-3">
            <div class="flex items-center gap-3">
                <input wire:model.live.debounce.300ms="tradesFilter" type="text" placeholder="Filter direction/result/symbol…"
                       class="w-64 bg-slate-800 border border-slate-600 rounded-lg px-3 py-1.5 text-sm text-slate-100 focus:border-blue-500 focus:outline-none">
                <span class="text-xs text-slate-500">{{ $this->sessionTrades->count() }} trades</span>
            </div>

            @if($this->sessionTrades->isEmpty())
                <p class="text-center py-8 text-slate-500 text-sm">No trades found.</p>
            @else
            <div class="overflow-x-auto rounded-lg">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="bg-slate-800/60 text-slate-400 uppercase tracking-wide">
                            <th class="px-3 py-2 text-left">Symbol</th>
                            <th wire:click="sortTrades('direction')" class="px-3 py-2 text-left cursor-pointer hover:text-white">Dir</th>
                            <th wire:click="sortTrades('entry_time')" class="px-3 py-2 text-left cursor-pointer hover:text-white">Entry</th>
                            <th class="px-3 py-2 text-right">Entry $</th>
                            <th wire:click="sortTrades('exit_time')" class="px-3 py-2 text-left cursor-pointer hover:text-white">Exit</th>
                            <th class="px-3 py-2 text-right">Exit $</th>
                            <th class="px-3 py-2 text-left">Reason</th>
                            <th wire:click="sortTrades('result')" class="px-3 py-2 text-center cursor-pointer hover:text-white">Result</th>
                            <th wire:click="sortTrades('pnl_points')" class="px-3 py-2 text-right cursor-pointer hover:text-white">PnL pts</th>
                            <th wire:click="sortTrades('r_multiple')" class="px-3 py-2 text-right cursor-pointer hover:text-white">R</th>
                            <th class="px-3 py-2 text-center w-8"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/30">
                        @foreach($this->sessionTrades as $trade)
                        <tr wire:key="lt-{{ $trade->id }}" class="hover:bg-slate-800/20">
                            <td class="px-3 py-2 font-mono text-slate-300">{{ $trade->symbol }}</td>
                            <td class="px-3 py-2">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold {{ $trade->direction === 'CALL' || $trade->direction === 'LONG' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400' }}">
                                    {{ $trade->direction }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-slate-400">{{ $trade->entry_time?->format('M j H:i') ?? '–' }}</td>
                            <td class="px-3 py-2 text-right text-slate-300">{{ $trade->entry_price ? number_format($trade->entry_price, 2) : '–' }}</td>
                            <td class="px-3 py-2 text-slate-400">{{ $trade->exit_time?->format('M j H:i') ?? '–' }}</td>
                            <td class="px-3 py-2 text-right text-slate-300">{{ $trade->exit_price ? number_format($trade->exit_price, 2) : '–' }}</td>
                            <td class="px-3 py-2 text-slate-500">{{ str_replace('_', ' ', $trade->exit_reason ?? '–') }}</td>
                            <td class="px-3 py-2 text-center">
                                @php
                                    $rc = match($trade->result) { 'win'=>'text-emerald-400','loss'=>'text-red-400','breakeven'=>'text-yellow-400', default=>'text-slate-400' };
                                @endphp
                                <span class="{{ $rc }} font-medium">{{ ucfirst($trade->result) }}</span>
                            </td>
                            <td class="px-3 py-2 text-right {{ ($trade->pnl_points ?? 0) >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                                {{ $trade->pnl_points !== null ? number_format($trade->pnl_points, 2) : '–' }}
                            </td>
                            <td class="px-3 py-2 text-right {{ ($trade->r_multiple ?? 0) >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                                {{ $trade->r_multiple !== null ? number_format($trade->r_multiple, 2).'R' : '–' }}
                            </td>
                            <td class="px-3 py-2 text-center">
                                <button wire:click="viewTrade({{ $trade->id }})" title="Ver en gráfica"
                                        class="text-slate-500 hover:text-blue-400 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>

        {{-- ── Stats Tab ── --}}
        @elseif($activeTab === 'stats')
        <div class="p-5 space-y-6">
            @if(!$s->isCompleted())
                <p class="text-slate-500 text-sm">Stats available after session completes.</p>
            @else
            @php
                $allTrades   = $this->sessionTrades;
                $wr          = $s->win_rate ?? 0;
                $pf          = $s->profit_factor ?? 0;
                $avgW        = $s->avg_winner_pts ?? 0;
                $avgL        = abs($s->avg_loser_pts ?? 0);
                $expectancy  = ($wr/100) * $avgW - ((100-$wr)/100) * $avgL;
                $avgR        = $allTrades->whereNotNull('r_multiple')->avg('r_multiple');
                $medianPnl   = (function($trades) {
                    $vals = $trades->pluck('pnl_points')->sort()->values();
                    $n = $vals->count();
                    if (!$n) return null;
                    return $n % 2 ? $vals[$n/2] : ($vals[$n/2-1] + $vals[$n/2]) / 2;
                })($allTrades);

                // By direction
                $byDir = $allTrades->groupBy('direction');

                // By hour (ET)
                $byHour = $allTrades->groupBy(fn($t) => $t->entry_time?->setTimezone('America/New_York')->format('H'));

                // By day of week
                $byDow = $allTrades->groupBy(fn($t) => $t->entry_time?->setTimezone('America/New_York')->format('N')); // 1=Mon..5=Fri
                $dowNames = ['1'=>'Mon','2'=>'Tue','3'=>'Wed','4'=>'Thu','5'=>'Fri','6'=>'Sat','7'=>'Sun'];

                // By exit reason
                $byReason = $allTrades->groupBy('exit_reason');

                // Consecutive stats
                $streak = 0; $maxWin = 0; $maxLoss = 0; $cur = 0; $curDir = null;
                foreach ($allTrades->sortBy('entry_time') as $t) {
                    $isW = $t->result === 'win';
                    if ($curDir === $isW) { $cur++; } else { $cur = 1; $curDir = $isW; }
                    if ($isW && $cur > $maxWin)  $maxWin  = $cur;
                    if (!$isW && $cur > $maxLoss) $maxLoss = $cur;
                }
            @endphp

            {{-- ── KPI Overview ── --}}
            <div>
                <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Overview</h4>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    @foreach([
                        ['Trades',        $s->total_trades,                                        'text-slate-200'],
                        ['Win Rate',       number_format($wr,1).'%',                               $wr>=50?'text-emerald-400':'text-red-400'],
                        ['Profit Factor',  $s->profit_factor!==null?number_format($pf,2):'–',      $pf>=1?'text-emerald-400':'text-red-400'],
                        ['Total PnL',      number_format($s->total_pnl_points??0,2).' pts',        ($s->total_pnl_points??0)>=0?'text-emerald-400':'text-red-400'],
                        ['Max Drawdown',   number_format($s->max_drawdown??0,2).' pts',             'text-red-400'],
                        ['Expectancy',     number_format($expectancy,3).' pts',                     $expectancy>=0?'text-emerald-400':'text-red-400'],
                        ['Avg Winner',     number_format($avgW,2).' pts',                          'text-emerald-400'],
                        ['Avg Loser',      '−'.number_format($avgL,2).' pts',                      'text-red-400'],
                        ['Avg R',          $avgR!==null?number_format($avgR,2).'R':'–',            ($avgR??0)>=0?'text-emerald-400':'text-red-400'],
                        ['Median PnL',     $medianPnl!==null?number_format($medianPnl,2).' pts':'–', ($medianPnl??0)>=0?'text-emerald-400':'text-red-400'],
                        ['Max Win Streak', $maxWin,                                                'text-emerald-400'],
                        ['Max Loss Streak',$maxLoss,                                               'text-red-400'],
                    ] as [$lbl,$val,$clr])
                    <div class="bg-slate-800/50 rounded-lg p-3 border border-slate-700/30">
                        <p class="text-[10px] text-slate-500 uppercase tracking-wide mb-1">{{ $lbl }}</p>
                        <p class="text-base font-bold {{ $clr }}">{{ $val }}</p>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- ── By Direction ── --}}
            <div>
                <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">By Direction</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach($byDir as $dir => $dTrades)
                    @php
                        $dW   = $dTrades->where('result','win');
                        $dL   = $dTrades->where('result','loss');
                        $dWr  = $dTrades->count() > 0 ? round($dW->count()/$dTrades->count()*100,1) : 0;
                        $dPf  = abs($dL->sum('pnl_points'))>0 ? round($dW->sum('pnl_points')/abs($dL->sum('pnl_points')),2) : null;
                        $dPnl = round($dTrades->sum('pnl_points'),2);
                        $isCall = in_array($dir, ['CALL','LONG']);
                    @endphp
                    <div class="bg-slate-800/40 rounded-lg p-4 border border-slate-700/30">
                        <div class="flex items-center justify-between mb-3">
                            <span class="px-2 py-0.5 rounded text-xs font-bold {{ $isCall ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400' }}">{{ $dir }}</span>
                            <span class="text-xs text-slate-400">{{ $dTrades->count() }} trades</span>
                        </div>
                        <div class="grid grid-cols-4 gap-2 text-center">
                            <div><p class="text-[10px] text-slate-500">Win%</p><p class="font-semibold text-sm {{ $dWr>=50?'text-emerald-400':'text-red-400' }}">{{ $dWr }}%</p></div>
                            <div><p class="text-[10px] text-slate-500">PF</p><p class="font-semibold text-sm {{ ($dPf??0)>=1?'text-emerald-400':'text-red-400' }}">{{ $dPf!==null?number_format($dPf,2):'–' }}</p></div>
                            <div><p class="text-[10px] text-slate-500">PnL</p><p class="font-semibold text-sm {{ $dPnl>=0?'text-emerald-400':'text-red-400' }}">{{ number_format($dPnl,2) }}</p></div>
                            <div><p class="text-[10px] text-slate-500">W/L</p><p class="font-semibold text-sm text-slate-300">{{ $dW->count() }}/{{ $dL->count() }}</p></div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- ── By Hour ── --}}
            @if($byHour->isNotEmpty())
            <div>
                <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">By Hour (ET)</h4>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="text-slate-500 border-b border-slate-700/40">
                                <th class="py-1.5 text-left pr-4">Hour</th>
                                <th class="py-1.5 text-center px-2">Trades</th>
                                <th class="py-1.5 text-center px-2">Win%</th>
                                <th class="py-1.5 text-right px-2">Avg PnL</th>
                                <th class="py-1.5 text-right px-2">Total PnL</th>
                                <th class="py-1.5 px-2 w-32">Distribution</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/20">
                            @foreach($byHour->sortKeys() as $hour => $hTrades)
                            @php
                                $hWr   = $hTrades->count()>0 ? round($hTrades->where('result','win')->count()/$hTrades->count()*100,0) : 0;
                                $hAvg  = round($hTrades->avg('pnl_points')??0, 2);
                                $hTot  = round($hTrades->sum('pnl_points'), 2);
                                $hMax  = $byHour->map(fn($g)=>$g->count())->max();
                                $barW  = $hMax > 0 ? round($hTrades->count()/$hMax*100) : 0;
                            @endphp
                            <tr class="hover:bg-slate-800/20">
                                <td class="py-2 pr-4 font-mono text-slate-300">{{ $hour }}:00</td>
                                <td class="py-2 text-center px-2 text-slate-400">{{ $hTrades->count() }}</td>
                                <td class="py-2 text-center px-2 {{ $hWr>=50?'text-emerald-400':'text-red-400' }}">{{ $hWr }}%</td>
                                <td class="py-2 text-right px-2 {{ $hAvg>=0?'text-emerald-400':'text-red-400' }}">{{ number_format($hAvg,2) }}</td>
                                <td class="py-2 text-right px-2 {{ $hTot>=0?'text-emerald-400':'text-red-400' }}">{{ number_format($hTot,2) }}</td>
                                <td class="py-2 px-2">
                                    <div class="h-2 bg-slate-700/40 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full {{ $hAvg>=0?'bg-emerald-500/60':'bg-red-500/60' }}" style="width:{{ $barW }}%"></div>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            {{-- ── By Day of Week ── --}}
            @if($byDow->isNotEmpty())
            <div>
                <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">By Day of Week</h4>
                <div class="grid grid-cols-5 gap-2">
                    @foreach($byDow->sortKeys() as $dow => $dowTrades)
                    @php
                        $dowWr  = $dowTrades->count()>0 ? round($dowTrades->where('result','win')->count()/$dowTrades->count()*100,0) : 0;
                        $dowPnl = round($dowTrades->sum('pnl_points'),2);
                    @endphp
                    <div class="bg-slate-800/40 rounded-lg p-3 text-center border border-slate-700/30">
                        <p class="text-xs font-semibold text-slate-400 mb-1">{{ $dowNames[$dow] ?? $dow }}</p>
                        <p class="text-xs text-slate-500">{{ $dowTrades->count() }} trades</p>
                        <p class="text-sm font-bold mt-1 {{ $dowWr>=50?'text-emerald-400':'text-red-400' }}">{{ $dowWr }}%</p>
                        <p class="text-xs {{ $dowPnl>=0?'text-emerald-400':'text-red-400' }}">{{ number_format($dowPnl,2) }}</p>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- ── By Exit Reason ── --}}
            @if($byReason->isNotEmpty())
            <div>
                <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">By Exit Reason</h4>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                    @foreach($byReason as $reason => $rTrades)
                    @php
                        $rWr  = $rTrades->count()>0 ? round($rTrades->where('result','win')->count()/$rTrades->count()*100,0) : 0;
                        $rPnl = round($rTrades->sum('pnl_points'),2);
                    @endphp
                    <div class="bg-slate-800/40 rounded-lg p-3 border border-slate-700/30">
                        <p class="text-[10px] text-slate-500 font-mono mb-1">{{ str_replace('_',' ',$reason??'unknown') }}</p>
                        <p class="text-xs text-slate-400">{{ $rTrades->count() }} trades · {{ $rWr }}% win</p>
                        <p class="text-sm font-bold {{ $rPnl>=0?'text-emerald-400':'text-red-400' }}">{{ number_format($rPnl,2) }} pts</p>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            @endif
        </div>

        {{-- ── Params Tab ── --}}
        @elseif($activeTab === 'params')
        <div class="p-5">
            <div class="flex items-center justify-between mb-4">
                <p class="text-xs font-semibold text-slate-500 uppercase">Strategy: {{ $s->strategyLabel() }}</p>
                <button wire:click="loadConfig({{ $s->id }})"
                        class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    Load Config
                </button>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                @foreach($s->params ?? [] as $key => $value)
                <div class="bg-slate-800/40 rounded-lg px-3 py-2">
                    <p class="text-[10px] text-slate-500 font-mono">{{ $key }}</p>
                    <p class="text-sm text-slate-200 mt-0.5">{{ $value ?? 'null' }}</p>
                </div>
                @endforeach
            </div>
        </div>

        {{-- ── Debug Tab ── --}}
        @elseif($activeTab === 'error')
        <div class="p-5">
            @if($s->error_message)
                <pre class="text-xs text-red-300 bg-slate-900 rounded-lg p-4 overflow-x-auto whitespace-pre-wrap">{{ $s->error_message }}</pre>
            @else
                <p class="text-slate-500 text-sm">No debug info.</p>
            @endif
        </div>
        @endif

    </div>
    @endif

    {{-- ── Trade Chart Modal ── --}}
    @if($selectedTradeId && $this->selectedTradeData)
    @php $td = $this->selectedTradeData['trade']; @endphp
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4"
         wire:ignore
         x-data="{
            _tc: null,
            showVolume: true,
            showBB: true,
            showEMAs: true,
            init() { this.$nextTick(() => this._buildChart()); },
            _buildChart() {
                const payload = this.$refs.labPayload;
                if (!payload) return;
                const data = JSON.parse(payload.textContent);
                if (!data || !data.candles || !data.candles.length) return;
                const tradeId = {{ $td['id'] ?? 0 }};
                window._labChartBuilt = window._labChartBuilt || {};
                if (window._labChartBuilt[tradeId]) return;
                window._labChartBuilt[tradeId] = true;
                const container = document.getElementById('lab-trade-chart');
                if (!container) return;
                container.innerHTML = '';
                this._tc = new window.TradingChart('lab-trade-chart', data.candles);
                const t = data.trade;
                const isCall = t.direction === 'CALL' || t.direction === 'LONG';
                const stratKey = data.strategy_key || t.strategy_key || '';

                if (stratKey === 'bollinger_rsi') {
                    const bbPeriod = data.session_params?.bb_length ?? 30;
                    const bbDev    = data.session_params?.bb_dev    ?? 2.0;
                    this._tc.addBollingerBands(bbPeriod, bbDev);
                    this._tc.addEMAs([
                        { period: 21,  color: '#3b82f6', title: 'EMA21'  },
                        { period: 50,  color: '#f59e0b', title: 'EMA50'  },
                        { period: 100, color: '#a78bfa', title: 'EMA100' },
                    ]);
                } else {
                    this._tc.addEMAs([
                        { period: 21,  color: '#3b82f6', title: 'EMA21'  },
                        { period: 50,  color: '#f59e0b', title: 'EMA50'  },
                        { period: 100, color: '#a78bfa', title: 'EMA100' },
                    ]);
                }

                const markers = [];
                if (t.entry_time_ts) {
                    markers.push({ time: t.entry_time_ts, position: isCall ? 'belowBar' : 'aboveBar', color: '#3b82f6', shape: isCall ? 'arrowUp' : 'arrowDown', text: 'Entry', size: 2 });
                }
                if (t.exit_time_ts && t.exit_time_ts !== t.entry_time_ts) {
                    const ec = t.result === 'win' ? '#10b981' : t.result === 'loss' ? '#ef4444' : '#94a3b8';
                    markers.push({ time: t.exit_time_ts, position: isCall ? 'aboveBar' : 'belowBar', color: ec, shape: 'circle', text: 'Exit', size: 2 });
                }
                markers.sort((a, b) => a.time - b.time);
                this._tc.candleSeries.setMarkers(markers);

                [
                    { price: t.stop_loss,     color: '#ef4444', title: 'SL',  style: 2 },
                    { price: t.take_profit_1, color: '#10b981', title: 'TP1', style: 2 },
                    { price: t.take_profit_2, color: '#34d399', title: 'TP2', style: 2 },
                    { price: t.take_profit_3, color: '#6ee7b7', title: 'TP3', style: 1 },
                ].forEach(l => {
                    if (l.price) {
                        this._tc.candleSeries.createPriceLine({ price: l.price, color: l.color, lineWidth: 1, lineStyle: l.style, axisLabelVisible: true, title: l.title });
                    }
                });

                if (data.view_from_ts && data.view_to_ts) {
                    this._tc.chart.timeScale().setVisibleRange({ from: data.view_from_ts, to: data.view_to_ts });
                }

                const overlay = document.getElementById('lab-chart-crosshair');
                if (overlay) {
                    this._tc.chart.subscribeCrosshairMove(param => {
                        if (!param.point || !param.time || param.point.x < 0 || param.point.y < 0) { overlay.style.display = 'none'; return; }
                        const price = param.seriesData.get(this._tc.candleSeries);
                        if (!price) { overlay.style.display = 'none'; return; }
                        const closePrice = price.close ?? price.value ?? null;
                        if (closePrice === null) { overlay.style.display = 'none'; return; }
                        const timeStr = new Date(param.time * 1000).toLocaleTimeString('en-US', { timeZone: 'America/New_York', hour: '2-digit', minute: '2-digit', hour12: false });
                        const dateStr = new Date(param.time * 1000).toLocaleDateString('en-US', { timeZone: 'America/New_York', month: 'short', day: 'numeric' });
                        overlay.querySelector('#lab-cross-price').textContent = closePrice.toFixed(2);
                        overlay.querySelector('#lab-cross-time').textContent  = dateStr + ' ' + timeStr + ' ET';
                        overlay.style.display = 'flex';
                    });
                }
                this.showVolume = true;
                this.showBB = true;
                this.showEMAs = true;
            },
            toggleVolume() { this.showVolume = !this.showVolume; if (this._tc) this._tc.toggleVolume(this.showVolume); },
            toggleBB()     { this.showBB     = !this.showBB;     if (this._tc) this._tc.toggleBB(this.showBB); },
            toggleEMAs()   { this.showEMAs   = !this.showEMAs;   if (this._tc) this._tc.toggleEMAs(this.showEMAs); },
            destroy() { if (this._tc) { this._tc.destroy(); this._tc = null; } window._labChartBuilt = {}; }
         }"
         x-on:keydown.escape.window="$wire.closeTrade()">

        <script type="application/json" x-ref="labPayload">@json($this->selectedTradeData)</script>

        @php
            $resClrM = match($td['result'] ?? '') {
                'win'  => 'text-emerald-400 bg-emerald-500/10 border-emerald-500/30',
                'loss' => 'text-red-400 bg-red-500/10 border-red-500/30',
                default => 'text-slate-400 bg-slate-700/30 border-slate-600/30'
            };
            $dirClrM = in_array($td['direction'] ?? '', ['CALL','LONG']) ? 'bg-blue-500/20 text-blue-300' : 'bg-red-500/20 text-red-300';
            $pnl     = $td['pnl_points'] ?? 0;
            $pnlPct  = $td['pnl_pct']    ?? 0;
            $rMult   = $td['r_multiple'] ?? null;
            $pnlClrM = $pnl >= 0 ? 'text-emerald-400' : 'text-red-400';
            $fmtEt   = fn($utc) => $utc ? \Carbon\Carbon::parse($utc, 'UTC')->setTimezone('America/New_York')->format('Y-m-d H:i').' ET' : '—';
            $stratKey = $this->selectedTradeData['strategy_key'] ?? ($td['strategy_key'] ?? '');
        @endphp

        <div class="w-full max-w-screen-2xl max-h-[96vh] bg-[#111318] border border-slate-700/60 rounded-2xl shadow-2xl flex flex-col overflow-hidden">

            {{-- Header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-700/50 flex-shrink-0">
                <div class="flex items-center gap-3">
                    <span class="text-lg font-bold text-slate-100 font-mono">{{ $td['symbol'] ?? '—' }}</span>
                    <span class="px-2 py-0.5 rounded text-xs font-bold {{ $dirClrM }}">{{ $td['direction'] ?? '—' }}</span>
                    <span class="px-2 py-0.5 rounded text-xs font-bold border {{ $resClrM }}">{{ ucfirst($td['result'] ?? '—') }}</span>
                    <span class="text-xs text-slate-400">{{ $this->selectedTradeData['timeframe'] }} · #{{ $td['id'] ?? '' }}</span>
                </div>
                <button wire:click="closeTrade" class="p-2 rounded-lg text-slate-400 hover:text-white hover:bg-slate-700/50 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="flex flex-1 overflow-hidden">

                {{-- Left sidebar --}}
                <div class="w-60 flex-shrink-0 overflow-y-auto border-r border-slate-700/50 p-4 space-y-4 text-xs">

                    {{-- P&L --}}
                    <div class="grid grid-cols-3 gap-2">
                        <div class="col-span-3 bg-slate-800/60 rounded-lg p-3 text-center">
                            <p class="text-slate-400 text-xs mb-0.5">P&L</p>
                            <p class="text-xl font-bold {{ $pnlClrM }}">{{ $pnl >= 0 ? '+' : '' }}{{ number_format($pnl, 2) }} pts</p>
                            <p class="text-sm {{ $pnlClrM }}">{{ $pnlPct >= 0 ? '+' : '' }}{{ number_format($pnlPct, 2) }}% · {{ $rMult !== null ? number_format($rMult, 2).'R' : '—' }}</p>
                        </div>
                        <div class="bg-slate-800/40 rounded-lg p-2 text-center">
                            <p class="text-slate-500 text-xs">MFE</p>
                            <p class="text-emerald-400 font-semibold">{{ number_format($td['max_favorable_excursion'] ?? 0, 2) }}</p>
                        </div>
                        <div class="bg-slate-800/40 rounded-lg p-2 text-center">
                            <p class="text-slate-500 text-xs">MAE</p>
                            <p class="text-red-400 font-semibold">{{ number_format($td['max_adverse_excursion'] ?? 0, 2) }}</p>
                        </div>
                        <div class="bg-slate-800/40 rounded-lg p-2 text-center">
                            <p class="text-slate-500 text-xs">Exit</p>
                            <p class="text-slate-300 font-semibold text-[10px]">{{ str_replace('_', ' ', $td['exit_reason'] ?? '—') }}</p>
                        </div>
                    </div>

                    {{-- Entry / Exit --}}
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Entry / Exit</p>
                        <div class="space-y-1">
                            @foreach([
                                ['Entry Time',  $fmtEt($td['entry_time'] ?? null)],
                                ['Entry Price', '$'.number_format($td['entry_price'] ?? 0, 2)],
                                ['Exit Time',   $fmtEt($td['exit_time'] ?? null)],
                                ['Exit Price',  '$'.number_format($td['exit_price'] ?? 0, 2)],
                                ['Stop Loss',   '$'.number_format($td['stop_loss'] ?? 0, 2)],
                                ['TP1',         '$'.number_format($td['take_profit_1'] ?? 0, 2)],
                                ['TP2',         $td['take_profit_2'] !== null ? '$'.number_format($td['take_profit_2'], 2) : '—'],
                                ['TP3',         $td['take_profit_3'] !== null ? '$'.number_format($td['take_profit_3'], 2) : '—'],
                            ] as [$lbl, $val])
                            <div class="flex justify-between">
                                <span class="text-slate-500">{{ $lbl }}</span>
                                <span class="text-slate-200 font-mono">{{ $val }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Indicators at entry --}}
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Indicators at Entry</p>
                        <div class="space-y-1">
                            @php
                                $indRows = $stratKey === 'bollinger_rsi' ? [
                                    ['BB Upper', ($td['bb_upper']  ?? null) !== null ? number_format($td['bb_upper'],  2) : '—'],
                                    ['BB Mid',   ($td['bb_middle'] ?? null) !== null ? number_format($td['bb_middle'], 2) : '—'],
                                    ['BB Lower', ($td['bb_lower']  ?? null) !== null ? number_format($td['bb_lower'],  2) : '—'],
                                    ['RSI',      ($td['rsi']       ?? null) !== null ? number_format($td['rsi'],       1) : '—'],
                                    ['ATR',      ($td['atr']       ?? null) !== null ? number_format($td['atr'],       4) : '—'],
                                ] : [
                                    ['EMA21',    ($td['ema21']        ?? null) !== null ? number_format($td['ema21'],        4) : '—'],
                                    ['EMA50',    ($td['ema50']        ?? null) !== null ? number_format($td['ema50'],        4) : '—'],
                                    ['EMA100',   ($td['ema100']       ?? null) !== null ? number_format($td['ema100'],       4) : '—'],
                                    ['Min Dist', ($td['min_distance'] ?? null) !== null ? number_format($td['min_distance'], 4) : '—'],
                                    ['RSI',      ($td['rsi']          ?? null) !== null ? number_format($td['rsi'],          1) : '—'],
                                    ['ATR',      ($td['atr']          ?? null) !== null ? number_format($td['atr'],          4) : '—'],
                                    ['BB Upper', ($td['bb_upper']     ?? null) !== null ? number_format($td['bb_upper'],     2) : '—'],
                                    ['BB Mid',   ($td['bb_middle']    ?? null) !== null ? number_format($td['bb_middle'],    2) : '—'],
                                    ['BB Lower', ($td['bb_lower']     ?? null) !== null ? number_format($td['bb_lower'],     2) : '—'],
                                ];
                            @endphp
                            @foreach($indRows as [$lbl, $val])
                            <div class="flex justify-between">
                                <span class="text-slate-500">{{ $lbl }}</span>
                                <span class="text-slate-200 font-mono">{{ $val }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Signal candle (Bollinger RSI shows the signal bar O/H/L/C) --}}
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Signal Candle</p>
                        <div class="grid grid-cols-4 gap-1 text-center">
                            @foreach(['O'=>'pullback_open','H'=>'pullback_high','L'=>'pullback_low','C'=>'pullback_close'] as $k=>$col)
                            <div class="bg-slate-800/40 rounded p-1.5">
                                <p class="text-slate-500 text-xs">{{ $k }}</p>
                                <p class="text-slate-200 font-mono text-xs">{{ ($td[$col] ?? null) !== null ? number_format($td[$col], 2) : '—' }}</p>
                            </div>
                            @endforeach
                        </div>
                    </div>

                </div>

                {{-- Right: Chart --}}
                <div class="flex-1 flex flex-col overflow-hidden">
                    <div class="flex items-center gap-3 px-4 py-2 border-b border-slate-700/30 flex-shrink-0 flex-wrap">
                        {{-- Markers legend --}}
                        <span class="text-xs text-slate-500 flex items-center gap-2 flex-wrap">
                            <span class="text-blue-400">▲ Entry</span>
                            <span class="text-slate-400">● Exit</span>
                            <span class="text-red-400">— SL</span>
                            <span class="text-emerald-400">— TP</span>
                        </span>

                        {{-- Indicator legend --}}
                        @if($stratKey === 'bollinger_rsi')
                        <span class="text-xs flex items-center gap-2">
                            <span class="flex items-center gap-1"><span class="inline-block w-4 h-0.5 bg-purple-400 rounded" style="border-top:1px dashed #a78bfa"></span><span class="text-purple-400">BB Upper/Lower</span></span>
                            <span class="flex items-center gap-1"><span class="inline-block w-4 h-0.5 bg-purple-400 rounded"></span><span class="text-purple-400">BB Mid</span></span>
                        </span>
                        @else
                        <span class="text-xs flex items-center gap-2">
                            <span class="flex items-center gap-1"><span class="inline-block w-4 h-0.5 bg-blue-500 rounded"></span><span class="text-blue-400">EMA21</span></span>
                            <span class="flex items-center gap-1"><span class="inline-block w-4 h-0.5 bg-amber-400 rounded"></span><span class="text-amber-400">EMA50</span></span>
                            <span class="flex items-center gap-1"><span class="inline-block w-4 h-0.5 bg-purple-400 rounded"></span><span class="text-purple-400">EMA100</span></span>
                        </span>
                        @endif

                        {{-- Indicator toggles --}}
                        <div class="ml-auto flex items-center gap-1.5">
                            @if($stratKey === 'bollinger_rsi')
                            <button x-on:click="toggleBB()"
                                    class="flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-medium transition-colors"
                                    :class="showBB ? 'bg-purple-600/40 text-purple-300' : 'bg-slate-800/60 text-slate-500'">
                                BB
                            </button>
                            @endif
                            <button x-on:click="toggleEMAs()"
                                    class="flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-medium transition-colors"
                                    :class="showEMAs ? 'bg-blue-600/40 text-blue-300' : 'bg-slate-800/60 text-slate-500'">
                                EMA
                            </button>
                            <button x-on:click="toggleVolume()"
                                    class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium transition-colors"
                                    :class="showVolume ? 'bg-slate-600/60 text-slate-200' : 'bg-slate-800/60 text-slate-500'">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                                Vol
                            </button>
                        </div>

                        @if(empty($this->selectedTradeData['candles']))
                        <span class="text-xs text-amber-400">No candle data in DB for this range</span>
                        @endif
                    </div>

                    <div class="flex-1 relative">
                        <div id="lab-trade-chart" class="absolute inset-0" wire:ignore></div>
                        <div id="lab-chart-crosshair" class="absolute top-2 left-2 hidden items-center gap-2 bg-slate-900/90 border border-slate-600/60 rounded-lg px-2.5 py-1.5 pointer-events-none z-10">
                            <span class="text-xs font-mono font-semibold text-slate-100" id="lab-cross-price"></span>
                            <span class="text-slate-600 text-xs">·</span>
                            <span class="text-xs text-slate-400" id="lab-cross-time"></span>
                        </div>
                        @if(empty($this->selectedTradeData['candles']))
                        <div class="absolute inset-0 flex items-center justify-center text-slate-500 text-sm">
                            No candles available — ensure candles are imported for this symbol/timeframe
                        </div>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    </div>
    @endif

    {{-- ── Sim Profit Modal ── --}}
    @if($showSimProfit)
    <div class="fixed inset-0 z-50 flex items-start justify-center bg-black/75 backdrop-blur-sm p-4 overflow-y-auto">
        <div class="w-full max-w-4xl bg-[#111318] border border-slate-700/60 rounded-2xl shadow-2xl my-8">

            {{-- Header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-700/50">
                <div>
                    <h2 class="text-lg font-bold text-slate-100">💰 Sim Profit Calculator</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Simula cuánto ganarías con opciones aplicando delta, gamma y theta a cada trade del backtest</p>
                </div>
                <button wire:click="closeSimProfit" class="text-slate-400 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Inputs --}}
            <div class="px-6 py-5 border-b border-slate-700/40">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-4">Parámetros por contrato</p>
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">💵 $ por contrato</label>
                        <input wire:model="simDollarPerContract" type="number" step="10" min="1"
                               class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-100 focus:border-emerald-500 focus:outline-none">
                        <p class="text-[10px] text-slate-500 mt-1">Capital invertido por contrato</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Δ Delta</label>
                        <input wire:model="simDelta" type="number" step="0.01" min="0" max="1"
                               class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-100 focus:border-emerald-500 focus:outline-none">
                        <p class="text-[10px] text-slate-500 mt-1">Ej: 0.30 = delta 30</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Γ Gamma</label>
                        <input wire:model="simGamma" type="number" step="0.001" min="-1" max="1"
                               class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-100 focus:border-emerald-500 focus:outline-none">
                        <p class="text-[10px] text-slate-500 mt-1">0 = sin ajuste gamma</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Θ Theta ($/día)</label>
                        <input wire:model="simTheta" type="number" step="0.01"
                               class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-100 focus:border-emerald-500 focus:outline-none">
                        <p class="text-[10px] text-slate-500 mt-1">Negativo = decaimiento</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">🏷️ Comisión SPY ($)</label>
                        <input wire:model="simCommission" type="number" step="0.01" min="0"
                               class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-100 focus:border-emerald-500 focus:outline-none">
                        <p class="text-[10px] text-slate-500 mt-1">SPX usa $1.80 auto</p>
                    </div>
                </div>
                <div class="mt-4 flex justify-end">
                    <button wire:click="calculateSimProfit" wire:loading.attr="disabled"
                            class="flex items-center gap-2 px-5 py-2 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white text-sm font-semibold rounded-lg transition-colors">
                        <span wire:loading.remove wire:target="calculateSimProfit">⚡ Calcular</span>
                        <span wire:loading wire:target="calculateSimProfit">Calculando…</span>
                    </button>
                </div>
            </div>

            {{-- Results --}}
            @if($simHasResults && !empty($simResults))
            <div class="px-6 py-5 space-y-4">

                {{-- SPX Toggle + Summary --}}
                <div class="flex items-center justify-between">
                    <p class="text-xs text-slate-500">
                        @if(!empty($simResults['spx_mode']))
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 bg-violet-500/20 text-violet-300 rounded-full text-[11px] font-semibold">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                Modo SPX · ×{{ $simSpxMultiplier }}
                            </span>
                        @else
                            <span class="text-slate-600 text-[11px]">Modo original (SPY)</span>
                        @endif
                    </p>
                    <button wire:click="toggleSpxMode"
                            class="flex items-center gap-2 px-3 py-1.5 text-xs font-semibold rounded-lg border transition-colors
                                   {{ !empty($simResults['spx_mode']) ? 'bg-violet-500/20 border-violet-500/50 text-violet-300 hover:bg-violet-500/30' : 'bg-slate-800 border-slate-600 text-slate-300 hover:border-violet-500/50 hover:text-violet-300' }}">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                        </svg>
                        {{ !empty($simResults['spx_mode']) ? 'Volver a SPY' : 'Convert to SPX' }}
                    </button>
                </div>

                {{-- Summary KPIs --}}
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                    <div class="bg-slate-800/60 rounded-lg p-4 text-center border border-slate-700/30">
                        <p class="text-[10px] text-slate-500 uppercase tracking-wide mb-1">Invertido total</p>
                        <p class="text-xl font-bold text-slate-300">${{ number_format($simResults['total_invested'] ?? 0, 2) }}</p>
                    </div>
                    <div class="bg-slate-800/60 rounded-lg p-4 text-center border border-slate-700/30">
                        <p class="text-[10px] text-slate-500 uppercase tracking-wide mb-1">Profit total</p>
                        <p class="text-xl font-bold {{ ($simResults['total_pnl'] ?? 0) >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                            {{ ($simResults['total_pnl'] ?? 0) >= 0 ? '+' : '' }}${{ number_format($simResults['total_pnl'] ?? 0, 2) }}
                        </p>
                    </div>
                    <div class="bg-slate-800/60 rounded-lg p-4 text-center border border-slate-700/30 sm:col-span-1">
                        <p class="text-[10px] text-slate-500 uppercase tracking-wide mb-1">Total estimado</p>
                        <p class="text-xl font-bold {{ ($simResults['total_estimated'] ?? 0) >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                            ${{ number_format($simResults['total_estimated'] ?? 0, 2) }}
                        </p>
                    </div>
                    <div class="bg-slate-800/60 rounded-lg p-4 text-center border border-slate-700/30">
                        <p class="text-[10px] text-slate-500 uppercase tracking-wide mb-1">Ganadores</p>
                        <p class="text-xl font-bold text-emerald-400">{{ $simResults['wins'] }}</p>
                    </div>
                    <div class="bg-slate-800/60 rounded-lg p-4 text-center border border-slate-700/30">
                        <p class="text-[10px] text-slate-500 uppercase tracking-wide mb-1">Perdedores</p>
                        <p class="text-xl font-bold text-red-400">{{ $simResults['losses'] }}</p>
                    </div>
                </div>

                {{-- Formula legend --}}
                <div class="bg-slate-900/60 rounded-lg px-4 py-3 text-[11px] text-slate-500 space-y-0.5">
                    <p><span class="text-slate-400 font-semibold">Fórmula:</span> $ = pnl_pts × Δ × 100 [× 10.02 si SPX] + ½Γ × pnl_pts² × 100 + Θ × días</p>
                    <p>Ej: 1.42 pts × 0.30 × 100 = <span class="text-slate-400">$42.60</span>. Theta negativo = decaimiento (costo).</p>
                </div>

                {{-- Trade-by-trade table --}}
                @php
                    $spxOn    = !empty($simResults['spx_mode']);
                    $colCount = $spxOn ? 11 : 10;
                    $sdIcon   = fn($col) => $simSort === $col ? ($simSortDir === 'asc' ? '↑' : '↓') : '';
                    $thClass  = 'px-3 py-2 cursor-pointer hover:text-white select-none';
                @endphp
                <div class="overflow-x-auto rounded-lg">
                    <table class="w-full text-xs" style="min-width:700px">
                        <thead>
                            <tr class="bg-slate-800/60 text-slate-400 uppercase tracking-wide text-[10px]">
                                <th class="px-2 py-2 text-left">Sym</th>
                                <th wire:click="sortSimTrades('direction')" class="{{ $thClass }} px-1 text-left">Dir {{ $sdIcon('direction') }}</th>
                                <th wire:click="sortSimTrades('entry_time')" class="{{ $thClass }} px-2 text-left">Entry {{ $sdIcon('entry_time') }}</th>
                                <th wire:click="sortSimTrades('exit_time')" class="{{ $thClass }} px-2 text-left">Exit {{ $sdIcon('exit_time') }}</th>
                                <th wire:click="sortSimTrades('exit_reason')" class="{{ $thClass }} px-2 text-left">Reason {{ $sdIcon('exit_reason') }}</th>
                                <th wire:click="sortSimTrades('result')" class="{{ $thClass }} px-2 text-center">Res {{ $sdIcon('result') }}</th>
                                <th wire:click="sortSimTrades('pnl_points')" class="{{ $thClass }} px-2 text-right">Pts{{ $spxOn ? '(SPY)' : '' }} {{ $sdIcon('pnl_points') }}</th>
                                @if($spxOn)
                                <th class="px-2 py-2 text-right text-violet-400">Pts SPX</th>
                                @endif
                                <th class="px-2 py-2 text-right text-slate-400">Ctr+Com</th>
                                <th wire:click="sortSimTrades('profit')" class="{{ $thClass }} px-2 text-right text-sky-400">Profit {{ $sdIcon('profit') }}</th>
                                <th wire:click="sortSimTrades('estimated_pnl')" class="{{ $thClass }} px-2 text-right text-emerald-400">Estim. {{ $sdIcon('estimated_pnl') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/30">
                            @forelse($simPagedRows as $row)
                            @php
                                $isProfit = ($row['profit'] ?? 0) >= 0;
                                $isPos    = ($row['estimated_pnl'] ?? 0) >= 0;
                                $isCall   = in_array($row['direction'], ['CALL','LONG']);
                            @endphp
                            <tr class="hover:bg-slate-800/20">
                                <td class="px-2 py-1.5 font-mono text-slate-300">{{ $row['symbol'] }}</td>
                                <td class="px-1 py-1.5">
                                    <span class="px-1 py-0.5 rounded text-[9px] font-bold {{ $isCall ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400' }}">
                                        {{ $row['direction'] }}
                                    </span>
                                </td>
                                <td class="px-2 py-1.5 text-slate-400 whitespace-nowrap">{{ $row['entry_time'] ?? '–' }}</td>
                                <td class="px-2 py-1.5 text-slate-400 whitespace-nowrap">{{ $row['exit_time'] ?? '–' }}</td>
                                <td class="px-2 py-1.5 text-slate-500 font-mono text-[9px] whitespace-nowrap">{{ str_replace('_', ' ', $row['exit_reason'] ?? '–') }}</td>
                                <td class="px-2 py-1.5 text-center">
                                    @php $rc = match($row['result']) { 'win'=>'text-emerald-400','loss'=>'text-red-400','breakeven'=>'text-yellow-400', default=>'text-slate-400' }; @endphp
                                    <span class="{{ $rc }} font-medium">{{ ucfirst($row['result']) }}</span>
                                </td>
                                <td class="px-2 py-1.5 text-right {{ $row['pnl_points'] >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                                    {{ number_format($row['pnl_points'], 2) }}
                                </td>
                                @if($spxOn)
                                <td class="px-2 py-1.5 text-right {{ ($row['pnl_points_spx'] ?? 0) >= 0 ? 'text-violet-300' : 'text-red-400' }}">
                                    {{ number_format($row['pnl_points_spx'] ?? 0, 2) }}
                                </td>
                                @endif
                                {{-- Contract + commission merged --}}
                                <td class="px-2 py-1.5 text-right leading-tight">
                                    <span class="text-slate-400 block">${{ number_format($row['contract_price'] ?? 0, 2) }}</span>
                                    <span class="text-orange-400 text-[9px]">-${{ number_format($row['commission'] ?? 0, 2) }}</span>
                                </td>
                                <td class="px-2 py-1.5 text-right font-semibold {{ $isProfit ? 'text-sky-400' : 'text-red-400' }}">
                                    {{ $isProfit ? '+' : '' }}${{ number_format($row['profit'] ?? 0, 2) }}
                                </td>
                                <td class="px-2 py-1.5 text-right font-bold {{ $isPos ? 'text-emerald-400' : 'text-red-400' }}">
                                    ${{ number_format($row['estimated_pnl'] ?? 0, 2) }}
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="{{ $colCount }}" class="px-3 py-6 text-center text-slate-500">No trades.</td>
                            </tr>
                            @endforelse
                            <tr class="bg-slate-800/40 border-t-2 border-slate-600 font-semibold text-xs">
                                <td colspan="{{ $colCount - 3 }}" class="px-3 py-3 text-right text-slate-400">TOTALES</td>
                                <td class="px-3 py-3 text-right text-slate-400">${{ number_format($simResults['total_invested'] ?? 0, 2) }}</td>
                                <td class="px-3 py-3 text-right {{ ($simResults['total_pnl'] ?? 0) >= 0 ? 'text-sky-400' : 'text-red-400' }}">
                                    {{ ($simResults['total_pnl'] ?? 0) >= 0 ? '+' : '' }}${{ number_format($simResults['total_pnl'] ?? 0, 2) }}
                                </td>
                                <td class="px-3 py-3 text-right text-lg font-bold {{ ($simResults['total_estimated'] ?? 0) >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                                    ${{ number_format($simResults['total_estimated'] ?? 0, 2) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                @if($simTotalPages > 1)
                <div class="flex items-center justify-between pt-1">
                    <span class="text-[11px] text-slate-500">
                        Página {{ $simPage }} de {{ $simTotalPages }}
                        · {{ count($simResults['trades'] ?? []) }} trades total
                    </span>
                    <div class="flex items-center gap-2">
                        <button wire:click="simPrevPage" @disabled($simPage <= 1)
                                class="px-3 py-1 text-xs rounded-lg border border-slate-600 text-slate-300 hover:border-slate-400 disabled:opacity-30 disabled:cursor-not-allowed transition-colors">
                            ← Anterior
                        </button>
                        <button wire:click="simNextPage" @disabled($simPage >= $simTotalPages)
                                class="px-3 py-1 text-xs rounded-lg border border-slate-600 text-slate-300 hover:border-slate-400 disabled:opacity-30 disabled:cursor-not-allowed transition-colors">
                            Siguiente →
                        </button>
                    </div>
                </div>
                @endif

            </div>
            @else
            <div class="px-6 py-10 text-center text-slate-500 text-sm">
                Configura los parámetros y presiona <strong class="text-slate-400">Calcular</strong> para ver la simulación.
            </div>
            @endif

        </div>
    </div>
    @endif

</div>

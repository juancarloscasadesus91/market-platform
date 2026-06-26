<div class="space-y-6" wire:poll.5s="refreshRunningSessions">

    {{-- ── Header ── --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-100">⚗️ Backtester</h1>
            <p class="text-sm text-slate-400 mt-0.5">EMA Pullback strategy · ThinkScript-compatible logic</p>
        </div>
        <div class="flex gap-3">
            <button wire:click="showAnalysis"
                    class="flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                Analyze Grid
            </button>
            <button wire:click="$set('showForm', true)"
                    class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                New Backtest
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="px-4 py-3 bg-emerald-500/10 border border-emerald-500/30 rounded-lg text-emerald-400 text-sm">
            {{ session('success') }}
        </div>
    @endif

    {{-- ── New Backtest Modal ── --}}
    @if($showForm)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4 overflow-y-auto">
        <div class="w-full max-w-2xl bg-[#111318] border border-slate-700/60 rounded-2xl shadow-2xl">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-700/50">
                <h2 class="text-lg font-bold text-slate-100">New Backtest Session</h2>
                <button wire:click="$set('showForm', false)" class="text-slate-400 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="p-6 space-y-5 max-h-[80vh] overflow-y-auto">

                {{-- Basic config --}}
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="block text-xs font-medium text-slate-400 mb-1">Session Name (optional)</label>
                        <input wire:model="sessionName" type="text" placeholder="e.g. SPY 5m April 2025"
                               class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-100 focus:border-blue-500 focus:outline-none">
                    </div>

                    <div class="col-span-2">
                        <label class="block text-xs font-medium text-slate-400 mb-1">Symbols (comma-separated)</label>
                        <input wire:model="symbolsInput" type="text" placeholder="SPY,QQQ,NVDA"
                               class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-100 focus:border-blue-500 focus:outline-none">
                        @error('symbolsInput') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Timeframe</label>
                        <select wire:model="timeframe"
                                class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-100 focus:border-blue-500 focus:outline-none">
                            <option value="1m">1 min</option>
                            <option value="5m">5 min</option>
                            <option value="15m">15 min</option>
                            <option value="30m">30 min</option>
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

                {{-- Strategy core --}}
                <div class="border border-slate-700/50 rounded-xl p-4 space-y-4">
                    <p class="text-xs font-semibold text-slate-300 uppercase tracking-wide">Strategy Parameters</p>
                    <div class="grid grid-cols-3 gap-3">
                        @foreach([['EMA Fast','emaFast'],['EMA Mid','emaMid'],['EMA Slow','emaSlow']] as [$lbl,$prop])
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">{{ $lbl }}</label>
                            <input wire:model="{{ $prop }}" type="number" min="1"
                                   class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-1.5 text-sm text-slate-100 focus:border-blue-500 focus:outline-none">
                        </div>
                        @endforeach
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Min EMA Dist %</label>
                            <input wire:model="minDistancePct" type="number" step="0.01" min="0"
                                   class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-1.5 text-sm text-slate-100 focus:border-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Max Bars After PB</label>
                            <input wire:model="maxBarsAfterPullback" type="number" min="1" max="10"
                                   class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-1.5 text-sm text-slate-100 focus:border-blue-500 focus:outline-none">
                        </div>
                    </div>
                </div>

                {{-- Stop / TP --}}
                <div class="border border-slate-700/50 rounded-xl p-4 space-y-4">
                    <p class="text-xs font-semibold text-slate-300 uppercase tracking-wide">Stop Loss & Take Profit</p>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Stop Type</label>
                            <select wire:model="stopType"
                                    class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-1.5 text-sm text-slate-100 focus:border-blue-500 focus:outline-none">
                                <option value="pullback">Below/Above Pullback</option>
                                <option value="ema_mid">Below/Above EMA{{ $emaMid }}</option>
                                <option value="ema_mid_range">Between EMA50 & EMA21</option>
                                <option value="atr">ATR-based</option>
                                <option value="percent">Percent of Entry</option>
                                <option value="ema_quadrant_trailing">EMA Quadrant Trailing</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">ATR Multiplier</label>
                            <input wire:model="stopAtrMult" type="number" step="0.1" min="0.5"
                                   class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-1.5 text-sm text-slate-100 focus:border-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Stop Buffer %</label>
                            <input wire:model="stopBufferPct" type="number" step="0.01" min="0"
                                   class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-1.5 text-sm text-slate-100 focus:border-blue-500 focus:outline-none">
                        </div>
                        @if($stopType === 'percent')
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Stop % of Entry</label>
                            <input wire:model="stopPct" type="number" step="0.01" min="0.01" max="10"
                                   class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-1.5 text-sm text-slate-100 focus:border-blue-500 focus:outline-none" placeholder="0.50">
                        </div>
                        @endif
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">TP Type</label>
                            <select wire:model="tpType"
                                    class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-1.5 text-sm text-slate-100 focus:border-blue-500 focus:outline-none">
                                <option value="risk_ratio">Risk Ratio (R)</option>
                                <option value="points">Fixed Points</option>
                                <option value="percent">Percent</option>
                                <option value="ema_quadrant_trail">EMA Quadrant Trailing</option>
                            </select>
                        </div>
                        @if($tpType !== 'ema_quadrant_trail')
                        @foreach([['TP1','tp1Value'],['TP2','tp2Value'],['TP3','tp3Value']] as [$lbl,$prop])
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">
                                {{ $lbl }}
                                @if($tpType === 'risk_ratio')
                                <span class="text-slate-600 font-normal ml-1">(R)</span>
                                @elseif($tpType === 'points')
                                <span class="text-slate-600 font-normal ml-1">(pts)</span>
                                @elseif($tpType === 'percent')
                                <span class="text-slate-600 font-normal ml-1">(%)</span>
                                @endif
                            </label>
                            <input wire:model="{{ $prop }}" type="number" step="0.1" min="0"
                                   class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-1.5 text-sm text-slate-100 focus:border-blue-500 focus:outline-none">
                        </div>
                        @endforeach
                        @else
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">
                                Step %
                                <span class="text-slate-600 font-normal ml-1">— size of each profit level</span>
                            </label>
                            <input wire:model="quadrantStepPct" type="number" step="1" min="5" max="50"
                                   class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-1.5 text-sm text-slate-100 focus:border-blue-500 focus:outline-none"
                                   placeholder="25">
                        </div>
                        @php
                            $qStep = max(5, min(50, (float) ($quadrantStepPct ?? 25)));
                            $qLevels = (int) round(100 / $qStep);
                            $qLevelLabels = collect(range(1, $qLevels))->map(fn($k) => ($k * $qStep) . '%')->join(' → ');
                        @endphp
                        <div class="col-span-2 flex items-center gap-2 px-3 py-2 bg-blue-500/10 border border-blue-500/20 rounded-lg text-xs text-blue-300">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ $qLevels }} niveles · Stop −{{ $qStep }}% · Profits: {{ $qLevelLabels }} · Trail: BE → cada nivel anterior
                        </div>
                        @endif

                        <div>
                            <label class="block text-xs text-slate-400 mb-1">
                                Max Trade Duration (min)
                                <span class="text-slate-600 font-normal ml-1">— exit after X minutes, leave blank to disable</span>
                            </label>
                            <input wire:model="maxTradeDurationMinutes" type="number" min="1" step="1"
                                   class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-1.5 text-sm text-slate-100 focus:border-blue-500 focus:outline-none" placeholder="30">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">
                                Force Exit Time
                                <span class="text-slate-600 font-normal ml-1">— close at or after this time (HH:MM), leave blank for EOD</span>
                            </label>
                            <input wire:model="forceExitTime" type="time"
                                   class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-1.5 text-sm text-slate-100 focus:border-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">
                                Min Entry Time
                                <span class="text-slate-600 font-normal ml-1">— trading window start (HH:MM ET)</span>
                            </label>
                            <input wire:model="minEntryTime" type="time"
                                   class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-1.5 text-sm text-slate-100 focus:border-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">
                                Max Entry Time
                                <span class="text-slate-600 font-normal ml-1">— trading window end (HH:MM ET)</span>
                            </label>
                            <input wire:model="maxEntryTime" type="time"
                                   class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-1.5 text-sm text-slate-100 focus:border-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">
                                Entry Candle Range %
                                <span class="text-slate-600 font-normal ml-1">— min candle range for entry (high-low/close * 100)</span>
                            </label>
                            <input wire:model="entryCandleDistancePct" type="number" step="0.01" min="0.01" max="5"
                                   class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-1.5 text-sm text-slate-100 focus:border-blue-500 focus:outline-none" placeholder="0.10">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">
                                Volume Rel Min
                                <span class="text-slate-600 font-normal ml-1">— min relative volume (leave blank to disable)</span>
                            </label>
                            <input wire:model="volumeRelMin" type="number" step="0.1" min="0.1" max="10"
                                   class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-1.5 text-sm text-slate-100 focus:border-blue-500 focus:outline-none" placeholder="0.5">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">
                                Volume Rel Max
                                <span class="text-slate-600 font-normal ml-1">— max relative volume (leave blank to disable)</span>
                            </label>
                            <input wire:model="volumeRelMax" type="number" step="0.1" min="0.1" max="10"
                                   class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-1.5 text-sm text-slate-100 focus:border-blue-500 focus:outline-none" placeholder="2.0">
                        </div>
                    </div>
                </div>

                {{-- Advanced filters toggle --}}
                <button wire:click="$toggle('showAdvanced')"
                        class="text-xs text-blue-400 hover:text-blue-300 flex items-center gap-1">
                    {{ $showAdvanced ? '▲ Hide' : '▼ Show' }} Advanced Filters
                </button>

                @if($showAdvanced)
                <div class="border border-slate-700/50 rounded-xl p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-semibold text-slate-300 uppercase tracking-wide">Indicators & Filters</p>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="ignoreIndicatorFilters" class="rounded border-slate-600 bg-slate-800 text-blue-600 focus:ring-blue-500 focus:ring-offset-slate-900">
                            <span class="text-xs text-slate-400">Ignore filters</span>
                        </label>
                    </div>
                    {{-- Config parameters (always visible unless ignore is checked) --}}
                    <div class="grid grid-cols-3 gap-3" @class(['hidden', $ignoreIndicatorFilters])>
                        @foreach([
                            ['RSI Period','rsiPeriod','number','1',null],
                            ['BB Period','bbPeriod','number','1',null],
                            ['BB Std Dev','bbStddev','number','0.1',null],
                            ['ATR Period','atrPeriod','number','1',null],
                            ['Vol Avg Period','volumeAvgPeriod','number','1',null],
                        ] as [$lbl,$prop,$type,$step,$placeholder])
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">{{ $lbl }}</label>
                            <input wire:model.live="{{ $prop }}" type="{{ $type }}" step="{{ $step }}"
                                   class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-1.5 text-sm text-slate-100 focus:border-blue-500 focus:outline-none"
                                   placeholder="{{ $placeholder ?? 'Empty to disable' }}">
                        </div>
                        @endforeach
                    </div>

                    {{-- Filters (disabled when ignore is checked) --}}
                    <div class="grid grid-cols-3 gap-3" @class(['opacity-50 pointer-events-none', $ignoreIndicatorFilters])>
                        @foreach([
                            ['RSI Max (CALL)','rsiMaxCall','number','1','70.0'],
                            ['RSI Min (PUT)','rsiMinPut','number','1','30.0'],
                            ['Max Candle/ATR','maxCandleAtrRatio','number','0.1','2.0'],
                            ['Max Price/EMA%','maxPriceEmaDistPct','number','0.1','2.0'],
                            ['Min BB Dist%','minBbDistPct','number','0.01','0.10'],
                        ] as [$lbl,$prop,$type,$step,$placeholder])
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">{{ $lbl }}</label>
                            <input wire:model.live="{{ $prop }}" type="{{ $type }}" step="{{ $step }}"
                                   class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-1.5 text-sm text-slate-100 focus:border-blue-500 focus:outline-none"
                                   placeholder="{{ $placeholder ?? 'Empty to disable' }}">
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- EMA distance filters (always available, not affected by ignore checkbox) --}}
                <div class="border border-slate-700/50 rounded-xl p-4 space-y-3">
                    <p class="text-xs font-semibold text-slate-300 uppercase tracking-wide">EMA Distance Filters</p>
                    <div class="grid grid-cols-4 gap-3">
                        @foreach([
                            ['Min EMA21-50 Dist (pts)','minEma21Ema50Dist'],
                            ['Max EMA21-50 Dist (pts)','maxEma21Ema50Dist'],
                            ['Min EMA50-100 Dist (pts)','minEma50Ema100Dist'],
                            ['Max EMA50-100 Dist (pts)','maxEma50Ema100Dist'],
                        ] as [$lbl,$prop])
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">
                                {{ $lbl }}
                                <span class="text-slate-600 ml-1">— blank = off</span>
                            </label>
                            <input wire:model="{{ $prop }}" type="number" step="0.01"
                                   placeholder="blank = off"
                                   class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-1.5 text-sm text-slate-100 placeholder-slate-600 focus:border-blue-500 focus:outline-none">
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                <div class="flex justify-end gap-3 pt-2">
                    <button wire:click="$set('showForm', false)"
                            class="px-4 py-2 text-sm text-slate-400 hover:text-white border border-slate-600 rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button wire:click="launch" wire:loading.attr="disabled"
                            class="px-5 py-2 bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white text-sm font-semibold rounded-lg transition-colors">
                        <span wire:loading.remove wire:target="launch">🚀 Launch Backtest</span>
                        <span wire:loading wire:target="launch">Queuing…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ── Sessions row ── --}}
    <div>
        <h2 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2 px-0.5">Sessions</h2>
        <div class="flex gap-3 overflow-x-auto pb-2 scrollbar-thin scrollbar-thumb-slate-700 scrollbar-track-transparent">

            @forelse($sessions as $sess)
            @php
                $isActive  = $selectedSession === $sess->id;
                $statusClr = match($sess->status) {
                    'completed'            => 'text-emerald-400',
                    'failed'               => 'text-red-400',
                    'running','importing'  => 'text-amber-400',
                    default                => 'text-slate-400',
                };
                $statusDot = match($sess->status) {
                    'completed'            => 'bg-emerald-400',
                    'failed'               => 'bg-red-400',
                    'running','importing'  => 'bg-amber-400 animate-pulse',
                    default                => 'bg-slate-500',
                };
            @endphp
            <div id="session-{{ $sess->id }}" wire:click="selectSession({{ $sess->id }})"
                 class="flex-shrink-0 w-52 cursor-pointer rounded-xl border p-3 transition-all group
                        {{ $isActive ? 'border-blue-500/60 bg-blue-500/10' : 'border-slate-700/50 bg-slate-800/40 hover:border-slate-600/70' }}">

                {{-- Status dot + name --}}
                <div class="flex items-center gap-1.5 mb-1.5">
                    <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ $statusDot }}"></span>
                    <p class="text-xs font-semibold text-slate-100 truncate leading-tight">{{ $sess->name }}</p>
                </div>

                <p class="text-xs text-slate-400 truncate">{{ $sess->symbolsLabel() }} · {{ $sess->timeframe }}</p>
                <p class="text-xs text-slate-500">{{ $sess->date_from?->format('M d') }} – {{ $sess->date_to?->format('M d, Y') }}</p>

                @if($sess->isCompleted())
                    <p class="text-xs text-slate-300 mt-1.5">
                        {{ $sess->total_trades }} trades · <span class="{{ $sess->win_rate >= 50 ? 'text-emerald-400' : 'text-red-400' }}">{{ $sess->win_rate ?? 0 }}% WR</span>
                    </p>
                @elseif($sess->isRunning())
                    <div class="mt-1.5 h-1 bg-slate-700 rounded-full overflow-hidden">
                        <div class="h-1 bg-amber-400 rounded-full transition-all" style="width:{{ $sess->progress }}%"></div>
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5">{{ $sess->progress }}%{{ $sess->progress_label ? ' · '.$sess->progress_label : '' }}</p>
                @elseif($sess->isFailed())
                    <p class="text-xs text-red-400/70 mt-1.5 truncate">{{ $sess->error_message }}</p>
                @endif

                <div class="mt-2 flex justify-end opacity-0 group-hover:opacity-100 transition-opacity">
                    <button wire:click.stop="deleteSession({{ $sess->id }})"
                            wire:confirm="Delete this session and all its trades?"
                            class="text-xs text-red-400/60 hover:text-red-400 transition-colors">Delete</button>
                </div>
            </div>
            @empty
            <div class="text-sm text-slate-500 py-3">
                No sessions yet — click <strong class="text-slate-300">New Backtest</strong> to start.
            </div>
            @endforelse

        </div>
    </div>

    {{-- ── Detail panel (full width) ── --}}
    <div>
            @if($currentSession)
                @php $s = $currentSession; @endphp

                {{-- Summary cards --}}
                @if($s->isCompleted())
                <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3 mb-5">
                    @php
                        $cards = [
                            ['Total Trades',   $s->total_trades,              'text-slate-100'],
                            ['Win Rate',        ($s->win_rate ?? 0).'%',       $s->win_rate >= 50 ? 'text-emerald-400' : 'text-red-400'],
                            ['Profit Factor',   $s->profit_factor ?? '—',      ($s->profit_factor ?? 0) >= 1 ? 'text-emerald-400' : 'text-red-400'],
                            ['Total P&L pts',   number_format($s->total_pnl_points ?? 0, 2), ($s->total_pnl_points ?? 0) >= 0 ? 'text-emerald-400' : 'text-red-400'],
                            ['Avg Winner',      number_format($s->avg_winner_pts ?? 0, 2),   'text-emerald-400'],
                            ['Avg Loser',       number_format($s->avg_loser_pts  ?? 0, 2),   'text-red-400'],
                            ['Max Drawdown',    number_format($s->max_drawdown   ?? 0, 2),   'text-orange-400'],
                            ['Best Hour',       $s->best_hour ?? '—',          'text-blue-400'],
                        ];
                    @endphp
                    @foreach($cards as [$label,$val,$clr])
                    <div class="bg-slate-800/60 border border-slate-700/50 rounded-xl p-4">
                        <p class="text-xs text-slate-400">{{ $label }}</p>
                        <p class="text-lg font-bold {{ $clr }} mt-1">{{ $val }}</p>
                    </div>
                    @endforeach
                </div>

                {{-- W/L bar --}}
                @php
                    $total = max(1, $s->total_trades);
                    $winPct  = round($s->winning_trades   / $total * 100, 1);
                    $losePct = round($s->losing_trades    / $total * 100, 1);
                    $bePct   = round($s->breakeven_trades / $total * 100, 1);
                @endphp
                <div class="flex h-2 rounded-full overflow-hidden bg-slate-700 mb-4">
                    <div class="bg-emerald-500" style="width:{{ $winPct }}%" title="{{ $s->winning_trades }} wins"></div>
                    <div class="bg-slate-500"   style="width:{{ $bePct }}%"  title="{{ $s->breakeven_trades }} breakeven"></div>
                    <div class="bg-red-500"     style="width:{{ $losePct }}%" title="{{ $s->losing_trades }} losses"></div>
                </div>

                {{-- Tabs --}}
                <div class="flex items-center gap-1 mb-4">
                    <div class="flex gap-1 p-1 bg-slate-800/60 rounded-xl">
                        <button wire:click="$set('activeTab','trades')"
                                class="px-4 py-1.5 rounded-lg text-sm font-medium transition-colors {{ $activeTab === 'trades' ? 'bg-slate-600 text-slate-100 shadow' : 'text-slate-400 hover:text-slate-200' }}">
                            Trades
                            @if($trades) <span class="ml-1 text-xs {{ $activeTab === 'trades' ? 'text-slate-300' : 'text-slate-500' }}">{{ $trades->total() }}</span> @endif
                        </button>
                        <button wire:click="$set('activeTab','patterns')"
                                class="px-4 py-1.5 rounded-lg text-sm font-medium transition-colors {{ $activeTab === 'patterns' ? 'bg-slate-600 text-slate-100 shadow' : 'text-slate-400 hover:text-slate-200' }}">
                            🔍 Pattern Analysis
                        </button>
                        <button wire:click="$set('activeTab','config')"
                                class="px-4 py-1.5 rounded-lg text-sm font-medium transition-colors {{ $activeTab === 'config' ? 'bg-slate-600 text-slate-100 shadow' : 'text-slate-400 hover:text-slate-200' }}">
                            ⚙️ Config
                        </button>
                    </div>
                </div>
                @endif

                @if($s->isCompleted() && $activeTab === 'trades' && $trades && $trades->total() > 0)
                <div class="bg-slate-800/40 border border-slate-700/50 rounded-xl overflow-hidden">
                    <div class="px-4 py-3 border-b border-slate-700/50 flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-slate-200">
                            Trades
                            <span class="ml-1.5 text-xs text-slate-400 font-normal">{{ $trades->total() }} total</span>
                        </p>
                        <input wire:model.live.debounce.300ms="tradesFilter" type="text"
                               placeholder="Filter symbol / direction / result…"
                               class="bg-slate-700 border border-slate-600 rounded-lg px-3 py-1.5 text-xs text-slate-100 w-56 focus:border-blue-500 focus:outline-none">
                    </div>

                    {{-- Top scrollbar (dual sync) --}}
                    <div x-data="{
                        init() {
                            const top  = this.$refs.scrollTop;
                            const body = this.$refs.scrollBody;
                            const sp   = this.$refs.spacer;
                            const sync = () => sp.style.width = body.scrollWidth + 'px';
                            sync();
                            new ResizeObserver(sync).observe(body);
                            top.addEventListener('scroll',  () => body.scrollLeft = top.scrollLeft);
                            body.addEventListener('scroll', () => top.scrollLeft  = body.scrollLeft);
                        }
                    }">
                        <div x-ref="scrollTop" class="overflow-x-auto overflow-y-hidden border-b border-slate-700/30" style="height:12px;">
                            <div x-ref="spacer" style="height:1px;"></div>
                        </div>
                        <div x-ref="scrollBody" class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="border-b border-slate-700/50 bg-slate-800/60 text-slate-400 uppercase tracking-wide">
                                    @php
                                        $th = fn($col,$lbl,$align='left') =>
                                            "<th wire:click=\"sortTrades('{$col}')\"
                                                 class=\"px-3 py-2.5 font-semibold text-{$align} cursor-pointer hover:text-white whitespace-nowrap select-none\">{$lbl}"
                                            . ($tradesSort===$col ? ($tradesSortDir==='asc' ? ' ↑' : ' ↓') : ' ⇅')
                                            . "</th>";
                                    @endphp
                                    {!! $th('symbol','Symbol') !!}
                                    {!! $th('direction','Dir','center') !!}
                                    {!! $th('entry_time','Entry Time') !!}
                                    {!! $th('entry_price','Entry $','right') !!}
                                    {!! $th('exit_price','Exit $','right') !!}
                                    {!! $th('stop_loss','SL','right') !!}
                                    {!! $th('take_profit_1','TP1','right') !!}
                                    {!! $th('pnl_points','P&L pts','right') !!}
                                    {!! $th('pnl_pct','P&L%','right') !!}
                                    {!! $th('r_multiple','R','right') !!}
                                    {!! $th('result','Result','center') !!}
                                    {!! $th('exit_reason','Exit Reason') !!}
                                    {!! $th('rsi','RSI','right') !!}
                                    {!! $th('atr','ATR','right') !!}
                                    {!! $th('rel_volume','Rel Vol','right') !!}
                                    <th class="px-3 py-2.5 font-semibold text-center whitespace-nowrap"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/30">
                                @foreach($trades as $t)
                                @php
                                    $resClr = match($t->result) {
                                        'win'  => 'text-emerald-400', 'loss' => 'text-red-400',
                                        'breakeven' => 'text-slate-400', default => 'text-slate-500'
                                    };
                                    $dirClr = $t->direction === 'CALL' ? 'bg-blue-500/20 text-blue-300' : 'bg-red-500/20 text-red-300';
                                @endphp
                                <tr class="hover:bg-slate-700/20 transition-colors">
                                    <td class="px-3 py-2 font-mono font-semibold text-slate-100">{{ $t->symbol }}</td>
                                    <td class="px-3 py-2 text-center">
                                        <span class="px-1.5 py-0.5 rounded text-xs font-bold {{ $dirClr }}">{{ $t->direction }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-slate-300 whitespace-nowrap">{{ $t->entry_time?->setTimezone('America/New_York')->format('M d H:i') }} ET</td>
                                    <td class="px-3 py-2 text-right font-mono text-slate-200">${{ number_format($t->entry_price ?? 0, 2) }}</td>
                                    <td class="px-3 py-2 text-right font-mono text-slate-200">${{ number_format($t->exit_price ?? 0, 2) }}</td>
                                    <td class="px-3 py-2 text-right font-mono text-red-300">${{ number_format($t->stop_loss ?? 0, 2) }}</td>
                                    <td class="px-3 py-2 text-right font-mono text-emerald-300">${{ number_format($t->take_profit_1 ?? 0, 2) }}</td>
                                    <td class="px-3 py-2 text-right font-mono font-semibold {{ $resClr }}">
                                        {{ ($t->pnl_points ?? 0) >= 0 ? '+' : '' }}{{ number_format($t->pnl_points ?? 0, 2) }}
                                    </td>
                                    <td class="px-3 py-2 text-right font-mono {{ $resClr }}">
                                        {{ ($t->pnl_pct ?? 0) >= 0 ? '+' : '' }}{{ number_format($t->pnl_pct ?? 0, 2) }}%
                                    </td>
                                    <td class="px-3 py-2 text-right font-mono {{ $resClr }}">
                                        {{ $t->r_multiple !== null ? number_format($t->r_multiple, 2).'R' : '—' }}
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <span class="px-1.5 py-0.5 rounded text-xs font-semibold {{ $resClr }}">{{ ucfirst($t->result) }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-slate-400 whitespace-nowrap">{{ str_replace('_', ' ', $t->exit_reason ?? '—') }}</td>
                                    <td class="px-3 py-2 text-right text-slate-300">{{ $t->rsi !== null ? number_format($t->rsi, 1) : '—' }}</td>
                                    <td class="px-3 py-2 text-right text-slate-300">{{ $t->atr !== null ? number_format($t->atr, 4) : '—' }}</td>
                                    <td class="px-3 py-2 text-right text-slate-300">{{ $t->rel_volume !== null ? number_format($t->rel_volume, 2).'x' : '—' }}</td>
                                    <td class="px-3 py-2 text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            <button wire:click="openSimProfit({{ $t->id }})"
                                                    class="p-1.5 rounded-lg bg-slate-700/50 hover:bg-emerald-500/20 text-slate-400 hover:text-emerald-400 transition-colors"
                                                    title="Simulate option profit">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                                </svg>
                                            </button>
                                            <button wire:click="viewTrade({{ $t->id }})"
                                                    class="p-1.5 rounded-lg bg-slate-700/50 hover:bg-blue-500/20 text-slate-400 hover:text-blue-400 transition-colors"
                                                    title="View trade detail">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        </div>
                    </div>

                    {{-- Pagination --}}
                    @if($trades->hasPages())
                    <div class="px-4 py-3 border-t border-slate-700/50">
                        {{ $trades->links() }}
                    </div>
                    @endif
                </div>

                @elseif($s->isCompleted() && $activeTab === 'patterns' && $patternAnalysis)
                @php $pa = $patternAnalysis; @endphp
                <div class="space-y-6">

                    {{-- ── Row 1: Parameter Comparison ── --}}
                    <div class="bg-slate-800/40 border border-slate-700/50 rounded-xl overflow-hidden">
                        <div class="px-5 py-3 border-b border-slate-700/40 flex items-center justify-between">
                            <p class="text-sm font-semibold text-slate-200">Indicator Distribution: Winners vs Losers</p>
                            <span class="text-xs text-slate-500">{{ $pa['win_count'] }} wins · {{ $pa['loss_count'] }} losses · sorted by divergence</span>
                        </div>
                        <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="border-b border-slate-700/40 bg-slate-800/60 text-slate-400 uppercase tracking-wide text-left">
                                    <th class="px-4 py-2.5 font-semibold w-32">Indicator</th>
                                    <th class="px-4 py-2.5 font-semibold text-emerald-400">Win avg</th>
                                    <th class="px-4 py-2.5 font-semibold text-emerald-400/50">Win min–max</th>
                                    <th class="px-4 py-2.5 font-semibold text-red-400">Loss avg</th>
                                    <th class="px-4 py-2.5 font-semibold text-red-400/50">Loss min–max</th>
                                    <th class="px-4 py-2.5 font-semibold text-right">Δ (wins vs losses)</th>
                                    <th class="px-4 py-2.5 font-semibold">Range comparison</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/30">
                            @foreach($pa['params'] as $pm)
                            @php
                                $w = $pm['win'];
                                $l = $pm['loss'];
                                $d = $pm['divergence'];
                                $dClr = $d === null ? 'text-slate-500' : ($d > 0 ? 'text-emerald-400' : 'text-red-400');
                                $fmt  = fn($v) => $v !== null ? number_format($v, 4) : '—';

                                // Compute bar positions (normalize min–max to 0–100 scale)
                                $globalMin = min($w['min'] ?? 0, $l['min'] ?? 0);
                                $globalMax = max($w['max'] ?? 1, $l['max'] ?? 1);
                                $range = max($globalMax - $globalMin, 0.0001);
                                $wLeft  = round(($w['min'] !== null ? ($w['min'] - $globalMin) / $range : 0) * 100, 1);
                                $wWidth = round(($w['min'] !== null && $w['max'] !== null ? ($w['max'] - $w['min']) / $range : 0) * 100, 1);
                                $lLeft  = round(($l['min'] !== null ? ($l['min'] - $globalMin) / $range : 0) * 100, 1);
                                $lWidth = round(($l['min'] !== null && $l['max'] !== null ? ($l['max'] - $l['min']) / $range : 0) * 100, 1);
                                $wMean  = round(($w['mean'] !== null ? ($w['mean'] - $globalMin) / $range : 0) * 100, 1);
                                $lMean  = round(($l['mean'] !== null ? ($l['mean'] - $globalMin) / $range : 0) * 100, 1);
                            @endphp
                            <tr class="hover:bg-slate-700/20 transition-colors">
                                <td class="px-4 py-2.5 font-semibold text-slate-300">{{ $pm['label'] }}</td>
                                <td class="px-4 py-2.5 text-emerald-400 font-mono">{{ $fmt($w['mean']) }}</td>
                                <td class="px-4 py-2.5 text-slate-500 font-mono">{{ $fmt($w['min']) }} – {{ $fmt($w['max']) }}</td>
                                <td class="px-4 py-2.5 text-red-400 font-mono">{{ $fmt($l['mean']) }}</td>
                                <td class="px-4 py-2.5 text-slate-500 font-mono">{{ $fmt($l['min']) }} – {{ $fmt($l['max']) }}</td>
                                <td class="px-4 py-2.5 text-right font-mono {{ $dClr }}">
                                    {{ $d !== null ? ($d > 0 ? '+' : '').$d.'%' : '—' }}
                                </td>
                                <td class="px-4 py-2.5 w-52">
                                    <div class="relative h-4 bg-slate-700/50 rounded-sm overflow-hidden">
                                        {{-- Win range bar (green) --}}
                                        @if($w['min'] !== null && $w['max'] !== null)
                                        <div class="absolute top-0.5 h-1.5 bg-emerald-500/50 rounded-sm"
                                             style="left:{{ $wLeft }}%; width:{{ $wWidth }}%"></div>
                                        {{-- Win mean tick --}}
                                        <div class="absolute top-0 h-4 w-px bg-emerald-400"
                                             style="left:{{ $wMean }}%"></div>
                                        @endif
                                        {{-- Loss range bar (red) --}}
                                        @if($l['min'] !== null && $l['max'] !== null)
                                        <div class="absolute bottom-0.5 h-1.5 bg-red-500/50 rounded-sm"
                                             style="left:{{ $lLeft }}%; width:{{ $lWidth }}%"></div>
                                        {{-- Loss mean tick --}}
                                        <div class="absolute top-0 h-4 w-px bg-red-400"
                                             style="left:{{ $lMean }}%"></div>
                                        @endif
                                    </div>
                                    <div class="flex justify-between text-slate-600 mt-0.5" style="font-size:9px">
                                        <span>{{ $fmt($globalMin) }}</span>
                                        <span>{{ $fmt($globalMax) }}</span>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                            </tbody>
                        </table>
                        </div>
                        <p class="px-5 py-2 text-xs text-slate-500 border-t border-slate-700/30">
                            Bar: <span class="text-emerald-400">■</span> Win P25–P75 &nbsp; <span class="text-red-400">■</span> Loss P25–P75 &nbsp; · &nbsp; Vertical tick = mean &nbsp;·&nbsp; Δ% = (win avg − loss avg) / avg of both
                        </p>
                    </div>

                    {{-- ── Row 2: By Hour + Direction ── --}}
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

                        {{-- By Hour --}}
                        <div class="lg:col-span-2 bg-slate-800/40 border border-slate-700/50 rounded-xl overflow-hidden">
                            <div class="px-5 py-3 border-b border-slate-700/40">
                                <p class="text-sm font-semibold text-slate-200">Win Rate by Entry Hour <span class="text-xs text-slate-500 font-normal">(ET)</span></p>
                            </div>
                            <div class="p-4 space-y-2">
                            @foreach($pa['by_hour'] as $hour => $hs)
                            @php
                                $wrClr = $hs['win_rate'] >= 60 ? 'bg-emerald-500' : ($hs['win_rate'] >= 40 ? 'bg-amber-400' : 'bg-red-500');
                                $pnlClr = $hs['avg_pnl'] >= 0 ? 'text-emerald-400' : 'text-red-400';
                            @endphp
                            <div class="flex items-center gap-3">
                                <span class="text-xs font-mono text-slate-400 w-12 flex-shrink-0">{{ $hour }}:00</span>
                                <div class="flex-1 relative h-5 bg-slate-700/50 rounded overflow-hidden">
                                    <div class="{{ $wrClr }} h-full rounded opacity-80 transition-all"
                                         style="width:{{ $hs['win_rate'] }}%"></div>
                                    <span class="absolute inset-0 flex items-center pl-2 text-xs font-semibold text-white mix-blend-plus-lighter">
                                        {{ $hs['win_rate'] }}% WR
                                    </span>
                                </div>
                                <span class="text-xs font-mono {{ $pnlClr }} w-16 text-right flex-shrink-0">
                                    {{ $hs['avg_pnl'] >= 0 ? '+' : '' }}{{ $hs['avg_pnl'] }}
                                </span>
                                <span class="text-xs text-slate-500 w-16 text-right flex-shrink-0">
                                    {{ $hs['wins'] }}/{{ $hs['total'] }} trades
                                </span>
                            </div>
                            @endforeach
                            </div>
                        </div>

                        {{-- Direction + Exit Reason --}}
                        <div class="space-y-4">
                            {{-- By Direction --}}
                            <div class="bg-slate-800/40 border border-slate-700/50 rounded-xl overflow-hidden">
                                <div class="px-5 py-3 border-b border-slate-700/40">
                                    <p class="text-sm font-semibold text-slate-200">By Direction</p>
                                </div>
                                <div class="p-4 grid grid-cols-2 gap-3">
                                @foreach($pa['by_dir'] as $dir => $ds)
                                @php
                                    $dirBg   = $dir === 'CALL' ? 'bg-blue-500/10 border-blue-500/30' : 'bg-red-500/10 border-red-500/30';
                                    $dirClr  = $dir === 'CALL' ? 'text-blue-400' : 'text-red-400';
                                    $wrColor = $ds['win_rate'] >= 50 ? 'text-emerald-400' : 'text-red-400';
                                @endphp
                                <div class="border {{ $dirBg }} rounded-lg p-3 text-center">
                                    <p class="text-xs font-bold {{ $dirClr }}">{{ $dir }}</p>
                                    <p class="text-lg font-bold {{ $wrColor }} mt-0.5">{{ $ds['win_rate'] }}%</p>
                                    <p class="text-xs text-slate-500">{{ $ds['wins'] }}/{{ $ds['total'] }}</p>
                                    <p class="text-xs {{ $ds['avg_pnl'] >= 0 ? 'text-emerald-400' : 'text-red-400' }} mt-0.5">
                                        avg {{ $ds['avg_pnl'] >= 0 ? '+' : '' }}{{ $ds['avg_pnl'] }} pts
                                    </p>
                                </div>
                                @endforeach
                                </div>
                            </div>

                            {{-- By Exit Reason --}}
                            <div class="bg-slate-800/40 border border-slate-700/50 rounded-xl overflow-hidden">
                                <div class="px-5 py-3 border-b border-slate-700/40">
                                    <p class="text-sm font-semibold text-slate-200">By Exit Reason</p>
                                </div>
                                <div class="divide-y divide-slate-700/30">
                                @foreach($pa['by_exit'] as $reason => $es)
                                @php
                                    $wrClr2 = $es['win_rate'] >= 60 ? 'text-emerald-400' : ($es['win_rate'] >= 40 ? 'text-amber-400' : 'text-red-400');
                                    $pnlClr2 = $es['avg_pnl'] >= 0 ? 'text-emerald-400' : 'text-red-400';
                                @endphp
                                <div class="flex items-center justify-between px-4 py-2 text-xs hover:bg-slate-700/20">
                                    <span class="text-slate-300 truncate">{{ str_replace('_', ' ', $reason) }}</span>
                                    <div class="flex items-center gap-3 flex-shrink-0">
                                        <span class="{{ $wrClr2 }} font-semibold">{{ $es['win_rate'] }}%</span>
                                        <span class="{{ $pnlClr2 }} font-mono">{{ $es['avg_pnl'] >= 0 ? '+' : '' }}{{ $es['avg_pnl'] }}</span>
                                        <span class="text-slate-500">{{ $es['total'] }}x</span>
                                    </div>
                                </div>
                                @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                @elseif($s->isCompleted() && $activeTab === 'config')
                @php $cfg = $s->strategy; @endphp
                @if($cfg)
                <div class="space-y-4">

                    {{-- Load Config button --}}
                    <div class="flex justify-end">
                        <button wire:click="loadConfig({{ $s->id }})"
                                class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold rounded-lg transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                            </svg>
                            Load Config
                        </button>
                    </div>

                    {{-- Backtest run info --}}
                    <div class="bg-slate-800/40 border border-slate-700/50 rounded-xl overflow-hidden">
                        <div class="px-5 py-3 border-b border-slate-700/40">
                            <p class="text-sm font-semibold text-slate-200">Run Parameters</p>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-px bg-slate-700/30">
                            @foreach([
                                ['Symbols',    $s->symbolsLabel()],
                                ['Timeframe',  $s->timeframe],
                                ['Date From',  $s->date_from?->format('Y-m-d')],
                                ['Date To',    $s->date_to?->format('Y-m-d')],
                            ] as [$lbl, $val])
                            <div class="bg-slate-800/60 px-4 py-3">
                                <p class="text-xs text-slate-500">{{ $lbl }}</p>
                                <p class="text-sm font-mono font-semibold text-slate-200 mt-0.5">{{ $val ?? '—' }}</p>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

                        {{-- EMAs & Pullback --}}
                        <div class="space-y-4">
                            <div class="bg-slate-800/40 border border-slate-700/50 rounded-xl overflow-hidden">
                                <div class="px-5 py-3 border-b border-slate-700/40">
                                    <p class="text-sm font-semibold text-slate-200">EMAs &amp; Pullback</p>
                                </div>
                                <div class="grid grid-cols-2 gap-px bg-slate-700/30">
                                    @foreach([
                                        ['EMA Fast',             $cfg->ema_fast],
                                        ['EMA Mid',              $cfg->ema_mid],
                                        ['EMA Slow',             $cfg->ema_slow],
                                        ['Min Distance %',       $cfg->min_distance_pct],
                                        ['Max Bars after PB',    $cfg->max_bars_after_pullback],
                                    ] as [$lbl, $val])
                                    <div class="bg-slate-800/60 px-4 py-3">
                                        <p class="text-xs text-slate-500">{{ $lbl }}</p>
                                        <p class="text-sm font-mono font-semibold text-slate-200 mt-0.5">{{ $val ?? '—' }}</p>
                                    </div>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Indicators --}}
                            <div class="bg-slate-800/40 border border-slate-700/50 rounded-xl overflow-hidden">
                                <div class="px-5 py-3 border-b border-slate-700/40">
                                    <p class="text-sm font-semibold text-slate-200">Indicators</p>
                                </div>
                                <div class="grid grid-cols-2 gap-px bg-slate-700/30">
                                    @foreach([
                                        ['RSI Period',       $cfg->rsi_period],
                                        ['BB Period',        $cfg->bb_period],
                                        ['BB Std Dev',       $cfg->bb_stddev],
                                        ['ATR Period',       $cfg->atr_period],
                                        ['Volume Avg Period',$cfg->volume_avg_period],
                                    ] as [$lbl, $val])
                                    <div class="bg-slate-800/60 px-4 py-3">
                                        <p class="text-xs text-slate-500">{{ $lbl }}</p>
                                        <p class="text-sm font-mono font-semibold text-slate-200 mt-0.5">{{ $val ?? '—' }}</p>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        {{-- Filters + Stop/TP --}}
                        <div class="space-y-4">
                            <div class="bg-slate-800/40 border border-slate-700/50 rounded-xl overflow-hidden">
                                <div class="px-5 py-3 border-b border-slate-700/40">
                                    <p class="text-sm font-semibold text-slate-200">Entry Filters</p>
                                </div>
                                <div class="grid grid-cols-2 gap-px bg-slate-700/30">
                                    @foreach([
                                        ['RSI Max (Call)',         $cfg->rsi_max_call],
                                        ['RSI Min (Put)',          $cfg->rsi_min_put],
                                        ['Max Candle/ATR Ratio',   $cfg->max_candle_atr_ratio],
                                        ['Max Price/EMA Dist %',   $cfg->max_price_ema_dist_pct],
                                        ['Min BB Dist %',          $cfg->min_bb_dist_pct],
                                        ['Min EMA21/50 Dist',      $cfg->min_ema21_ema50_dist],
                                        ['Max EMA21/50 Dist',      $cfg->max_ema21_ema50_dist],
                                        ['Min EMA50/100 Dist',     $cfg->min_ema50_ema100_dist],
                                        ['Max EMA50/100 Dist',     $cfg->max_ema50_ema100_dist],
                                    ] as [$lbl, $val])
                                    <div class="bg-slate-800/60 px-4 py-3">
                                        <p class="text-xs text-slate-500">{{ $lbl }}</p>
                                        <p class="text-sm font-mono font-semibold text-slate-200 mt-0.5">{{ $val ?? '—' }}</p>
                                    </div>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Stop & TP --}}
                            <div class="bg-slate-800/40 border border-slate-700/50 rounded-xl overflow-hidden">
                                <div class="px-5 py-3 border-b border-slate-700/40">
                                    <p class="text-sm font-semibold text-slate-200">Stop Loss &amp; Take Profit</p>
                                </div>
                                @php
                                    $isQuadrant = in_array($cfg->tp_type, ['ema_quadrant_trail']) ||
                                                  in_array($cfg->stop_type, ['ema_quadrant_trailing']);
                                    $stepPct    = $cfg->quadrant_step_pct ?? 25.0;
                                    $numLevels  = $stepPct > 0 ? (int) round(100 / $stepPct) : 4;
                                    $stopLossCfg = [
                                        ['Stop Type',       $cfg->stop_type],
                                        ['Stop ATR Mult',   $cfg->stop_atr_mult],
                                        ['Stop Buffer %',   $cfg->stop_buffer_pct],
                                    ];
                                    if ($cfg->stop_type === 'percent') {
                                        $stopLossCfg[] = ['Stop %', $cfg->stop_pct];
                                    }
                                    $stopLossCfg[] = ['TP Type', $cfg->tp_type];
                                    if ($isQuadrant) {
                                        $stopLossCfg[] = ['Step %',     $stepPct . '%'];
                                        $stopLossCfg[] = ['Niveles',    $numLevels];
                                        $stopLossCfg[] = ['Stop Inicial', '-' . $stepPct . '% del rango'];
                                        $stopLossCfg[] = ['Trail',      'BE → nivel anterior'];
                                    } else {
                                        $stopLossCfg[] = ['TP1 Value', $cfg->tp1_value];
                                        $stopLossCfg[] = ['TP2 Value', $cfg->tp2_value];
                                        $stopLossCfg[] = ['TP3 Value', $cfg->tp3_value];
                                    }
                                    $stopLossCfg[] = ['Force Exit Time', $cfg->force_exit_time];
                                @endphp
                                <div class="grid grid-cols-2 gap-px bg-slate-700/30">
                                    @foreach($stopLossCfg as [$lbl, $val])
                                    <div class="bg-slate-800/60 px-4 py-3">
                                        <p class="text-xs text-slate-500">{{ $lbl }}</p>
                                        <p class="text-sm font-mono font-semibold text-slate-200 mt-0.5">{{ $val ?? '—' }}</p>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
                @endif

                @elseif($s->isRunning())
                <div class="flex flex-col items-center justify-center py-20 text-slate-400 gap-3">
                    <svg class="w-8 h-8 animate-spin text-blue-400" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <p class="text-sm">{{ $s->progress_label ?: 'Running backtest…' }}</p>
                    <p class="text-xs text-slate-500">{{ $s->progress }}% complete · auto-refreshing</p>
                </div>

                @elseif($s->isFailed())
                <div class="p-5 bg-red-500/10 border border-red-500/30 rounded-xl text-red-300 text-sm mb-4">
                    <p class="font-semibold">Backtest failed — ver debug abajo</p>
                </div>

                @else
                <div class="text-center py-20 text-slate-500 text-sm">
                    Queued — waiting for queue worker to start…
                </div>
                @endif

                {{-- ── Debug panel (visible whenever error_message has data) ── --}}
                @if($s->error_message)
                <div class="mt-4" x-data="{ open: false }">
                    <button @click="open = !open"
                            class="flex items-center gap-2 text-xs font-mono text-amber-400 hover:text-amber-300 border border-amber-500/30 bg-amber-500/5 rounded-lg px-3 py-2 w-full text-left transition-colors">
                        <span x-text="open ? '▼' : '▶'"></span>
                        <span>🐛 Debug log — Schwab API fetch</span>
                        <span class="ml-auto text-amber-500/60">click para {{ 'expandir/colapsar' }}</span>
                    </button>
                    <div x-show="open" x-transition class="mt-2">
                        <pre class="bg-slate-950 border border-slate-700/50 rounded-xl p-4 text-xs text-emerald-300 font-mono overflow-x-auto whitespace-pre-wrap break-words max-h-[60vh] overflow-y-auto">{{ $s->error_message }}</pre>
                    </div>
                </div>
                @endif

            @else
            <div class="flex flex-col items-center justify-center h-60 text-slate-500 gap-2">
                <svg class="w-10 h-10 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                <p class="text-sm">Select a session to view results</p>
            </div>
            @endif
    </div>

    {{-- ── Trade Detail Modal ── --}}
    @if($selectedTradeId && $selectedTradeData)
    @php $td = $selectedTradeData['trade']; @endphp
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4"
         x-data="{
            _tc: null,
            showVolume: true,
            init() {
                this.$nextTick(() => this._buildChart());
            },
            _buildChart() {
                const payload = this.$refs.payload;
                if (!payload) return;
                const data = JSON.parse(payload.textContent);
                if (!data || !data.candles || !data.candles.length) return;

                const container = document.getElementById('bt-trade-chart');
                if (!container) return;
                container.innerHTML = '';

                this._tc = new window.TradingChart('bt-trade-chart', data.candles);
                const t = data.trade;
                const isCall = t.direction === 'CALL';

                // ── EMAs ──
                this._tc.addEMAs([
                    { period: 21,  color: '#3b82f6', title: 'EMA21'  },
                    { period: 50,  color: '#f59e0b', title: 'EMA50'  },
                    { period: 100, color: '#a78bfa', title: 'EMA100' },
                ]);

                // ── Markers ──
                const markers = [];
                if (t.pullback_ts) {
                    markers.push({ time: t.pullback_ts, position: isCall ? 'aboveBar' : 'belowBar', color: '#f59e0b', shape: 'circle', text: 'PB', size: 1 });
                }
                if (t.confirm_ts) {
                    markers.push({ time: t.confirm_ts, position: isCall ? 'aboveBar' : 'belowBar', color: '#a78bfa', shape: 'circle', text: 'Conf', size: 1 });
                }
                if (t.entry_time_ts) {
                    markers.push({ time: t.entry_time_ts, position: isCall ? 'belowBar' : 'aboveBar', color: '#3b82f6', shape: isCall ? 'arrowUp' : 'arrowDown', text: 'Entry', size: 2 });
                }
                if (t.exit_time_ts && t.exit_time_ts !== t.entry_time_ts) {
                    const ec = t.result === 'win' ? '#10b981' : t.result === 'loss' ? '#ef4444' : '#94a3b8';
                    markers.push({ time: t.exit_time_ts, position: isCall ? 'aboveBar' : 'belowBar', color: ec, shape: 'circle', text: 'Exit', size: 2 });
                }
                markers.sort((a, b) => a.time - b.time);
                this._tc.candleSeries.setMarkers(markers);

                // ── Price lines ──
                const lines = [
                    { price: t.stop_loss,     color: '#ef4444', title: 'SL',  style: 2 },
                    { price: t.take_profit_1, color: '#10b981', title: 'TP1', style: 2 },
                    { price: t.take_profit_2, color: '#34d399', title: 'TP2', style: 2 },
                    { price: t.take_profit_3, color: '#6ee7b7', title: 'TP3', style: 1 },
                ];
                lines.forEach(l => {
                    if (l.price) {
                        this._tc.candleSeries.createPriceLine({ price: l.price, color: l.color, lineWidth: 1, lineStyle: l.style, axisLabelVisible: true, title: l.title });
                    }
                });

                // ── Focus view on trade window (warmup bars hidden left) ──
                if (data.view_from_ts && data.view_to_ts) {
                    this._tc.chart.timeScale().setVisibleRange({
                        from: data.view_from_ts,
                        to:   data.view_to_ts,
                    });
                }

                // ── Crosshair overlay ──
                const overlay = document.getElementById('bt-chart-crosshair');
                if (overlay) {
                    this._tc.chart.subscribeCrosshairMove(param => {
                        if (!param.point || !param.time || param.point.x < 0 || param.point.y < 0) {
                            overlay.style.display = 'none';
                            return;
                        }
                        const price = param.seriesData.get(this._tc.candleSeries);
                        if (!price) { overlay.style.display = 'none'; return; }
                        const closePrice = price.close ?? price.value ?? null;
                        if (closePrice === null) { overlay.style.display = 'none'; return; }
                        const timeStr = new Date(param.time * 1000).toLocaleTimeString('en-US', {
                            timeZone: 'America/New_York', hour: '2-digit', minute: '2-digit', hour12: false
                        });
                        const dateStr = new Date(param.time * 1000).toLocaleDateString('en-US', {
                            timeZone: 'America/New_York', month: 'short', day: 'numeric'
                        });
                        overlay.querySelector('#bt-cross-price').textContent = closePrice.toFixed(2);
                        overlay.querySelector('#bt-cross-time').textContent  = dateStr + ' ' + timeStr + ' ET';
                        overlay.style.display = 'flex';
                    });
                }

                this.showVolume = true;
            },
            toggleVolume() {
                this.showVolume = !this.showVolume;
                if (this._tc) this._tc.toggleVolume(this.showVolume);
            },
            destroy() {
                if (this._tc) { this._tc.destroy(); this._tc = null; }
            }
         }"
         x-on:keydown.escape.window="$wire.closeTrade()">

        {{-- Hidden JSON payload --}}
        <script type="application/json" x-ref="payload">@json($selectedTradeData)</script>

        <div class="w-full max-w-screen-2xl max-h-[96vh] bg-[#111318] border border-slate-700/60 rounded-2xl shadow-2xl flex flex-col overflow-hidden">

            {{-- Header --}}
            @php
                $resClrM = match($td['result'] ?? '') {
                    'win'  => 'text-emerald-400 bg-emerald-500/10 border-emerald-500/30',
                    'loss' => 'text-red-400 bg-red-500/10 border-red-500/30',
                    default => 'text-slate-400 bg-slate-700/30 border-slate-600/30'
                };
                $dirClrM = ($td['direction'] ?? '') === 'CALL' ? 'bg-blue-500/20 text-blue-300' : 'bg-red-500/20 text-red-300';
            @endphp
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-700/50 flex-shrink-0">
                <div class="flex items-center gap-3">
                    <span class="text-lg font-bold text-slate-100 font-mono">{{ $td['symbol'] ?? '—' }}</span>
                    <span class="px-2 py-0.5 rounded text-xs font-bold {{ $dirClrM }}">{{ $td['direction'] ?? '—' }}</span>
                    <span class="px-2 py-0.5 rounded text-xs font-bold border {{ $resClrM }}">{{ ucfirst($td['result'] ?? '—') }}</span>
                    <span class="text-xs text-slate-400">{{ $selectedTradeData['timeframe'] }} · #{{ $td['id'] ?? '' }}</span>
                </div>
                <button wire:click="closeTrade" class="p-2 rounded-lg text-slate-400 hover:text-white hover:bg-slate-700/50 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="flex flex-1 overflow-hidden">

                {{-- Left: Details --}}
                <div class="w-60 flex-shrink-0 overflow-y-auto border-r border-slate-700/50 p-4 space-y-4 text-xs">

                    {{-- P&L summary --}}
                    @php
                        $pnl    = $td['pnl_points'] ?? 0;
                        $pnlPct = $td['pnl_pct'] ?? 0;
                        $r      = $td['r_multiple'] ?? null;
                        $pnlClr = $pnl >= 0 ? 'text-emerald-400' : 'text-red-400';
                    @endphp
                    <div class="grid grid-cols-3 gap-2">
                        <div class="col-span-3 bg-slate-800/60 rounded-lg p-3 text-center">
                            <p class="text-slate-400 text-xs mb-0.5">P&L</p>
                            <p class="text-xl font-bold {{ $pnlClr }}">{{ $pnl >= 0 ? '+' : '' }}{{ number_format($pnl, 2) }} pts</p>
                            <p class="text-sm {{ $pnlClr }}">{{ $pnlPct >= 0 ? '+' : '' }}{{ number_format($pnlPct, 2) }}% · {{ $r !== null ? number_format($r, 2).'R' : '—' }}</p>
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
                            <p class="text-slate-300 font-semibold">{{ str_replace('_', ' ', $td['exit_reason'] ?? '—') }}</p>
                        </div>
                    </div>

                    {{-- Entry / Exit --}}
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Entry / Exit</p>
                        @php
                            $fmtEt = fn($utc) => $utc ? \Carbon\Carbon::parse($utc, 'UTC')->setTimezone('America/New_York')->format('Y-m-d H:i') . ' ET' : '—';
                            $rows = [
                                ['Entry Time',  $fmtEt($td['entry_time'] ?? null)],
                                ['Entry Price', '$'.number_format($td['entry_price'] ?? 0, 2)],
                                ['Exit Time',   $fmtEt($td['exit_time'] ?? null)],
                                ['Exit Price',  '$'.number_format($td['exit_price'] ?? 0, 2)],
                                ['Stop Loss',   '$'.number_format($td['stop_loss'] ?? 0, 2)],
                                ['TP1',         '$'.number_format($td['take_profit_1'] ?? 0, 2)],
                                ['TP2',         $td['take_profit_2'] !== null ? '$'.number_format($td['take_profit_2'], 2) : '—'],
                                ['TP3',         $td['take_profit_3'] !== null ? '$'.number_format($td['take_profit_3'], 2) : '—'],
                            ];
                        @endphp
                        <div class="space-y-1">
                            @foreach($rows as [$lbl, $val])
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
                        @php
                            $indRows = [
                                ['EMA21',    $td['ema21']     !== null ? number_format($td['ema21'], 4) : '—'],
                                ['EMA50',    $td['ema50']     !== null ? number_format($td['ema50'], 4) : '—'],
                                ['EMA100',   $td['ema100']    !== null ? number_format($td['ema100'], 4) : '—'],
                                ['Min Dist', $td['min_distance'] !== null ? number_format($td['min_distance'], 4) : '—'],
                                ['RSI',      $td['rsi']       !== null ? number_format($td['rsi'], 1) : '—'],
                                ['ATR',      $td['atr']       !== null ? number_format($td['atr'], 4) : '—'],
                                ['BB Upper', $td['bb_upper']  !== null ? number_format($td['bb_upper'], 2) : '—'],
                                ['BB Mid',   $td['bb_middle'] !== null ? number_format($td['bb_middle'], 2) : '—'],
                                ['BB Lower', $td['bb_lower']  !== null ? number_format($td['bb_lower'], 2) : '—'],
                                ['Volume',   $td['volume']    !== null ? number_format($td['volume']) : '—'],
                                ['Rel Vol',  $td['rel_volume'] !== null ? number_format($td['rel_volume'], 2).'x' : '—'],
                            ];
                        @endphp
                        <div class="space-y-1">
                            @foreach($indRows as [$lbl, $val])
                            <div class="flex justify-between">
                                <span class="text-slate-500">{{ $lbl }}</span>
                                <span class="text-slate-200 font-mono">{{ $val }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Pullback candle --}}
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Pullback Candle
                            <span class="text-slate-600 font-normal ml-1">{{ isset($td['pullback_time']) && $td['pullback_time'] ? \Carbon\Carbon::parse($td['pullback_time'], 'UTC')->setTimezone('America/New_York')->format('Y-m-d H:i') . ' ET' : '' }}</span>
                        </p>
                        <div class="grid grid-cols-4 gap-1 text-center">
                            @foreach(['O'=>'pullback_open','H'=>'pullback_high','L'=>'pullback_low','C'=>'pullback_close'] as $k=>$col)
                            <div class="bg-slate-800/40 rounded p-1.5">
                                <p class="text-slate-500 text-xs">{{ $k }}</p>
                                <p class="text-slate-200 font-mono text-xs">{{ $td[$col] !== null ? number_format($td[$col], 2) : '—' }}</p>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Confirm candle --}}
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Confirm Candle
                            <span class="text-slate-600 font-normal ml-1">{{ isset($td['confirm_time']) && $td['confirm_time'] ? \Carbon\Carbon::parse($td['confirm_time'], 'UTC')->setTimezone('America/New_York')->format('Y-m-d H:i') . ' ET' : '' }}</span>
                        </p>
                        <div class="grid grid-cols-4 gap-1 text-center">
                            @foreach(['O'=>'confirm_open','H'=>'confirm_high','L'=>'confirm_low','C'=>'confirm_close'] as $k=>$col)
                            <div class="bg-slate-800/40 rounded p-1.5">
                                <p class="text-slate-500 text-xs">{{ $k }}</p>
                                <p class="text-slate-200 font-mono text-xs">{{ $td[$col] !== null ? number_format($td[$col], 2) : '—' }}</p>
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
                            <span class="text-amber-400">● PB</span>
                            <span class="text-purple-400">● Conf</span>
                            <span class="text-red-400">— SL</span>
                            <span class="text-emerald-400">— TP</span>
                        </span>

                        {{-- EMA legend --}}
                        <span class="text-xs flex items-center gap-2">
                            <span class="flex items-center gap-1"><span class="inline-block w-4 h-0.5 bg-blue-500 rounded"></span><span class="text-blue-400">EMA21</span></span>
                            <span class="flex items-center gap-1"><span class="inline-block w-4 h-0.5 bg-amber-400 rounded"></span><span class="text-amber-400">EMA50</span></span>
                            <span class="flex items-center gap-1"><span class="inline-block w-4 h-0.5 bg-purple-400 rounded"></span><span class="text-purple-400">EMA100</span></span>
                        </span>

                        {{-- Volume toggle --}}
                        <button x-on:click="toggleVolume()"
                                class="ml-auto flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium transition-colors"
                                :class="showVolume ? 'bg-slate-600/60 text-slate-200' : 'bg-slate-800/60 text-slate-500'">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            Vol
                        </button>

                        @if(empty($selectedTradeData['candles']))
                            <span class="text-xs text-amber-400">No candle data in DB for this range</span>
                        @endif
                    </div>
                    <div class="flex-1 relative">
                        <div id="bt-trade-chart" class="absolute inset-0" wire:ignore></div>
                        <div id="bt-chart-crosshair" class="absolute top-2 left-2 hidden items-center gap-2 bg-slate-900/90 border border-slate-600/60 rounded-lg px-2.5 py-1.5 pointer-events-none z-10">
                            <span class="text-xs font-mono font-semibold text-slate-100" id="bt-cross-price"></span>
                            <span class="text-slate-600 text-xs">·</span>
                            <span class="text-xs text-slate-400" id="bt-cross-time"></span>
                        </div>
                        @if(empty($selectedTradeData['candles']))
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

    {{-- ── Grid Analysis Modal ── --}}
    @if($showAnalysisModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4 overflow-y-auto">
        <div class="w-full max-w-4xl bg-[#111318] border border-slate-700/60 rounded-2xl shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-700/50 sticky top-0 bg-[#111318] z-10">
                <h2 class="text-lg font-bold text-slate-100">📊 Grid Search Analysis</h2>
                <button wire:click="closeAnalysis" class="text-slate-400 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="p-6 space-y-6">
                @if(!$gridAnalysis)
                    <div class="text-center py-8 text-slate-400">
                        No completed sessions with trades found
                    </div>
                @else
                    {{-- Best Profit Factor --}}
                    <div>
                        <h3 class="text-sm font-semibold text-emerald-400 mb-3">🏆 Best Profit Factor</h3>
                        <div class="space-y-2">
                            @foreach($gridAnalysis['best_profit_factor'] as $r)
                            <div class="flex items-center justify-between bg-slate-800/40 rounded-lg px-4 py-2">
                                <div class="flex items-center gap-4">
                                    <span class="text-emerald-400 font-mono font-bold">{{ number_format($r['profit_factor'], 2) }}</span>
                                    <span class="text-slate-400 text-sm">Win: {{ number_format($r['win_rate'], 1) }}% | PnL: ${{ number_format($r['total_pnl'], 2) }}</span>
                                </div>
                                <div class="text-right">
                                    <button wire:click="selectSessionFromAnalysis({{ $r['session']->id }})" class="text-xs text-blue-400 hover:text-blue-300 underline">{{ $r['session']->name }}</button>
                                    <span class="text-xs text-slate-500 block">dist:{{ number_format($r['params']['min_distance_pct'], 2) }} bars:{{ $r['params']['max_bars_after_pullback'] }} stop:{{ number_format($r['params']['stop_atr_mult'], 1) }} tp:{{ number_format($r['params']['tp1_value'], 1) }}</span>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Best Win Rate --}}
                    <div>
                        <h3 class="text-sm font-semibold text-emerald-400 mb-3">🏆 Best Win Rate</h3>
                        <div class="space-y-2">
                            @foreach($gridAnalysis['best_win_rate'] as $r)
                            <div class="flex items-center justify-between bg-slate-800/40 rounded-lg px-4 py-2">
                                <div class="flex items-center gap-4">
                                    <span class="text-emerald-400 font-mono font-bold">{{ number_format($r['win_rate'], 1) }}%</span>
                                    <span class="text-slate-400 text-sm">PF: {{ number_format($r['profit_factor'], 2) }} | PnL: ${{ number_format($r['total_pnl'], 2) }}</span>
                                </div>
                                <div class="text-right">
                                    <button wire:click="selectSessionFromAnalysis({{ $r['session']->id }})" class="text-xs text-blue-400 hover:text-blue-300 underline">{{ $r['session']->name }}</button>
                                    <span class="text-xs text-slate-500 block">dist:{{ number_format($r['params']['min_distance_pct'], 2) }} bars:{{ $r['params']['max_bars_after_pullback'] }} stop:{{ number_format($r['params']['stop_atr_mult'], 1) }} tp:{{ number_format($r['params']['tp1_value'], 1) }}</span>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Best Avg Winner --}}
                    <div>
                        <h3 class="text-sm font-semibold text-emerald-400 mb-3">🏆 Best Avg Winner</h3>
                        <div class="space-y-2">
                            @foreach($gridAnalysis['best_avg_winner'] as $r)
                            <div class="flex items-center justify-between bg-slate-800/40 rounded-lg px-4 py-2">
                                <div class="flex items-center gap-4">
                                    <span class="text-emerald-400 font-mono font-bold">${{ number_format($r['avg_winner'], 2) }}</span>
                                    <span class="text-slate-400 text-sm">PF: {{ number_format($r['profit_factor'], 2) }} | Win: {{ number_format($r['win_rate'], 1) }}%</span>
                                </div>
                                <div class="text-right">
                                    <button wire:click="selectSessionFromAnalysis({{ $r['session']->id }})" class="text-xs text-blue-400 hover:text-blue-300 underline">{{ $r['session']->name }}</button>
                                    <span class="text-xs text-slate-500 block">dist:{{ number_format($r['params']['min_distance_pct'], 2) }} bars:{{ $r['params']['max_bars_after_pullback'] }} stop:{{ number_format($r['params']['stop_atr_mult'], 1) }} tp:{{ number_format($r['params']['tp1_value'], 1) }}</span>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Avg Winner > Avg Loser --}}
                    <div>
                        <h3 class="text-sm font-semibold text-emerald-400 mb-3">🏆 Avg Winner > Avg Loser</h3>
                        <div class="space-y-2">
                            @foreach($gridAnalysis['avg_winner_gt_avg_loser'] as $r)
                            <div class="flex items-center justify-between bg-slate-800/40 rounded-lg px-4 py-2">
                                <div class="flex items-center gap-4">
                                    <span class="text-emerald-400 font-mono font-bold">${{ number_format($r['avg_winner'], 2) }} > ${{ number_format(abs($r['avg_loser']), 2) }}</span>
                                    <span class="text-slate-400 text-sm">PF: {{ number_format($r['profit_factor'], 2) }}</span>
                                </div>
                                <div class="text-right">
                                    <button wire:click="selectSessionFromAnalysis({{ $r['session']->id }})" class="text-xs text-blue-400 hover:text-blue-300 underline">{{ $r['session']->name }}</button>
                                    <span class="text-xs text-slate-500 block">dist:{{ number_format($r['params']['min_distance_pct'], 2) }} bars:{{ $r['params']['max_bars_after_pullback'] }} stop:{{ number_format($r['params']['stop_atr_mult'], 1) }} tp:{{ number_format($r['params']['tp1_value'], 1) }}</span>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Best Max Drawdown --}}
                    <div>
                        <h3 class="text-sm font-semibold text-emerald-400 mb-3">🏆 Best Max Drawdown (lowest)</h3>
                        <div class="space-y-2">
                            @foreach($gridAnalysis['best_max_drawdown'] as $r)
                            <div class="flex items-center justify-between bg-slate-800/40 rounded-lg px-4 py-2">
                                <div class="flex items-center gap-4">
                                    <span class="text-emerald-400 font-mono font-bold">{{ number_format($r['max_drawdown'], 2) }}%</span>
                                    <span class="text-slate-400 text-sm">PnL: ${{ number_format($r['total_pnl'], 2) }} | PF: {{ number_format($r['profit_factor'], 2) }}</span>
                                </div>
                                <div class="text-right">
                                    <button wire:click="selectSessionFromAnalysis({{ $r['session']->id }})" class="text-xs text-blue-400 hover:text-blue-300 underline">{{ $r['session']->name }}</button>
                                    <span class="text-xs text-slate-500 block">dist:{{ number_format($r['params']['min_distance_pct'], 2) }} bars:{{ $r['params']['max_bars_after_pullback'] }} stop:{{ number_format($r['params']['stop_atr_mult'], 1) }} tp:{{ number_format($r['params']['tp1_value'], 1) }}</span>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Best Total PnL --}}
                    <div>
                        <h3 class="text-sm font-semibold text-emerald-400 mb-3">🏆 Best Total PnL</h3>
                        <div class="space-y-2">
                            @foreach($gridAnalysis['best_total_pnl'] as $r)
                            <div class="flex items-center justify-between bg-slate-800/40 rounded-lg px-4 py-2">
                                <div class="flex items-center gap-4">
                                    <span class="text-emerald-400 font-mono font-bold">${{ number_format($r['total_pnl'], 2) }}</span>
                                    <span class="text-slate-400 text-sm">PF: {{ number_format($r['profit_factor'], 2) }} | Win: {{ number_format($r['win_rate'], 1) }}% | DD: {{ number_format($r['max_drawdown'], 2) }}%</span>
                                </div>
                                <div class="text-right">
                                    <button wire:click="selectSessionFromAnalysis({{ $r['session']->id }})" class="text-xs text-blue-400 hover:text-blue-300 underline">{{ $r['session']->name }}</button>
                                    <span class="text-xs text-slate-500 block">dist:{{ number_format($r['params']['min_distance_pct'], 2) }} bars:{{ $r['params']['max_bars_after_pullback'] }} stop:{{ number_format($r['params']['stop_atr_mult'], 1) }} tp:{{ number_format($r['params']['tp1_value'], 1) }}</span>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
    @endif

    {{-- ── Sim Profit Modal ── --}}
    @if($showSimProfitModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4">
        <div class="w-full max-w-lg bg-[#111318] border border-slate-700/60 rounded-2xl shadow-2xl">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-700/50">
                <h2 class="text-lg font-bold text-slate-100">📈 Option Profit Simulator</h2>
                <button wire:click="closeSimProfit" class="text-slate-400 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="p-6 space-y-4">
                @php $res = $simProfitResult; @endphp
                @if($res)
                <div class="bg-slate-800/40 border border-slate-700/50 rounded-xl p-4">
                    <p class="text-xs text-slate-500 mb-2">Trade: {{ $res['direction'] }} ({{ ucfirst($res['result']) }}) @ ${{ number_format($res['contract_price'], 2) }}</p>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-slate-500">New Price</p>
                            <p class="text-lg font-mono font-bold text-slate-200">${{ number_format($res['new_price'], 2) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">Price Change</p>
                            <p class="text-lg font-mono font-bold {{ $res['price_change'] >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                                {{ $res['price_change'] >= 0 ? '+' : '' }}${{ number_format($res['price_change'], 3) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">Per Contract P/L</p>
                            <p class="text-lg font-mono font-bold {{ $res['per_contract_pnl'] >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                                {{ $res['per_contract_pnl'] >= 0 ? '+' : '' }}${{ number_format($res['per_contract_pnl'], 2) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">Total P/L ({{ $simContracts }} contract{{ $simContracts > 1 ? 's' : '' }})</p>
                            <p class="text-lg font-mono font-bold {{ $res['total_pnl'] >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                                {{ $res['total_pnl'] >= 0 ? '+' : '' }}${{ number_format($res['total_pnl'], 2) }}
                            </p>
                        </div>
                    </div>
                </div>
                @endif

                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-slate-300">Contract Settings</p>
                        <button wire:click="switchUnderlying"
                                class="px-3 py-1.5 bg-slate-700 hover:bg-slate-600 text-slate-300 text-xs font-semibold rounded-lg transition-colors">
                            Switch to {{ $simUnderlying === 'SPY' ? 'SPX' : 'SPY' }}
                        </button>
                    </div>
                    <p class="text-xs text-slate-500">Current underlying: <span class="font-semibold text-slate-300">{{ $simUnderlying }}</span></p>

                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Simulate Result</label>
                        <select wire:model="simResult"
                                class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-100 focus:border-blue-500 focus:outline-none">
                            <option value="auto">Auto (use actual trade result)</option>
                            <option value="win">Simulate Win</option>
                            <option value="loss">Simulate Loss</option>
                            <option value="breakeven">Simulate Breakeven</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-slate-500 mb-1">Contract Price ($)</label>
                            <input wire:model.defer="simContractPrice" type="number" step="0.01" min="0.01"
                                   class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-100 focus:border-blue-500 focus:outline-none"
                                   placeholder="0.00">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-500 mb-1">Contracts</label>
                            <input wire:model.defer="simContracts" type="number" step="1" min="1" max="1000"
                                   class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-100 focus:border-blue-500 focus:outline-none"
                                   placeholder="1">
                        </div>
                    </div>

                    <p class="text-sm font-semibold text-slate-300">Option Greeks (SPY Example)</p>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs text-slate-500 mb-1">Delta</label>
                            <input wire:model.defer="simDelta" type="number" step="0.01" min="0" max="1"
                                   class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-100 focus:border-blue-500 focus:outline-none"
                                   placeholder="0.50">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-500 mb-1">Gamma</label>
                            <input wire:model.defer="simGamma" type="number" step="0.01" min="0" max="10"
                                   class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-100 focus:border-blue-500 focus:outline-none"
                                   placeholder="0.10">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-500 mb-1">Theta (daily)</label>
                            <input wire:model.defer="simTheta" type="number" step="0.01" min="-10" max="0"
                                   class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-100 focus:border-blue-500 focus:outline-none"
                                   placeholder="-0.05">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Price Move ($)</label>
                        <input wire:model.defer="simPriceMove" type="number" step="0.1" min="-100" max="100"
                               class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-100 focus:border-blue-500 focus:outline-none"
                               placeholder="0.0">
                    </div>

                    <button wire:click="$refresh('simProfitResult')"
                            class="w-full py-2 bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold rounded-lg transition-colors">
                        Recalculate
                    </button>
                </div>

                <p class="text-xs text-slate-500">
                    Formula: Δprice ≈ delta × ΔS + 0.5 × gamma × (ΔS)² + theta (1 day decay)
                </p>
            </div>
        </div>
    </div>
    @endif

</div>

@script
<script>
    $wire.on('scrollToSession', (data) => {
        const element = document.getElementById('session-' + data.sessionId);
        if (element) {
            element.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
        }
    });
</script>
@endscript

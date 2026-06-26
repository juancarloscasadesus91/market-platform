<div class="p-6 space-y-6 max-w-[1920px] mx-auto" @if($view === 'detail') wire:poll.2s @endif>

    {{-- Flash Messages --}}
    @if($successMessage)
    <div class="flex items-center space-x-3 px-4 py-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20">
        <svg class="w-5 h-5 text-emerald-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span class="text-emerald-400 text-sm flex-1">{{ $successMessage }}</span>
        <button wire:click="$set('successMessage', null)" class="text-emerald-400 hover:text-emerald-300">✕</button>
    </div>
    @endif
    @if($errorMessage)
    <div class="flex items-center space-x-3 px-4 py-3 rounded-xl bg-rose-500/10 border border-rose-500/20">
        <svg class="w-5 h-5 text-rose-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span class="text-rose-400 text-sm flex-1">{{ $errorMessage }}</span>
        <button wire:click="$set('errorMessage', null)" class="text-rose-400 hover:text-rose-300">✕</button>
    </div>
    @endif

    {{-- ===================== LIST VIEW ===================== --}}
    @if($view === 'list')

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white">Strategy Bots</h1>
            <p class="text-slate-400 text-sm mt-0.5">Configure and manage your automated trading strategies on Schwab</p>
        </div>
        <button wire:click="showCreate"
            class="flex items-center space-x-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-semibold rounded-xl transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <span>New Bot</span>
        </button>
    </div>

    @if($bots->isEmpty())
    <div class="flex flex-col items-center justify-center py-24 space-y-5">
        <div class="w-20 h-20 rounded-2xl bg-violet-500/10 border border-violet-500/20 flex items-center justify-center">
            <svg class="w-10 h-10 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
        </div>
        <div class="text-center">
            <h2 class="text-xl font-semibold text-white mb-2">No bots yet</h2>
            <p class="text-slate-400 text-sm max-w-sm">Create your first strategy bot. Configure it in paper mode first to validate performance before going live.</p>
        </div>
        <button wire:click="showCreate" class="px-6 py-3 bg-violet-600 hover:bg-violet-500 text-white font-semibold rounded-xl transition-colors">
            Create First Bot
        </button>
    </div>
    @else
    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-5">
        @foreach($bots as $bot)
        @php
            $statusColor = match($bot->status) {
                'running' => ['ring' => 'ring-emerald-500/30', 'dot' => 'bg-emerald-400', 'badge' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20', 'label' => 'Running'],
                'paused'  => ['ring' => 'ring-amber-500/30',   'dot' => 'bg-amber-400',   'badge' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',   'label' => 'Paused'],
                'stopped' => ['ring' => 'ring-rose-500/20',    'dot' => 'bg-rose-400',    'badge' => 'bg-rose-500/10 text-rose-400 border-rose-500/20',    'label' => 'Stopped'],
                default   => ['ring' => 'ring-slate-700/30',   'dot' => 'bg-slate-500',   'badge' => 'bg-slate-700/50 text-slate-400 border-slate-600/50', 'label' => 'Idle'],
            };
            $pnlPositive = $bot->total_pnl >= 0;
            $winRate = $bot->total_trades > 0 ? round(($bot->winning_trades / $bot->total_trades) * 100, 1) : 0;
        @endphp
        <div class="rounded-2xl bg-slate-900/60 border border-slate-700/50 ring-1 {{ $statusColor['ring'] }} overflow-hidden hover:border-slate-600/70 transition-all">
            {{-- Card Header --}}
            <div class="px-5 py-4 border-b border-slate-800/60 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="relative">
                        <div class="w-10 h-10 rounded-xl bg-violet-500/10 border border-violet-500/20 flex items-center justify-center">
                            <svg class="w-5 h-5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </div>
                        @if($bot->status === 'running')
                        <span class="absolute -top-1 -right-1 w-3 h-3 bg-emerald-400 rounded-full animate-pulse border-2 border-slate-900"></span>
                        @endif
                    </div>
                    <div>
                        <div class="font-semibold text-white text-sm">{{ $bot->name }}</div>
                        <div class="text-xs text-slate-400">{{ $bot->symbol }} · {{ $bot->timeframe }} · {{ strtoupper(str_replace('_', ' ', $bot->strategy_key)) }}</div>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    @if($bot->paper_mode)
                    <span class="px-2 py-0.5 rounded text-xs font-bold bg-blue-500/10 text-blue-400 border border-blue-500/20">PAPER</span>
                    @else
                    <span class="px-2 py-0.5 rounded text-xs font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">LIVE</span>
                    @endif
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium border {{ $statusColor['badge'] }}">
                        <span class="inline-block w-1.5 h-1.5 rounded-full {{ $statusColor['dot'] }} mr-1 align-middle"></span>
                        {{ $statusColor['label'] }}
                    </span>
                </div>
            </div>

            {{-- Stats --}}
            <div class="px-5 py-4 grid grid-cols-4 gap-3">
                <div class="text-center">
                    <div class="text-xs text-slate-500 mb-1">Balance</div>
                    <div class="text-sm font-bold {{ $pnlPositive ? 'text-emerald-400' : 'text-rose-400' }}">
                        ${{ number_format($bot->paper_mode ? $bot->paper_balance : 0, 0) }}
                    </div>
                </div>
                <div class="text-center">
                    <div class="text-xs text-slate-500 mb-1">P&L</div>
                    <div class="text-sm font-bold {{ $pnlPositive ? 'text-emerald-400' : 'text-rose-400' }}">
                        {{ $pnlPositive ? '+' : '' }}${{ number_format($bot->total_pnl, 0) }}
                    </div>
                </div>
                <div class="text-center">
                    <div class="text-xs text-slate-500 mb-1">Trades</div>
                    <div class="text-sm font-bold text-white">{{ $bot->total_trades }}</div>
                </div>
                <div class="text-center">
                    <div class="text-xs text-slate-500 mb-1">Win %</div>
                    <div class="text-sm font-bold {{ $winRate >= 50 ? 'text-emerald-400' : 'text-rose-400' }}">{{ $winRate }}%</div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="px-5 py-3 bg-slate-800/30 border-t border-slate-800/60 flex items-center justify-between">
                <div class="flex items-center space-x-1">
                    @if($bot->status === 'idle' || $bot->status === 'stopped')
                    <button wire:click="startBot({{ $bot->id }})" wire:confirm="Start bot '{{ $bot->name }}'?"
                        class="p-2 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 transition-colors" title="Start">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </button>
                    @endif
                    @if($bot->status === 'running')
                    <button wire:click="pauseBot({{ $bot->id }})"
                        class="p-2 rounded-lg bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 transition-colors" title="Pause">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M6 4h4v16H6zM14 4h4v16h-4z"/></svg>
                    </button>
                    @endif
                    @if($bot->status === 'paused')
                    <button wire:click="resumeBot({{ $bot->id }})"
                        class="p-2 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 transition-colors" title="Resume">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </button>
                    @endif
                    @if($bot->status === 'running' || $bot->status === 'paused')
                    <button wire:click="stopBot({{ $bot->id }})" wire:confirm="Stop bot '{{ $bot->name }}'?"
                        class="p-2 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 transition-colors" title="Stop">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10h6v4H9z"/></svg>
                    </button>
                    @endif
                </div>
                <div class="flex items-center space-x-1">
                    <button wire:click="showDetail({{ $bot->id }})"
                        class="px-3 py-1.5 text-xs font-medium bg-slate-700/50 hover:bg-slate-700 text-slate-300 rounded-lg transition-colors">
                        Details
                    </button>
                    <button wire:click="showEdit({{ $bot->id }})"
                        class="px-3 py-1.5 text-xs font-medium bg-slate-700/50 hover:bg-slate-700 text-slate-300 rounded-lg transition-colors">
                        Edit
                    </button>
                    <button wire:click="deleteBot({{ $bot->id }})" wire:confirm="Delete '{{ $bot->name }}'? This cannot be undone."
                        class="p-1.5 text-slate-500 hover:text-rose-400 transition-colors" title="Delete">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    @endif {{-- end list --}}

    {{-- ===================== CREATE / EDIT FORM ===================== --}}
    @if($view === 'create' || $view === 'edit')

    <div class="flex items-center justify-between">
        <div class="flex items-center space-x-3">
            <button wire:click="showList" class="p-2 rounded-lg hover:bg-slate-800/80 text-slate-400 hover:text-white transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <div>
                <h1 class="text-2xl font-bold text-white">{{ $view === 'create' ? 'New Strategy Bot' : 'Edit Bot' }}</h1>
                <p class="text-slate-400 text-sm">Configure strategy, position sizing, and risk parameters</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        {{-- LEFT: Main config --}}
        <div class="xl:col-span-2 space-y-5">

            {{-- Identity --}}
            <div class="rounded-xl bg-slate-900/50 border border-slate-700/50 overflow-hidden">
                <div class="px-5 py-4 bg-slate-800/30 border-b border-slate-700/50">
                    <h3 class="font-semibold text-white">Bot Identity</h3>
                </div>
                <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Bot Name</label>
                        <input type="text" wire:model="formName" placeholder="e.g. SPY EMA Pullback Paper"
                            class="w-full px-3 py-2 bg-slate-800/80 border border-slate-700/50 rounded-lg text-white text-sm focus:outline-none focus:border-blue-500/60 placeholder-slate-500">
                        @error('formName') <span class="text-rose-400 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Strategy</label>
                        <select wire:model.live="formStrategyKey"
                            class="w-full px-3 py-2 bg-slate-800/80 border border-slate-700/50 rounded-lg text-white text-sm focus:outline-none focus:border-blue-500/60">
                            @foreach($strategyOptions as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Symbol</label>
                        <input type="text" wire:model="formSymbol" placeholder="SPY"
                            class="w-full px-3 py-2 bg-slate-800/80 border border-slate-700/50 rounded-lg text-white text-sm focus:outline-none focus:border-blue-500/60 placeholder-slate-500 uppercase">
                        @error('formSymbol') <span class="text-rose-400 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Timeframe</label>
                        <select wire:model="formTimeframe"
                            class="w-full px-3 py-2 bg-slate-800/80 border border-slate-700/50 rounded-lg text-white text-sm focus:outline-none focus:border-blue-500/60">
                            @foreach(['1m' => '1 min','5m' => '5 min','15m' => '15 min','30m' => '30 min','1h' => '1 Hour','1d' => '1 Day'] as $val => $lbl)
                            <option value="{{ $val }}">{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Trade Type</label>
                        <div class="grid grid-cols-2 gap-3">
                            <button type="button" wire:click="$set('formTradeType', 'equity')"
                                class="py-3 px-4 rounded-xl border text-sm font-bold transition-all text-left
                                {{ $formTradeType === 'equity' ? 'bg-blue-500/20 border-blue-500/50 text-blue-300 ring-1 ring-blue-500/30' : 'bg-slate-800/80 border-slate-700/50 text-slate-400 hover:text-white' }}">
                                <div class="text-base mb-0.5">📈 Equity / ETF</div>
                                <div class="text-xs font-normal opacity-70">Buy/sell shares directly (SPY, QQQ...)</div>
                            </button>
                            <button type="button" wire:click="$set('formTradeType', 'options')"
                                class="py-3 px-4 rounded-xl border text-sm font-bold transition-all text-left
                                {{ $formTradeType === 'options' ? 'bg-violet-500/20 border-violet-500/50 text-violet-300 ring-1 ring-violet-500/30' : 'bg-slate-800/80 border-slate-700/50 text-slate-400 hover:text-white' }}">
                                <div class="text-base mb-0.5">🎯 Options Contracts</div>
                                <div class="text-xs font-normal opacity-70">Signal on index → bot picks contract by delta</div>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Mode & Budget --}}
            <div class="rounded-xl bg-slate-900/50 border border-slate-700/50 overflow-hidden">
                <div class="px-5 py-4 bg-slate-800/30 border-b border-slate-700/50 flex items-center justify-between">
                    <h3 class="font-semibold text-white">Mode & Budget</h3>
                    <div class="flex items-center space-x-3">
                        <span class="text-sm text-slate-400">Paper Mode</span>
                        <button wire:click="$toggle('formPaperMode')"
                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors {{ $formPaperMode ? 'bg-blue-600' : 'bg-slate-700' }}">
                            <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform {{ $formPaperMode ? 'translate-x-6' : 'translate-x-1' }}"></span>
                        </button>
                        <span class="text-sm font-bold {{ $formPaperMode ? 'text-blue-400' : 'text-amber-400' }}">
                            {{ $formPaperMode ? 'PAPER' : 'LIVE' }}
                        </span>
                    </div>
                </div>
                <div class="p-5 space-y-4">
                    @if($formPaperMode)
                    <div class="flex items-start space-x-3 px-4 py-3 rounded-lg bg-blue-500/5 border border-blue-500/15">
                        <svg class="w-5 h-5 text-blue-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-blue-300 text-xs leading-relaxed">
                            <strong>Paper Mode:</strong> Schwab doesn't have a paper trading API, so we simulate it internally. Set your virtual budget and the bot will track P&L in real-time without placing real orders.
                        </p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Paper Budget ($)</label>
                        <input type="number" wire:model="formPaperBudget" min="100" step="100"
                            class="w-full px-3 py-2 bg-slate-800/80 border border-slate-700/50 rounded-lg text-white text-sm focus:outline-none focus:border-blue-500/60">
                        @error('formPaperBudget') <span class="text-rose-400 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>
                    @else
                    <div class="flex items-start space-x-3 px-4 py-3 rounded-lg bg-amber-500/5 border border-amber-500/15">
                        <svg class="w-5 h-5 text-amber-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <p class="text-amber-300 text-xs leading-relaxed">
                            <strong>Live Mode:</strong> The bot will place real orders on your Schwab account. Test thoroughly in paper mode first!
                        </p>
                    </div>
                    @if(!empty($schwabAccounts))
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Schwab Account</label>
                        <select wire:model="formSchwabAccountHash"
                            class="w-full px-3 py-2 bg-slate-800/80 border border-slate-700/50 rounded-lg text-white text-sm focus:outline-none focus:border-amber-500/60">
                            <option value="">-- Select Account --</option>
                            @foreach($schwabAccounts as $acc)
                            <option value="{{ $acc['hash'] }}">{{ $acc['number'] }} ({{ $acc['type'] }})</option>
                            @endforeach
                        </select>
                    </div>
                    @else
                    <p class="text-slate-500 text-xs">No Schwab accounts found. <a href="{{ route('schwab.account') }}" class="text-blue-400 underline">Connect Schwab Trader API</a> first.</p>
                    @endif
                    @endif
                </div>
            </div>

            {{-- Position Sizing --}}
            <div class="rounded-xl bg-slate-900/50 border border-slate-700/50 overflow-hidden">
                <div class="px-5 py-4 bg-slate-800/30 border-b border-slate-700/50">
                    <h3 class="font-semibold text-white">Position Sizing</h3>
                </div>
                <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Size Type</label>
                        <select wire:model="formPositionSizeType"
                            class="w-full px-3 py-2 bg-slate-800/80 border border-slate-700/50 rounded-lg text-white text-sm focus:outline-none focus:border-blue-500/60">
                            <option value="fixed_dollars">Fixed Dollar Amount</option>
                            <option value="fixed_shares">Fixed Shares / Contracts</option>
                            <option value="risk_pct">% of Balance per Trade</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">
                            @if($formPositionSizeType === 'fixed_dollars') Amount ($)
                            @elseif($formPositionSizeType === 'fixed_shares') Quantity
                            @else % of Balance
                            @endif
                        </label>
                        <input type="number" wire:model="formPositionSizeValue" min="1" step="0.1"
                            class="w-full px-3 py-2 bg-slate-800/80 border border-slate-700/50 rounded-lg text-white text-sm focus:outline-none focus:border-blue-500/60">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Max Concurrent Trades</label>
                        <input type="number" wire:model="formMaxConcurrent" min="1" max="20" step="1"
                            class="w-full px-3 py-2 bg-slate-800/80 border border-slate-700/50 rounded-lg text-white text-sm focus:outline-none focus:border-blue-500/60">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Max Daily Loss % (kill-switch)</label>
                        <input type="number" wire:model="formMaxDailyLossPct" min="0" max="100" step="0.5" placeholder="e.g. 5"
                            class="w-full px-3 py-2 bg-slate-800/80 border border-slate-700/50 rounded-lg text-white text-sm focus:outline-none focus:border-blue-500/60 placeholder-slate-500">
                        <p class="text-xs text-slate-500 mt-1">Bot stops for the day if daily loss exceeds this %</p>
                    </div>
                </div>
            </div>

            {{-- Options Config (shown only when trade_type = options) --}}
            @if($formTradeType === 'options')
            <div class="rounded-xl bg-violet-900/20 border border-violet-500/30 overflow-hidden">
                <div class="px-5 py-4 bg-violet-900/20 border-b border-violet-500/20 flex items-center space-x-2">
                    <span class="text-lg">🎯</span>
                    <div>
                        <h3 class="font-semibold text-violet-300">Options Contract Settings</h3>
                        <p class="text-xs text-violet-400/70 mt-0.5">Bot picks the best contract from Schwab's option chain on every signal</p>
                    </div>
                </div>
                <div class="p-5 space-y-5">
                    {{-- Contract selection --}}
                    <div>
                        <h4 class="text-xs font-bold text-violet-400/80 uppercase tracking-widest mb-3">Contract Selection</h4>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Target Delta</label>
                                <input type="number" wire:model="formOptionDeltaTarget" min="0.01" max="0.99" step="0.01"
                                    class="w-full px-3 py-2 bg-slate-800/80 border border-violet-500/30 rounded-lg text-white text-sm focus:outline-none focus:border-violet-500/60">
                                <p class="text-xs text-slate-500 mt-1">0.40 = near ATM, 0.20 = OTM</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Delta Tolerance ±</label>
                                <input type="number" wire:model="formOptionDeltaTol" min="0.01" max="0.20" step="0.01"
                                    class="w-full px-3 py-2 bg-slate-800/80 border border-violet-500/30 rounded-lg text-white text-sm focus:outline-none focus:border-violet-500/60">
                                <p class="text-xs text-slate-500 mt-1">Accepted delta range</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Contracts</label>
                                <input type="number" wire:model="formOptionContracts" min="1" max="100" step="1"
                                    class="w-full px-3 py-2 bg-slate-800/80 border border-violet-500/30 rounded-lg text-white text-sm focus:outline-none focus:border-violet-500/60">
                                <p class="text-xs text-slate-500 mt-1">Number of contracts per signal</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Min DTE</label>
                                <input type="number" wire:model="formOptionMinDte" min="0" max="60" step="1"
                                    class="w-full px-3 py-2 bg-slate-800/80 border border-violet-500/30 rounded-lg text-white text-sm focus:outline-none focus:border-violet-500/60">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Max DTE</label>
                                <input type="number" wire:model="formOptionMaxDte" min="1" max="60" step="1"
                                    class="w-full px-3 py-2 bg-slate-800/80 border border-violet-500/30 rounded-lg text-white text-sm focus:outline-none focus:border-violet-500/60">
                                <p class="text-xs text-slate-500 mt-1">e.g. 7 = up to 7-DTE</p>
                            </div>
                        </div>
                    </div>

                    {{-- Exit rules --}}
                    <div>
                        <h4 class="text-xs font-bold text-violet-400/80 uppercase tracking-widest mb-3">Option Exit Rules <span class="text-slate-500 normal-case font-normal">(% of contract value, leave empty to disable)</span></h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Stop Loss % <span class="text-rose-400">▼</span></label>
                                <div class="relative">
                                    <input type="number" wire:model="formOptionStopLossPct" min="1" max="99" step="1" placeholder="e.g. 50"
                                        class="w-full px-3 py-2 bg-slate-800/80 border border-rose-500/30 rounded-lg text-white text-sm focus:outline-none focus:border-rose-500/60 placeholder-slate-500">
                                    <span class="absolute right-3 top-2.5 text-slate-500 text-xs">%</span>
                                </div>
                                <p class="text-xs text-slate-500 mt-1">Exit if contract loses this % (e.g. 50 = -50%)</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Take Profit % <span class="text-emerald-400">▲</span></label>
                                <div class="relative">
                                    <input type="number" wire:model="formOptionTpPct" min="1" max="500" step="1" placeholder="e.g. 100"
                                        class="w-full px-3 py-2 bg-slate-800/80 border border-emerald-500/30 rounded-lg text-white text-sm focus:outline-none focus:border-emerald-500/60 placeholder-slate-500">
                                    <span class="absolute right-3 top-2.5 text-slate-500 text-xs">%</span>
                                </div>
                                <p class="text-xs text-slate-500 mt-1">Exit if contract gains this % (e.g. 100 = +100%)</p>
                            </div>
                        </div>
                        <div class="mt-3 px-3 py-2 rounded-lg bg-slate-800/30 border border-slate-700/30 text-xs text-slate-400">
                            <strong class="text-slate-300">Note:</strong> Index SL/TP from strategy params also apply — whichever triggers first closes the position.
                        </div>
                    </div>

                    {{-- Order type --}}
                    <div>
                        <h4 class="text-xs font-bold text-violet-400/80 uppercase tracking-widest mb-3">Order Execution</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Order Type</label>
                                <select wire:model="formOptionOrderType"
                                    class="w-full px-3 py-2 bg-slate-800/80 border border-violet-500/30 rounded-lg text-white text-sm focus:outline-none focus:border-violet-500/60">
                                    <option value="mid">Mid price (bid+ask)/2</option>
                                    <option value="limit">Limit (mid + offset)</option>
                                    <option value="market">Market</option>
                                </select>
                            </div>
                            @if($formOptionOrderType !== 'market')
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Limit Offset ($)</label>
                                <input type="number" wire:model="formOptionLimitOffset" min="0" max="1" step="0.01"
                                    class="w-full px-3 py-2 bg-slate-800/80 border border-violet-500/30 rounded-lg text-white text-sm focus:outline-none focus:border-violet-500/60">
                                <p class="text-xs text-slate-500 mt-1">Added to mid price on buy</p>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @endif

        </div>

        {{-- RIGHT: Strategy params --}}
        <div class="space-y-4">
            <div class="rounded-xl bg-slate-900/50 border border-slate-700/50 overflow-hidden sticky top-20">
                <div class="px-5 py-4 bg-slate-800/30 border-b border-slate-700/50">
                    <h3 class="font-semibold text-white">Strategy Parameters</h3>
                    <p class="text-xs text-slate-400 mt-0.5">{{ $strategyOptions[$formStrategyKey] ?? $formStrategyKey }}</p>
                </div>
                <div class="p-4 space-y-5 max-h-[70vh] overflow-y-auto">
                    @foreach($schemaGroups as $groupName => $fields)
                    <div>
                        <h4 class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-3 border-b border-slate-800/80 pb-1">{{ $groupName }}</h4>
                        <div class="space-y-3">
                            @foreach($fields as $field)
                            <div>
                                <label class="block text-xs text-slate-400 mb-1">{{ $field['label'] }}</label>
                                @if($field['type'] === 'select')
                                <select wire:model="formParams.{{ $field['key'] }}"
                                    class="w-full px-2 py-1.5 bg-slate-800/80 border border-slate-700/50 rounded-lg text-white text-xs focus:outline-none focus:border-blue-500/60">
                                    @foreach($field['options'] as $val => $lbl)
                                    <option value="{{ $val }}">{{ $lbl }}</option>
                                    @endforeach
                                </select>
                                @elseif($field['type'] === 'time')
                                <input type="time" wire:model="formParams.{{ $field['key'] }}"
                                    class="w-full px-2 py-1.5 bg-slate-800/80 border border-slate-700/50 rounded-lg text-white text-xs focus:outline-none focus:border-blue-500/60">
                                @else
                                <input type="number"
                                    wire:model="formParams.{{ $field['key'] }}"
                                    step="{{ $field['step'] ?? 1 }}"
                                    min="{{ $field['min'] ?? '' }}"
                                    max="{{ $field['max'] ?? '' }}"
                                    placeholder="{{ $field['default'] ?? '' }}"
                                    class="w-full px-2 py-1.5 bg-slate-800/80 border border-slate-700/50 rounded-lg text-white text-xs focus:outline-none focus:border-blue-500/60 placeholder-slate-600">
                                @endif
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>
                <div class="p-4 border-t border-slate-700/50 space-y-2">
                    @if($view === 'create')
                    <button wire:click="createBot"
                        class="w-full py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-semibold rounded-xl transition-colors text-sm">
                        Create Bot
                    </button>
                    @else
                    <button wire:click="updateBot"
                        class="w-full py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-semibold rounded-xl transition-colors text-sm">
                        Save Changes
                    </button>
                    @endif
                    <button wire:click="showList"
                        class="w-full py-2 bg-slate-800/80 hover:bg-slate-700/80 text-slate-400 rounded-xl transition-colors text-sm">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    @endif {{-- end create/edit --}}

    {{-- ===================== DETAIL VIEW ===================== --}}
    @if($view === 'detail' && $selectedBot)
    @php
        $bot = $selectedBot;
        $pnlPositive = $bot->total_pnl >= 0;
        $winRate = $bot->total_trades > 0 ? round(($bot->winning_trades / $bot->total_trades) * 100, 1) : 0;
        $budgetUsedPct = $bot->paper_budget > 0 ? min(100, abs($bot->total_pnl) / $bot->paper_budget * 100) : 0;
        $balancePct = $bot->paper_budget > 0 ? ($bot->paper_balance / $bot->paper_budget) * 100 : 100;
        $statusColor = match($bot->status) {
            'running' => 'text-emerald-400',
            'paused'  => 'text-amber-400',
            'stopped' => 'text-rose-400',
            default   => 'text-slate-400',
        };
    @endphp

    {{-- Header --}}
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center space-x-3">
            <button wire:click="showList" class="p-2 rounded-lg hover:bg-slate-800/80 text-slate-400 hover:text-white transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <div>
                <div class="flex items-center space-x-3">
                    <h1 class="text-xl font-bold text-white">{{ $bot->name }}</h1>
                    @if($bot->paper_mode)
                    <span class="px-2 py-0.5 rounded text-xs font-bold bg-blue-500/10 text-blue-400 border border-blue-500/20">PAPER</span>
                    @else
                    <span class="px-2 py-0.5 rounded text-xs font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">LIVE</span>
                    @endif
                    <span class="text-sm font-semibold {{ $statusColor }}">● {{ ucfirst($bot->status) }}</span>
                </div>
                <p class="text-slate-400 text-sm mt-0.5">{{ $bot->symbol }} · {{ $bot->timeframe }} · {{ $strategyOptions[$bot->strategy_key] ?? $bot->strategy_key }}</p>
            </div>
        </div>
        <div class="flex items-center space-x-2">
            @if($bot->status === 'idle' || $bot->status === 'stopped')
            <button wire:click="startBot({{ $bot->id }})" wire:confirm="Start '{{ $bot->name }}'?"
                class="flex items-center space-x-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold rounded-xl transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/></svg>
                <span>Start</span>
            </button>
            @endif
            @if($bot->status === 'running')
            <button wire:click="pauseBot({{ $bot->id }})"
                class="flex items-center space-x-1.5 px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white text-sm font-semibold rounded-xl transition-colors">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M6 4h4v16H6zM14 4h4v16h-4z"/></svg>
                <span>Pause</span>
            </button>
            <button wire:click="stopBot({{ $bot->id }})" wire:confirm="Stop the bot?"
                class="flex items-center space-x-1.5 px-4 py-2 bg-rose-600 hover:bg-rose-500 text-white text-sm font-semibold rounded-xl transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10h6v4H9z"/></svg>
                <span>Stop</span>
            </button>
            @endif
            @if($bot->status === 'paused')
            <button wire:click="resumeBot({{ $bot->id }})"
                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold rounded-xl transition-colors">Resume</button>
            <button wire:click="stopBot({{ $bot->id }})" wire:confirm="Stop the bot?"
                class="px-4 py-2 bg-rose-600 hover:bg-rose-500 text-white text-sm font-semibold rounded-xl transition-colors">Stop</button>
            @endif
            <button wire:click="showEdit({{ $bot->id }})"
                class="px-4 py-2 bg-slate-700/80 hover:bg-slate-700 text-slate-300 text-sm font-semibold rounded-xl transition-colors">Edit</button>
            @if($bot->paper_mode)
            <button wire:click="resetPaperBalance({{ $bot->id }})" wire:confirm="Reset paper balance? All trades will be deleted."
                class="px-4 py-2 bg-slate-700/80 hover:bg-slate-700 text-slate-400 text-sm rounded-xl transition-colors">Reset</button>
            @endif
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4">
        @php
            $detailCards = [
                ['label' => $bot->paper_mode ? 'Paper Balance' : 'Account Balance',
                 'value' => '$' . number_format($bot->paper_mode ? $bot->paper_balance : 0, 2),
                 'sub'   => 'Budget: $' . number_format($bot->paper_budget, 0),
                 'color' => $balancePct >= 100 ? 'emerald' : ($balancePct >= 80 ? 'blue' : 'amber')],
                ['label' => 'Total P&L',
                 'value' => ($pnlPositive ? '+$' : '-$') . number_format(abs($bot->total_pnl), 2),
                 'sub'   => ($pnlPositive ? '+' : '') . number_format($bot->total_pnl_pct, 2) . '%',
                 'color' => $pnlPositive ? 'emerald' : 'rose'],
                ['label' => 'Total Trades', 'value' => $bot->total_trades, 'sub' => $bot->open_trades_count . ' open', 'color' => 'blue'],
                ['label' => 'Win Rate', 'value' => $winRate . '%',
                 'sub'   => $bot->winning_trades . 'W / ' . $bot->losing_trades . 'L',
                 'color' => $winRate >= 50 ? 'emerald' : 'rose'],
                ['label' => 'Max Drawdown', 'value' => '$' . number_format($bot->max_drawdown, 2), 'sub' => 'From peak', 'color' => 'rose'],
                ['label' => 'Avg Trade',
                 'value' => $bot->total_trades > 0 ? ($pnlPositive ? '+$' : '-$') . number_format(abs($bot->total_pnl / max(1, $bot->total_trades)), 2) : '$0',
                 'sub' => 'Per closed trade', 'color' => $pnlPositive ? 'emerald' : 'rose'],
            ];
            $dc = ['blue'=>['bg'=>'bg-blue-500/10','b'=>'border-blue-500/20','t'=>'text-blue-400'],'emerald'=>['bg'=>'bg-emerald-500/10','b'=>'border-emerald-500/20','t'=>'text-emerald-400'],'rose'=>['bg'=>'bg-rose-500/10','b'=>'border-rose-500/20','t'=>'text-rose-400'],'amber'=>['bg'=>'bg-amber-500/10','b'=>'border-amber-500/20','t'=>'text-amber-400'],'slate'=>['bg'=>'bg-slate-800/50','b'=>'border-slate-700/50','t'=>'text-slate-200']];
        @endphp
        @foreach($detailCards as $card)
        @php $c = $dc[$card['color']] ?? $dc['slate']; @endphp
        <div class="rounded-xl border {{ $c['bg'] }} {{ $c['b'] }} p-4">
            <div class="text-xs text-slate-400 mb-1">{{ $card['label'] }}</div>
            <div class="text-lg font-bold {{ $c['t'] }}">{{ $card['value'] }}</div>
            <div class="text-xs text-slate-500 mt-0.5">{{ $card['sub'] }}</div>
        </div>
        @endforeach
    </div>

    {{-- Balance bar --}}
    @if($bot->paper_mode)
    <div class="rounded-xl bg-slate-900/50 border border-slate-700/50 p-4">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Paper Balance Progress</span>
            <span class="text-xs text-slate-400">${{ number_format($bot->paper_balance, 2) }} / ${{ number_format($bot->paper_budget, 2) }}</span>
        </div>
        <div class="h-3 rounded-full bg-slate-800 overflow-hidden">
            <div class="h-full rounded-full transition-all duration-500 {{ $balancePct >= 100 ? 'bg-emerald-500' : ($balancePct >= 80 ? 'bg-blue-500' : 'bg-amber-500') }}"
                style="width: {{ min(100, max(0, $balancePct)) }}%"></div>
        </div>
    </div>
    @endif

    {{-- Tabs --}}
    <div class="flex items-center space-x-1 border-b border-slate-700/50">
        @foreach([['key'=>'trades','label'=>'Trades ('.count($botTrades).')'],['key'=>'config','label'=>'Configuration'],['key'=>'paper_sim','label'=>'Paper Simulator']] as $tab)
        <button wire:click="setDetailTab('{{ $tab['key'] }}')"
            class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $detailTab === $tab['key'] ? 'border-blue-500 text-blue-400' : 'border-transparent text-slate-400 hover:text-white' }}">
            {{ $tab['label'] }}
        </button>
        @endforeach
    </div>

    {{-- TAB: Trades --}}
    @if($detailTab === 'trades')

    {{-- ── OPEN POSITIONS LIVE MONITOR ─────────────────────────────────── --}}
    @php $openTrades = $botTrades->where('status', 'open'); @endphp
    @if($openTrades->isNotEmpty())
    <div class="space-y-3 mb-6">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-bold text-white flex items-center space-x-2">
                <span class="inline-block w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span>Open Positions</span>
                <span class="text-slate-500 font-normal">(refreshes every 5s)</span>
            </h3>
            <span class="text-xs text-slate-500">{{ now()->format('H:i:s') }} ET</span>
        </div>

        @foreach($openTrades as $trade)
        @php
            $isLong      = in_array($trade->direction, ['CALL','LONG']);
            $sym         = strtoupper($trade->symbol);
            $livePrice   = $livePrices[$sym] ?? null;
            $hasLive     = $livePrice !== null && $livePrice > 0;

            // Options contract live data
            $isOptions     = !empty($trade->option_contract_symbol);
            $optQuote      = $isOptions ? ($liveOptionQuotes[$trade->option_contract_symbol] ?? null) : null;
            $optMark       = $optQuote ? (float) $optQuote['mark'] : null;
            $optBid        = $optQuote ? (float) $optQuote['bid']  : null;
            $optAsk        = $optQuote ? (float) $optQuote['ask']  : null;
            $optDelta      = $optQuote ? (float) $optQuote['delta'] : ($trade->option_delta ?? null);
            $optTheta      = $optQuote ? (float) $optQuote['theta'] : ($trade->option_theta ?? null);
            $optGamma      = $optQuote ? (float) $optQuote['gamma'] : ($trade->option_gamma ?? null);
            $optIv         = $optQuote ? (float) $optQuote['iv']   : ($trade->option_iv   ?? null);
            $optContracts  = (int) ($trade->option_contracts ?? 1);
            $optEntryMark  = (float) ($trade->option_entry_price ?? 0);

            // P&L: for options use contract value; for equity use index price
            if ($isOptions && $optMark !== null && $optEntryMark > 0) {
                $unrealPnl    = ($optMark - $optEntryMark) * $optContracts * 100;
                $unrealPnlPct = ($trade->entry_value > 0) ? ($unrealPnl / $trade->entry_value) * 100 : 0;
            } else {
                $unrealPnl    = $hasLive
                    ? ($isLong ? ($livePrice - $trade->entry_price) : ($trade->entry_price - $livePrice)) * $trade->quantity
                    : null;
                $unrealPnlPct = ($hasLive && $trade->entry_value > 0)
                    ? ($unrealPnl / $trade->entry_value) * 100
                    : null;
            }
            $pnlUp = $unrealPnl !== null && $unrealPnl >= 0;

            // Progress bar: position of current price between SL and TP1
            $sl  = $trade->stop_loss;
            $tp1 = $trade->take_profit_1;
            $barPct = null;
            if ($hasLive && $sl && $tp1) {
                $range = abs($tp1 - $sl);
                $barPct = $range > 0
                    ? (($isLong ? ($livePrice - $sl) : ($sl - $livePrice)) / $range) * 100
                    : 0;
                $barPct = max(0, min(100, $barPct));
            }

            // Duration
            $duration = $trade->entry_time ? now()->diff($trade->entry_time) : null;
            $durationStr = $duration
                ? ($duration->h > 0 ? $duration->h . 'h ' : '') . $duration->i . 'm ' . $duration->s . 's'
                : '—';

            // Distance % to SL and TP1
            $distSl  = ($hasLive && $sl)  ? abs(($livePrice - $sl)  / $livePrice * 100) : null;
            $distTp1 = ($hasLive && $tp1) ? abs(($livePrice - $tp1) / $livePrice * 100) : null;
        @endphp
        <div class="rounded-xl border {{ $pnlUp ? 'border-emerald-500/25 bg-emerald-500/5' : 'border-rose-500/25 bg-rose-500/5' }} overflow-hidden">
            {{-- Top row --}}
            <div class="px-5 py-3 flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center space-x-3">
                    <span class="px-2.5 py-1 rounded-lg text-xs font-bold {{ $isLong ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30' : 'bg-rose-500/15 text-rose-400 border border-rose-500/30' }}">
                        {{ $trade->direction }}
                    </span>
                    <div>
                        <span class="text-white font-bold">{{ $sym }}</span>
                        <span class="text-slate-400 text-xs ml-2">{{ $trade->quantity }} shares · entered {{ $trade->entry_time?->format('H:i:s') }}</span>
                    </div>
                    <span class="text-xs text-slate-500 font-mono">⏱ {{ $durationStr }}</span>
                </div>

                {{-- Live Price + Unrealized P&L --}}
                <div class="flex items-center space-x-5">
                    @if($hasLive)
                    <div class="text-right">
                        <div class="text-xs text-slate-400 mb-0.5">Live Price</div>
                        <div class="text-xl font-bold text-white font-mono">${{ number_format($livePrice, 2) }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs text-slate-400 mb-0.5">Unrealized P&L</div>
                        <div class="text-2xl font-bold font-mono {{ $pnlUp ? 'text-emerald-400' : 'text-rose-400' }}">
                            {{ $pnlUp ? '+' : '' }}${{ number_format($unrealPnl, 2) }}
                        </div>
                        <div class="text-xs font-semibold {{ $pnlUp ? 'text-emerald-500' : 'text-rose-500' }}">
                            {{ $pnlUp ? '+' : '' }}{{ number_format($unrealPnlPct, 2) }}%
                        </div>
                    </div>
                    @else
                    <div class="text-right">
                        <div class="text-xs text-slate-500">No live price</div>
                        <div class="text-sm text-slate-400">Market data unavailable</div>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Levels row --}}
            <div class="px-5 py-2 border-t border-slate-800/50 grid grid-cols-3 md:grid-cols-6 gap-3 text-center">
                <div>
                    <div class="text-xs text-slate-500 mb-0.5">Entry</div>
                    <div class="text-sm font-semibold text-slate-300 font-mono">${{ number_format($trade->entry_price, 2) }}</div>
                </div>
                <div>
                    <div class="text-xs text-rose-400 mb-0.5">Stop Loss</div>
                    <div class="text-sm font-semibold text-rose-400 font-mono">
                        {{ $sl ? '$' . number_format($sl, 2) : '—' }}
                    </div>
                    @if($distSl !== null)
                    <div class="text-xs text-slate-500">{{ number_format($distSl, 2) }}% away</div>
                    @endif
                </div>
                <div>
                    <div class="text-xs text-emerald-400 mb-0.5">TP 1</div>
                    <div class="text-sm font-semibold text-emerald-400 font-mono">
                        {{ $tp1 ? '$' . number_format($tp1, 2) : '—' }}
                    </div>
                    @if($distTp1 !== null)
                    <div class="text-xs text-slate-500">{{ number_format($distTp1, 2) }}% away</div>
                    @endif
                </div>
                <div>
                    <div class="text-xs text-emerald-500/70 mb-0.5">TP 2</div>
                    <div class="text-sm font-semibold text-emerald-500/70 font-mono">
                        {{ $trade->take_profit_2 ? '$' . number_format($trade->take_profit_2, 2) : '—' }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-emerald-600/60 mb-0.5">TP 3</div>
                    <div class="text-sm font-semibold text-emerald-600/60 font-mono">
                        {{ $trade->take_profit_3 ? '$' . number_format($trade->take_profit_3, 2) : '—' }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-slate-500 mb-0.5">Value</div>
                    <div class="text-sm font-semibold text-slate-300 font-mono">${{ number_format($trade->entry_value, 0) }}</div>
                </div>
            </div>

            {{-- ── OPTIONS CONTRACT LIVE DATA ──────────────────────────── --}}
            @if($isOptions)
            <div class="px-5 py-3 border-t border-slate-800/50 bg-slate-950/30">
                <div class="flex items-center justify-between mb-2.5">
                    <div class="flex items-center space-x-2">
                        <span class="text-xs font-bold text-violet-400 uppercase tracking-wider">Option Contract</span>
                        <span class="text-xs font-mono text-slate-300 bg-slate-800/80 px-2 py-0.5 rounded">{{ $trade->option_contract_symbol }}</span>
                        <span class="text-xs text-slate-500">{{ $optContracts }} contract{{ $optContracts > 1 ? 's' : '' }} · exp {{ $trade->option_expiry }}</span>
                    </div>
                    @if($optQuote)
                    <span class="text-xs text-emerald-400 flex items-center space-x-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse inline-block"></span>
                        <span>Live greeks</span>
                    </span>
                    @endif
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    {{-- Contract price --}}
                    <div class="rounded-lg bg-slate-800/50 p-3">
                        <div class="text-xs text-slate-500 mb-1">Contract Value (mark)</div>
                        <div class="text-lg font-bold font-mono {{ $pnlUp ? 'text-emerald-400' : 'text-rose-400' }}">
                            {{ $optMark !== null ? '$' . number_format($optMark, 2) : '—' }}
                        </div>
                        @if($optBid !== null && $optAsk !== null)
                        <div class="text-xs text-slate-500 mt-0.5">
                            Bid <span class="text-rose-400 font-mono">${{ number_format($optBid, 2) }}</span>
                            / Ask <span class="text-emerald-400 font-mono">${{ number_format($optAsk, 2) }}</span>
                        </div>
                        @endif
                        <div class="text-xs text-slate-400 mt-1">
                            Entry: <span class="font-mono">${{ number_format($optEntryMark, 2) }}</span>
                            @if($optMark !== null && $optEntryMark > 0)
                            @php $optChgPct = (($optMark - $optEntryMark) / $optEntryMark) * 100; @endphp
                            <span class="ml-1 font-semibold {{ $optChgPct >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                                {{ $optChgPct >= 0 ? '+' : '' }}{{ number_format($optChgPct, 1) }}%
                            </span>
                            @endif
                        </div>
                    </div>
                    {{-- P&L on contract --}}
                    <div class="rounded-lg bg-slate-800/50 p-3">
                        <div class="text-xs text-slate-500 mb-1">P&L ({{ $optContracts }} × 100)</div>
                        <div class="text-lg font-bold font-mono {{ $pnlUp ? 'text-emerald-400' : 'text-rose-400' }}">
                            @if($unrealPnl !== null)
                                {{ $pnlUp ? '+$' : '-$' }}{{ number_format(abs($unrealPnl), 2) }}
                            @else —
                            @endif
                        </div>
                        @if($unrealPnlPct !== null)
                        <div class="text-xs font-semibold {{ $pnlUp ? 'text-emerald-500' : 'text-rose-500' }}">
                            {{ $unrealPnlPct >= 0 ? '+' : '' }}{{ number_format($unrealPnlPct, 1) }}%
                        </div>
                        @endif
                        <div class="text-xs text-slate-500 mt-1">
                            Cost basis: ${{ number_format($trade->entry_value, 2) }}
                        </div>
                    </div>
                    {{-- Delta / Gamma --}}
                    <div class="rounded-lg bg-slate-800/50 p-3">
                        <div class="text-xs text-slate-500 mb-2">Greeks</div>
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-violet-300">Δ Delta</span>
                                <span class="text-sm font-bold text-white font-mono">{{ $optDelta !== null ? number_format(abs($optDelta), 2) : '—' }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-blue-300">Γ Gamma</span>
                                <span class="text-xs font-mono text-slate-300">{{ $optGamma !== null ? number_format($optGamma, 4) : '—' }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-rose-300">Θ Theta</span>
                                <span class="text-xs font-mono text-rose-400">{{ $optTheta !== null ? number_format($optTheta, 4) : '—' }}</span>
                            </div>
                        </div>
                    </div>
                    {{-- IV / Strike --}}
                    <div class="rounded-lg bg-slate-800/50 p-3">
                        <div class="text-xs text-slate-500 mb-2">Contract Details</div>
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-slate-400">Strike</span>
                                <span class="text-sm font-bold text-white font-mono">${{ $trade->option_strike ? number_format($trade->option_strike, 0) : '—' }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-slate-400">IV</span>
                                <span class="text-xs font-mono text-amber-300">{{ $optIv ? number_format($optIv * 100, 1) . '%' : '—' }}</span>
                            </div>
                            @if($hasLive && $trade->option_strike)
                            @php
                                $otmDist = $isLong
                                    ? (($livePrice - $trade->option_strike) / $livePrice) * 100
                                    : (($trade->option_strike - $livePrice) / $livePrice) * 100;
                            @endphp
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-slate-400">{{ $otmDist >= 0 ? 'ITM' : 'OTM' }}</span>
                                <span class="text-xs font-mono {{ $otmDist >= 0 ? 'text-emerald-400' : 'text-slate-400' }}">{{ number_format(abs($otmDist), 2) }}%</span>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
                {{-- Index context --}}
                @if($hasLive)
                <div class="mt-2.5 px-3 py-2 rounded-lg bg-slate-800/30 border border-slate-700/30 flex items-center justify-between text-xs">
                    <span class="text-slate-500">Underlying index ({{ $sym }}):</span>
                    <span class="text-white font-bold font-mono">${{ number_format($livePrice, 2) }}</span>
                    <span class="text-slate-500">SL @ <span class="text-rose-400 font-mono">${{ $trade->stop_loss ? number_format($trade->stop_loss, 2) : '—' }}</span></span>
                    <span class="text-slate-500">TP1 @ <span class="text-emerald-400 font-mono">${{ $trade->take_profit_1 ? number_format($trade->take_profit_1, 2) : '—' }}</span></span>
                    <span class="text-slate-400 italic">Exit triggers on index price</span>
                </div>
                @endif
            </div>
            @endif

            {{-- Progress bar: SL ←→ TP1 --}}
            @if($barPct !== null)
            <div class="px-5 py-3 border-t border-slate-800/50">
                <div class="flex items-center justify-between text-xs text-slate-500 mb-1.5">
                    <span class="text-rose-400 font-semibold">SL ${{ number_format($sl, 2) }}</span>
                    <span class="text-slate-400">Price position between SL → TP1</span>
                    <span class="text-emerald-400 font-semibold">TP1 ${{ number_format($tp1, 2) }}</span>
                </div>
                <div class="relative h-4 rounded-full bg-slate-800 overflow-hidden">
                    {{-- Danger zone: 0-40% --}}
                    <div class="absolute inset-y-0 left-0 w-[40%] bg-rose-500/10 rounded-l-full"></div>
                    {{-- Safe zone: 60-100% --}}
                    <div class="absolute inset-y-0 right-0 w-[40%] bg-emerald-500/10 rounded-r-full"></div>
                    {{-- Fill bar --}}
                    <div class="h-full rounded-full transition-all duration-700 ease-out
                        {{ $barPct < 33 ? 'bg-rose-500' : ($barPct < 66 ? 'bg-amber-400' : 'bg-emerald-500') }}"
                        style="width: {{ $barPct }}%">
                    </div>
                    {{-- Current price marker --}}
                    <div class="absolute top-0 bottom-0 w-0.5 bg-white/80 shadow-sm transition-all duration-700"
                        style="left: calc({{ $barPct }}% - 1px)">
                    </div>
                </div>
                <div class="text-center text-xs text-slate-400 mt-1">
                    {{ number_format($barPct, 1) }}% of the way to TP1
                </div>
            </div>
            @endif

            {{-- Action buttons --}}
            @if($bot->paper_mode)
            <div class="px-5 py-2.5 border-t border-slate-800/50 bg-slate-900/40 flex items-center space-x-2">
                <span class="text-xs text-slate-500 mr-2">Manual close:</span>
                <button wire:click="closePaperTrade({{ $trade->id }}, {{ $tp1 ?? $trade->entry_price }}, 'tp1')"
                    class="px-3 py-1.5 text-xs font-semibold bg-emerald-500/10 hover:bg-emerald-500/25 text-emerald-400 border border-emerald-500/20 rounded-lg transition-colors">
                    Close at TP1
                </button>
                <button wire:click="closePaperTrade({{ $trade->id }}, {{ $sl ?? $trade->entry_price }}, 'stop')"
                    class="px-3 py-1.5 text-xs font-semibold bg-rose-500/10 hover:bg-rose-500/25 text-rose-400 border border-rose-500/20 rounded-lg transition-colors">
                    Close at SL
                </button>
                @if($hasLive)
                <button wire:click="closePaperTrade({{ $trade->id }}, {{ $livePrice }}, 'market')"
                    class="px-3 py-1.5 text-xs font-semibold bg-slate-700/50 hover:bg-slate-700 text-slate-300 border border-slate-600/50 rounded-lg transition-colors">
                    Close @ Market (${{ number_format($livePrice, 2) }})
                </button>
                @endif
                <button wire:click="closePaperTrade({{ $trade->id }}, {{ $trade->entry_price }}, 'breakeven')"
                    class="px-3 py-1.5 text-xs bg-slate-800/80 hover:bg-slate-700 text-slate-400 rounded-lg transition-colors">
                    Breakeven
                </button>
            </div>
            @endif
        </div>
        @endforeach
    </div>
    @endif

    {{-- ── FILTER BAR ───────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between mb-3">
        <div class="flex items-center space-x-2">
            @foreach(['all'=>'All','open'=>'Open','closed'=>'Closed'] as $f => $l)
            <button wire:click="setTradesFilter('{{ $f }}')"
                class="px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors {{ $tradesFilter === $f ? 'bg-blue-500/20 border-blue-500/40 text-blue-400' : 'bg-slate-800/50 border-slate-700/50 text-slate-400 hover:text-white' }}">
                {{ $l }}
            </button>
            @endforeach
        </div>
        <span class="text-xs text-slate-500">{{ $botTrades->count() }} trades</span>
    </div>

    {{-- ── HISTORY TABLE ────────────────────────────────────────────────── --}}
    @if($botTrades->isEmpty())
    <div class="flex flex-col items-center justify-center py-16">
        <svg class="w-12 h-12 text-slate-700 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        <p class="text-slate-400 text-sm">No trades yet. Start the bot to begin trading.</p>
    </div>
    @else
    <div class="rounded-xl border border-slate-700/50 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-800/50 border-b border-slate-700/50">
                        <th class="text-left px-4 py-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Dir</th>
                        <th class="text-right px-4 py-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Entry</th>
                        <th class="text-right px-4 py-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Exit / Live</th>
                        <th class="text-right px-4 py-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Qty</th>
                        <th class="text-right px-4 py-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">SL</th>
                        <th class="text-right px-4 py-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">TP1</th>
                        <th class="text-right px-4 py-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">P&L</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Status</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Time</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/50">
                    @foreach($botTrades as $trade)
                    @php
                        $isCall    = in_array($trade->direction, ['CALL','LONG']);
                        $sym       = strtoupper($trade->symbol);
                        $liveP     = $livePrices[$sym] ?? null;
                        $hasLiveP  = $liveP !== null && $liveP > 0 && $trade->status === 'open';
                        $displayPnl = $trade->status === 'open' && $hasLiveP
                            ? ($isCall ? ($liveP - $trade->entry_price) : ($trade->entry_price - $liveP)) * $trade->quantity
                            : $trade->pnl;
                        $pnlPos = ($displayPnl ?? 0) >= 0;
                        $tStatus = match($trade->status) {
                            'open'   => 'bg-blue-500/10 text-blue-400',
                            'closed' => ($pnlPos ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400'),
                            default  => 'bg-slate-700/50 text-slate-400',
                        };
                    @endphp
                    <tr class="hover:bg-slate-800/30 transition-colors {{ $trade->status === 'open' ? 'border-l-2 border-l-blue-500/30' : '' }}">
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $isCall ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400' }}">
                                {{ $trade->direction }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right text-slate-200 font-mono text-xs">${{ number_format($trade->entry_price, 2) }}</td>
                        <td class="px-4 py-3 text-right font-mono text-xs">
                            @if($trade->status === 'open' && $hasLiveP)
                                <span class="text-white font-bold">${{ number_format($liveP, 2) }}</span>
                                <span class="text-slate-500 text-xs ml-1">live</span>
                            @elseif($trade->exit_price)
                                <span class="text-slate-300">${{ number_format($trade->exit_price, 2) }}</span>
                            @else
                                <span class="text-slate-600">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right text-slate-300 text-xs">{{ $trade->quantity }}</td>
                        <td class="px-4 py-3 text-right text-rose-400 font-mono text-xs">
                            {{ $trade->stop_loss ? '$' . number_format($trade->stop_loss, 2) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right text-emerald-400 font-mono text-xs">
                            {{ $trade->take_profit_1 ? '$' . number_format($trade->take_profit_1, 2) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right font-semibold {{ $pnlPos ? 'text-emerald-400' : 'text-rose-400' }}">
                            @if($displayPnl !== null)
                                {{ $pnlPos ? '+' : '' }}${{ number_format($displayPnl, 2) }}
                                @if($trade->status === 'open' && $hasLiveP)
                                <span class="text-xs opacity-60 ml-0.5">*</span>
                                @endif
                            @else
                                <span class="text-slate-600">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded text-xs font-medium {{ $tStatus }}">
                                {{ ucfirst($trade->status) }}
                                @if($trade->exit_reason) · {{ $trade->exit_reason }} @endif
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-400 font-mono">
                            {{ $trade->entry_time?->format('M d H:i') }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-4 py-2 border-t border-slate-800/50 bg-slate-900/30">
            <span class="text-xs text-slate-600">* unrealized P&L based on live price</span>
        </div>
    </div>
    @endif

    @endif

    {{-- TAB: Config --}}
    @if($detailTab === 'config')
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="rounded-xl bg-slate-900/50 border border-slate-700/50 overflow-hidden">
            <div class="px-5 py-4 bg-slate-800/30 border-b border-slate-700/50">
                <h3 class="font-semibold text-white">Bot Configuration</h3>
            </div>
            <div class="p-5 space-y-2">
                @php
                    $cfgFields = [
                        'Symbol' => $bot->symbol,
                        'Timeframe' => $bot->timeframe,
                        'Strategy' => $strategyOptions[$bot->strategy_key] ?? $bot->strategy_key,
                        'Mode' => $bot->paper_mode ? 'Paper Trading' : 'Live Trading',
                        'Budget' => '$' . number_format($bot->paper_budget, 2),
                        'Position Size Type' => ucwords(str_replace('_', ' ', $bot->position_size_type)),
                        'Position Size' => $bot->position_size_type === 'fixed_dollars' ? '$' . number_format($bot->position_size_value, 2) : $bot->position_size_value,
                        'Max Concurrent Trades' => $bot->max_concurrent_trades,
                        'Max Daily Loss %' => $bot->max_daily_loss_pct ? $bot->max_daily_loss_pct . '%' : 'None',
                        'Status' => ucfirst($bot->status),
                        'Started At' => $bot->started_at?->format('M d Y H:i') ?? 'Never',
                        'Schwab Account' => $bot->schwab_account_hash ?: 'N/A (paper)',
                    ];
                @endphp
                @foreach($cfgFields as $label => $value)
                <div class="flex items-center justify-between py-1.5 border-b border-slate-800/50 last:border-0">
                    <span class="text-sm text-slate-400">{{ $label }}</span>
                    <span class="text-sm font-medium text-white">{{ $value }}</span>
                </div>
                @endforeach
            </div>
        </div>
        <div class="rounded-xl bg-slate-900/50 border border-slate-700/50 overflow-hidden">
            <div class="px-5 py-4 bg-slate-800/30 border-b border-slate-700/50">
                <h3 class="font-semibold text-white">Strategy Parameters</h3>
            </div>
            <div class="p-5 max-h-96 overflow-y-auto space-y-2">
                @foreach($bot->strategy_params ?? [] as $key => $val)
                <div class="flex items-center justify-between py-1.5 border-b border-slate-800/50 last:border-0">
                    <span class="text-xs text-slate-400 font-mono">{{ $key }}</span>
                    <span class="text-xs font-semibold text-white">{{ $val ?? 'null' }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- TAB: Paper Simulator --}}
    @if($detailTab === 'paper_sim')
    @if(!$bot->paper_mode)
    <div class="flex items-center space-x-3 p-4 rounded-xl bg-amber-500/10 border border-amber-500/20">
        <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span class="text-amber-300 text-sm">Paper Simulator is only available in paper mode.</span>
    </div>
    @else
    @php
        $simSym      = strtoupper($bot->symbol);
        $simLive     = $livePrices[$simSym] ?? null;
        $params      = $bot->strategy_params ?? [];
        $stopPct     = (float) ($params['stop_pct'] ?? $params['stop_buffer_pct'] ?? 1.0);
        $tp1Val      = (float) ($params['tp1_value'] ?? 1.0);
        $tp2Val      = (float) ($params['tp2_value'] ?? 2.0);
        $tp3Val      = (float) ($params['tp3_value'] ?? 3.0);
        $tpType      = $params['tp_type'] ?? 'risk_ratio';
        $isRR        = $tpType === 'risk_ratio';

        // Pre-calculate preview for CALL using live price
        $previewEntry = $simLive ?? 0;
        $previewSL    = $previewEntry > 0 ? $previewEntry * (1 - $stopPct / 100) : null;
        $previewRisk  = $previewEntry && $previewSL ? $previewEntry - $previewSL : 0;
        $previewTP1   = $previewEntry > 0 ? ($isRR ? $previewEntry + $previewRisk * $tp1Val : $previewEntry * (1 + $tp1Val / 100)) : null;
        $previewTP2   = $previewEntry > 0 ? ($isRR ? $previewEntry + $previewRisk * $tp2Val : $previewEntry * (1 + $tp2Val / 100)) : null;
        $previewTP3   = $previewEntry > 0 ? ($isRR ? $previewEntry + $previewRisk * $tp3Val : $previewEntry * (1 + $tp3Val / 100)) : null;
    @endphp

    <div class="max-w-2xl mx-auto space-y-4"
        x-data="{
            dir: 'CALL',
            price: '{{ $simLive ? number_format($simLive, 2, '.', '') : '' }}',
            get entry() { return parseFloat(this.price) || 0 },
            get stopPct() { return {{ $stopPct }} },
            get tp1Val() { return {{ $tp1Val }} },
            get tp2Val() { return {{ $tp2Val }} },
            get isRR() { return {{ $isRR ? 'true' : 'false' }} },
            get isLong() { return this.dir === 'CALL' },
            get sl() {
                if (!this.entry) return null;
                return this.isLong ? this.entry * (1 - this.stopPct/100) : this.entry * (1 + this.stopPct/100);
            },
            get risk() { return this.sl ? Math.abs(this.entry - this.sl) : 0 },
            get tp1() {
                if (!this.entry) return null;
                if (this.isRR) return this.isLong ? this.entry + this.risk * this.tp1Val : this.entry - this.risk * this.tp1Val;
                return this.isLong ? this.entry * (1 + this.tp1Val/100) : this.entry * (1 - this.tp1Val/100);
            },
            get tp2() {
                if (!this.entry) return null;
                if (this.isRR) return this.isLong ? this.entry + this.risk * this.tp2Val : this.entry - this.risk * this.tp2Val;
                return this.isLong ? this.entry * (1 + this.tp2Val/100) : this.entry * (1 - this.tp2Val/100);
            },
            get pnlAtTP1() { return this.tp1 ? (this.isLong ? (this.tp1 - this.entry) : (this.entry - this.tp1)) : 0 },
            get pnlAtSL()  { return this.sl  ? (this.isLong ? (this.sl  - this.entry) : (this.entry - this.sl))  : 0 },
            fmt(v) { return v ? '$' + parseFloat(v).toFixed(2) : '—' }
        }">

        {{-- Live Price Banner --}}
        @if($simLive)
        <div class="flex items-center justify-between px-5 py-3 rounded-xl bg-slate-800/50 border border-slate-700/40">
            <div class="flex items-center space-x-3">
                <span class="inline-block w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span class="text-sm font-semibold text-slate-300">{{ $simSym }} Live Price</span>
            </div>
            <div class="text-2xl font-bold text-white font-mono">${{ number_format($simLive, 2) }}</div>
            <button @click="price = '{{ number_format($simLive, 2, '.', '') }}'"
                class="px-3 py-1.5 text-xs font-semibold bg-blue-500/10 hover:bg-blue-500/20 text-blue-400 border border-blue-500/20 rounded-lg transition-colors">
                Use Live Price
            </button>
        </div>
        @else
        <div class="px-4 py-3 rounded-xl bg-slate-800/30 border border-slate-700/30">
            <p class="text-xs text-slate-400">Live price not available — market may be closed or Schwab token expired. Enter price manually.</p>
        </div>
        @endif

        {{-- Options mode info banner --}}
        @if($bot->trade_type === 'options')
        <div class="flex items-start space-x-3 px-4 py-3 rounded-xl bg-violet-500/10 border border-violet-500/20">
            <span class="text-violet-400 text-lg mt-0.5">🎯</span>
            <div class="space-y-0.5">
                <p class="text-sm font-semibold text-violet-300">Options Bot — same logic as live execution</p>
                <p class="text-xs text-violet-400/80 leading-relaxed">
                    Enter the <strong class="text-white">index price</strong> ({{ $simSym }}) as entry reference.
                    The bot will call Schwab's option chain, find the contract with
                    <strong class="text-white">Δ ≈ {{ number_format($bot->option_delta_target ?? 0.40, 2) }}</strong>
                    (±{{ number_format($bot->option_delta_tolerance ?? 0.05, 2) }}),
                    DTE {{ $bot->option_min_dte ?? 1 }}–{{ $bot->option_max_dte ?? 7 }} days,
                    and open <strong class="text-white">{{ $bot->option_contracts ?? 1 }} contract(s)</strong>.
                    SL/TP levels below are on the <em>index</em> — they trigger SELL_TO_CLOSE on the contract.
                </p>
                <div class="flex items-center space-x-4 mt-1 text-xs text-violet-400/60">
                    <span>Contract SL: {{ $bot->option_stop_loss_pct ? '-' . $bot->option_stop_loss_pct . '%' : 'disabled' }}</span>
                    <span>Contract TP: {{ $bot->option_take_profit_pct ? '+' . $bot->option_take_profit_pct . '%' : 'disabled' }}</span>
                    <span>Order: {{ strtoupper($bot->option_order_type ?? 'mid') }}</span>
                </div>
            </div>
        </div>
        @endif

        {{-- Simulator Form --}}
        <div class="rounded-xl bg-slate-900/50 border border-slate-700/50 overflow-hidden">
            <div class="px-5 py-4 bg-slate-800/30 border-b border-slate-700/50">
                <h3 class="font-semibold text-white">Simulate Manual Signal</h3>
                <p class="text-xs text-slate-400 mt-0.5">
                    @if($bot->trade_type === 'options')
                        Set the index entry price — the contract will be auto-selected from Schwab's option chain
                    @else
                        Enter the price you want to test — use the live price above for a realistic simulation
                    @endif
                </p>
            </div>
            <div class="p-5 space-y-4">

                {{-- Direction --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Direction</label>
                    <div class="grid grid-cols-2 gap-3">
                        <button @click="dir = 'CALL'" :class="dir === 'CALL' ? 'bg-emerald-500/20 border-emerald-500/50 text-emerald-400 ring-1 ring-emerald-500/30' : 'bg-slate-800/80 border-slate-700/50 text-slate-400 hover:text-white'"
                            class="py-3 rounded-xl border font-bold text-sm transition-all">
                            ▲ CALL (LONG)
                        </button>
                        <button @click="dir = 'PUT'" :class="dir === 'PUT' ? 'bg-rose-500/20 border-rose-500/50 text-rose-400 ring-1 ring-rose-500/30' : 'bg-slate-800/80 border-slate-700/50 text-slate-400 hover:text-white'"
                            class="py-3 rounded-xl border font-bold text-sm transition-all">
                            ▼ PUT (SHORT)
                        </button>
                    </div>
                </div>

                {{-- Entry Price --}}
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="text-xs font-semibold text-slate-400 uppercase tracking-wider">
                            @if($bot->trade_type === 'options')
                                Index Entry Price ($) <span class="text-violet-400 normal-case font-normal ml-1">— for SL/TP calculation</span>
                            @else
                                Entry Price ($)
                            @endif
                        </label>
                        @if($simLive)
                        <span class="text-xs text-slate-500">Live: <strong class="text-white">${{ number_format($simLive, 2) }}</strong></span>
                        @endif
                    </div>
                    <input x-model="price" type="number" step="0.01"
                        :placeholder="'{{ $simLive ? number_format($simLive, 2, '.', '') : 'e.g. 590.50' }}'"
                        class="w-full px-3 py-2.5 bg-slate-800/80 border {{ $bot->trade_type === 'options' ? 'border-violet-500/40 focus:border-violet-500/60' : 'border-slate-700/50 focus:border-blue-500/60' }} rounded-lg text-white text-sm focus:outline-none font-mono">
                </div>

                {{-- Real-time preview of SL / TP / P&L --}}
                <div x-show="entry > 0" class="rounded-xl overflow-hidden border border-slate-700/40">
                    <div class="px-4 py-2 bg-slate-800/60 text-xs font-bold text-slate-400 uppercase tracking-wider border-b border-slate-700/40">
                        @if($bot->trade_type === 'options')
                            Index SL / TP Preview <span class="text-violet-400 ml-1 normal-case font-normal">(exit triggers on index)</span>
                        @else
                            Trade Preview (auto-calculated)
                        @endif
                    </div>
                    <div class="grid grid-cols-2 divide-x divide-slate-800/60">
                        {{-- Left: levels --}}
                        <div class="p-4 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-slate-500">Index Entry</span>
                                <span class="text-sm font-bold text-white font-mono" x-text="fmt(entry)"></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-rose-400">Stop Loss</span>
                                <span class="text-sm font-bold text-rose-400 font-mono" x-text="fmt(sl)"></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-emerald-400">TP 1</span>
                                <span class="text-sm font-bold text-emerald-400 font-mono" x-text="fmt(tp1)"></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-emerald-500/70">TP 2</span>
                                <span class="text-sm font-semibold text-emerald-500/70 font-mono" x-text="fmt(tp2)"></span>
                            </div>
                        </div>
                        {{-- Right: P&L outcomes (for equity) or contract exit info (for options) --}}
                        <div class="p-4 space-y-3">
                            @if($bot->trade_type === 'options')
                            <div class="text-xs font-bold text-violet-400/70 uppercase tracking-wider mb-1">Contract exits when...</div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-emerald-400">Index hits TP1</span>
                                <span class="text-xs font-bold text-emerald-400">SELL_TO_CLOSE</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-rose-400">Index hits SL</span>
                                <span class="text-xs font-bold text-rose-400">SELL_TO_CLOSE</span>
                            </div>
                            @if($bot->option_stop_loss_pct)
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-rose-300">Contract -{{ $bot->option_stop_loss_pct }}%</span>
                                <span class="text-xs font-bold text-rose-300">SELL_TO_CLOSE</span>
                            </div>
                            @endif
                            @if($bot->option_take_profit_pct)
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-emerald-300">Contract +{{ $bot->option_take_profit_pct }}%</span>
                                <span class="text-xs font-bold text-emerald-300">SELL_TO_CLOSE</span>
                            </div>
                            @endif
                            @else
                            <div class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">If hit...</div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-emerald-400">TP1 hit</span>
                                <span class="text-sm font-bold text-emerald-400 font-mono"
                                    x-text="'+$' + (pnlAtTP1).toFixed(2)"></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-rose-400">SL hit</span>
                                <span class="text-sm font-bold text-rose-400 font-mono"
                                    x-text="'$' + (pnlAtSL).toFixed(2)"></span>
                            </div>
                            <div class="flex items-center justify-between pt-2 border-t border-slate-800/60">
                                <span class="text-xs text-slate-400">Risk / Reward</span>
                                <span class="text-sm font-bold text-white"
                                    x-text="pnlAtSL < 0 ? '1 : ' + (pnlAtTP1 / Math.abs(pnlAtSL)).toFixed(2) : '—'"></span>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Open button --}}
                <button @click="if(entry > 0) { $wire.simulatePaperSignal({{ $bot->id }}, dir, entry); price = ''; }"
                    class="w-full py-3 font-bold text-sm rounded-xl transition-all"
                    :class="dir === 'CALL' ? 'bg-emerald-600 hover:bg-emerald-500 text-white' : 'bg-rose-600 hover:bg-rose-500 text-white'"
                    :disabled="!entry">
                    @if($bot->trade_type === 'options')
                        <span x-text="'Open ' + dir + ' — auto-select Δ≈{{ number_format($bot->option_delta_target ?? 0.40, 2) }} contract @ index ' + fmt(entry)"></span>
                    @else
                        <span x-text="'Open ' + dir + ' Paper Trade @ ' + fmt(entry)"></span>
                    @endif
                </button>

            </div>
        </div>

        <div class="px-4 py-3 rounded-xl bg-slate-800/30 border border-slate-700/30 text-xs text-slate-400 leading-relaxed">
            <strong class="text-slate-300">Tip:</strong> Always use the live market price as entry. The preview shows exact SL/TP levels and the P&L you'd make or lose if each level is hit.
            Current paper balance: <strong class="text-blue-400">${{ number_format($bot->paper_balance, 2) }}</strong>
        </div>
    </div>
    @endif
    @endif

    @endif {{-- end detail --}}

</div>

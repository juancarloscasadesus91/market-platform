<div class="p-6 space-y-6" wire:poll.3s="refreshActiveSession">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-white">Alpaca Strategy Lab</h1>
            <p class="text-sm text-slate-400">Ordenes Alpaca paper/live con sincronizacion de fills, SL/TP y logs.</p>
        </div>
        <button wire:click="createNew" class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-emerald-950 hover:bg-emerald-400">
            New Strategy
        </button>
    </div>

    @if(session('alpaca_success'))
        <div class="rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">{{ session('alpaca_success') }}</div>
    @endif

    @if(session('alpaca_error'))
        <div class="rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">{{ session('alpaca_error') }}</div>
    @endif

    @if($showForm)
        <div class="fixed inset-0 z-[80] flex items-start justify-center overflow-y-auto bg-black/70 px-4 py-6 sm:py-10">
            <div class="min-h-full w-full max-w-6xl">
                <form wire:submit.prevent="saveSession" class="rounded-lg border border-slate-700 bg-slate-900 shadow-2xl">
                    <div class="sticky top-0 z-10 flex items-center justify-between gap-3 rounded-t-lg border-b border-slate-700 bg-slate-900 px-5 py-4">
                        <div>
                            <h2 class="text-sm font-semibold text-white">{{ $editingSessionId ? 'Edit Alpaca Strategy' : 'Create Alpaca Strategy' }}</h2>
                            <p class="text-xs text-slate-500">{{ $editingSessionId ? 'Changes apply to the next scheduler tick. Open trades keep their current entry, SL and TP.' : 'Create a new Alpaca strategy session.' }}</p>
                        </div>
                        <button type="button" wire:click="cancelEdit" class="rounded-md bg-slate-800 px-3 py-1.5 text-xs font-semibold text-slate-200 hover:bg-slate-700">
                            Close
                        </button>
                    </div>

                    <div class="max-h-[calc(100vh-10rem)] overflow-y-auto p-5">
                        <div class="grid gap-4 lg:grid-cols-7">
                            <label class="lg:col-span-2">
                                <span class="text-xs text-slate-400">Name</span>
                                <input wire:model="name" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white" placeholder="SPY EMA paper">
                            </label>
                            <label>
                                <span class="text-xs text-slate-400">Symbol</span>
                                <input wire:model="symbol" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white">
                            </label>
                            <label>
                                <span class="text-xs text-slate-400">Timeframe</span>
                                <select wire:model="timeframe" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white">
                                    @foreach(['1m','5m','15m','30m','1h','1d'] as $tf)
                                        <option value="{{ $tf }}">{{ $tf }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="lg:col-span-2">
                                <span class="text-xs text-slate-400">Strategy</span>
                                <select wire:model.live="strategyKey" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white">
                                    @foreach($this->strategyOptions as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>
                                <span class="text-xs text-slate-400">Mode</span>
                                <select wire:model="mode" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white">
                                    <option value="paper">Paper</option>
                                    <option value="live">Live</option>
                                </select>
                            </label>
                        </div>

                        <div class="mt-4 grid gap-4 lg:grid-cols-5">
                            <label>
                                <span class="text-xs text-slate-400">Size Type</span>
                                <select wire:model="positionSizeType" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white">
                                    <option value="fixed_qty">Shares Qty</option>
                                    <option value="fixed_notional">Dollar Notional</option>
                                </select>
                            </label>
                            <label>
                                <span class="text-xs text-slate-400">Size</span>
                                <input wire:model="positionSizeValue" type="number" step="0.0001" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white">
                            </label>
                            <label>
                                <span class="text-xs text-slate-400">Max Trades</span>
                                <input wire:model="maxConcurrentTrades" type="number" min="1" max="5" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white">
                            </label>
                            <label>
                                <span class="text-xs text-slate-400">SL %</span>
                                <input wire:model="stopLossPct" type="number" step="0.01" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white">
                            </label>
                            <label>
                                <span class="text-xs text-slate-400">TP %</span>
                                <input wire:model="takeProfitPct" type="number" step="0.01" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white">
                            </label>
                        </div>

                        <div class="mt-5 space-y-4">
                            @foreach($this->schemaByGroup as $group => $fields)
                                <div>
                                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $group }}</h3>
                                    <div class="grid gap-3 md:grid-cols-3 xl:grid-cols-4">
                                        @foreach($fields as $field)
                                            <label>
                                                <span class="text-xs text-slate-400">{{ $field['label'] ?? $field['key'] }}</span>
                                                @if(($field['type'] ?? '') === 'select')
                                                    <select wire:model="params.{{ $field['key'] }}" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white">
                                                        @foreach(($field['options'] ?? []) as $value => $label)
                                                            <option value="{{ $value }}">{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <input
                                                        wire:model="params.{{ $field['key'] }}"
                                                        type="{{ ($field['type'] ?? 'string') === 'time' ? 'time' : 'number' }}"
                                                        step="{{ $field['step'] ?? 'any' }}"
                                                        class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white"
                                                    >
                                                @endif
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if($errors->any())
                            <div class="mt-4 rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-300">
                                {{ $errors->first() }}
                            </div>
                        @endif
                    </div>

                    <div class="flex justify-end gap-3 border-t border-slate-700 bg-slate-900 px-5 py-4">
                        <button type="button" wire:click="cancelEdit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-slate-200 hover:bg-slate-700">
                            Cancel
                        </button>
                        <button class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-emerald-950 hover:bg-emerald-400">
                            {{ $editingSessionId ? 'Save Changes' : 'Create Lab Session' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[420px_minmax(0,1fr)]">
        <div class="rounded-lg border border-slate-700 bg-slate-900/70">
            <div class="border-b border-slate-700 px-4 py-3 text-sm font-semibold text-slate-200">Sessions</div>
            <div class="divide-y divide-slate-800">
                @forelse($this->sessions as $session)
                    <div wire:key="session-{{ $session->id }}" class="p-4 {{ $selectedSession === $session->id ? 'bg-slate-800/60' : '' }}">
                        <button wire:click="selectSession({{ $session->id }})" class="w-full text-left">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="truncate font-semibold text-white">{{ $session->name ?: $session->symbol . ' ' . $session->timeframe }}</div>
                                    <div class="text-xs text-slate-400">{{ $session->strategyLabel() }} · {{ strtoupper($session->mode) }} · {{ $session->symbol }} · {{ $session->timeframe }}</div>
                                </div>
                                <span class="rounded-full px-2 py-1 text-xs {{ $session->status === 'running' ? 'bg-emerald-500/15 text-emerald-300' : 'bg-slate-700 text-slate-300' }}">
                                    {{ $session->status }}
                                </span>
                            </div>
                        </button>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button wire:click="editSession({{ $session->id }})" class="rounded-md bg-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-200 hover:bg-slate-600">Edit</button>
                            <button wire:click="start({{ $session->id }})" class="rounded-md bg-emerald-500/15 px-3 py-1.5 text-xs font-semibold text-emerald-300 hover:bg-emerald-500/25">Start</button>
                            <button wire:click="runNow({{ $session->id }})" class="rounded-md bg-blue-500/15 px-3 py-1.5 text-xs font-semibold text-blue-300 hover:bg-blue-500/25">Run Now</button>
                            <button wire:click="pause({{ $session->id }})" class="rounded-md bg-amber-500/15 px-3 py-1.5 text-xs font-semibold text-amber-300 hover:bg-amber-500/25">Pause</button>
                            <button wire:click="stop({{ $session->id }})" class="rounded-md bg-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-200 hover:bg-slate-600">Stop</button>
                            <button wire:click="deleteSession({{ $session->id }})" wire:confirm="Delete this Alpaca lab session?" class="rounded-md bg-red-500/15 px-3 py-1.5 text-xs font-semibold text-red-300 hover:bg-red-500/25">Delete</button>
                        </div>
                    </div>
                @empty
                    <div class="p-4 text-sm text-slate-400">No Alpaca lab sessions yet.</div>
                @endforelse
            </div>
            <div class="border-t border-slate-700 p-4">{{ $this->sessions->links() }}</div>
        </div>

        <div class="space-y-6">
            @php
                $s = $this->selectedSessionModel;
                $selectedTrade = $this->selectedTradeModel;
            @endphp
            @if($s)
                <div class="grid gap-3 md:grid-cols-5">
                    <div class="rounded-lg border border-slate-700 bg-slate-900/70 p-4">
                        <div class="text-xs text-slate-500">Trades</div>
                        <div class="mt-1 text-xl font-semibold text-white">{{ $s->total_trades }}</div>
                    </div>
                    <div class="rounded-lg border border-slate-700 bg-slate-900/70 p-4">
                        <div class="text-xs text-slate-500">Winners</div>
                        <div class="mt-1 text-xl font-semibold text-emerald-300">{{ $s->winning_trades }}</div>
                    </div>
                    <div class="rounded-lg border border-slate-700 bg-slate-900/70 p-4">
                        <div class="text-xs text-slate-500">Losers</div>
                        <div class="mt-1 text-xl font-semibold text-red-300">{{ $s->losing_trades }}</div>
                    </div>
                    <div class="rounded-lg border border-slate-700 bg-slate-900/70 p-4">
                        <div class="text-xs text-slate-500">P&L</div>
                        <div class="mt-1 text-xl font-semibold {{ $s->total_pnl >= 0 ? 'text-emerald-300' : 'text-red-300' }}">${{ number_format($s->total_pnl, 2) }}</div>
                    </div>
                    <div class="rounded-lg border border-slate-700 bg-slate-900/70 p-4">
                        <div class="text-xs text-slate-500">Last Run</div>
                        <div class="mt-1 text-sm font-semibold text-white">{{ $s->last_run_at?->diffForHumans() ?? 'Never' }}</div>
                    </div>
                </div>

                @if($s->error_message)
                    <div class="rounded-lg border border-red-500/30 bg-red-500/10 p-4 text-sm text-red-300">{{ $s->error_message }}</div>
                @endif

                @php
                    $openTrades = $s->trades->whereIn('status', ['pending', 'open', 'closing']);
                    $openTradesClass = $openTrades->isNotEmpty() ? 'space-y-3' : 'hidden';
                @endphp
                    <div class="{{ $openTradesClass }}">
                        <div class="flex items-center justify-between">
                            <h3 class="flex items-center gap-2 text-sm font-bold text-white">
                                <span class="inline-block h-2 w-2 rounded-full bg-emerald-400"></span>
                                <span>Open Positions</span>
                                <span class="font-normal text-slate-500">(click para ver detalle)</span>
                            </h3>
                            <span class="text-xs text-slate-500">{{ now('America/New_York')->format('H:i:s') }} ET</span>
                        </div>

                        @foreach($openTrades as $trade)
                            @php
                                $isSelectedOpen = $selectedTrade?->id === $trade->id;
                                $tradeLogs = $isSelectedOpen ? $selectedTrade->logs : $trade->logs()->latest()->limit(5)->get();
                                $latestSnapshot = $tradeLogs->firstWhere('event', 'monitor_snapshot');
                                $contract = $trade->signal_data['selected_contract'] ?? null;
                                $isOption = is_array($contract) || ($trade->signal_data['trade_asset'] ?? null) === 'option';
                                $currentPrice = $latestSnapshot['context']['current_price'] ?? $trade->entry_price;
                                $displayPnl = $latestSnapshot['context']['unrealized_pnl'] ?? null;
                                $displayPnlPct = $latestSnapshot['context']['unrealized_pnl_pct'] ?? null;
                                $pnlUp = ($displayPnl ?? 0) >= 0;
                                $duration = $trade->entry_time ? now()->diff($trade->entry_time) : null;
                                $durationStr = $duration ? (($duration->h > 0 ? $duration->h . 'h ' : '') . $duration->i . 'm ' . $duration->s . 's') : '-';
                                $entryValue = ($trade->entry_price && $trade->quantity) ? $trade->entry_price * $trade->quantity : ($trade->notional ?? 0);
                                $liveLabel = $latestSnapshot ? 'Live Price' : 'No live snapshot';
                                $liveValue = $latestSnapshot ? '$'.number_format((float) $currentPrice, 2) : 'Waiting for next tick';
                                $liveValueClass = $latestSnapshot ? 'font-mono text-lg font-bold text-white' : 'text-sm text-slate-400';
                                $pnlPctText = $displayPnlPct !== null ? (((float) $displayPnlPct >= 0 ? '+' : '') . number_format((float) $displayPnlPct, 2) . '%') : '';
                                $contractSymbol = is_array($contract) ? ($contract['symbol'] ?? $trade->symbol) : $trade->symbol;
                                $contractExp = is_array($contract) ? ($contract['expiration_date'] ?? '-') : '-';
                                $contractStrike = is_array($contract) && isset($contract['strike_price']) ? '$'.number_format((float) $contract['strike_price'], 0) : '-';
                                $contractDelta = is_array($contract) && isset($contract['delta']) ? number_format(abs((float) $contract['delta']), 2) : '-';
                                $detailTitle = $isOption ? 'Contract Details' : 'Order Details';
                                $detailRowOneLabel = $isOption ? 'Strike' : 'Entry order';
                                $detailRowOneValue = $isOption ? $contractStrike : ($trade->entry_order_id ?? '-');
                                $detailRowTwoLabel = $isOption ? 'Delta' : 'Status';
                                $detailRowTwoValue = $isOption ? $contractDelta : $trade->status;
                                $snapshotText = $latestSnapshot ? ' · Snapshot: '.$latestSnapshot->created_at->timezone('America/New_York')->format('H:i:s').' ET' : '';
                                $expandedClass = $isSelectedOpen ? '' : 'hidden';
                                $manualCloseLabel = $trade->status === 'open' ? 'Close @ Market' : 'Closing...';
                                $manualCloseClass = $trade->status === 'open' ? 'border-red-500/30 bg-red-500/15 text-red-200 hover:bg-red-500/25' : 'border-slate-700 bg-slate-800/60 text-slate-500';
                                $manualCloseDisabled = $trade->status === 'open' ? '' : 'disabled';
                            @endphp

                            <div wire:key="open-trade-{{ $trade->id }}" class="overflow-hidden rounded-xl border {{ $pnlUp ? 'border-emerald-500/25 bg-emerald-500/5' : 'border-rose-500/25 bg-rose-500/5' }}">
                                <button type="button" wire:click="selectTrade({{ $trade->id }})" class="w-full px-5 py-3 text-left">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div class="flex items-center gap-3">
                                            <span class="rounded-lg border px-2.5 py-1 text-xs font-bold {{ $trade->side === 'buy' ? 'border-emerald-500/30 bg-emerald-500/15 text-emerald-400' : 'border-rose-500/30 bg-rose-500/15 text-rose-400' }}">
                                                {{ strtoupper($trade->direction ?: $trade->side) }}
                                            </span>
                                            <div>
                                                <span class="font-bold text-white">{{ $trade->symbol }}</span>
                                                <span class="ml-2 text-xs text-slate-400">{{ $trade->quantity ?? '-' }} qty · entered {{ $trade->entry_time?->timezone('America/New_York')->format('H:i:s') ?? '-' }}</span>
                                            </div>
                                            <span class="font-mono text-xs text-slate-500">{{ $durationStr }}</span>
                                        </div>

                                        <div class="text-right">
                                            <div class="text-xs {{ $latestSnapshot ? 'text-slate-400' : 'text-slate-500' }}">{{ $liveLabel }}</div>
                                            <div class="{{ $liveValueClass }}">{{ $liveValue }}</div>
                                        </div>
                                    </div>
                                </button>

                                <div class="grid grid-cols-3 gap-3 border-t border-slate-800/50 px-5 py-2 text-center md:grid-cols-5">
                                    <div>
                                        <div class="text-xs text-slate-500">Entry</div>
                                        <div class="font-mono text-sm font-semibold text-slate-300">${{ $trade->entry_price ? number_format($trade->entry_price, 2) : '-' }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-rose-400">Stop Loss</div>
                                        <div class="font-mono text-sm font-semibold text-rose-400">{{ $trade->stop_loss ? '$'.number_format($trade->stop_loss, 2) : '-' }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-emerald-400">TP</div>
                                        <div class="font-mono text-sm font-semibold text-emerald-400">{{ $trade->take_profit ? '$'.number_format($trade->take_profit, 2) : '-' }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-slate-500">Value</div>
                                        <div class="font-mono text-sm font-semibold text-slate-300">${{ number_format((float) $entryValue, 0) }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-slate-500">Open P&L</div>
                                        <div class="font-mono text-sm font-semibold {{ $pnlUp ? 'text-emerald-400' : 'text-rose-400' }}">
                                            {{ $displayPnl !== null ? (($pnlUp ? '+$' : '-$') . number_format(abs((float) $displayPnl), 2)) : '-' }}
                                        </div>
                                        <div class="text-xs {{ $pnlUp ? 'text-emerald-500' : 'text-rose-500' }}">{{ $pnlPctText }}</div>
                                    </div>
                                </div>

                                    <div class="{{ $expandedClass }} border-t border-slate-800/50 bg-slate-950/30 px-5 py-3">
                                        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="text-xs font-bold uppercase tracking-wider {{ $isOption ? 'text-violet-400' : 'text-blue-400' }}">{{ $isOption ? 'Option Contract' : 'Open Trade' }}</span>
                                                <span class="rounded bg-slate-800/80 px-2 py-0.5 font-mono text-xs text-slate-300">{{ $contractSymbol }}</span>
                                                <span class="text-xs text-slate-500">exp {{ $contractExp }}</span>
                                            </div>
                                            <button
                                                type="button"
                                                wire:click.stop="closeTrade({{ $trade->id }})"
                                                wire:confirm="Close this open trade at market?"
                                                wire:loading.attr="disabled"
                                                wire:target="closeTrade({{ $trade->id }})"
                                                {{ $manualCloseDisabled }}
                                                class="rounded-md border px-3 py-1.5 text-xs font-semibold {{ $manualCloseClass }}"
                                            >
                                                <span wire:loading.remove wire:target="closeTrade({{ $trade->id }})">{{ $manualCloseLabel }}</span>
                                                <span wire:loading wire:target="closeTrade({{ $trade->id }})">Sending...</span>
                                            </button>
                                        </div>

                                        <div class="grid gap-4 md:grid-cols-4">
                                            <div class="rounded-lg bg-slate-800/50 p-3">
                                                <div class="mb-1 text-xs text-slate-500">Current / Mark</div>
                                                <div class="font-mono text-lg font-bold {{ $pnlUp ? 'text-emerald-400' : 'text-rose-400' }}">{{ $latestSnapshot ? '$'.number_format((float) $currentPrice, 2) : '-' }}</div>
                                                <div class="mt-1 text-xs text-slate-400">Entry: <span class="font-mono">${{ $trade->entry_price ? number_format($trade->entry_price, 2) : '-' }}</span></div>
                                            </div>
                                            <div class="rounded-lg bg-slate-800/50 p-3">
                                                <div class="mb-1 text-xs text-slate-500">P&L</div>
                                                <div class="font-mono text-lg font-bold {{ $pnlUp ? 'text-emerald-400' : 'text-rose-400' }}">
                                                    {{ $displayPnl !== null ? (($pnlUp ? '+$' : '-$') . number_format(abs((float) $displayPnl), 2)) : '-' }}
                                                </div>
                                                <div class="mt-1 text-xs text-slate-500">Cost basis: ${{ number_format((float) $entryValue, 2) }}</div>
                                            </div>
                                            <div class="rounded-lg bg-slate-800/50 p-3">
                                                <div class="mb-2 text-xs text-slate-500">Risk Levels</div>
                                                <div class="flex justify-between text-xs"><span class="text-rose-300">Stop</span><span class="font-mono text-rose-400">{{ $trade->stop_loss ? '$'.number_format($trade->stop_loss, 2) : '-' }}</span></div>
                                                <div class="mt-1 flex justify-between text-xs"><span class="text-emerald-300">Target</span><span class="font-mono text-emerald-400">{{ $trade->take_profit ? '$'.number_format($trade->take_profit, 2) : '-' }}</span></div>
                                                <div class="mt-1 flex justify-between text-xs"><span class="text-slate-400">Side</span><span class="font-mono text-slate-300">{{ strtoupper($trade->side) }}</span></div>
                                            </div>
                                            <div class="rounded-lg bg-slate-800/50 p-3">
                                                <div class="mb-2 text-xs text-slate-500">{{ $detailTitle }}</div>
                                                <div class="flex justify-between text-xs"><span class="text-slate-400">{{ $detailRowOneLabel }}</span><span class="font-mono text-white">{{ $detailRowOneValue }}</span></div>
                                                <div class="mt-1 flex justify-between text-xs"><span class="text-violet-300">{{ $detailRowTwoLabel }}</span><span class="font-mono text-white">{{ $detailRowTwoValue }}</span></div>
                                            </div>
                                        </div>

                                        <div class="mt-3 rounded-lg border border-slate-800 bg-slate-950/40 px-3 py-2 text-xs text-slate-500">
                                            Last sync: {{ $trade->last_sync_at?->timezone('America/New_York')->format('H:i:s') ?? '-' }} ET{{ $snapshotText }}
                                        </div>
                                    </div>
                            </div>
                        @endforeach
                    </div>

                <div class="rounded-lg border border-slate-700 bg-slate-900/70">
                    <div class="border-b border-slate-700 px-4 py-3 text-sm font-semibold text-slate-200">Trades</div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-950/60 text-xs uppercase text-slate-500">
                                <tr>
                                    <th class="px-4 py-3 text-left">Status</th>
                                    <th class="px-4 py-3 text-left">Side</th>
                                    <th class="px-4 py-3 text-right">Qty</th>
                                    <th class="px-4 py-3 text-right">Entry</th>
                                    <th class="px-4 py-3 text-right">SL</th>
                                    <th class="px-4 py-3 text-right">TP</th>
                                    <th class="px-4 py-3 text-right">Exit</th>
                                    <th class="px-4 py-3 text-right">P&L</th>
                                    <th class="px-4 py-3 text-left">Orders</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800">
                                @forelse($s->trades as $trade)
                                    <tr wire:click="selectTrade({{ $trade->id }})" class="cursor-pointer hover:bg-slate-800/50 {{ $selectedTrade?->id === $trade->id ? 'bg-blue-500/10 ring-1 ring-inset ring-blue-500/25' : '' }}">
                                        <td class="px-4 py-3 text-slate-200">{{ $trade->status }}</td>
                                        <td class="px-4 py-3 text-slate-300">{{ strtoupper($trade->side) }} {{ $trade->direction }}</td>
                                        <td class="px-4 py-3 text-right text-slate-300">{{ $trade->quantity ?? '-' }}</td>
                                        <td class="px-4 py-3 text-right text-slate-300">{{ $trade->entry_price ? number_format($trade->entry_price, 2) : '-' }}</td>
                                        <td class="px-4 py-3 text-right text-slate-300">{{ $trade->stop_loss ? number_format($trade->stop_loss, 2) : '-' }}</td>
                                        <td class="px-4 py-3 text-right text-slate-300">{{ $trade->take_profit ? number_format($trade->take_profit, 2) : '-' }}</td>
                                        <td class="px-4 py-3 text-right text-slate-300">{{ $trade->exit_price ? number_format($trade->exit_price, 2) : '-' }}</td>
                                        <td class="px-4 py-3 text-right {{ ($trade->pnl ?? 0) >= 0 ? 'text-emerald-300' : 'text-red-300' }}">{{ $trade->pnl !== null ? '$'.number_format($trade->pnl, 2) : '-' }}</td>
                                        <td class="px-4 py-3 text-xs text-slate-500">
                                            <div>{{ $trade->entry_order_id }}</div>
                                            <div>{{ $trade->exit_order_id }}</div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="9" class="px-4 py-6 text-center text-slate-500">No trades yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-700 bg-slate-900/70">
                    <div class="border-b border-slate-700 px-4 py-3 text-sm font-semibold text-slate-200">Logs</div>
                    <div class="divide-y divide-slate-800">
                        @if($s->logs->isEmpty())
                            <div class="p-4 text-sm text-slate-500">No logs yet.</div>
                        @else
                            @foreach($s->logs as $log)
                                @php
                                    $eventLabels = [
                                        'entry_blocked_by_opposite_order' => 'Entry blocked',
                                        'tick_failed' => 'Tick failed',
                                        'trigger_not_met' => 'Trigger not met',
                                        'one_shot_already_used' => 'One shot used',
                                        'no_trigger_price' => 'No price',
                                        'entry_order_submitted' => 'Entry sent',
                                        'entry_filled' => 'Entry filled',
                                        'entry_not_filled' => 'Entry not filled',
                                        'exit_order_submitted' => 'Exit sent',
                                        'exit_filled' => 'Exit filled',
                                    ];
                                    $eventLabel = $eventLabels[$log->event] ?? ucwords(str_replace('_', ' ', (string) $log->event));
                                    $message = $log->event === 'entry_blocked_by_opposite_order'
                                        ? 'Alpaca blocked the entry because an opposite SPY order is already open. Cancel or resolve that open order, then the bot can submit the new entry.'
                                        : $log->message;
                                @endphp
                            <div class="grid gap-2 px-4 py-3 text-sm md:grid-cols-[150px_minmax(220px,300px)_minmax(0,1fr)]">
                                <div class="text-xs text-slate-500">{{ $log->created_at->timezone('America/New_York')->format('m-d h:i:s A') }} ET</div>
                                <div class="min-w-0 break-words font-medium {{ $log->level === 'error' ? 'text-red-300' : ($log->level === 'warning' ? 'text-amber-300' : 'text-slate-300') }}">{{ $eventLabel }}</div>
                                <div class="min-w-0 break-words text-slate-300">{{ $message }}</div>
                            </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            @else
                <div class="rounded-lg border border-slate-700 bg-slate-900/70 p-8 text-center text-slate-400">Select or create an Alpaca paper strategy session.</div>
            @endif
        </div>
    </div>
</div>

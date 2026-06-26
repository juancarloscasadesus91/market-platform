<div class="p-6 space-y-6 max-w-[1800px] mx-auto" wire:poll.10s="refreshData">
    <div class="flex items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-white">Alpaca Trading</h1>
                <span class="px-2.5 py-1 rounded text-xs font-bold border {{ $mode === 'live' ? 'bg-rose-500/10 text-rose-300 border-rose-500/30' : 'bg-emerald-500/10 text-emerald-300 border-emerald-500/30' }}">
                    {{ strtoupper($mode) }}
                </span>
            </div>
            <p class="text-sm text-slate-400 mt-1">Account, positions, orders, and manual equity/ETF trading.</p>
        </div>
        <div class="flex items-center gap-3">
            <div class="inline-flex rounded-lg border border-slate-700 bg-slate-900/80 p-1">
                <button wire:click="setMode('paper')"
                    class="px-4 py-1.5 rounded-md text-sm font-semibold transition-colors {{ $mode === 'paper' ? 'bg-emerald-600 text-white' : 'text-slate-400 hover:text-white' }}">
                    Paper
                </button>
                <button wire:click="setMode('live')"
                    class="px-4 py-1.5 rounded-md text-sm font-semibold transition-colors {{ $mode === 'live' ? 'bg-rose-600 text-white' : 'text-slate-400 hover:text-white' }}">
                    Live
                </button>
            </div>
            <button wire:click="refreshData"
                class="px-4 py-2 bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 rounded-lg text-sm font-medium transition-colors">
                Refresh
            </button>
        </div>
    </div>

    @if($mode === 'live')
        <div class="px-4 py-3 rounded-lg bg-rose-500/10 border border-rose-500/25 text-rose-200 text-sm">
            LIVE mode is selected. Orders and close actions will be sent to your real Alpaca account.
        </div>
    @endif

    @if($successMessage)
        <div class="px-4 py-3 rounded-lg bg-emerald-500/10 border border-emerald-500/25 text-emerald-300 text-sm">
            {{ $successMessage }}
        </div>
    @endif

    @if($errorMessage)
        <div class="px-4 py-3 rounded-lg bg-rose-500/10 border border-rose-500/25 text-rose-300 text-sm">
            {{ $errorMessage }}
        </div>
    @endif

    @if(!$configured)
        <div class="rounded-xl bg-slate-900/60 border border-slate-700/50 p-5">
            <h2 class="text-lg font-semibold text-white mb-3">Configuration</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                <div class="bg-slate-950/50 rounded-lg border border-slate-800 p-3">
                    <div class="text-xs uppercase tracking-wider text-slate-500 mb-1">Required</div>
                    @if($mode === 'live')
                        <div class="font-mono text-slate-300">ALPACA_LIVE_API_KEY</div>
                        <div class="font-mono text-slate-300">ALPACA_LIVE_API_SECRET</div>
                    @else
                        <div class="font-mono text-slate-300">ALPACA_PAPER_API_KEY</div>
                        <div class="font-mono text-slate-300">ALPACA_PAPER_API_SECRET</div>
                    @endif
                </div>
                <div class="bg-slate-950/50 rounded-lg border border-slate-800 p-3">
                    <div class="text-xs uppercase tracking-wider text-slate-500 mb-1">{{ ucfirst($mode) }} endpoint</div>
                    <div class="font-mono text-slate-300 break-all">
                        {{ $mode === 'live' ? 'ALPACA_LIVE_BASE_URL=https://api.alpaca.markets' : 'ALPACA_PAPER_BASE_URL=https://paper-api.alpaca.markets' }}
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
        @foreach([
            ['label' => 'Portfolio Value', 'value' => $account['portfolio_value'] ?? null],
            ['label' => 'Buying Power', 'value' => $account['buying_power'] ?? null],
            ['label' => 'Cash', 'value' => $account['cash'] ?? null],
            ['label' => 'Equity', 'value' => $account['equity'] ?? null],
            ['label' => 'Status', 'value' => strtoupper((string)($account['status'] ?? 'not connected')), 'plain' => true],
        ] as $stat)
            <div class="rounded-xl bg-slate-900/60 border border-slate-700/50 p-4">
                <div class="text-xs uppercase tracking-wider text-slate-500 mb-2">{{ $stat['label'] }}</div>
                <div class="text-xl font-bold text-white font-mono">
                    @if($stat['value'] === null || $stat['value'] === '')
                        —
                    @elseif($stat['plain'] ?? false)
                        {{ $stat['value'] }}
                    @else
                        ${{ number_format((float) $stat['value'], 2) }}
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="rounded-xl bg-slate-900/60 border border-slate-700/50 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-800">
                <h2 class="font-semibold text-white">Manual Order</h2>
            </div>
            <div class="p-5 space-y-4">
                <div class="grid grid-cols-2 gap-2 rounded-lg bg-slate-950/40 border border-slate-800 p-1">
                    <button type="button" wire:click="setAssetClass('equity')"
                        class="px-3 py-2 rounded-md text-sm font-semibold transition-colors {{ $assetClass === 'equity' ? 'bg-blue-600 text-white' : 'text-slate-400 hover:text-white' }}">
                        Equity / ETF
                    </button>
                    <button type="button" wire:click="setAssetClass('option')"
                        class="px-3 py-2 rounded-md text-sm font-semibold transition-colors {{ $assetClass === 'option' ? 'bg-violet-600 text-white' : 'text-slate-400 hover:text-white' }}">
                        Option
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">{{ $assetClass === 'option' ? 'Underlying' : 'Symbol' }}</label>
                        <input wire:model="symbol" type="text"
                            class="w-full px-3 py-2 bg-slate-800/80 border border-slate-700 rounded-lg text-white text-sm uppercase focus:outline-none focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Side</label>
                        <select wire:model="side"
                            class="w-full px-3 py-2 bg-slate-800/80 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:border-blue-500">
                            <option value="buy">Buy</option>
                            <option value="sell">Sell</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Order Type</label>
                        <select wire:model.live="orderType"
                            class="w-full px-3 py-2 bg-slate-800/80 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:border-blue-500">
                            <option value="market">Market</option>
                            <option value="limit">Limit</option>
                            <option value="stop">Stop</option>
                            <option value="stop_limit">Stop Limit</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Time In Force</label>
                        <select wire:model="timeInForce"
                            class="w-full px-3 py-2 bg-slate-800/80 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:border-blue-500">
                            <option value="day">DAY</option>
                            <option value="gtc">GTC</option>
                            @if($assetClass === 'equity')
                                <option value="opg">OPG</option>
                                <option value="cls">CLS</option>
                                <option value="ioc">IOC</option>
                                <option value="fok">FOK</option>
                            @endif
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Quantity</label>
                        <input wire:model.live="qty" type="number" min="0" step="0.000001"
                            class="w-full px-3 py-2 bg-slate-800/80 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:border-blue-500">
                        <p class="text-xs text-slate-500 mt-1">{{ $assetClass === 'option' ? 'Whole contracts only' : 'Use shares' }}</p>
                    </div>
                    @if($assetClass === 'equity')
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Notional $</label>
                            <input wire:model.live="notional" type="number" min="0" step="0.01"
                                class="w-full px-3 py-2 bg-slate-800/80 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:border-blue-500">
                            <p class="text-xs text-slate-500 mt-1">Use dollars instead</p>
                        </div>
                    @endif
                    @if(in_array($orderType, ['limit', 'stop_limit'], true))
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Limit Price</label>
                            <input wire:model="limitPrice" type="number" min="0" step="0.01"
                                class="w-full px-3 py-2 bg-slate-800/80 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:border-blue-500">
                        </div>
                    @endif
                    @if(in_array($orderType, ['stop', 'stop_limit'], true))
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Stop Price</label>
                            <input wire:model="stopPrice" type="number" min="0" step="0.01"
                                class="w-full px-3 py-2 bg-slate-800/80 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:border-blue-500">
                        </div>
                    @endif
                </div>

                @if($assetClass === 'equity')
                    <label class="flex items-center gap-3 px-3 py-2 rounded-lg bg-slate-800/40 border border-slate-700/50 cursor-pointer">
                        <input wire:model="extendedHours" type="checkbox" class="rounded border-slate-600 bg-slate-900">
                        <span class="text-sm text-slate-300">Extended hours</span>
                    </label>
                @else
                    <div class="rounded-lg bg-violet-500/10 border border-violet-500/20 p-3 text-sm text-violet-200">
                        Selected contract: <span class="font-mono text-white">{{ $selectedOptionSymbol ?: 'none' }}</span>
                    </div>
                @endif

                <button wire:click="submitOrder" wire:loading.attr="disabled"
                    class="w-full px-4 py-3 rounded-lg {{ $mode === 'live' ? 'bg-rose-600 hover:bg-rose-500' : 'bg-blue-600 hover:bg-blue-500' }} disabled:opacity-60 text-white text-sm font-bold transition-colors">
                    Submit {{ ucfirst($mode) }} Order
                </button>

                @if($assetClass === 'option')
                    <div class="pt-4 border-t border-slate-800 space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Call / Put</label>
                                <select wire:model.live="optionType"
                                    class="w-full px-3 py-2 bg-slate-800/80 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:border-violet-500">
                                    <option value="call">Call</option>
                                    <option value="put">Put</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Expiration</label>
                                <input wire:model="optionExpirationDate" type="date"
                                    class="w-full px-3 py-2 bg-slate-800/80 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:border-violet-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Min Strike</label>
                                <input wire:model="optionMinStrike" type="number" min="0" step="0.01"
                                    class="w-full px-3 py-2 bg-slate-800/80 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:border-violet-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Max Strike</label>
                                <input wire:model="optionMaxStrike" type="number" min="0" step="0.01"
                                    class="w-full px-3 py-2 bg-slate-800/80 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:border-violet-500">
                            </div>
                        </div>

                        <button type="button" wire:click="searchOptionContracts" wire:loading.attr="disabled"
                            class="w-full px-4 py-2.5 rounded-lg bg-violet-600 hover:bg-violet-500 disabled:opacity-60 text-white text-sm font-bold transition-colors">
                            Search Contracts
                        </button>

                        <div class="max-h-80 overflow-y-auto rounded-lg border border-slate-800">
                            <table class="w-full text-xs">
                                <thead class="sticky top-0 bg-slate-950 text-slate-500 uppercase tracking-wider">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Contract</th>
                                        <th class="px-3 py-2 text-right">Strike</th>
                                        <th class="px-3 py-2 text-right">Exp</th>
                                        <th class="px-3 py-2 text-right">Bid</th>
                                        <th class="px-3 py-2 text-right">Ask</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800">
                                    @forelse($optionContracts as $contract)
                                        @php
                                            $contractSymbol = $contract['symbol'] ?? '';
                                            $quote = $optionQuotes[$contractSymbol] ?? [];
                                            $bid = $quote['bp'] ?? $quote['bid_price'] ?? null;
                                            $ask = $quote['ap'] ?? $quote['ask_price'] ?? null;
                                        @endphp
                                        <tr wire:click="selectOptionContract('{{ $contractSymbol }}')"
                                            class="cursor-pointer hover:bg-slate-800/50 {{ $selectedOptionSymbol === $contractSymbol ? 'bg-violet-500/15' : '' }}">
                                            <td class="px-3 py-2 font-mono text-slate-200">{{ $contractSymbol }}</td>
                                            <td class="px-3 py-2 text-right font-mono text-slate-300">{{ $contract['strike_price'] ?? '—' }}</td>
                                            <td class="px-3 py-2 text-right font-mono text-slate-400">{{ $contract['expiration_date'] ?? '—' }}</td>
                                            <td class="px-3 py-2 text-right font-mono text-slate-300">{{ $bid !== null ? number_format((float) $bid, 2) : '—' }}</td>
                                            <td class="px-3 py-2 text-right font-mono text-slate-300">{{ $ask !== null ? number_format((float) $ask, 2) : '—' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-3 py-8 text-center text-slate-500">Search for contracts.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="xl:col-span-2 rounded-xl bg-slate-900/60 border border-slate-700/50 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-800 flex items-center justify-between">
                <h2 class="font-semibold text-white">Positions</h2>
                <span class="text-xs text-slate-500">{{ count($positions) }} open</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-950/50 text-xs uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Symbol</th>
                            <th class="px-4 py-3 text-right">Qty</th>
                            <th class="px-4 py-3 text-right">Avg Entry</th>
                            <th class="px-4 py-3 text-right">Market Value</th>
                            <th class="px-4 py-3 text-right">P&L</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse($positions as $position)
                            @php $pnl = (float)($position['unrealized_pl'] ?? 0); @endphp
                            <tr class="hover:bg-slate-800/30">
                                <td class="px-4 py-3 font-bold text-white">{{ $position['symbol'] ?? '—' }}</td>
                                <td class="px-4 py-3 text-right font-mono text-slate-300">{{ $position['qty'] ?? '—' }}</td>
                                <td class="px-4 py-3 text-right font-mono text-slate-300">${{ number_format((float)($position['avg_entry_price'] ?? 0), 2) }}</td>
                                <td class="px-4 py-3 text-right font-mono text-slate-300">${{ number_format((float)($position['market_value'] ?? 0), 2) }}</td>
                                <td class="px-4 py-3 text-right font-mono {{ $pnl >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">{{ $pnl >= 0 ? '+' : '' }}${{ number_format($pnl, 2) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <button wire:click="closePosition('{{ $position['symbol'] ?? '' }}')"
                                        class="px-3 py-1.5 rounded bg-rose-500/10 hover:bg-rose-500/20 text-rose-300 border border-rose-500/25 text-xs font-semibold">
                                        Close
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-slate-500">No open positions.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div class="rounded-xl bg-slate-900/60 border border-slate-700/50 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-800 flex items-center justify-between">
                <h2 class="font-semibold text-white">Open Orders</h2>
                <span class="text-xs text-slate-500">{{ count($openOrders) }} working</span>
            </div>
            @include('livewire.partials.alpaca-orders-table', ['orders' => $openOrders, 'showCancel' => true])
        </div>

        <div class="rounded-xl bg-slate-900/60 border border-slate-700/50 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-800">
                <h2 class="font-semibold text-white">Latest Orders</h2>
            </div>
            @include('livewire.partials.alpaca-orders-table', ['orders' => $latestOrders, 'showCancel' => false])
        </div>
    </div>

    @if($confirmModalOpen)
        <div class="fixed inset-0 z-[100] flex items-center justify-center px-4 py-6">
            <button type="button" wire:click="closeConfirmModal" class="absolute inset-0 bg-black/70 backdrop-blur-sm"></button>

            <div class="relative w-full max-w-md rounded-xl bg-slate-950 border border-slate-700 shadow-2xl overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-800 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg flex items-center justify-center border {{ $confirmTone === 'rose' ? 'bg-rose-500/10 border-rose-500/30 text-rose-300' : ($confirmTone === 'amber' ? 'bg-amber-500/10 border-amber-500/30 text-amber-300' : 'bg-blue-500/10 border-blue-500/30 text-blue-300') }}">
                            @if($confirmTone === 'rose')
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                            @else
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @endif
                        </div>
                        <h3 class="text-base font-bold text-white">{{ $confirmTitle }}</h3>
                    </div>
                    <button type="button" wire:click="closeConfirmModal" class="p-1.5 rounded-lg text-slate-500 hover:text-white hover:bg-slate-800">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="p-5 space-y-4">
                    <p class="text-sm text-slate-300 leading-6">{{ $confirmMessage }}</p>

                    @if($confirmAction === 'submit_order')
                        <div class="rounded-lg bg-slate-900 border border-slate-800 p-3 grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <div class="text-xs text-slate-500 uppercase tracking-wider">Symbol</div>
                                <div class="font-mono text-white">{{ $assetClass === 'option' ? $selectedOptionSymbol : strtoupper($symbol) }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-slate-500 uppercase tracking-wider">Side</div>
                                <div class="font-mono {{ $side === 'buy' ? 'text-emerald-300' : 'text-rose-300' }}">{{ strtoupper($side) }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-slate-500 uppercase tracking-wider">Type</div>
                                <div class="font-mono text-white">{{ strtoupper(str_replace('_', ' ', $orderType)) }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-slate-500 uppercase tracking-wider">Size</div>
                                <div class="font-mono text-white">{{ $notional && $assetClass === 'equity' ? '$' . number_format((float) $notional, 2) : $qty . ($assetClass === 'option' ? ' contract(s)' : ' shares') }}</div>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="px-5 py-4 bg-slate-900/80 border-t border-slate-800 flex justify-end gap-3">
                    <button type="button" wire:click="closeConfirmModal"
                        class="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 text-sm font-semibold">
                        Cancel
                    </button>
                    <button type="button" wire:click="confirmModalAction"
                        class="px-4 py-2 rounded-lg text-white text-sm font-bold {{ $confirmTone === 'rose' ? 'bg-rose-600 hover:bg-rose-500' : ($confirmTone === 'amber' ? 'bg-amber-600 hover:bg-amber-500' : 'bg-blue-600 hover:bg-blue-500') }}">
                        {{ $confirmButton }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

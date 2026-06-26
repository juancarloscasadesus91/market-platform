<div class="p-6 space-y-6 max-w-[1920px] mx-auto" wire:poll.30s="refresh">

    {{-- Not Authenticated --}}
    @if(!$isAuthenticated && !$isLoading)
    <div class="flex flex-col items-center justify-center py-24 space-y-6">
        <div class="w-20 h-20 rounded-2xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center">
            <svg class="w-10 h-10 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
            </svg>
        </div>
        <div class="text-center">
            <h2 class="text-xl font-semibold text-white mb-2">Connect your Schwab Account</h2>
            <p class="text-slate-400 text-sm max-w-md">Authenticate with your Schwab Trader API to view account balances, positions, and orders in real-time.</p>
        </div>
        <a href="{{ $traderAuthUrl }}"
           class="flex items-center space-x-2 px-6 py-3 bg-blue-600 hover:bg-blue-500 text-white font-semibold rounded-xl transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
            <span>Connect Schwab Trader API</span>
        </a>
        @if($error)
        <div class="px-4 py-3 rounded-lg bg-rose-500/10 border border-rose-500/20 text-rose-400 text-sm max-w-md text-center">
            {{ $error }}
        </div>
        @endif
    </div>
    @elseif($isLoading)
    <div class="flex items-center justify-center py-24">
        <div class="flex flex-col items-center space-y-4">
            <div class="w-10 h-10 border-2 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
            <span class="text-slate-400 text-sm">Loading account data...</span>
        </div>
    </div>
    @else

    {{-- Error Banner --}}
    @if($error)
    <div class="flex items-center space-x-3 px-4 py-3 rounded-xl bg-rose-500/10 border border-rose-500/20">
        <svg class="w-5 h-5 text-rose-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span class="text-rose-400 text-sm flex-1">{{ $error }}</span>
        <button wire:click="refresh" class="text-rose-400 hover:text-rose-300 text-xs underline">Retry</button>
    </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white">Schwab Account</h1>
            <p class="text-slate-400 text-sm mt-0.5">{{ $accountSummary['accountCount'] ?? 0 }} account(s) connected &bull; Auto-refreshes every 30s</p>
        </div>
        <div class="flex items-center space-x-3">
            {{-- Account Selector --}}
            @if(count($accounts) > 1)
            <div class="flex items-center space-x-2">
                @foreach($accounts as $acc)
                @php
                    $sec = $acc['securitiesAccount'] ?? [];
                    $hash = $acc['hashValue'] ?? $sec['accountNumber'] ?? '';
                    $num  = $sec['accountNumber'] ?? 'N/A';
                    $type = $sec['type'] ?? '';
                @endphp
                <button
                    wire:click="selectAccount('{{ $hash }}')"
                    class="px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors
                        {{ $selectedAccountHash === $hash
                            ? 'bg-blue-500/20 border-blue-500/40 text-blue-400'
                            : 'bg-slate-800/50 border-slate-700/50 text-slate-400 hover:text-white' }}">
                    ...{{ substr($num, -4) }} {{ $type }}
                </button>
                @endforeach
            </div>
            @endif
            <button wire:click="refresh"
                class="flex items-center space-x-2 px-4 py-2 bg-slate-800/80 hover:bg-slate-700/80 border border-slate-700/50 rounded-xl text-slate-300 text-sm transition-colors">
                <svg class="w-4 h-4" wire:loading.class="animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                <span>Refresh</span>
            </button>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4">
        @php
            $cards = [
                ['label' => 'Total Value',      'value' => '$' . number_format($accountSummary['totalLiquidationValue'] ?? 0, 2), 'color' => 'blue',    'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v1m0 4v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                ['label' => 'Cash Balance',     'value' => '$' . number_format($accountSummary['totalCashBalance'] ?? 0, 2),      'color' => 'emerald', 'icon' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z'],
                ['label' => 'Buying Power',     'value' => '$' . number_format($accountSummary['totalBuyingPower'] ?? 0, 2),      'color' => 'violet',  'icon' => 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'],
                ['label' => 'Margin Balance',   'value' => '$' . number_format($accountSummary['totalMarginBalance'] ?? 0, 2),    'color' => 'amber',   'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
                ['label' => 'Day P&L',          'value' => ($accountSummary['totalDayPnl'] ?? 0) >= 0 ? '+$' . number_format($accountSummary['totalDayPnl'] ?? 0, 2) : '-$' . number_format(abs($accountSummary['totalDayPnl'] ?? 0), 2), 'color' => ($accountSummary['totalDayPnl'] ?? 0) >= 0 ? 'emerald' : 'rose', 'icon' => 'M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z'],
                ['label' => 'Positions',        'value' => count($positions),                                                     'color' => 'slate',   'icon' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10'],
            ];
            $colorMap = [
                'blue'    => ['bg' => 'bg-blue-500/10',    'border' => 'border-blue-500/20',    'text' => 'text-blue-400',    'icon' => 'text-blue-400'],
                'emerald' => ['bg' => 'bg-emerald-500/10', 'border' => 'border-emerald-500/20', 'text' => 'text-emerald-400', 'icon' => 'text-emerald-400'],
                'violet'  => ['bg' => 'bg-violet-500/10',  'border' => 'border-violet-500/20',  'text' => 'text-violet-400',  'icon' => 'text-violet-400'],
                'amber'   => ['bg' => 'bg-amber-500/10',   'border' => 'border-amber-500/20',   'text' => 'text-amber-400',   'icon' => 'text-amber-400'],
                'rose'    => ['bg' => 'bg-rose-500/10',    'border' => 'border-rose-500/20',    'text' => 'text-rose-400',    'icon' => 'text-rose-400'],
                'slate'   => ['bg' => 'bg-slate-800/50',   'border' => 'border-slate-700/50',   'text' => 'text-slate-200',   'icon' => 'text-slate-400'],
            ];
        @endphp
        @foreach($cards as $card)
        @php $c = $colorMap[$card['color']]; @endphp
        <div class="rounded-xl border {{ $c['bg'] }} {{ $c['border'] }} p-4 space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-slate-400">{{ $card['label'] }}</span>
                <svg class="w-4 h-4 {{ $c['icon'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $card['icon'] }}"/>
                </svg>
            </div>
            <div class="text-xl font-bold {{ $c['text'] }}">{{ $card['value'] }}</div>
        </div>
        @endforeach
    </div>

    {{-- Tab Navigation --}}
    <div class="flex items-center space-x-1 border-b border-slate-700/50">
        @foreach([['key' => 'overview', 'label' => 'Overview'], ['key' => 'positions', 'label' => 'Positions (' . count($positions) . ')'], ['key' => 'orders', 'label' => 'Orders (' . count($orders) . ')'], ['key' => 'balances', 'label' => 'Balances']] as $tab)
        <button wire:click="setTab('{{ $tab['key'] }}')"
            class="px-4 py-3 text-sm font-medium border-b-2 transition-colors
                {{ $activeTab === $tab['key']
                    ? 'border-blue-500 text-blue-400'
                    : 'border-transparent text-slate-400 hover:text-white hover:border-slate-600' }}">
            {{ $tab['label'] }}
        </button>
        @endforeach
    </div>

    {{-- TAB: Overview --}}
    @if($activeTab === 'overview')
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Account Details --}}
        @foreach($accounts as $account)
        @php
            $sec              = $account['securitiesAccount'] ?? [];
            $currentBalances  = $sec['currentBalances'] ?? [];
            $projectedBalances= $sec['projectedBalances'] ?? [];
            $initialBalances  = $sec['initialBalances'] ?? [];
            $acctNum          = $sec['accountNumber'] ?? 'N/A';
            $acctType         = $sec['type'] ?? 'N/A';
            $isDayTrader      = $sec['isDayTrader'] ?? false;
            $isClosingOnly    = $sec['isClosingOnlyRestricted'] ?? false;
            $positionsCount   = count($sec['positions'] ?? []);
        @endphp
        <div class="rounded-xl bg-slate-900/50 border border-slate-700/50 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-700/50 bg-slate-800/30">
                <div class="flex items-center space-x-3">
                    <div class="w-9 h-9 rounded-lg bg-blue-500/10 border border-blue-500/20 flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="text-sm font-semibold text-white">Account {{ $acctNum }}</div>
                        <div class="text-xs text-slate-400">{{ $acctType }}</div>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    @if($isDayTrader)
                    <span class="px-2 py-0.5 rounded text-xs font-medium bg-amber-500/10 text-amber-400 border border-amber-500/20">PDT</span>
                    @endif
                    @if($isClosingOnly)
                    <span class="px-2 py-0.5 rounded text-xs font-medium bg-rose-500/10 text-rose-400 border border-rose-500/20">Closing Only</span>
                    @endif
                    <span class="px-2 py-0.5 rounded text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Active</span>
                </div>
            </div>
            <div class="p-5 grid grid-cols-2 gap-4">
                @php
                    $balFields = [
                        ['label' => 'Liquidation Value', 'value' => $currentBalances['liquidationValue'] ?? null],
                        ['label' => 'Cash Balance',       'value' => $currentBalances['cashBalance'] ?? $currentBalances['totalCash'] ?? null],
                        ['label' => 'Buying Power',       'value' => $projectedBalances['buyingPower'] ?? $currentBalances['buyingPower'] ?? null],
                        ['label' => 'Equity',             'value' => $currentBalances['equity'] ?? $currentBalances['totalCash'] ?? null],
                        ['label' => 'Margin Balance',     'value' => $currentBalances['marginBalance'] ?? null],
                        ['label' => 'Maintenance Req.',   'value' => $currentBalances['maintenanceRequirement'] ?? null],
                        ['label' => 'Open P&L',           'value' => $currentBalances['unrealizedProfitLoss'] ?? null],
                        ['label' => 'Positions',          'value' => $positionsCount, 'raw' => true],
                    ];
                @endphp
                @foreach($balFields as $field)
                @if($field['value'] !== null)
                <div class="bg-slate-800/30 rounded-lg p-3">
                    <div class="text-xs text-slate-400 mb-1">{{ $field['label'] }}</div>
                    <div class="text-sm font-semibold text-white">
                        @if(isset($field['raw']) && $field['raw'])
                            {{ $field['value'] }}
                        @else
                            ${{ number_format((float)$field['value'], 2) }}
                        @endif
                    </div>
                </div>
                @endif
                @endforeach
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- TAB: Positions --}}
    @if($activeTab === 'positions')
    @if(empty($positions))
    <div class="flex flex-col items-center justify-center py-16 space-y-3">
        <svg class="w-12 h-12 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
        </svg>
        <p class="text-slate-400 text-sm">No open positions</p>
    </div>
    @else
    <div class="rounded-xl border border-slate-700/50 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-800/50 border-b border-slate-700/50">
                        <th class="text-left px-4 py-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Symbol</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Type</th>
                        <th class="text-right px-4 py-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Qty</th>
                        <th class="text-right px-4 py-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Avg Price</th>
                        <th class="text-right px-4 py-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Market Value</th>
                        <th class="text-right px-4 py-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Day P&L</th>
                        <th class="text-right px-4 py-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Day P&L %</th>
                        @if(count($accounts) > 1)
                        <th class="text-left px-4 py-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Account</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/50">
                    @foreach($positions as $pos)
                    @php
                        $qty = $pos['longQuantity'] > 0 ? $pos['longQuantity'] : -$pos['shortQuantity'];
                        $isShort = $qty < 0;
                        $pnlPositive = $pos['dayPnl'] >= 0;
                        $pnlPctAbs = abs($pos['dayPnlPct']);
                        $assetColors = [
                            'EQUITY'   => 'bg-blue-500/10 text-blue-400',
                            'OPTION'   => 'bg-violet-500/10 text-violet-400',
                            'FIXED_INCOME' => 'bg-amber-500/10 text-amber-400',
                            'ETF'      => 'bg-teal-500/10 text-teal-400',
                        ];
                        $assetBadge = $assetColors[$pos['assetType']] ?? 'bg-slate-700/50 text-slate-300';
                    @endphp
                    <tr class="hover:bg-slate-800/30 transition-colors">
                        <td class="px-4 py-3">
                            <div class="font-semibold text-white">{{ $pos['symbol'] }}</div>
                            @if($pos['description'])
                            <div class="text-xs text-slate-500 truncate max-w-[160px]">{{ $pos['description'] }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded text-xs font-medium {{ $assetBadge }}">{{ $pos['assetType'] }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <span class="{{ $isShort ? 'text-rose-400' : 'text-emerald-400' }} font-semibold">
                                {{ $isShort ? '' : '+' }}{{ number_format($qty, 0) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right text-slate-200">${{ number_format($pos['averagePrice'], 2) }}</td>
                        <td class="px-4 py-3 text-right font-semibold text-white">${{ number_format($pos['marketValue'], 2) }}</td>
                        <td class="px-4 py-3 text-right font-semibold {{ $pnlPositive ? 'text-emerald-400' : 'text-rose-400' }}">
                            {{ $pnlPositive ? '+' : '' }}${{ number_format($pos['dayPnl'], 2) }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $pnlPositive ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400' }}">
                                {{ $pnlPositive ? '+' : '-' }}{{ number_format($pnlPctAbs, 2) }}%
                            </span>
                        </td>
                        @if(count($accounts) > 1)
                        <td class="px-4 py-3 text-slate-400 text-xs">...{{ substr($pos['account'], -4) }}</td>
                        @endif
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-slate-800/30 border-t border-slate-700/50">
                        <td colspan="4" class="px-4 py-3 text-xs font-semibold text-slate-400 uppercase">Total</td>
                        <td class="px-4 py-3 text-right font-bold text-white">
                            ${{ number_format(collect($positions)->sum('marketValue'), 2) }}
                        </td>
                        @php $totalDayPnl = collect($positions)->sum('dayPnl'); @endphp
                        <td class="px-4 py-3 text-right font-bold {{ $totalDayPnl >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                            {{ $totalDayPnl >= 0 ? '+' : '' }}${{ number_format($totalDayPnl, 2) }}
                        </td>
                        <td colspan="{{ count($accounts) > 1 ? 2 : 1 }}"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    @endif
    @endif

    {{-- TAB: Orders --}}
    @if($activeTab === 'orders')
    {{-- Filter Buttons --}}
    <div class="flex items-center space-x-2">
        @foreach(['ALL', 'WORKING', 'FILLED', 'CANCELED', 'REJECTED', 'EXPIRED'] as $f)
        <button wire:click="setOrdersFilter('{{ $f }}')"
            class="px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors
                {{ $ordersFilter === $f
                    ? 'bg-blue-500/20 border-blue-500/40 text-blue-400'
                    : 'bg-slate-800/50 border-slate-700/50 text-slate-400 hover:text-white' }}">
            {{ $f }}
        </button>
        @endforeach
    </div>

    @if(empty($orders))
    <div class="flex flex-col items-center justify-center py-16 space-y-3">
        <svg class="w-12 h-12 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
        </svg>
        <p class="text-slate-400 text-sm">No orders found</p>
    </div>
    @else
    <div class="rounded-xl border border-slate-700/50 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-800/50 border-b border-slate-700/50">
                        <th class="text-left px-4 py-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Order ID</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Symbol</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Type</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Instruction</th>
                        <th class="text-right px-4 py-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Qty</th>
                        <th class="text-right px-4 py-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Filled</th>
                        <th class="text-right px-4 py-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Price</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Status</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Entered</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/50">
                    @foreach($orders as $order)
                    @php
                        $legs = $order['orderLegCollection'] ?? [];
                        $firstLeg = $legs[0] ?? [];
                        $instrument = $firstLeg['instrument'] ?? [];
                        $symbol = $instrument['symbol'] ?? 'N/A';
                        $instruction = $firstLeg['instruction'] ?? 'N/A';
                        $isBuy = str_contains(strtoupper($instruction), 'BUY');
                        $status = $order['status'] ?? 'UNKNOWN';
                        $statusColors = [
                            'FILLED'    => 'bg-emerald-500/10 text-emerald-400',
                            'WORKING'   => 'bg-blue-500/10 text-blue-400',
                            'CANCELED'  => 'bg-slate-700/50 text-slate-400',
                            'REJECTED'  => 'bg-rose-500/10 text-rose-400',
                            'EXPIRED'   => 'bg-amber-500/10 text-amber-400',
                            'QUEUED'    => 'bg-violet-500/10 text-violet-400',
                        ];
                        $statusBadge = $statusColors[$status] ?? 'bg-slate-700/50 text-slate-300';
                        $enteredTime = $order['enteredTime'] ?? null;
                        $price = $order['price'] ?? $order['stopPrice'] ?? null;
                        $avgPrice = $order['orderActivityCollection'][0]['executionLegs'][0]['price'] ?? null;
                    @endphp
                    <tr class="hover:bg-slate-800/30 transition-colors">
                        <td class="px-4 py-3 text-slate-500 text-xs font-mono">{{ $order['orderId'] ?? 'N/A' }}</td>
                        <td class="px-4 py-3 font-semibold text-white">{{ $symbol }}</td>
                        <td class="px-4 py-3 text-slate-300 text-xs">{{ $order['orderType'] ?? 'N/A' }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $isBuy ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400' }}">
                                {{ $instruction }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right text-slate-200">{{ number_format($order['quantity'] ?? 0, 0) }}</td>
                        <td class="px-4 py-3 text-right text-slate-200">{{ number_format($order['filledQuantity'] ?? 0, 0) }}</td>
                        <td class="px-4 py-3 text-right text-slate-200">
                            @if($avgPrice)
                                ${{ number_format($avgPrice, 2) }}
                            @elseif($price)
                                ${{ number_format($price, 2) }}
                            @else
                                <span class="text-slate-500">MKT</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded text-xs font-medium {{ $statusBadge }}">{{ $status }}</span>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-400">
                            {{ $enteredTime ? \Carbon\Carbon::parse($enteredTime)->format('M d, H:i') : 'N/A' }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
    @endif

    {{-- TAB: Balances --}}
    @if($activeTab === 'balances')
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @foreach($accounts as $account)
        @php
            $sec = $account['securitiesAccount'] ?? [];
            $currentBalances   = $sec['currentBalances'] ?? [];
            $projectedBalances = $sec['projectedBalances'] ?? [];
            $initialBalances   = $sec['initialBalances'] ?? [];
            $acctNum = $sec['accountNumber'] ?? 'N/A';
            $acctType = $sec['type'] ?? '';
        @endphp
        <div class="rounded-xl border border-slate-700/50 overflow-hidden bg-slate-900/30">
            <div class="px-5 py-4 bg-slate-800/30 border-b border-slate-700/50">
                <h3 class="font-semibold text-white">Account {{ $acctNum }} <span class="text-slate-400 text-sm">({{ $acctType }})</span></h3>
            </div>
            <div class="p-5 space-y-4">
                {{-- Current Balances --}}
                @if(!empty($currentBalances))
                <div>
                    <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">Current Balances</h4>
                    <div class="space-y-2">
                        @foreach($currentBalances as $key => $val)
                        @if(is_numeric($val))
                        <div class="flex items-center justify-between py-1.5 border-b border-slate-800/50 last:border-0">
                            <span class="text-sm text-slate-400">{{ ucwords(str_replace(['_', 'Amount'], [' ', ''], preg_replace('/([A-Z])/', ' $1', $key))) }}</span>
                            <span class="text-sm font-semibold {{ (float)$val < 0 ? 'text-rose-400' : 'text-white' }}">${{ number_format((float)$val, 2) }}</span>
                        </div>
                        @endif
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Projected Balances --}}
                @if(!empty($projectedBalances))
                <div>
                    <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">Projected Balances</h4>
                    <div class="space-y-2">
                        @foreach($projectedBalances as $key => $val)
                        @if(is_numeric($val))
                        <div class="flex items-center justify-between py-1.5 border-b border-slate-800/50 last:border-0">
                            <span class="text-sm text-slate-400">{{ ucwords(str_replace(['_', 'Amount'], [' ', ''], preg_replace('/([A-Z])/', ' $1', $key))) }}</span>
                            <span class="text-sm font-semibold {{ (float)$val < 0 ? 'text-rose-400' : 'text-white' }}">${{ number_format((float)$val, 2) }}</span>
                        </div>
                        @endif
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    @endif

    @endif {{-- end isAuthenticated --}}
</div>

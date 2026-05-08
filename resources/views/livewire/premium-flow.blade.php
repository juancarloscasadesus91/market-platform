<div wire:poll.1s>
    <x-card>
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center space-x-3">
                <h2 class="text-lg font-semibold text-white">Premium Flow</h2>
                <div class="flex items-center space-x-2">
                    <div class="w-2 h-2 bg-emerald-400 rounded-full animate-pulse"></div>
                    <span class="text-xs text-slate-500">Live</span>
                </div>
            </div>

            <!-- Timeframe Selector -->
            <div class="flex items-center space-x-1 bg-slate-800/50 rounded-lg p-1">
                <button wire:click="setTimeframe('1m')" class="px-2 py-1 text-xs font-medium rounded transition-colors {{ $timeframe === '1m' ? 'bg-blue-500 text-white' : 'text-slate-400 hover:text-white' }}">1m</button>
                <button wire:click="setTimeframe('5m')" class="px-2 py-1 text-xs font-medium rounded transition-colors {{ $timeframe === '5m' ? 'bg-blue-500 text-white' : 'text-slate-400 hover:text-white' }}">5m</button>
                <button wire:click="setTimeframe('15m')" class="px-2 py-1 text-xs font-medium rounded transition-colors {{ $timeframe === '15m' ? 'bg-blue-500 text-white' : 'text-slate-400 hover:text-white' }}">15m</button>
                <button wire:click="setTimeframe('30m')" class="px-2 py-1 text-xs font-medium rounded transition-colors {{ $timeframe === '30m' ? 'bg-blue-500 text-white' : 'text-slate-400 hover:text-white' }}">30m</button>
                <button wire:click="setTimeframe('1h')" class="px-2 py-1 text-xs font-medium rounded transition-colors {{ $timeframe === '1h' ? 'bg-blue-500 text-white' : 'text-slate-400 hover:text-white' }}">1h</button>
                <button wire:click="setTimeframe('1d')" class="px-2 py-1 text-xs font-medium rounded transition-colors {{ $timeframe === '1d' ? 'bg-blue-500 text-white' : 'text-slate-400 hover:text-white' }}">1d</button>
            </div>
        </div>

        <!-- Net Premium Flow -->
        <div class="mb-6 p-4 rounded-lg transition-all duration-300 {{ $premiumData['netPremium'] >= 0 ? 'bg-emerald-500/10 border border-emerald-500/30' : 'bg-rose-500/10 border border-rose-500/30' }}">
            <div class="flex items-center justify-between mb-2">
                <p class="text-xs text-slate-400 uppercase tracking-wide">Net Premium Flow</p>
                <span class="text-xs text-slate-600">Real-time</span>
            </div>
            <div class="flex items-baseline space-x-2">
                <p class="text-3xl font-bold transition-all duration-300 {{ $premiumData['netPremium'] >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                    {{ $premiumData['netPremium'] >= 0 ? '+' : '' }}${{ number_format(abs($premiumData['netPremium']) / 1000000, 2) }}M
                </p>
                <p class="text-sm text-slate-500">
                    {{ $premiumData['netPremium'] >= 0 ? 'Bullish' : 'Bearish' }}
                </p>
            </div>
        </div>

        <!-- Call vs Put Premium -->
        <div class="grid grid-cols-2 gap-4 mb-6">
            <!-- Call Premium -->
            <div class="p-4 rounded-lg bg-emerald-500/5 border border-emerald-500/20 transition-all duration-300 hover:bg-emerald-500/10">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-xs text-slate-400 uppercase tracking-wide">Call Premium</p>
                    <svg class="w-5 h-5 text-emerald-400 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                </div>
                <p class="text-2xl font-bold text-emerald-400 mb-1 transition-all duration-300">
                    ${{ number_format($premiumData['callPremium'] / 1000000, 2) }}M
                </p>
                <p class="text-xs text-slate-500">{{ number_format($premiumData['callContracts']) }} contracts</p>
            </div>

            <!-- Put Premium -->
            <div class="p-4 rounded-lg bg-rose-500/5 border border-rose-500/20 transition-all duration-300 hover:bg-rose-500/10">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-xs text-slate-400 uppercase tracking-wide">Put Premium</p>
                    <svg class="w-5 h-5 text-rose-400 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path>
                    </svg>
                </div>
                <p class="text-2xl font-bold text-rose-400 mb-1 transition-all duration-300">
                    ${{ number_format($premiumData['putPremium'] / 1000000, 2) }}M
                </p>
                <p class="text-xs text-slate-500">{{ number_format($premiumData['putContracts']) }} contracts</p>
            </div>
        </div>

        <!-- Premium Ratio -->
        <div class="pt-4 border-t border-slate-800/50">
            <div class="flex items-center justify-between">
                <span class="text-sm text-slate-400">Call/Put Premium Ratio</span>
                <span class="text-lg font-bold text-white">{{ number_format($premiumData['ratio'], 2) }}</span>
            </div>

            <!-- Visual Bar -->
            <div class="mt-3 h-2 bg-slate-800 rounded-full overflow-hidden">
                @php
                    $total = $premiumData['callPremium'] + $premiumData['putPremium'];
                    $callPercent = $total > 0 ? ($premiumData['callPremium'] / $total * 100) : 50;
                @endphp
                <div class="h-full bg-gradient-to-r from-emerald-500 to-emerald-400 transition-all duration-500" style="width: {{ $callPercent }}%"></div>
            </div>
            <div class="flex items-center justify-between mt-1 text-xs text-slate-500">
                <span>{{ number_format($callPercent, 1) }}% Calls</span>
                <span>{{ number_format(100 - $callPercent, 1) }}% Puts</span>
            </div>
        </div>

        <!-- Timeframe Info -->
        <div class="mt-4 pt-4 border-t border-slate-800/50">
            <p class="text-xs text-slate-500 text-center">
                Showing premium flow for
                <span class="text-blue-400 font-medium">
                    @switch($timeframe)
                        @case('1m') last 1 minute @break
                        @case('5m') last 5 minutes @break
                        @case('15m') last 15 minutes @break
                        @case('30m') last 30 minutes @break
                        @case('1h') last hour @break
                        @case('1d') today @break
                    @endswitch
                </span>
            </p>
        </div>
    </x-card>
</div>

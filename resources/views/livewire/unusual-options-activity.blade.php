<div class="relative" 
     x-data="{ 
         filtering: @entangle('isFiltering'),
         tracking: @entangle('pausePolling').live,
         showFilter: false,
         startFilter() {
             this.showFilter = true;
         }
     }" 
     x-init="setInterval(() => { if (!tracking) $wire.pollForNewTrades() }, 2000)"
     @resume-polling.window="setTimeout(() => { $wire.resumePolling(); showFilter = false; }, 2000)">
    <x-card>
        <div class="mb-4">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center space-x-3">
                    <h2 class="text-lg font-semibold text-white">Unusual Options Activity</h2>
                    <div class="flex items-center space-x-2">
                        <div :class="!tracking ? 'w-2 h-2 bg-amber-400 rounded-full animate-pulse' : 'w-2 h-2 bg-slate-600 rounded-full'"></div>
                        <span class="text-xs text-slate-500" x-text="!tracking ? 'Live - {{ $ticker }}' : 'Paused'"></span>
                        <span class="text-xs text-slate-600">| Contracts: {{ count($unusualActivity) }}</span>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <!-- Toggle Tracking Button -->
                    <button 
                        @click="tracking = !tracking; if (tracking) { $wire.clearTrades() }" 
                        :class="!tracking ? 'bg-emerald-500/20 text-emerald-400 hover:bg-emerald-500/30' : 'bg-slate-700 text-slate-400 hover:bg-slate-600'"
                        class="px-4 py-2 text-xs font-medium rounded-lg transition-colors cursor-pointer flex items-center space-x-2"
                        :title="!tracking ? 'Stop tracking' : 'Start tracking'">
                        <svg x-show="!tracking" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <svg x-show="tracking" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span x-text="!tracking ? 'Tracking ON' : 'Tracking OFF'"></span>
                    </button>
                    <button 
                        @click="startFilter(); $wire.clearTrades()" 
                        :disabled="tracking"
                        :class="tracking ? 'opacity-50 cursor-not-allowed' : 'hover:bg-red-500/30'"
                        class="px-3 py-1 text-xs font-medium bg-red-500/20 text-red-400 rounded-lg transition-colors cursor-pointer"
                        title="Clear all captured trades">
                        Clear
                    </button>
                </div>
            </div>
            <div class="flex items-center justify-between mb-4" :class="tracking && 'opacity-50 pointer-events-none'">
                <!-- Type Filter (Left) -->
                <div class="flex items-center space-x-1 bg-slate-800/50 rounded-lg p-1">
                    <button @click="startFilter(); $wire.setFilter('all')" :disabled="tracking" class="px-3 py-1 text-xs font-medium rounded transition-colors cursor-pointer {{ $filter === 'all' ? 'bg-blue-500 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-700/50' }}">All</button>
                    <button @click="startFilter(); $wire.setFilter('calls')" :disabled="tracking" class="px-3 py-1 text-xs font-medium rounded transition-colors cursor-pointer {{ $filter === 'calls' ? 'bg-emerald-500 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-700/50' }}">Calls</button>
                    <button @click="startFilter(); $wire.setFilter('puts')" :disabled="tracking" class="px-3 py-1 text-xs font-medium rounded transition-colors cursor-pointer {{ $filter === 'puts' ? 'bg-rose-500 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-700/50' }}">Puts</button>
                </div>

                <!-- DTE Filters (Right) -->
                <div class="flex items-center space-x-3">
                    <!-- DTE Filter -->
                    <div class="flex items-center space-x-1 bg-slate-800/50 rounded-lg p-1">
                        <button @click="startFilter(); $wire.setDteFilter('0dte')" :disabled="tracking" class="px-2 py-1 text-xs font-medium rounded transition-colors cursor-pointer {{ $dteFilter === '0dte' ? 'bg-purple-500 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-700/50' }}">0DTE</button>
                        <button @click="startFilter(); $wire.setDteFilter('1-7dte')" :disabled="tracking" class="px-2 py-1 text-xs font-medium rounded transition-colors cursor-pointer {{ $dteFilter === '1-7dte' ? 'bg-purple-500 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-700/50' }}">1-7</button>
                        <button @click="startFilter(); $wire.setDteFilter('8-30dte')" :disabled="tracking" class="px-2 py-1 text-xs font-medium rounded transition-colors cursor-pointer {{ $dteFilter === '8-30dte' ? 'bg-purple-500 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-700/50' }}">8-30</button>
                        <button @click="startFilter(); $wire.setDteFilter('31-60dte')" :disabled="tracking" class="px-2 py-1 text-xs font-medium rounded transition-colors cursor-pointer {{ $dteFilter === '31-60dte' ? 'bg-purple-500 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-700/50' }}">31-60</button>
                        <button @click="startFilter(); $wire.setDteFilter('60+dte')" :disabled="tracking" class="px-2 py-1 text-xs font-medium rounded transition-colors cursor-pointer {{ $dteFilter === '60+dte' ? 'bg-purple-500 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-700/50' }}">60+</button>
                        <button @click="startFilter(); $wire.setDteFilter('all')" :disabled="tracking" class="px-2 py-1 text-xs font-medium rounded transition-colors cursor-pointer {{ $dteFilter === 'all' ? 'bg-purple-500 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-700/50' }}">All</button>
                    </div>

                    <!-- Custom DTE Input -->
                    <div class="flex items-center space-x-2 bg-slate-800/50 rounded-lg p-1">
                        <input 
                            type="number" 
                            wire:model.live="customDte"
                            @input="startFilter()"
                            :disabled="tracking"
                            placeholder="Custom DTE" 
                            min="0"
                            class="w-24 px-2 py-1 text-xs bg-slate-700/50 text-white border border-slate-600 rounded focus:outline-none focus:border-purple-500 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        />
                        @if($customDte !== null)
                            <span class="px-2 py-1 text-xs font-medium bg-purple-500 text-white rounded">{{ $customDte }}DTE</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtering Overlay -->
        <div x-show="showFilter || filtering" 
             x-transition:enter="transition ease-out duration-100"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             class="absolute inset-0 bg-slate-900/80 backdrop-blur-sm rounded-lg flex items-center justify-center z-10"
             style="display: none;">
            <div class="flex items-center space-x-3 text-amber-400">
                <svg class="animate-spin h-6 w-6" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="text-sm font-medium">Filtering...</span>
            </div>
        </div>

        <!-- Contracts Container -->
        <div class="bg-slate-800/30 rounded-lg p-4 max-h-[600px] overflow-y-auto">
            @if(empty($unusualActivity))
                <div class="text-center py-8">
                <svg class="w-12 h-12 mx-auto text-slate-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                <p class="text-sm text-slate-400">No high volume contracts found</p>
                    <p class="text-xs text-slate-600 mt-1">Monitoring for contracts with volume ≥ 5...</p>
                </div>
            @else
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-3">
                @foreach($unusualActivity as $activity)
                    <div class="p-3 rounded-lg border transition-all duration-300 hover:scale-[1.01] {{ $activity['type'] === 'CALL' ? 'bg-emerald-500/5 border-emerald-500/30' : 'bg-rose-500/5 border-rose-500/30' }}">
                        <!-- Contract ID Header -->
                        <div class="mb-3 pb-3 border-b border-slate-700/50">
                            <div class="flex items-center justify-between mb-1">
                                <button 
                                    onclick="
                                        navigator.clipboard.writeText('{{ $activity['contractId'] ?? '' }}');
                                        const icon = this.querySelector('svg');
                                        const path = icon.querySelector('path');
                                        const text = this.querySelector('.contract-id-text');
                                        text.classList.add('text-blue-400');
                                        path.setAttribute('d', 'M5 13l4 4L19 7');
                                        setTimeout(() => {
                                            text.classList.remove('text-blue-400');
                                            path.setAttribute('d', 'M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3');
                                        }, 1000);
                                    "
                                    class="flex items-center space-x-2 cursor-pointer group"
                                    title="Click to copy">
                                    <p class="contract-id-text text-xs text-slate-400 font-mono group-hover:text-blue-400 transition-colors">
                                        ID: {{ $activity['contractId'] ?? 'N/A' }}
                                    </p>
                                    <svg class="w-4 h-4 text-slate-500 group-hover:text-blue-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path>
                                    </svg>
                                </button>
                                <span class="text-xs font-medium {{ $activity['type'] === 'CALL' ? 'text-emerald-400' : 'text-rose-400' }} uppercase">
                                    {{ $activity['type'] ?? 'N/A' }}
                                </span>
                            </div>
                            <p class="text-[10px] text-slate-600">Captured at {{ $activity['capturedAt'] ?? 'N/A' }}</p>
                        </div>
                        
                        <div class="flex items-start justify-between mb-2">
                            <!-- Left: Strike & Symbol -->
                            <div class="flex items-center space-x-2">
                                <div class="flex flex-col">
                                    <span class="text-[10px] text-slate-500 uppercase tracking-wide">Strike</span>
                                    <span class="text-xl font-bold text-white">
                                        {{ (int)($activity['strike'] ?? 0) }}
                                    </span>
                                    <span class="text-[10px] text-slate-600">{{ $activity['symbol'] ?? 'N/A' }}</span>
                                </div>

                                <!-- Action Badge -->
                                <div class="px-2 py-0.5 rounded {{ ($activity['action'] ?? '') === 'BUY' ? 'bg-blue-500/20 text-blue-400' : 'bg-orange-500/20 text-orange-400' }}">
                                    <span class="text-[10px] font-bold">{{ $activity['action'] }}</span>
                                </div>

                                <!-- Unusual Badge -->
                                @if($activity['isUnusual'])
                                    <span class="px-1.5 py-0.5 text-[9px] font-bold bg-amber-500/20 text-amber-400 rounded uppercase">
                                        Unusual
                                    </span>
                                @endif
                            </div>

                            <!-- Right: Premium -->
                            <div class="text-right">
                                <p class="text-[10px] text-slate-500 uppercase tracking-wide">Premium</p>
                                <p class="text-lg font-bold text-white">
                                    ${{ number_format($activity['premium'] / 1000, 0) }}K
                                </p>
                            </div>
                        </div>

                        <!-- Details Grid -->
                        <div class="grid grid-cols-4 gap-3 pt-3 border-t border-slate-700/30">
                            <div>
                                <p class="text-[10px] text-slate-500 uppercase tracking-wide mb-1">Volume</p>
                                <p class="text-sm font-semibold text-white">{{ number_format($activity['volume']) }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] text-slate-500 uppercase tracking-wide mb-1">OI</p>
                                <p class="text-sm font-semibold text-slate-400">{{ number_format($activity['openInterest']) }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] text-slate-500 uppercase tracking-wide mb-1">Vol/OI</p>
                                <p class="text-sm font-semibold {{ $activity['volumeOIRatio'] > 0.5 ? 'text-amber-400' : 'text-slate-400' }}">
                                    {{ number_format($activity['volumeOIRatio'] * 100, 0) }}%
                                </p>
                            </div>
                            <div>
                                <p class="text-[10px] text-slate-500 uppercase tracking-wide mb-1">IV</p>
                                <p class="text-sm font-semibold text-slate-400">{{ number_format($activity['impliedVolatility'] * 100, 0) }}%</p>
                            </div>
                        </div>

                        <!-- Price Details -->
                        <div class="flex items-center justify-between mt-2 pt-2 border-t border-slate-700/30">
                            <div class="flex items-center space-x-2 text-[10px]">
                                <span class="text-slate-500">Bid: <span class="text-white font-medium">${{ number_format($activity['bid'], 2) }}</span></span>
                                <span class="text-slate-500">Ask: <span class="text-white font-medium">${{ number_format($activity['ask'], 2) }}</span></span>
                                <span class="text-slate-500">Last: <span class="text-white font-bold">${{ number_format($activity['last'], 2) }}</span></span>
                            </div>
                            <div>
                                @if($activity['daysToExpiration'] === 0)
                                    <span class="px-1.5 py-0.5 text-[10px] font-bold bg-purple-500/20 text-purple-400 rounded">0DTE</span>
                                @else
                                    <span class="text-[10px] font-semibold text-purple-400">{{ $activity['daysToExpiration'] }}DTE</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
                </div>
            @endif
        </div>
    </x-card>
</div>

<div>
    <!-- Controls -->
    <div class="mb-6 space-y-4">
        <!-- Ticker Selector -->
        <div class="flex items-center flex-wrap gap-2">
            @foreach($tickers as $ticker)
                <div class="flex items-center space-x-1 px-3 py-1.5 bg-slate-800/50 rounded-lg border border-slate-700/50">
                    <span class="text-sm font-medium text-white">{{ $ticker }}</span>
                    <button wire:click="removeTicker('{{ $ticker }}')" class="text-slate-400 hover:text-rose-400 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            @endforeach
            
            @if($showTickerInput)
                <div class="relative">
                    <div class="flex items-center space-x-2">
                        <input 
                            type="text" 
                            wire:model.live="newTicker" 
                            wire:keydown.enter="addTicker"
                            placeholder="Search symbols..."
                            class="px-3 py-1.5 text-sm bg-slate-900 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 w-64"
                            autofocus
                        >
                        <button wire:click="addTicker" class="px-3 py-1.5 text-xs font-medium text-white bg-blue-500 hover:bg-blue-600 rounded-lg transition-colors">
                            Add
                        </button>
                        <button wire:click="toggleTickerInput" class="px-3 py-1.5 text-xs font-medium text-slate-400 hover:text-white transition-colors">
                            Cancel
                        </button>
                    </div>
                    
                    <!-- Search Results Dropdown -->
                    @if($showSearchResults && $searchResults->isNotEmpty())
                        <div class="absolute top-full left-0 mt-1 w-64 bg-slate-800 rounded-lg shadow-xl border border-slate-700 z-50 max-h-64 overflow-y-auto">
                            @foreach($searchResults as $result)
                                <button 
                                    wire:click="addTicker('{{ $result->ticker }}')"
                                    class="w-full px-4 py-2 text-left hover:bg-slate-700 transition-colors border-b border-slate-700/50 last:border-b-0"
                                >
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center space-x-2">
                                                <p class="text-sm font-semibold text-white">{{ $result->ticker }}</p>
                                                <span class="px-1.5 py-0.5 text-[10px] font-medium bg-emerald-500/20 text-emerald-400 rounded">Options</span>
                                            </div>
                                            <p class="text-xs text-slate-400 truncate">{{ $result->name }}</p>
                                        </div>
                                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                        </svg>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            @else
                <button wire:click="toggleTickerInput" class="px-3 py-1.5 text-xs font-medium text-slate-400 hover:text-white bg-slate-800/30 hover:bg-slate-800/50 rounded-lg border border-dashed border-slate-700 transition-colors">
                    + Add Symbol
                </button>
            @endif
        </div>
        
        <!-- Metric Selector -->
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <button 
                    wire:click="setMetric('volume')"
                    class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors {{ $metric === 'volume' ? 'bg-blue-500 text-white' : 'text-slate-400 hover:text-white bg-slate-800/50' }}"
                >
                    Volume
                </button>
                <button 
                    wire:click="setMetric('premium')"
                    class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors {{ $metric === 'premium' ? 'bg-blue-500 text-white' : 'text-slate-400 hover:text-white bg-slate-800/50' }}"
                >
                    Premium Flow
                </button>
                <button 
                    wire:click="setMetric('iv')"
                    class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors {{ $metric === 'iv' ? 'bg-blue-500 text-white' : 'text-slate-400 hover:text-white bg-slate-800/50' }}"
                >
                    IV
                </button>
            </div>
        </div>
    </div>

    <!-- Heatmap Grid -->
    <div class="glass rounded-xl border border-slate-800/50 p-6">
        @if($heatmapData->isEmpty())
            <div class="text-center py-12">
                <div class="mb-4">
                    <svg class="w-16 h-16 mx-auto text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                </div>
                <p class="text-sm font-medium text-slate-300 mb-2">No option data available</p>
                <p class="text-xs text-slate-500">The selected symbols don't have option contracts for this expiration date.</p>
                <p class="text-xs text-slate-500 mt-1">Try adding symbols like SPY, QQQ, AAPL, NVDA, or TSLA.</p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
                @foreach($heatmapData->groupBy('ticker') as $ticker => $cells)
                    <div class="space-y-2">
                        <h3 class="text-sm font-semibold text-white mb-3">{{ $ticker }}</h3>
                        
                        <div class="space-y-1">
                            @foreach($cells->sortBy('strike') as $cell)
                                <div 
                                    class="p-3 rounded-lg {{ $cell->getIntensity() }} border border-slate-700/30 hover:border-slate-500 hover:scale-105 transition-all cursor-pointer shadow-lg"
                                    title="Strike: ${{ number_format($cell->strike, 2) }} | Vol: {{ number_format($cell->volume) }} | Premium: ${{ number_format($cell->premiumFlow) }}"
                                >
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-bold {{ $cell->getTextColor() }}">
                                            ${{ number_format($cell->strike, 0) }}
                                        </span>
                                        <span class="text-xs font-medium {{ $cell->getTextColor() }} opacity-75">
                                            {{ $cell->optionType->label() }}
                                        </span>
                                    </div>
                                    <div class="mt-2 text-sm font-bold {{ $cell->getTextColor() }}">
                                        @if($metric === 'volume')
                                            {{ number_format($cell->volume) }}
                                        @elseif($metric === 'premium')
                                            ${{ number_format($cell->premiumFlow / 1000, 1) }}K
                                        @else
                                            {{ number_format($cell->impliedVolatility * 100, 0) }}%
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Legend -->
            <div class="mt-6 pt-6 border-t border-slate-800/50">
                <div class="flex items-center justify-between text-xs">
                    <div class="flex items-center space-x-6">
                        <div class="flex items-center space-x-2">
                            <span class="font-medium text-slate-400">Calls:</span>
                            <div class="flex items-center space-x-1">
                                <div class="w-6 h-4 rounded bg-emerald-500/50"></div>
                                <span class="text-slate-400">→</span>
                                <div class="w-6 h-4 rounded bg-emerald-600"></div>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="font-medium text-slate-400">Puts:</span>
                            <div class="flex items-center space-x-1">
                                <div class="w-6 h-4 rounded bg-rose-500/50"></div>
                                <span class="text-slate-400">→</span>
                                <div class="w-6 h-4 rounded bg-rose-600"></div>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="font-medium text-slate-400">Low Activity:</span>
                            <div class="w-6 h-4 rounded bg-slate-700/50"></div>
                        </div>
                    </div>
                    <div class="text-slate-500">
                        Intensity based on volume + premium flow
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

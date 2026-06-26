<div x-data="optionMonitor()" x-init="init()"
     @contract-selected.window="handleContractSelected($event.detail)"
     @contract-loaded.window="handleContractLoaded($event.detail)"
     @contracts-bulk-loaded.window="handleBulkLoaded($event.detail)">
    <x-card class="relative">
        <!-- Loading Overlay -->
        <div x-show="isLoading"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="absolute inset-0 bg-slate-900/80 backdrop-blur-sm z-50 flex items-center justify-center rounded-lg">
            <div class="text-center">
                <div class="inline-block animate-spin rounded-full h-12 w-12 border-4 border-purple-500 border-t-transparent mb-4"></div>
                <p class="text-white font-medium">Loading contract...</p>
                <p class="text-slate-400 text-sm mt-1">Connecting to stream</p>
            </div>
        </div>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-white">Live Option Monitor</h2>
            <div class="flex items-center space-x-2">
                <div class="flex items-center space-x-2">
                    <div :class="{
                        'w-2 h-2 rounded-full': true,
                        'bg-slate-500': connectionState === 'disconnected',
                        'bg-yellow-400 animate-pulse': connectionState === 'connecting' || connectionState === 'authenticating',
                        'bg-emerald-400': connectionState === 'streaming',
                        'bg-rose-400': connectionState === 'error'
                    }"></div>
                    <span class="text-xs text-slate-400" x-text="connectionState"></span>
                </div>
            </div>
        </div>

        @if($error)
            <div class="mb-4 p-3 bg-rose-500/10 border border-rose-500/20 rounded text-sm text-rose-400">
                {{ $error }}
            </div>
        @endif

        @if(!$hasTraderAccess)
            <div class="mb-4 p-3 bg-yellow-500/10 border border-yellow-500/20 rounded text-sm text-yellow-400">
                <p class="font-medium mb-1">Trader API Access Required</p>
                <p class="text-xs">This feature requires access to Schwab Trader API. Please ensure your app has the correct scopes.</p>
            </div>
        @endif

        <!-- Search + DTE Scanner in one row -->
        <div class="mb-3 grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-2xl">

            <!-- Left: manual contract search -->
            <div>
                <label class="block text-xs font-medium text-slate-400 mb-0.5">Add Option Contract</label>
                <div class="flex items-center gap-2">
                    <input
                        type="text"
                        x-model="contractInput"
                        @keydown.enter="loadContractQuick()"
                        placeholder="SPXW260505C7250"
                        class="w-48 px-2 py-1 text-xs bg-slate-700/50 text-white border border-slate-600 rounded focus:outline-none focus:border-purple-500 font-mono"
                    />
                    <button
                        @click="loadContractQuick()"
                        :disabled="contracts.length >= 50"
                        class="px-3 py-1 text-xs font-medium text-white bg-purple-500 hover:bg-purple-600 disabled:bg-slate-600 disabled:cursor-not-allowed rounded transition-colors"
                    >Add</button>
                </div>
                <p class="mt-0.5 text-xs text-slate-600">SYMBOL + YYMMDD + C/P + STRIKE &nbsp;·&nbsp; max 50</p>
            </div>

            <!-- Right: DTE Scanner -->
            <div>
                <label class="block text-xs font-medium text-slate-400 mb-0.5">DTE Scanner</label>
                <div class="flex items-center gap-2">
                    <label class="text-xs text-slate-500">Min</label>
                    <input type="number" x-model.number="dteMin" min="0" max="365"
                           class="w-12 px-1.5 py-1 text-xs bg-slate-700/50 text-white border border-slate-600 rounded focus:outline-none focus:border-purple-500 text-center" />
                    <label class="text-xs text-slate-500">Max</label>
                    <input type="number" x-model.number="dteMax" min="0" max="365"
                           class="w-12 px-1.5 py-1 text-xs bg-slate-700/50 text-white border border-slate-600 rounded focus:outline-none focus:border-purple-500 text-center" />
                    <label class="text-xs text-slate-500">±ATM</label>
                    <input type="number" x-model.number="dteStrikes" min="1" max="12"
                           class="w-12 px-1.5 py-1 text-xs bg-slate-700/50 text-white border border-slate-600 rounded focus:outline-none focus:border-purple-500 text-center" />
                    <button @click="runDteScanner()"
                            :disabled="isDteScanning || contracts.length >= 50"
                            class="px-3 py-1 text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:bg-slate-600 disabled:cursor-not-allowed rounded transition-colors flex items-center gap-1">
                        <svg x-show="isDteScanning" class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span x-text="isDteScanning ? 'Scanning…' : 'Scan'"></span>
                    </button>
                </div>
            </div>

        </div>

        <!-- Market Sentiment Analysis -->
        <div x-show="contracts.length > 0" class="mt-6 mb-6">
            <div class="p-4 bg-gradient-to-r from-slate-800/50 to-slate-700/50 rounded-lg border border-slate-600">
                <h3 class="text-sm font-semibold text-white mb-4 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    Market Sentiment Analysis
                </h3>

                <!-- Premium Comparison Bars -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <!-- CALLS Bar -->
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-xs font-medium text-emerald-400">📈 CALLS Net Premium</span>
                            <span class="text-sm font-bold text-emerald-400" x-text="'$' + callFlowStats.netPremium.toLocaleString()"></span>
                        </div>
                        <div class="h-3 bg-slate-700 rounded-full overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-emerald-500 to-emerald-400 transition-all duration-500"
                                 :style="'width: ' + Math.min(100, (Math.abs(callFlowStats.netPremium) / Math.max(Math.abs(callFlowStats.netPremium) + Math.abs(putFlowStats.netPremium), 1)) * 100) + '%'"></div>
                        </div>
                    </div>

                    <!-- PUTS Bar -->
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-xs font-medium text-rose-400">📉 PUTS Net Premium</span>
                            <span class="text-sm font-bold text-rose-400" x-text="'$' + putFlowStats.netPremium.toLocaleString()"></span>
                        </div>
                        <div class="h-3 bg-slate-700 rounded-full overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-rose-500 to-rose-400 transition-all duration-500"
                                 :style="'width: ' + Math.min(100, (Math.abs(putFlowStats.netPremium) / Math.max(Math.abs(callFlowStats.netPremium) + Math.abs(putFlowStats.netPremium), 1)) * 100) + '%'"></div>
                        </div>
                    </div>
                </div>

                <!-- Sentiment Indicator -->
                <div class="p-4 rounded-lg mb-4" :class="{
                    'bg-emerald-500/20 border border-emerald-500/30': marketSentiment.type === 'very_bullish' || marketSentiment.type === 'bullish',
                    'bg-rose-500/20 border border-rose-500/30': marketSentiment.type === 'very_bearish' || marketSentiment.type === 'bearish',
                    'bg-blue-500/20 border border-blue-500/30': marketSentiment.type === 'neutral',
                    'bg-purple-500/20 border border-purple-500/30': marketSentiment.type === 'volatile'
                }">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center">
                            <span class="text-2xl mr-3" x-text="marketSentiment.emoji"></span>
                            <div>
                                <h4 class="text-lg font-bold" :class="{
                                    'text-emerald-400': marketSentiment.type === 'very_bullish' || marketSentiment.type === 'bullish',
                                    'text-rose-400': marketSentiment.type === 'very_bearish' || marketSentiment.type === 'bearish',
                                    'text-blue-400': marketSentiment.type === 'neutral',
                                    'text-purple-400': marketSentiment.type === 'volatile'
                                }" x-text="marketSentiment.label"></h4>
                                <p class="text-xs text-slate-400" x-text="'Ratio: ' + marketSentiment.ratio"></p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-slate-400">Confidence</p>
                            <p class="text-lg font-bold text-white" x-text="marketSentiment.confidence + '%'"></p>
                        </div>
                    </div>

                    <!-- Explanation -->
                    <div class="bg-slate-900/50 rounded p-3 mb-3">
                        <p class="text-sm text-slate-300 mb-2" x-html="marketSentiment.explanation"></p>
                    </div>

                    <!-- Price Targets -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                        <!-- Upside Target -->
                        <div class="bg-slate-900/50 rounded p-3 border-l-4 border-emerald-500">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <p class="text-xs text-slate-400 mb-1">⬆️ Upside Target</p>
                                    <p class="text-2xl font-bold text-emerald-400" x-text="marketSentiment.upsideTarget || 'N/A'"></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs text-slate-400">Probability</p>
                                    <p class="text-lg font-bold text-emerald-400" x-text="marketSentiment.upsideProbability + '%'"></p>
                                </div>
                            </div>
                            <p class="text-xs text-slate-500" x-show="marketSentiment.upsideTarget">
                                Based on $<span x-text="(callFlowStats.totalPremium / 1000).toFixed(0)"></span>K CALL premium (today)
                            </p>
                        </div>

                        <!-- Downside Target -->
                        <div class="bg-slate-900/50 rounded p-3 border-l-4 border-rose-500">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <p class="text-xs text-slate-400 mb-1">⬇️ Downside Target</p>
                                    <p class="text-2xl font-bold text-rose-400" x-text="marketSentiment.downsideTarget || 'N/A'"></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs text-slate-400">Probability</p>
                                    <p class="text-lg font-bold text-rose-400" x-text="marketSentiment.downsideProbability + '%'"></p>
                                </div>
                            </div>
                            <p class="text-xs text-slate-500" x-show="marketSentiment.downsideTarget">
                                Based on $<span x-text="(putFlowStats.totalPremium / 1000).toFixed(0)"></span>K PUT premium (today)
                            </p>
                        </div>
                    </div>

                    <!-- Expected Range Visualization -->
                    <div class="bg-slate-900/50 rounded p-3">
                        <p class="text-xs text-slate-400 mb-2">Expected Range</p>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-bold text-rose-400" x-text="marketSentiment.downsideTarget || '?'"></span>
                            <span class="text-xs text-slate-500">←──────────→</span>
                            <span class="text-sm font-bold text-emerald-400" x-text="marketSentiment.upsideTarget || '?'"></span>
                        </div>
                        <div class="h-2 bg-slate-700 rounded-full overflow-hidden relative">
                            <div class="absolute inset-0 bg-gradient-to-r from-rose-500 via-slate-600 to-emerald-500"></div>
                        </div>
                        <p class="text-xs text-slate-500 mt-2 text-center">
                            Range: <span class="text-white font-semibold" x-text="marketSentiment.expectedRange"></span>
                        </p>
                    </div>

                    <!-- Key Levels (Compact) -->
                    <div class="grid grid-cols-2 gap-2 mt-3">
                        <div class="bg-emerald-500/10 rounded p-2 border border-emerald-500/20">
                            <p class="text-xs text-slate-400">Key CALL Level</p>
                            <p class="text-sm font-bold text-emerald-400" x-text="marketSentiment.callLevel || 'N/A'"></p>
                        </div>
                        <div class="bg-rose-500/10 rounded p-2 border border-rose-500/20">
                            <p class="text-xs text-slate-400">Key PUT Level</p>
                            <p class="text-sm font-bold text-rose-400" x-text="marketSentiment.putLevel || 'N/A'"></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Two Column Layout: CALLS | PUTS -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- CALLS Section -->
            <div x-show="callContracts.length > 0">
                <div class="mb-3 p-4 bg-emerald-900/20 rounded-lg border border-emerald-700/30">
                    <h3 class="text-sm font-semibold text-emerald-400 mb-3">
                        📈 CALLS Portfolio (<span x-text="callContracts.length"></span> contracts)
                    </h3>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="p-3 bg-purple-500/10 border border-purple-500/20 rounded">
                            <p class="text-xs text-slate-400">Total Premium (Today)</p>
                            <p class="text-lg font-bold text-purple-400" x-text="'$' + callFlowStats.totalPremium.toLocaleString()"></p>
                            <p class="text-xs text-slate-500" x-text="callFlowStats.trades + ' trades'"></p>
                        </div>
                        <div class="p-3 bg-emerald-500/10 border border-emerald-500/20 rounded">
                            <p class="text-xs text-slate-400">Buy Premium</p>
                            <p class="text-lg font-bold text-emerald-400" x-text="'$' + callFlowStats.buyPremium.toLocaleString()"></p>
                        </div>
                        <div class="p-3 bg-rose-500/10 border border-rose-500/20 rounded">
                            <p class="text-xs text-slate-400">Sell Premium</p>
                            <p class="text-lg font-bold text-rose-400" x-text="'$' + callFlowStats.sellPremium.toLocaleString()"></p>
                        </div>
                        <div class="p-3 bg-amber-500/10 border border-amber-500/20 rounded">
                            <p class="text-xs text-slate-400">MID Premium</p>
                            <p class="text-lg font-bold text-amber-400" x-text="'$' + (callStats.midPremium || 0).toLocaleString()"></p>
                            <p class="text-xs text-slate-500" x-text="(callStats.midTrades || 0) + ' trades'"></p>
                        </div>
                        <div class="p-3 border rounded" :class="{
                            'bg-emerald-500/10 border-emerald-500/20': callFlowStats.netPremium >= 0,
                            'bg-rose-500/10 border-rose-500/20': callFlowStats.netPremium < 0
                        }">
                            <p class="text-xs text-slate-400">Net Premium Flow</p>
                            <p class="text-lg font-bold" :class="{
                                'text-emerald-400': callFlowStats.netPremium >= 0,
                                'text-rose-400': callFlowStats.netPremium < 0
                            }" x-text="(callFlowStats.netPremium >= 0 ? '+' : '') + '$' + callFlowStats.netPremium.toLocaleString()"></p>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <p class="text-xs text-emerald-400 mb-2">Active CALL Contracts</p>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="contract in callContracts" :key="contract.symbol">
                            <div @click="selectCallContract(contracts.indexOf(contract)); copyToClipboard(contract.userInputSymbol)"
                                 class="cursor-pointer px-3 py-2 rounded-lg transition-all"
                                 :class="{
                                     'bg-emerald-500/30 border-2 border-emerald-500': contracts.indexOf(contract) === selectedCallIndex,
                                     'bg-slate-700/30 border border-slate-600 hover:bg-slate-700/50': contracts.indexOf(contract) !== selectedCallIndex
                                 }">
                                <div class="flex items-center space-x-2">
                                    <span class="text-sm font-mono text-white" x-text="contract.userInputSymbol"></span>
                                    <span class="text-xs px-1.5 py-0.5 rounded" :class="{
                                        'bg-emerald-500/20 text-emerald-400': contract.detailedStats.netPremium >= 0,
                                        'bg-rose-500/20 text-rose-400': contract.detailedStats.netPremium < 0
                                    }" x-text="formatPremium(contract.detailedStats.netPremium)"></span>
                                    <button @click.stop="removeContract(contracts.indexOf(contract))"
                                            class="text-slate-400 hover:text-rose-400 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- CALLS Contract Details -->
                <div x-show="activeCallContract !== null">
                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div class="p-3 bg-slate-700/30 rounded">
                            <p class="text-xs text-slate-400">Bid</p>
                            <p class="text-lg font-semibold text-rose-400" x-text="activeCallContract?.quote.bid || '-'"></p>
                        </div>
                        <div class="p-3 bg-slate-700/30 rounded">
                            <p class="text-xs text-slate-400">Ask</p>
                            <p class="text-lg font-semibold text-emerald-400" x-text="activeCallContract?.quote.ask || '-'"></p>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-2 mb-3">
                        <div class="p-2 bg-emerald-500/10 border border-emerald-500/20 rounded">
                            <p class="text-xs text-slate-400">Buy Premium</p>
                            <p class="text-sm font-semibold text-emerald-400" x-text="'$' + (activeCallContract?.detailedStats.buyPremium || 0).toLocaleString()"></p>
                        </div>
                        <div class="p-2 bg-rose-500/10 border border-rose-500/20 rounded">
                            <p class="text-xs text-slate-400">Sell Premium</p>
                            <p class="text-sm font-semibold text-rose-400" x-text="'$' + (activeCallContract?.detailedStats.sellPremium || 0).toLocaleString()"></p>
                        </div>
                        <div class="p-2 bg-purple-500/10 border border-purple-500/20 rounded">
                            <p class="text-xs text-slate-400">Total Premium</p>
                            <p class="text-sm font-semibold text-purple-400" x-text="'$' + (activeCallContract?.detailedStats.totalPremium || 0).toLocaleString()"></p>
                        </div>
                    </div>
                    <div class="p-3 border rounded mb-4" :class="{
                        'bg-emerald-500/10 border-emerald-500/20': (activeCallContract?.detailedStats.netPremium || 0) >= 0,
                        'bg-rose-500/10 border-rose-500/20': (activeCallContract?.detailedStats.netPremium || 0) < 0
                    }">
                        <p class="text-xs text-slate-400">Net Premium Flow</p>
                        <p class="text-2xl font-bold" :class="{
                            'text-emerald-400': (activeCallContract?.detailedStats.netPremium || 0) >= 0,
                            'text-rose-400': (activeCallContract?.detailedStats.netPremium || 0) < 0
                        }" x-text="((activeCallContract?.detailedStats.netPremium || 0) >= 0 ? '+' : '') + '$' + (activeCallContract?.detailedStats.netPremium || 0).toLocaleString()"></p>
                    </div>
                    <button @click="showCallDetails = !showCallDetails" class="w-full px-4 py-2 text-sm font-medium text-slate-400 hover:text-white bg-slate-700/30 hover:bg-slate-700/50 rounded transition-colors">
                        <span x-text="showCallDetails ? 'Hide Details' : 'View Details'"></span>
                    </button>

                    <!-- Expanded Details -->
                    <div x-show="showCallDetails" x-transition class="mt-4 space-y-4">
                        <!-- Greeks -->
                        <div class="grid grid-cols-2 md:grid-cols-5 gap-2">
                            <div class="p-2 bg-slate-700/20 rounded">
                                <p class="text-xs text-slate-500">Delta</p>
                                <p class="text-sm font-medium text-slate-300" x-text="activeCallContract?.quote.delta ? Math.abs(activeCallContract.quote.delta).toFixed(2) : '-'"></p>
                            </div>
                            <div class="p-2 bg-slate-700/20 rounded">
                                <p class="text-xs text-slate-500">Gamma</p>
                                <p class="text-sm font-medium text-slate-300" x-text="activeCallContract?.quote.gamma ? activeCallContract.quote.gamma.toFixed(2) : '-'"></p>
                            </div>
                            <div class="p-2 bg-slate-700/20 rounded">
                                <p class="text-xs text-slate-500">Theta</p>
                                <p class="text-sm font-medium text-slate-300" x-text="activeCallContract?.quote.theta ? activeCallContract.quote.theta.toFixed(2) : '-'"></p>
                            </div>
                            <div class="p-2 bg-slate-700/20 rounded">
                                <p class="text-xs text-slate-500">Vega</p>
                                <p class="text-sm font-medium text-slate-300" x-text="activeCallContract?.quote.vega ? activeCallContract.quote.vega.toFixed(2) : '-'"></p>
                            </div>
                            <div class="p-2 bg-slate-700/20 rounded">
                                <p class="text-xs text-slate-500">IV</p>
                                <p class="text-sm font-medium text-slate-300" x-text="activeCallContract?.quote.iv ? (activeCallContract.quote.iv * 100).toFixed(2) + '%' : '-'"></p>
                            </div>
                        </div>

                        <!-- Volume Stats -->
                        <div class="grid grid-cols-3 gap-2">
                            <div class="p-2 bg-emerald-500/10 border border-emerald-500/20 rounded">
                                <p class="text-xs text-slate-400">Ask-Side</p>
                                <p class="text-sm font-semibold text-emerald-400" x-text="activeCallContract?.detailedStats.askSideVolume || 0"></p>
                                <p class="text-xs text-slate-500" x-text="(activeCallContract?.detailedStats.askSidePercent || 0) + '%'"></p>
                            </div>
                            <div class="p-2 bg-rose-500/10 border border-rose-500/20 rounded">
                                <p class="text-xs text-slate-400">Bid-Side</p>
                                <p class="text-sm font-semibold text-rose-400" x-text="activeCallContract?.detailedStats.bidSideVolume || 0"></p>
                                <p class="text-xs text-slate-500" x-text="(activeCallContract?.detailedStats.bidSidePercent || 0) + '%'"></p>
                            </div>
                            <div class="p-2 bg-amber-500/10 border border-amber-500/20 rounded">
                                <p class="text-xs text-slate-400">Mid</p>
                                <p class="text-sm font-semibold text-amber-400" x-text="activeCallContract?.detailedStats.midVolume || 0"></p>
                                <p class="text-xs text-slate-500" x-text="(activeCallContract?.detailedStats.midPercent || 0) + '%'"></p>
                            </div>
                        </div>

                        <!-- MID Premium & Aggressiveness -->
                        <div class="grid grid-cols-2 gap-2">
                            <div class="p-3 bg-amber-500/10 border border-amber-500/20 rounded">
                                <p class="text-xs text-slate-400">MID Premium</p>
                                <p class="text-lg font-bold text-amber-400" x-text="'$' + ((activeCallContract?.detailedStats.midPremium || 0)).toLocaleString()"></p>
                                <p class="text-xs text-slate-500" x-text="(activeCallContract?.detailedStats.midTrades || 0) + ' trades'"></p>
                            </div>
                            <div class="p-3 bg-slate-700/20 border border-slate-600 rounded">
                                <p class="text-xs text-slate-400 mb-1">Avg Aggressiveness</p>
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 h-2 bg-slate-700 rounded-full overflow-hidden">
                                        <div class="h-full bg-gradient-to-r from-rose-500 via-amber-500 to-emerald-500 transition-all duration-300"
                                             :style="'width: ' + ((activeCallContract?.detailedStats.avgAggressiveness || 0.5) * 100) + '%'"></div>
                                    </div>
                                    <span class="text-sm font-mono text-white" x-text="((activeCallContract?.detailedStats.avgAggressiveness || 0.5) * 100).toFixed(0) + '%'"></span>
                                </div>
                                <p class="text-xs text-slate-500 mt-1">0% = Bid | 100% = Ask</p>
                            </div>
                        </div>

                        <!-- Prints Table -->
                        <div class="bg-slate-700/20 rounded-lg overflow-hidden">
                            <div class="px-3 py-2 bg-slate-700/50 border-b border-slate-600 flex justify-between items-center">
                                <h4 class="text-xs font-semibold text-white">Live Prints</h4>
                                <button @click="loadHistoricalPrints(activeCallContract)"
                                        x-show="activeCallContract"
                                        class="px-2 py-1 text-xs bg-purple-500/20 hover:bg-purple-500/30 text-purple-400 rounded border border-purple-500/30 transition-colors">
                                    📜 History
                                </button>
                            </div>
                            <div class="max-h-64 overflow-y-auto">
                                <table class="w-full text-xs">
                                    <thead class="bg-slate-700/30 sticky top-0">
                                        <tr>
                                            <th class="px-2 py-1 text-left text-slate-400 font-medium">Time</th>
                                            <th class="px-2 py-1 text-right text-slate-400 font-medium">Price</th>
                                            <th class="px-2 py-1 text-right text-slate-400 font-medium">Size</th>
                                            <th class="px-2 py-1 text-right text-slate-400 font-medium">Premium</th>
                                            <th class="px-2 py-1 text-center text-slate-400 font-medium">Side</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="print in (activeCallContract?.prints || [])" :key="print.sequence">
                                            <tr class="border-b border-slate-700/30 hover:bg-slate-700/20">
                                                <td class="px-2 py-1 text-slate-300" x-text="print.time"></td>
                                                <td class="px-2 py-1 text-right font-mono text-white" x-text="print.price.toFixed(2)"></td>
                                                <td class="px-2 py-1 text-right text-slate-300" x-text="print.size"></td>
                                                <td class="px-2 py-1 text-right text-slate-300" x-text="'$' + print.premium.toLocaleString()"></td>
                                                <td class="px-2 py-1 text-center">
                                                    <span :class="{
                                                        'px-1.5 py-0.5 rounded text-xs font-medium': true,
                                                        'bg-emerald-500/20 text-emerald-400': print.side === 'ASK',
                                                        'bg-rose-500/20 text-rose-400': print.side === 'BID',
                                                        'bg-blue-500/20 text-blue-400': print.side === 'MID'
                                                    }" x-text="print.side"></span>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                                <div x-show="!activeCallContract || activeCallContract.prints.length === 0" class="p-4 text-center text-slate-500">
                                    <p class="text-xs">No prints yet...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PUTS Section -->
            <div x-show="putContracts.length > 0">
                <div class="mb-3 p-4 bg-rose-900/20 rounded-lg border border-rose-700/30">
                    <h3 class="text-sm font-semibold text-rose-400 mb-3">
                        📉 PUTS Portfolio (<span x-text="putContracts.length"></span> contracts)
                    </h3>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="p-3 bg-purple-500/10 border border-purple-500/20 rounded">
                            <p class="text-xs text-slate-400">Total Premium (Today)</p>
                            <p class="text-lg font-bold text-purple-400" x-text="'$' + putFlowStats.totalPremium.toLocaleString()"></p>
                            <p class="text-xs text-slate-500" x-text="putFlowStats.trades + ' trades'"></p>
                        </div>
                        <div class="p-3 bg-emerald-500/10 border border-emerald-500/20 rounded">
                            <p class="text-xs text-slate-400">Buy Premium</p>
                            <p class="text-lg font-bold text-emerald-400" x-text="'$' + putFlowStats.buyPremium.toLocaleString()"></p>
                        </div>
                        <div class="p-3 bg-rose-500/10 border border-rose-500/20 rounded">
                            <p class="text-xs text-slate-400">Sell Premium</p>
                            <p class="text-lg font-bold text-rose-400" x-text="'$' + putFlowStats.sellPremium.toLocaleString()"></p>
                        </div>
                        <div class="p-3 bg-amber-500/10 border border-amber-500/20 rounded">
                            <p class="text-xs text-slate-400">MID Premium</p>
                            <p class="text-lg font-bold text-amber-400" x-text="'$' + (putStats.midPremium || 0).toLocaleString()"></p>
                            <p class="text-xs text-slate-500" x-text="(putStats.midTrades || 0) + ' trades'"></p>
                        </div>
                        <div class="p-3 border rounded" :class="{
                            'bg-emerald-500/10 border-emerald-500/20': putFlowStats.netPremium >= 0,
                            'bg-rose-500/10 border-rose-500/20': putFlowStats.netPremium < 0
                        }">
                            <p class="text-xs text-slate-400">Net Premium Flow</p>
                            <p class="text-lg font-bold" :class="{
                                'text-emerald-400': putFlowStats.netPremium >= 0,
                                'text-rose-400': putFlowStats.netPremium < 0
                            }" x-text="(putFlowStats.netPremium >= 0 ? '+' : '') + '$' + putFlowStats.netPremium.toLocaleString()"></p>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <p class="text-xs text-rose-400 mb-2">Active PUT Contracts</p>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="contract in putContracts" :key="contract.symbol">
                            <div @click="selectPutContract(contracts.indexOf(contract)); copyToClipboard(contract.userInputSymbol)"
                                 class="cursor-pointer px-3 py-2 rounded-lg transition-all"
                                 :class="{
                                     'bg-rose-500/30 border-2 border-rose-500': contracts.indexOf(contract) === selectedPutIndex,
                                     'bg-slate-700/30 border border-slate-600 hover:bg-slate-700/50': contracts.indexOf(contract) !== selectedPutIndex
                                 }">
                                <div class="flex items-center space-x-2">
                                    <span class="text-sm font-mono text-white" x-text="contract.userInputSymbol"></span>
                                    <span class="text-xs px-1.5 py-0.5 rounded" :class="{
                                        'bg-emerald-500/20 text-emerald-400': contract.detailedStats.netPremium >= 0,
                                        'bg-rose-500/20 text-rose-400': contract.detailedStats.netPremium < 0
                                    }" x-text="formatPremium(contract.detailedStats.netPremium)"></span>
                                    <button @click.stop="removeContract(contracts.indexOf(contract))"
                                            class="text-slate-400 hover:text-rose-400 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- PUTS Contract Details -->
                <div x-show="activePutContract !== null">
                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div class="p-3 bg-slate-700/30 rounded">
                            <p class="text-xs text-slate-400">Bid</p>
                            <p class="text-lg font-semibold text-rose-400" x-text="activePutContract?.quote.bid || '-'"></p>
                        </div>
                        <div class="p-3 bg-slate-700/30 rounded">
                            <p class="text-xs text-slate-400">Ask</p>
                            <p class="text-lg font-semibold text-emerald-400" x-text="activePutContract?.quote.ask || '-'"></p>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-2 mb-3">
                        <div class="p-2 bg-emerald-500/10 border border-emerald-500/20 rounded">
                            <p class="text-xs text-slate-400">Buy Premium</p>
                            <p class="text-sm font-semibold text-emerald-400" x-text="'$' + (activePutContract?.detailedStats.buyPremium || 0).toLocaleString()"></p>
                        </div>
                        <div class="p-2 bg-rose-500/10 border border-rose-500/20 rounded">
                            <p class="text-xs text-slate-400">Sell Premium</p>
                            <p class="text-sm font-semibold text-rose-400" x-text="'$' + (activePutContract?.detailedStats.sellPremium || 0).toLocaleString()"></p>
                        </div>
                        <div class="p-2 bg-purple-500/10 border border-purple-500/20 rounded">
                            <p class="text-xs text-slate-400">Total Premium</p>
                            <p class="text-sm font-semibold text-purple-400" x-text="'$' + (activePutContract?.detailedStats.totalPremium || 0).toLocaleString()"></p>
                        </div>
                    </div>
                    <div class="p-3 border rounded mb-4" :class="{
                        'bg-emerald-500/10 border-emerald-500/20': (activePutContract?.detailedStats.netPremium || 0) >= 0,
                        'bg-rose-500/10 border-rose-500/20': (activePutContract?.detailedStats.netPremium || 0) < 0
                    }">
                        <p class="text-xs text-slate-400">Net Premium Flow</p>
                        <p class="text-2xl font-bold" :class="{
                            'text-emerald-400': (activePutContract?.detailedStats.netPremium || 0) >= 0,
                            'text-rose-400': (activePutContract?.detailedStats.netPremium || 0) < 0
                        }" x-text="((activePutContract?.detailedStats.netPremium || 0) >= 0 ? '+' : '') + '$' + (activePutContract?.detailedStats.netPremium || 0).toLocaleString()"></p>
                    </div>
                    <button @click="showPutDetails = !showPutDetails" class="w-full px-4 py-2 text-sm font-medium text-slate-400 hover:text-white bg-slate-700/30 hover:bg-slate-700/50 rounded transition-colors">
                        <span x-text="showPutDetails ? 'Hide Details' : 'View Details'"></span>
                    </button>

                    <!-- Expanded Details -->
                    <div x-show="showPutDetails" x-transition class="mt-4 space-y-4">
                        <!-- Greeks -->
                        <div class="grid grid-cols-2 md:grid-cols-5 gap-2">
                            <div class="p-2 bg-slate-700/20 rounded">
                                <p class="text-xs text-slate-500">Delta</p>
                                <p class="text-sm font-medium text-slate-300" x-text="activePutContract?.quote.delta ? Math.abs(activePutContract.quote.delta).toFixed(2) : '-'"></p>
                            </div>
                            <div class="p-2 bg-slate-700/20 rounded">
                                <p class="text-xs text-slate-500">Gamma</p>
                                <p class="text-sm font-medium text-slate-300" x-text="activePutContract?.quote.gamma ? activePutContract.quote.gamma.toFixed(2) : '-'"></p>
                            </div>
                            <div class="p-2 bg-slate-700/20 rounded">
                                <p class="text-xs text-slate-500">Theta</p>
                                <p class="text-sm font-medium text-slate-300" x-text="activePutContract?.quote.theta ? activePutContract.quote.theta.toFixed(2) : '-'"></p>
                            </div>
                            <div class="p-2 bg-slate-700/20 rounded">
                                <p class="text-xs text-slate-500">Vega</p>
                                <p class="text-sm font-medium text-slate-300" x-text="activePutContract?.quote.vega ? activePutContract.quote.vega.toFixed(2) : '-'"></p>
                            </div>
                            <div class="p-2 bg-slate-700/20 rounded">
                                <p class="text-xs text-slate-500">IV</p>
                                <p class="text-sm font-medium text-slate-300" x-text="activePutContract?.quote.iv ? (activePutContract.quote.iv * 100).toFixed(2) + '%' : '-'"></p>
                            </div>
                        </div>

                        <!-- Volume Stats -->
                        <div class="grid grid-cols-3 gap-2">
                            <div class="p-2 bg-emerald-500/10 border border-emerald-500/20 rounded">
                                <p class="text-xs text-slate-400">Ask-Side</p>
                                <p class="text-sm font-semibold text-emerald-400" x-text="activePutContract?.detailedStats.askSideVolume || 0"></p>
                                <p class="text-xs text-slate-500" x-text="(activePutContract?.detailedStats.askSidePercent || 0) + '%'"></p>
                            </div>
                            <div class="p-2 bg-rose-500/10 border border-rose-500/20 rounded">
                                <p class="text-xs text-slate-400">Bid-Side</p>
                                <p class="text-sm font-semibold text-rose-400" x-text="activePutContract?.detailedStats.bidSideVolume || 0"></p>
                                <p class="text-xs text-slate-500" x-text="(activePutContract?.detailedStats.bidSidePercent || 0) + '%'"></p>
                            </div>
                            <div class="p-2 bg-amber-500/10 border border-amber-500/20 rounded">
                                <p class="text-xs text-slate-400">Mid</p>
                                <p class="text-sm font-semibold text-amber-400" x-text="activePutContract?.detailedStats.midVolume || 0"></p>
                                <p class="text-xs text-slate-500" x-text="(activePutContract?.detailedStats.midPercent || 0) + '%'"></p>
                            </div>
                        </div>

                        <!-- MID Premium & Aggressiveness -->
                        <div class="grid grid-cols-2 gap-2">
                            <div class="p-3 bg-amber-500/10 border border-amber-500/20 rounded">
                                <p class="text-xs text-slate-400">MID Premium</p>
                                <p class="text-lg font-bold text-amber-400" x-text="'$' + ((activePutContract?.detailedStats.midPremium || 0)).toLocaleString()"></p>
                                <p class="text-xs text-slate-500" x-text="(activePutContract?.detailedStats.midTrades || 0) + ' trades'"></p>
                            </div>
                            <div class="p-3 bg-slate-700/20 border border-slate-600 rounded">
                                <p class="text-xs text-slate-400 mb-1">Avg Aggressiveness</p>
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 h-2 bg-slate-700 rounded-full overflow-hidden">
                                        <div class="h-full bg-gradient-to-r from-rose-500 via-amber-500 to-emerald-500 transition-all duration-300"
                                             :style="'width: ' + ((activePutContract?.detailedStats.avgAggressiveness || 0.5) * 100) + '%'"></div>
                                    </div>
                                    <span class="text-sm font-mono text-white" x-text="((activePutContract?.detailedStats.avgAggressiveness || 0.5) * 100).toFixed(0) + '%'"></span>
                                </div>
                                <p class="text-xs text-slate-500 mt-1">0% = Bid | 100% = Ask</p>
                            </div>
                        </div>

                        <!-- Prints Table -->
                        <div class="bg-slate-700/20 rounded-lg overflow-hidden">
                            <div class="px-3 py-2 bg-slate-700/50 border-b border-slate-600 flex justify-between items-center">
                                <h4 class="text-xs font-semibold text-white">Live Prints</h4>
                                <button @click="loadHistoricalPrints(activePutContract)"
                                        x-show="activePutContract"
                                        class="px-2 py-1 text-xs bg-purple-500/20 hover:bg-purple-500/30 text-purple-400 rounded border border-purple-500/30 transition-colors">
                                    📜 History
                                </button>
                            </div>
                            <div class="max-h-64 overflow-y-auto">
                                <table class="w-full text-xs">
                                    <thead class="bg-slate-700/30 sticky top-0">
                                        <tr>
                                            <th class="px-2 py-1 text-left text-slate-400 font-medium">Time</th>
                                            <th class="px-2 py-1 text-right text-slate-400 font-medium">Price</th>
                                            <th class="px-2 py-1 text-right text-slate-400 font-medium">Size</th>
                                            <th class="px-2 py-1 text-right text-slate-400 font-medium">Premium</th>
                                            <th class="px-2 py-1 text-center text-slate-400 font-medium">Side</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="print in (activePutContract?.prints || [])" :key="print.sequence">
                                            <tr class="border-b border-slate-700/30 hover:bg-slate-700/20">
                                                <td class="px-2 py-1 text-slate-300" x-text="print.time"></td>
                                                <td class="px-2 py-1 text-right font-mono text-white" x-text="print.price.toFixed(2)"></td>
                                                <td class="px-2 py-1 text-right text-slate-300" x-text="print.size"></td>
                                                <td class="px-2 py-1 text-right text-slate-300" x-text="'$' + print.premium.toLocaleString()"></td>
                                                <td class="px-2 py-1 text-center">
                                                    <span :class="{
                                                        'px-1.5 py-0.5 rounded text-xs font-medium': true,
                                                        'bg-emerald-500/20 text-emerald-400': print.side === 'ASK',
                                                        'bg-rose-500/20 text-rose-400': print.side === 'BID',
                                                        'bg-blue-500/20 text-blue-400': print.side === 'MID'
                                                    }" x-text="print.side"></span>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                                <div x-show="!activePutContract || activePutContract.prints.length === 0" class="p-4 text-center text-slate-500">
                                    <p class="text-xs">No prints yet...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-card>

    <!-- Notification Toast -->
    <div x-show="showNotification"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform translate-y-2"
         x-transition:enter-end="opacity-100 transform translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 transform translate-y-0"
         x-transition:leave-end="opacity-0 transform translate-y-2"
         class="fixed bottom-4 right-4 bg-emerald-500/20 border border-emerald-500/30 text-emerald-400 px-4 py-2 rounded-lg shadow-lg text-sm z-50">
        <span x-text="notificationMessage"></span>
    </div>
</div>

<script>
function optionMonitor() {
    return {
        ws: null,
        isMonitoring: false,
        isLoading: false,
        connectionState: 'disconnected',
        contractInput: '',
        credentials: null,
        showCallDetails: false,
        showPutDetails: false,
        showDteScanner: false,
        dteMin: 0,
        dteMax: 0,
        dteStrikes: 3,
        isDteScanning: false,
        showNotification: false,
        notificationMessage: '',
        notificationTimer: null,

        // Multiple contracts support
        contracts: [],
        selectedCallIndex: null,
        selectedPutIndex: null,
        selectedIndex: null,

        // Global stats (sum of all contracts)
        globalStats: {
            totalPremium: 0,
            buyPremium: 0,
            sellPremium: 0,
            netPremium: 0,
            totalTrades: 0
        },

        // Force reactivity trigger
        lastUpdate: 0,

        // Computed properties for active contracts
        get activeCallContract() {
            return this.selectedCallIndex !== null ? this.contracts[this.selectedCallIndex] : null;
        },

        get activePutContract() {
            return this.selectedPutIndex !== null ? this.contracts[this.selectedPutIndex] : null;
        },

        // Unified active contract for backwards compatibility
        get activeContract() {
            return this.selectedIndex !== null ? this.contracts[this.selectedIndex] : null;
        },

        // Computed properties for CALLS and PUTS
        get callContracts() {
            return this.contracts.filter(c => this.isCall(c.symbol));
        },

        get putContracts() {
            return this.contracts.filter(c => !this.isCall(c.symbol));
        },

        get callStats() {
            return this.calculateGroupStats(this.callContracts);
        },

        get putStats() {
            return this.calculateGroupStats(this.putContracts);
        },

        get callFlowStats() {
            // Today's flow only (real-time WebSocket data)
            return this.callContracts.reduce((acc, contract) => {
                acc.buyPremium += contract.todayFlow.buyPremium;
                acc.sellPremium += contract.todayFlow.sellPremium;
                acc.netPremium += contract.todayFlow.netPremium;
                acc.totalPremium += contract.todayFlow.totalPremium;
                acc.trades += contract.todayFlow.trades;
                return acc;
            }, { buyPremium: 0, sellPremium: 0, netPremium: 0, totalPremium: 0, trades: 0 });
        },

        get putFlowStats() {
            // Today's flow only (real-time WebSocket data)
            return this.putContracts.reduce((acc, contract) => {
                acc.buyPremium += contract.todayFlow.buyPremium;
                acc.sellPremium += contract.todayFlow.sellPremium;
                acc.netPremium += contract.todayFlow.netPremium;
                acc.totalPremium += contract.todayFlow.totalPremium;
                acc.trades += contract.todayFlow.trades;
                return acc;
            }, { buyPremium: 0, sellPremium: 0, netPremium: 0, totalPremium: 0, trades: 0 });
        },

        copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                this.showNotificationMessage('Contract copied: ' + text);
            }).catch(err => {
                console.error('Failed to copy text:', err);
                this.showNotificationMessage('Failed to copy contract');
            });
        },

        showNotificationMessage(message) {
            // Clear any existing timer
            if (this.notificationTimer) {
                clearTimeout(this.notificationTimer);
            }

            this.notificationMessage = message;
            this.showNotification = true;

            // Hide after 2 seconds
            this.notificationTimer = setTimeout(() => {
                this.showNotification = false;
            }, 2000);
        },

        isCall(symbol) {
            // SPXW 260508C07390000 - C indica CALL, P indica PUT
            return symbol.includes('C0') || symbol.includes('C1') || symbol.includes('C2') ||
                   symbol.includes('C3') || symbol.includes('C4') || symbol.includes('C5') ||
                   symbol.includes('C6') || symbol.includes('C7') || symbol.includes('C8') || symbol.includes('C9');
        },

        calculateGroupStats(contracts) {
            return contracts.reduce((acc, contract) => {
                acc.totalPremium += contract.detailedStats.totalPremium;
                acc.buyPremium += contract.detailedStats.buyPremium;
                acc.sellPremium += contract.detailedStats.sellPremium;
                acc.netPremium += contract.detailedStats.netPremium;
                acc.totalTrades += contract.detailedStats.totalPrints;
                acc.midPremium += (contract.detailedStats.midPremium || 0);
                acc.midTrades += (contract.detailedStats.midTrades || 0);
                return acc;
            }, {
                totalPremium: 0,
                buyPremium: 0,
                sellPremium: 0,
                netPremium: 0,
                totalTrades: 0,
                midPremium: 0,
                midTrades: 0
            });
        },

        get marketSentiment() {
            // Force recalculation on data update
            const _ = this.lastUpdate;

            // Use today's flow (real-time WebSocket data) for sentiment analysis
            // This excludes potentially stale historical snapshot data
            const callNet = this.callFlowStats.netPremium;
            const putNet = this.putFlowStats.netPremium;

            // CRITICAL: netPremium interpretation (FIXED - ASK/BID were inverted)
            // - Positive callNet = buying calls (bullish pressure)
            // - Negative callNet = selling calls (bearish pressure)
            // - Positive putNet = buying puts (bearish pressure)
            // - Negative putNet = selling puts (bullish pressure)
            // Net sentiment = callNet - putNet
            // Positive netSentiment = bullish, Negative = bearish
            const netSentiment = callNet - putNet;
            const total = Math.abs(callNet) + Math.abs(putNet);

            if (total === 0) {
                return {
                    type: 'neutral',
                    label: 'No Data',
                    emoji: '⏳',
                    ratio: '0:0',
                    confidence: 0,
                    explanation: 'Waiting for market activity...',
                    expectedMove: 'Unknown',
                    callLevel: null,
                    putLevel: null
                };
            }

            // Calculate dominance as percentage of net sentiment vs total activity
            const dominance = (Math.abs(netSentiment) / total) * 100;

            // Calculate improved confidence with multiple factors
            const confidence = this.calculateConfidence(dominance);

            // Determinar tipo de sentimiento basado en netSentiment
            let type, label, emoji, explanation, expectedMove;
            const absCallNet = Math.abs(callNet);
            const absPutNet = Math.abs(putNet);

            if (netSentiment > total * 0.5) {
                // Very bullish: call buying dominates significantly
                type = 'very_bullish';
                label = 'Very Bullish';
                emoji = '🚀';
                const ratio = absCallNet / Math.max(absPutNet, 1);
                explanation = `<strong>Strong bullish pressure detected!</strong><br>
                    CALLS net premium ($${(callNet/1000).toFixed(0)}K) is ${ratio.toFixed(1)}x higher than PUTS ($${(putNet/1000).toFixed(0)}K).
                    Traders are aggressively buying calls, expecting a significant upward move.
                    The strikes with highest CALL activity act as <strong>magnetic resistance levels</strong>.`;
                expectedMove = 'Strong Upward';
            } else if (netSentiment > total * 0.2) {
                // Bullish: moderate call buying advantage
                type = 'bullish';
                label = 'Bullish';
                emoji = '📈';
                const advantage = ((callNet - putNet) / Math.max(putNet, 1) * 100).toFixed(0);
                explanation = `<strong>Moderate bullish bias.</strong><br>
                    CALLS net premium ($${(callNet/1000).toFixed(0)}K) exceeds PUTS ($${(putNet/1000).toFixed(0)}K) by ${advantage}%.
                    Market participants are positioning for upside, but with less conviction.
                    Watch for breakout above key CALL strikes.`;
                expectedMove = 'Moderate Upward';
            } else if (netSentiment > -total * 0.2) {
                // Neutral: balanced activity
                type = 'neutral';
                label = 'Neutral / Range-Bound';
                emoji = '⚖️';
                explanation = `<strong>Balanced premium flow.</strong><br>
                    CALLS ($${(callNet/1000).toFixed(0)}K) and PUTS ($${(putNet/1000).toFixed(0)}K) are nearly equal,
                    suggesting market indecision. Price likely to consolidate between the most active CALL and PUT strikes.
                    Breakout direction unclear.`;
                expectedMove = 'Sideways / Range';
            } else if (netSentiment > -total * 0.5) {
                // Bearish: moderate put buying advantage
                type = 'bearish';
                label = 'Bearish';
                emoji = '📉';
                const advantage = ((putNet - callNet) / Math.max(callNet, 1) * 100).toFixed(0);
                explanation = `<strong>Moderate bearish bias.</strong><br>
                    PUTS net premium ($${(putNet/1000).toFixed(0)}K) exceeds CALLS ($${(callNet/1000).toFixed(0)}K) by ${advantage}%.
                    Traders are buying protection or betting on downside.
                    Key PUT strikes act as <strong>support levels</strong> where buyers may step in.`;
                expectedMove = 'Moderate Downward';
            } else {
                // Very bearish: put buying dominates significantly
                type = 'very_bearish';
                label = 'Very Bearish';
                emoji = '💥';
                const ratio = absPutNet / Math.max(absCallNet, 1);
                explanation = `<strong>Strong bearish pressure!</strong><br>
                    PUTS net premium ($${(putNet/1000).toFixed(0)}K) is ${ratio.toFixed(1)}x higher than CALLS ($${(callNet/1000).toFixed(0)}K).
                    Heavy put buying indicates fear or strong downside conviction.
                    Expect price to test PUT strike levels as potential support zones.`;
                expectedMove = 'Strong Downward';
            }

            // Caso especial: ambos muy altos (volatilidad esperada)
            if (callNet > 100000 && putNet > 100000 && dominance < 40) {
                type = 'volatile';
                label = 'High Volatility Expected';
                emoji = '⚡';
                explanation = `<strong>Straddle/Strangle activity detected!</strong><br>
                    Both CALLS ($${(callNet/1000).toFixed(0)}K) and PUTS ($${(putNet/1000).toFixed(0)}K) show high premium.
                    Traders expect a <strong>big move</strong> but are unsure of direction. Likely ahead of major event (earnings, FOMC, etc.).`;
                expectedMove = 'Large (Either Direction)';
            }

            // Extraer strikes más activos
            const callLevel = this.getMostActiveStrike(this.callContracts);
            const putLevel = this.getMostActiveStrike(this.putContracts);

            // Calcular targets basados en premium-weighted strikes
            const targets = this.calculateTargets();

            return {
                type,
                label,
                emoji,
                ratio: `${callNet >= 0 ? '+' : ''}${(callNet/1000).toFixed(0)}K : ${putNet >= 0 ? '+' : ''}${(putNet/1000).toFixed(0)}K`,
                confidence: confidence,
                explanation,
                expectedMove,
                callLevel,
                putLevel,
                upsideTarget: targets.upside,
                downsideTarget: targets.downside,
                upsideProbability: targets.upsideProb,
                downsideProbability: targets.downsideProb,
                expectedRange: targets.range
            };
        },

        calculateConfidence(baseDominance) {
            // 1. Base confidence from premium dominance
            let confidence = baseDominance;

            // 2. Volume Weight Factor
            const totalVolume = this.contracts.reduce((sum, c) => sum + (c.quote.volume || 0), 0);
            const volumeWeight = totalVolume > 100000 ? 1.2 : (totalVolume > 50000 ? 1.1 : 1.0);

            // 3. Volatility Factor (lower confidence when IV is high = more uncertainty)
            const avgIV = this.getAverageIV();
            const ivFactor = avgIV > 40 ? 0.8 : (avgIV > 30 ? 0.9 : 1.0);

            // 4. Concentration Factor (higher confidence when premium is concentrated in few strikes)
            const concentrationBonus = this.getPremiumConcentration();

            // 5. DTE Factor (less time = more confidence in direction)
            const dteFactor = this.getDTEFactor();

            // Apply all factors
            let adjustedConfidence = confidence * volumeWeight * ivFactor * concentrationBonus * dteFactor;

            // Limit between 0-100%
            return Math.min(100, Math.max(0, Math.round(adjustedConfidence)));
        },

        getAverageIV() {
            const contracts = [...this.callContracts, ...this.putContracts];
            if (contracts.length === 0) return 0;

            const totalIV = contracts.reduce((sum, c) => sum + (c.quote.iv || 0), 0);
            return totalIV / contracts.length;
        },

        getPremiumConcentration() {
            // Calculate what % of total premium is in the top contract
            const allContracts = [...this.callContracts, ...this.putContracts];
            if (allContracts.length === 0) return 1.0;

            const totalPremium = allContracts.reduce((sum, c) => sum + c.lightStats.totalPremium, 0);
            if (totalPremium === 0) return 1.0;

            const topContract = allContracts.reduce((max, c) =>
                c.lightStats.totalPremium > max.lightStats.totalPremium ? c : max,
                allContracts[0]
            );

            const concentration = topContract.lightStats.totalPremium / totalPremium;

            // High concentration (>50%) = more confidence
            if (concentration > 0.5) return 1.3;
            if (concentration > 0.3) return 1.15;
            return 1.0;
        },

        getDTEFactor() {
            // Estimate DTE from contract symbols (e.g., SPXW260508 = May 8, 2026)
            const allContracts = [...this.callContracts, ...this.putContracts];
            if (allContracts.length === 0) return 1.0;

            // Extract date from first contract
            const match = allContracts[0].symbol.match(/\d{6}/);
            if (!match) return 1.0;

            const dateStr = match[0]; // e.g., "260508"
            const year = 2000 + parseInt(dateStr.substring(0, 2));
            const month = parseInt(dateStr.substring(2, 4)) - 1;
            const day = parseInt(dateStr.substring(4, 6));

            const expirationDate = new Date(year, month, day);
            const today = new Date();
            const dte = Math.floor((expirationDate - today) / (1000 * 60 * 60 * 24));

            // Less DTE = more confidence
            if (dte === 0) return 1.5;      // 0 DTE - very high confidence
            if (dte <= 2) return 1.3;       // 1-2 DTE - high confidence
            if (dte <= 5) return 1.1;       // 3-5 DTE - moderate boost
            return 1.0;                      // 6+ DTE - no boost
        },

        calculateTargets() {
            // Get current underlying price (estimate from contract prices)
            const currentPrice = this.estimateUnderlyingPrice();

            // Calculate premium-weighted average strike for calls (using today's flow for real-time updates)
            let callWeightedStrike = 0;
            let callTotalWeight = 0;
            this.callContracts.forEach(contract => {
                const strike = this.extractStrike(contract.symbol);
                // Use today's flow for real-time updates, fallback to historical if no flow yet
                const weight = contract.todayFlow.totalPremium > 0
                    ? contract.todayFlow.totalPremium
                    : contract.lightStats.totalPremium;
                if (strike && weight > 0) {
                    callWeightedStrike += strike * weight;
                    callTotalWeight += weight;
                }
            });
            const upsideTarget = callTotalWeight > 0 ? Math.round(callWeightedStrike / callTotalWeight) : null;

            // Calculate premium-weighted average strike for puts (using today's flow for real-time updates)
            let putWeightedStrike = 0;
            let putTotalWeight = 0;
            this.putContracts.forEach(contract => {
                const strike = this.extractStrike(contract.symbol);
                // Use today's flow for real-time updates, fallback to historical if no flow yet
                const weight = contract.todayFlow.totalPremium > 0
                    ? contract.todayFlow.totalPremium
                    : contract.lightStats.totalPremium;
                if (strike && weight > 0) {
                    putWeightedStrike += strike * weight;
                    putTotalWeight += weight;
                }
            });
            const downsideTarget = putTotalWeight > 0 ? Math.round(putWeightedStrike / putTotalWeight) : null;

            // Calculate probabilities based on premium ratio (using today's flow)
            const callNet = this.callFlowStats.netPremium;
            const putNet = this.putFlowStats.netPremium;
            const totalPremium = Math.abs(callNet) + Math.abs(putNet);

            const upsideProb = totalPremium > 0 ? Math.round((Math.abs(callNet) / totalPremium) * 100) : 50;
            const downsideProb = totalPremium > 0 ? Math.round((Math.abs(putNet) / totalPremium) * 100) : 50;

            // Calculate expected range
            const range = upsideTarget && downsideTarget ?
                `${downsideTarget} - ${upsideTarget}` : 'N/A';

            return {
                upside: upsideTarget,
                downside: downsideTarget,
                upsideProb,
                downsideProb,
                range,
                currentPrice
            };
        },

        estimateUnderlyingPrice() {
            // Estimate from ATM contracts (where delta is closest to 0.5 for calls or -0.5 for puts)
            const allContracts = [...this.callContracts, ...this.putContracts];
            if (allContracts.length === 0) return null;

            // Find contract with delta closest to ±0.5
            const atmContract = allContracts.reduce((closest, contract) => {
                const delta = Math.abs(contract.quote.delta || 0);
                const closestDelta = Math.abs(closest.quote.delta || 0);
                const targetDelta = 0.5;
                return Math.abs(delta - targetDelta) < Math.abs(closestDelta - targetDelta) ? contract : closest;
            }, allContracts[0]);

            return this.extractStrike(atmContract.symbol);
        },

        extractStrike(symbol) {
            // Extract strike from symbol (e.g., SPXW260508C07390000 -> 7390)
            const match = symbol.match(/[CP]0*(\d+)0{3}$/);
            return match ? parseInt(match[1]) : null;
        },

        formatPremium(premium) {
            // Format premium: show in millions if >= 1,000K
            const absPremium = Math.abs(premium);
            const sign = premium >= 0 ? '+' : '';

            if (absPremium >= 1000000) {
                // >= 1M: show as X.XXM
                return sign + '$' + (absPremium / 1000000).toFixed(2) + 'M';
            } else {
                // < 1M: show as X.XK
                return sign + '$' + (absPremium / 1000).toFixed(1) + 'K';
            }
        },

        getMostActiveStrike(contracts) {
            if (contracts.length === 0) return null;

            // Encontrar el contrato con mayor premium
            const mostActive = contracts.reduce((max, contract) => {
                return contract.lightStats.totalPremium > max.lightStats.totalPremium ? contract : max;
            }, contracts[0]);

            // Extraer el strike del símbolo (ej: SPXW260508C07390000 -> 7390)
            const match = mostActive.symbol.match(/[CP]0*(\d+)0{3}$/);
            return match ? match[1] : 'N/A';
        },

        init() {
            this.updateCredentials();
            const initialContract = @js($selectedContract);
            if (initialContract && initialContract.length > 0) {
                this.addContract(initialContract);
                this.startMonitor();
            }
        },

        createContract(symbol) {
            return {
                symbol: symbol,
                userInputSymbol: symbol, // Preserve original user input
                seenFirstTick: false,
                quote: {
                    bid: null, ask: null, last: null, mark: null, volume: 0,
                    delta: null, gamma: null, theta: null, vega: null, iv: null
                },
                lastVolume: 0,
                prints: [],
                lightStats: {
                    buyPremium: 0,
                    sellPremium: 0,
                    netPremium: 0,
                    totalPremium: 0,
                    totalTrades: 0,
                    midPremium: 0,
                    midTrades: 0
                },
                todayFlow: {
                    buyPremium: 0,
                    sellPremium: 0,
                    netPremium: 0,
                    totalPremium: 0,
                    trades: 0
                },
                detailedStats: {
                    totalPrints: 0,
                    totalVolume: 0,
                    askSideVolume: 0,
                    bidSideVolume: 0,
                    midVolume: 0,
                    askSidePercent: 0,
                    bidSidePercent: 0,
                    midPercent: 0,
                    largestPrint: 0,
                    totalPremium: 0,
                    buyPremium: 0,
                    sellPremium: 0,
                    netPremium: 0,
                    midPremium: 0,
                    midTrades: 0,
                    totalAggressiveness: 0,
                    avgAggressiveness: 0.5
                }
            };
        },

        addContract(symbol) {
            // Check if already exists
            if (this.contracts.some(c => c.symbol === symbol)) {
                console.log('Contract already exists:', symbol);
                return -1;
            }

            const contract = this.createContract(symbol);
            this.contracts.push(contract);
            const contractIndex = this.contracts.length - 1;
            this.selectedIndex = contractIndex;

            console.log('Contract added:', symbol);

            // Resubscribe if already connected
            if (this.isMonitoring && this.connectionState === 'streaming') {
                this.subscribe();
            }

            return contractIndex;
        },

        removeContract(index) {
            this.contracts.splice(index, 1);

            // Adjust selectedCallIndex
            if (this.selectedCallIndex === index) {
                this.selectedCallIndex = null;
            } else if (this.selectedCallIndex !== null && this.selectedCallIndex > index) {
                this.selectedCallIndex--;
            }

            // Adjust selectedPutIndex
            if (this.selectedPutIndex === index) {
                this.selectedPutIndex = null;
            } else if (this.selectedPutIndex !== null && this.selectedPutIndex > index) {
                this.selectedPutIndex--;
            }

            // Resubscribe with remaining contracts
            if (this.isMonitoring && this.contracts.length > 0) {
                this.subscribe();
            } else if (this.contracts.length === 0) {
                this.stopMonitor();
            }
        },

        selectCallContract(index) {
            this.selectedCallIndex = index;
            this.selectedIndex = index;

            // Load historical prints if contract doesn't have any
            const contract = this.contracts[index];
            if (contract && contract.prints.length === 0 && contract.detailedStats.totalPrints > 0) {
                this.loadHistoricalPrints(contract);
            }
        },

        selectPutContract(index) {
            this.selectedPutIndex = index;
            this.selectedIndex = index;

            // Load historical prints if contract doesn't have any
            const contract = this.contracts[index];
            if (contract && contract.prints.length === 0 && contract.detailedStats.totalPrints > 0) {
                this.loadHistoricalPrints(contract);
            }
        },

        async loadContractQuick() {
            if (!this.contractInput) return;
            if (this.contracts.length >= 50) {
                alert('Maximum 50 contracts reached');
                return;
            }

            this.isLoading = true;

            const input = this.contractInput.replace(/^\./, '');
            const match = input.match(/^([A-Z]+)(\d{6})([CP])(\d+)$/);

            if (!match) {
                alert('Invalid format. Use: SYMBOL + YYMMDD + C/P + STRIKE');
                this.isLoading = false;
                return;
            }

            const symbolRoot = match[1];
            const dateStr = match[2];
            const type = match[3];
            const strike = match[4];
            const date = '20' + dateStr.substring(0, 2) + '-' + dateStr.substring(2, 4) + '-' + dateStr.substring(4, 6);
            const underlying = symbolRoot.startsWith('SPXW') ? '$SPX' : symbolRoot;

            try {
                await this.$wire.fetchContractSymbol(underlying, type === 'C' ? 'CALL' : 'PUT', parseInt(strike), date);
                this.contractInput = '';
            } catch (error) {
                console.error('Error loading contract:', error);
                this.isLoading = false;
            }
        },

        handleContractLoaded(detail) {
            console.log('handleContractLoaded called with:', detail);
            const contractIndex = this.addContract(detail.symbol);

            // Calculate initial premium based on current volume if available
            if (detail.volume && detail.lastPrice && contractIndex !== -1) {
                this.calculateInitialPremium(contractIndex, detail.volume, detail.lastPrice);
            }

            if (!this.isMonitoring) {
                setTimeout(() => {
                    this.startMonitor();
                }, 500);
            }

            this.isLoading = false;
        },

        async runDteScanner() {
            if (this.isDteScanning) return;
            if (this.contracts.length >= 50) {
                alert('Maximum 50 contracts reached');
                return;
            }
            this.isDteScanning = true;
            try {
                await this.$wire.loadContractsByDTE(
                    parseInt(this.dteMin),
                    parseInt(this.dteMax),
                    parseInt(this.dteStrikes)
                );
            } catch (e) {
                console.error('DTE scanner error:', e);
            } finally {
                this.isDteScanning = false;
            }
        },

        handleBulkLoaded(detail) {
            const symbols = detail.symbols || [];
            let added = 0;
            for (const symbol of symbols) {
                if (this.contracts.length >= 50) break;
                const idx = this.addContract(symbol);
                if (idx !== -1) added++;
            }
            if (added > 0) {
                this.showNotificationMessage(added + ' contracts loaded');

                // Load historical snapshots for all contracts
                this.loadHistoricalData(symbols);

                // Auto-select first call and first put
                const firstCallIdx = this.contracts.findIndex(c => this.isCall(c.symbol));
                const firstPutIdx  = this.contracts.findIndex(c => !this.isCall(c.symbol));
                if (firstCallIdx !== -1) this.selectedCallIndex = firstCallIdx;
                if (firstPutIdx  !== -1) this.selectedPutIndex  = firstPutIdx;
                this.selectedIndex = firstCallIdx !== -1 ? firstCallIdx : firstPutIdx;

                if (!this.isMonitoring) {
                    setTimeout(() => this.startMonitor(), 500);
                } else {
                    this.subscribe();
                }
            } else {
                this.showNotificationMessage('No new contracts to add');
            }
        },

        handleContractSelected(detail) {
            console.log('handleContractSelected called with:', detail);
            if (detail.symbol) {
                const contractIndex = this.addContract(detail.symbol);

                // Calculate initial premium based on current volume if available
                if (detail.volume && detail.lastPrice && contractIndex !== -1) {
                    this.calculateInitialPremium(contractIndex, detail.volume, detail.lastPrice);
                }

                if (!this.isMonitoring) {
                    setTimeout(() => {
                        this.startMonitor();
                    }, 500);
                }
            }
        },

        updateCredentials() {
            const socketUrl = @js($streamerSocketUrl ?? '');
            const customerId = @js($schwabClientCustomerId ?? '');
            const accessToken = @js($accessToken ?? '');

            if (socketUrl && socketUrl.length > 0 && customerId && customerId.length > 0 && accessToken && accessToken.length > 0) {
                this.credentials = {
                    streamerSocketUrl: socketUrl,
                    schwabClientCustomerId: customerId,
                    schwabClientCorrelId: @js($schwabClientCorrelId ?? ''),
                    schwabClientChannel: @js($schwabClientChannel ?? ''),
                    schwabClientFunctionId: @js($schwabClientFunctionId ?? ''),
                    accessToken: accessToken
                };
                console.log('✓ Credentials successfully loaded');
                console.log('✓ URL '+ this.credentials.streamerSocketUrl);
            } else {
                this.credentials = null;
                console.warn('✗ Missing credentials - cannot start monitor');
            }
        },

        startMonitor() {
            this.updateCredentials();

            if (!this.credentials) {
                alert('Missing streaming credentials. Please ensure Trader API is authenticated.');
                this.isLoading = false;
                return;
            }

            if (this.contracts.length === 0) {
                alert('Please add a contract first');
                this.isLoading = false;
                return;
            }

            console.log('Starting monitor for', this.contracts.length, 'contracts');
            this.isMonitoring = true;
            this.connectionState = 'connecting';
            this.connectWebSocket();
        },

        stopMonitor() {
            this.isMonitoring = false;
            this.connectionState = 'disconnected';
            if (this.ws) {
                this.ws.close();
                this.ws = null;
            }
        },

        connectWebSocket() {
            try {
                this.ws = new WebSocket(this.credentials.streamerSocketUrl);

                this.ws.onopen = () => {
                    console.log('WebSocket connected');
                    this.connectionState = 'authenticating';
                    this.login();
                    setTimeout(() => {
                        this.isLoading = false;
                    }, 1000);
                };

                this.ws.onmessage = (event) => {
                    this.handleMessage(JSON.parse(event.data));
                };

                this.ws.onerror = (error) => {
                    console.error('WebSocket error:', error);
                    this.connectionState = 'error';
                };

                this.ws.onclose = (event) => {
                    console.error('⚠️ WebSocket CLOSED - code:', event.code, 'reason:', event.reason, 'wasClean:', event.wasClean);
                    this.connectionState = 'disconnected';
                    if (this.heartbeatInterval) {
                        clearInterval(this.heartbeatInterval);
                        this.heartbeatInterval = null;
                    }
                    if (this.isMonitoring) {
                        console.log('🔄 Attempting reconnect in 3 seconds...');
                        setTimeout(() => this.connectWebSocket(), 3000);
                    }
                };

                // Start heartbeat to keep connection alive
                this.startHeartbeat();
            } catch (error) {
                this.connectionState = 'error';
            }
        },

        startHeartbeat() {
            if (this.heartbeatInterval) {
                clearInterval(this.heartbeatInterval);
            }

            this.heartbeatInterval = setInterval(() => {
                if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                    const ping = {
                        "requests": [{
                            "service": "ADMIN",
                            "requestid": "heartbeat",
                            "command": "QOS",
                            "SchwabClientCustomerId": this.credentials.schwabClientCustomerId,
                            "SchwabClientCorrelId": this.credentials.schwabClientCorrelId,
                            "parameters": {
                                "qoslevel": "0"
                            }
                        }]
                    };
                    this.ws.send(JSON.stringify(ping));
                }
            }, 30000); // Every 30 seconds
        },

        login() {
            const loginRequest = {
                requests: [{
                    service: 'ADMIN',
                    command: 'LOGIN',
                    requestid: 0,
                    SchwabClientCustomerId: this.credentials.schwabClientCustomerId,
                    SchwabClientCorrelId: this.credentials.schwabClientCorrelId,
                    SchwabClientChannel: this.credentials.schwabClientChannel,
                    SchwabClientFunctionId: this.credentials.schwabClientFunctionId,
                    parameters: {
                        Authorization: this.credentials.accessToken,
                        SchwabClientChannel: this.credentials.schwabClientChannel,
                        SchwabClientFunctionId: this.credentials.schwabClientFunctionId
                    }
                }]
            };

            this.ws.send(JSON.stringify(loginRequest));
        },

        subscribe() {
            const symbols = this.contracts.map(c => c.symbol.replace(/^\./, '')).join(',');
            console.log(this.credentials.schwabClientCustomerId);
            console.log(this.credentials.schwabClientCorrelId);

            const subscribeRequest = {
                "requests": [
                    {
                        "service": "LEVELONE_OPTIONS",
                        "requestid": "1",
                        "command": "SUBS",
                        "SchwabClientCustomerId": this.credentials.schwabClientCustomerId,
                        "SchwabClientCorrelId": this.credentials.schwabClientCorrelId,
                        "parameters": {
                            "keys": symbols,
                            "fields": "0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40,41"
                        }
                    }
                ]
            };

            this.ws.send(JSON.stringify(subscribeRequest));
        },

        handleMessage(data) {
            if (data.response) {
                data.response.forEach(resp => {
                    if (resp.command === 'LOGIN' && resp.content && resp.content.code === 0) {
                        this.connectionState = 'subscribed';
                        this.subscribe();
                    } else if (resp.command === 'SUBS' && resp.content && resp.content.code === 0) {
                    }
                });
            }

            if (data.data) {
                data.data.forEach(item => {
                    if (item.service === 'LEVELONE_OPTIONS') {
                        this.handleLevelOne(item);
                    }
                });
            }

            if (this.connectionState === 'subscribed' && data.data) {
                this.connectionState = 'streaming';
            }
        },

        handleLevelOne(data) {
            if (!data.content) return;

            data.content.forEach(quote => {
                const symbol = quote.key || quote['0'];
                const contractIndex = this.contracts.findIndex(c => c.symbol === symbol || c.symbol === '.' + symbol);

                if (contractIndex === -1) return;

                const contract = this.contracts[contractIndex];

                // Always use processFullQuote for ALL contracts to ensure real-time updates
                this.processFullQuote(contractIndex, quote);
            });

            this.updateGlobalStats();

            // Force Alpine.js reactivity update
            this.lastUpdate = Date.now();
        },

        processFullQuote(index, quote) {
            const contract = this.contracts[index];

            contract.quote.bid = parseFloat(quote['2']) || contract.quote.bid;
            contract.quote.ask = parseFloat(quote['3']) || contract.quote.ask;
            contract.quote.last = parseFloat(quote['4']) || contract.quote.last;
            contract.quote.mark = parseFloat(quote['5']) || contract.quote.mark;
            contract.quote.delta = parseFloat(quote['28']) || contract.quote.delta;
            contract.quote.gamma = parseFloat(quote['29']) || contract.quote.gamma;
            contract.quote.theta = parseFloat(quote['30']) || contract.quote.theta;
            contract.quote.vega = parseFloat(quote['31']) || contract.quote.vega;
            contract.quote.iv = parseFloat(quote['17']) || contract.quote.iv;

            const currentVolume = quote['8'] !== undefined ? parseInt(quote['8']) : contract.quote.volume;

            if (!contract.seenFirstTick) {
                contract.seenFirstTick = true;
                contract.lastVolume = currentVolume;
                contract.quote.volume = currentVolume;

                // Only calculate initial premium if we don't have historical data
                if (contract.lightStats.totalPremium === 0) {
                    const basePrice = contract.quote.last
                        || contract.quote.mark
                        || ((contract.quote.bid && contract.quote.ask) ? (contract.quote.bid + contract.quote.ask) / 2 : 0);
                    if (basePrice > 0 && currentVolume > 0) {
                        const historicalPremium = Math.round(basePrice * currentVolume * 100);
                        contract.lightStats.totalPremium = historicalPremium;
                        contract.detailedStats.totalPremium = historicalPremium;
                        contract.detailedStats.totalVolume = currentVolume;
                    }
                }
            } else if (currentVolume > contract.lastVolume && contract.quote.last > 0) {
                const size = currentVolume - contract.lastVolume;
                const price = contract.quote.last;
                const aggressiveness = this.calculateAggressiveness(contract.quote, price);
                const side = this.classifyTradeSide(aggressiveness);
                const premium = Math.round(price * size * 100);
                const time = new Date().toLocaleTimeString();

                // Only store prints for selected contracts to save memory
                const isSelected = index === this.selectedCallIndex || index === this.selectedPutIndex;
                if (isSelected) {
                    contract.prints.unshift({
                        time, price, size,
                        sequence: contract.detailedStats.totalPrints + 1,
                        side, premium,
                        volume: currentVolume,
                        aggressiveness: aggressiveness
                    });

                    if (contract.prints.length > 100) {
                        contract.prints.pop();
                    }
                }

                // Always update stats for ALL contracts
                this.updateDetailedStats(contract, size, side, premium, aggressiveness);
                this.updateLightStats(contract.lightStats, side, premium, aggressiveness);
                contract.lastVolume = currentVolume;
                contract.quote.volume = currentVolume;
            }
        },

        processLightQuote(index, quote) {
            const contract = this.contracts[index];

            contract.quote.bid = parseFloat(quote['2']) || contract.quote.bid;
            contract.quote.ask = parseFloat(quote['3']) || contract.quote.ask;
            contract.quote.last = parseFloat(quote['4']) || contract.quote.last;

            const currentVolume = quote['8'] !== undefined ? parseInt(quote['8']) : contract.quote.volume;

            if (!contract.seenFirstTick) {
                contract.seenFirstTick = true;
                contract.lastVolume = currentVolume;
                contract.quote.volume = currentVolume;

                // Only calculate initial premium if we don't have historical data
                if (contract.lightStats.totalPremium === 0) {
                    const basePrice = contract.quote.last
                        || contract.quote.mark
                        || ((contract.quote.bid && contract.quote.ask) ? (contract.quote.bid + contract.quote.ask) / 2 : 0);
                    if (basePrice > 0 && currentVolume > 0) {
                        contract.lightStats.totalPremium = Math.round(basePrice * currentVolume * 100);
                    }
                }
            } else if (currentVolume > contract.lastVolume && contract.quote.last > 0) {
                const size = currentVolume - contract.lastVolume;
                const price = contract.quote.last;
                const aggressiveness = this.calculateAggressiveness(contract.quote, price);
                const side = this.classifyTradeSide(aggressiveness);
                const premium = Math.round(price * size * 100);

                this.updateLightStats(contract.lightStats, side, premium, aggressiveness);
                contract.lastVolume = currentVolume;
                contract.quote.volume = currentVolume;
            }
        },

        calculateAggressiveness(quote, price) {
            // Calculate trade aggressiveness (0 = full BID, 1 = full ASK)
            if (!quote.bid || !quote.ask || quote.ask === quote.bid) {
                return 0.5; // Neutral if no spread
            }

            const spread = quote.ask - quote.bid;
            const aggressiveness = (price - quote.bid) / spread;

            // Clamp between 0 and 1
            return Math.max(0, Math.min(1, aggressiveness));
        },

        classifyTradeSide(aggressiveness) {
            // Classify based on aggressiveness score
            if (aggressiveness >= 0.8) return 'ASK';
            if (aggressiveness <= 0.2) return 'BID';
            return 'MID';
        },

        determineSide(quote, price) {
            const aggressiveness = this.calculateAggressiveness(quote, price);
            return this.classifyTradeSide(aggressiveness);
        },

        updateLightStats(stats, side, premium, aggressiveness = null) {
            stats.totalTrades++;
            stats.totalPremium += premium;

            // Use weighted premium based on aggressiveness
            // FIXED: Inverted to match ASK=sell, BID=buy
            if (aggressiveness !== null) {
                const sellWeight = aggressiveness;  // High aggressiveness = ASK = sell
                const buyWeight = 1 - aggressiveness;  // Low aggressiveness = BID = buy

                stats.buyPremium += Math.round(premium * buyWeight);
                stats.sellPremium += Math.round(premium * sellWeight);
                stats.netPremium += Math.round(premium * buyWeight) - Math.round(premium * sellWeight);

                // Track MID trades separately
                if (side === 'MID') {
                    stats.midPremium = (stats.midPremium || 0) + premium;
                    stats.midTrades = (stats.midTrades || 0) + 1;
                }
            } else {
                // Fallback to old logic if no aggressiveness provided
                // FIXED: ASK = sell side, BID = buy side (inverted from original)
                if (side === 'ASK') {
                    stats.sellPremium += premium;
                    stats.netPremium -= premium;
                } else if (side === 'BID') {
                    stats.buyPremium += premium;
                    stats.netPremium += premium;
                } else if (side === 'MID') {
                    stats.midPremium = (stats.midPremium || 0) + premium;
                    stats.midTrades = (stats.midTrades || 0) + 1;
                }
            }
        },

        updateDetailedStats(contract, size, side, premium, aggressiveness = null) {
            const stats = contract.detailedStats;

            stats.totalPrints++;
            stats.totalVolume += size;
            stats.totalPremium += premium;

            // Use weighted premium if aggressiveness is provided
            // FIXED: Inverted to match ASK=sell, BID=buy
            if (aggressiveness !== null) {
                const sellWeight = aggressiveness;  // High aggressiveness = ASK = sell
                const buyWeight = 1 - aggressiveness;  // Low aggressiveness = BID = buy

                const buyPremiumInc = Math.round(premium * buyWeight);
                const sellPremiumInc = Math.round(premium * sellWeight);

                stats.buyPremium += buyPremiumInc;
                stats.sellPremium += sellPremiumInc;
                stats.netPremium += buyPremiumInc - sellPremiumInc;

                // Track today's flow separately (real-time WebSocket data only)
                contract.todayFlow.buyPremium += buyPremiumInc;
                contract.todayFlow.sellPremium += sellPremiumInc;
                contract.todayFlow.netPremium += buyPremiumInc - sellPremiumInc;
                contract.todayFlow.totalPremium += premium;
                contract.todayFlow.trades++;

                // Track volume by side
                if (side === 'ASK') {
                    stats.askSideVolume += size;
                } else if (side === 'BID') {
                    stats.bidSideVolume += size;
                } else {
                    stats.midVolume += size;
                    stats.midPremium = (stats.midPremium || 0) + premium;
                    stats.midTrades = (stats.midTrades || 0) + 1;
                }

                // Track cumulative aggressiveness for average calculation
                stats.totalAggressiveness = (stats.totalAggressiveness || 0) + aggressiveness;
                stats.avgAggressiveness = stats.totalAggressiveness / stats.totalPrints;
            } else {
                // Fallback to old logic
                // FIXED: ASK = sell side, BID = buy side (inverted from original)
                if (side === 'ASK') {
                    stats.askSideVolume += size;
                    stats.sellPremium += premium;
                    stats.netPremium -= premium;
                } else if (side === 'BID') {
                    stats.bidSideVolume += size;
                    stats.buyPremium += premium;
                    stats.netPremium += premium;
                } else {
                    stats.midVolume += size;
                    stats.midPremium = (stats.midPremium || 0) + premium;
                    stats.midTrades = (stats.midTrades || 0) + 1;
                }
            }

            if (size > stats.largestPrint) stats.largestPrint = size;

            if (stats.totalVolume > 0) {
                stats.askSidePercent = Math.round((stats.askSideVolume / stats.totalVolume) * 100);
                stats.bidSidePercent = Math.round((stats.bidSideVolume / stats.totalVolume) * 100);
                stats.midPercent = Math.round((stats.midVolume / stats.totalVolume) * 100);
            }
        },

        calculateInitialPremium(contractIndex, currentVolume, lastPrice) {
            const contract = this.contracts[contractIndex];

            // Set initial volume to track where we started
            contract.lastVolume = currentVolume;
            contract.quote.volume = currentVolume;
            contract.quote.last = lastPrice;

            // Calculate initial premium estimate
            const initialPremium = Math.round(lastPrice * currentVolume * 100);

            // Initialize stats with total premium only (no bid/ask assumption)
            // We'll let real-time trades determine the actual distribution
            contract.lightStats.totalPremium = initialPremium;
            contract.lightStats.buyPremium = 0; // Start at 0, let real trades determine
            contract.lightStats.sellPremium = 0; // Start at 0, let real trades determine
            contract.lightStats.netPremium = 0; // Start at 0, let real trades determine
            contract.lightStats.totalTrades = Math.floor(currentVolume / 100); // Rough estimate

            // Update detailed stats with total premium only
            contract.detailedStats.totalVolume = currentVolume;
            contract.detailedStats.totalPremium = initialPremium;
            contract.detailedStats.buyPremium = 0; // Start at 0
            contract.detailedStats.sellPremium = 0; // Start at 0
            contract.detailedStats.netPremium = 0; // Start at 0
            contract.detailedStats.askSideVolume = 0; // Start at 0
            contract.detailedStats.bidSideVolume = 0; // Start at 0
            contract.detailedStats.midVolume = 0;

            // Percentages start at 0 since we have no distribution data
            contract.detailedStats.askSidePercent = 0;
            contract.detailedStats.bidSidePercent = 0;
            contract.detailedStats.midPercent = 0;

            console.log(`Initial premium calculated for ${contract.symbol}:`, {
                volume: currentVolume,
                price: lastPrice,
                totalPremium: initialPremium,
                note: "Bid/ask distribution will be determined by real-time trades"
            });

            // Update global stats
            this.updateGlobalStats();
        },

        updateGlobalStats() {
            this.globalStats = {
                totalPremium: 0,
                buyPremium: 0,
                sellPremium: 0,
                netPremium: 0,
                totalTrades: 0
            };

            this.contracts.forEach(contract => {
                this.globalStats.totalPremium += contract.lightStats.totalPremium;
                this.globalStats.buyPremium += contract.lightStats.buyPremium;
                this.globalStats.sellPremium += contract.lightStats.sellPremium;
                this.globalStats.netPremium += contract.lightStats.netPremium;
                this.globalStats.totalTrades += contract.lightStats.totalTrades;
            });

            // Force reactivity update
            this.lastUpdate = Date.now();
        },

        async loadHistoricalData(symbols) {
            try {
                console.log('🔍 Loading historical snapshots for:', symbols);
                console.log('📊 Current contracts before loading:', this.contracts.map(c => ({
                    symbol: c.symbol,
                    buyPremium: c.lightStats.buyPremium,
                    sellPremium: c.lightStats.sellPremium
                })));

                // Call Livewire backend method
                const snapshots = await @this.loadHistoricalSnapshots(symbols);

                console.log('📦 Raw snapshots received:', snapshots);

                if (!snapshots || Object.keys(snapshots).length === 0) {
                    console.warn('⚠️ No historical snapshots found');
                    return;
                }

                console.log('✅ Loaded', Object.keys(snapshots).length, 'snapshots');

                // Apply snapshots to contracts
                this.contracts.forEach(contract => {
                    const snapshot = snapshots[contract.symbol];
                    if (snapshot) {
                        console.log(`Applying snapshot to ${contract.symbol}:`, snapshot);

                        // Initialize with historical data
                        contract.lightStats.totalPremium = snapshot.total_premium || 0;
                        contract.lightStats.buyPremium = snapshot.buy_premium || 0;
                        contract.lightStats.sellPremium = snapshot.sell_premium || 0;
                        contract.lightStats.netPremium = snapshot.net_premium || 0;

                        contract.detailedStats.totalPremium = snapshot.total_premium || 0;
                        contract.detailedStats.buyPremium = snapshot.buy_premium || 0;
                        contract.detailedStats.sellPremium = snapshot.sell_premium || 0;
                        contract.detailedStats.netPremium = snapshot.net_premium || 0;
                        contract.detailedStats.totalVolume = snapshot.total_volume || 0;

                        // Set baseline volume for tracking new trades
                        contract.lastVolume = snapshot.total_volume || 0;
                        contract.quote.volume = snapshot.total_volume || 0;

                        // Mark as having seen first tick (we have historical data)
                        contract.seenFirstTick = true;

                        console.log(`${contract.symbol} initialized with $${(snapshot.total_premium / 1000).toFixed(1)}K premium, ${snapshot.total_volume} volume`);
                    }
                });

                // Update global stats
                this.updateGlobalStats();

                console.log('📊 Contracts after loading snapshots:', this.contracts.map(c => ({
                    symbol: c.symbol,
                    buyPremium: c.lightStats.buyPremium,
                    sellPremium: c.lightStats.sellPremium,
                    totalPremium: c.lightStats.totalPremium,
                    lastVolume: c.lastVolume
                })));

                this.showNotificationMessage('Historical data loaded');
            } catch (error) {
                console.error('❌ Error loading historical data:', error);
            }
        },

        async loadHistoricalPrints(contract) {
            if (!contract) return;

            try {
                console.log('Loading historical prints for:', contract.symbol);
                this.showNotificationMessage('Loading historical prints...');

                // Call Livewire backend method
                const historicalPrints = await @this.loadHistoricalPrints(contract.symbol, 100);

                if (!historicalPrints || historicalPrints.length === 0) {
                    this.showNotificationMessage('No historical prints found');
                    return;
                }

                console.log(`Loaded ${historicalPrints.length} historical prints`);

                // Replace current prints with historical ones
                // Add sequence numbers
                contract.prints = historicalPrints.map((print, index) => ({
                    ...print,
                    sequence: historicalPrints.length - index,
                    isHistorical: true
                }));

                this.showNotificationMessage(`Loaded ${historicalPrints.length} historical prints`);
            } catch (error) {
                console.error('Error loading historical prints:', error);
                this.showNotificationMessage('Error loading historical prints');
            }
        },
    };
}
</script>

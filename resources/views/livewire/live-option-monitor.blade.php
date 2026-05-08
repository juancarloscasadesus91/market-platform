<div x-data="optionMonitor()" x-init="init()"
     @contract-selected.window="handleContractSelected($event.detail)"
     @contract-loaded.window="handleContractLoaded($event.detail)">
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

        <!-- Contract Input -->
        <div class="mb-4">
            <label class="block text-xs font-medium text-slate-400 mb-2">Add Option Contract</label>
            <div class="flex gap-3">
                <input
                    type="text"
                    x-model="contractInput"
                    @keydown.enter="loadContractQuick()"
                    placeholder="e.g., SPXW260505C7250"
                    class="flex-1 px-3 py-2 text-sm bg-slate-700/50 text-white border border-slate-600 rounded focus:outline-none focus:border-purple-500 font-mono"
                />
                <button
                    @click="loadContractQuick()"
                    :disabled="contracts.length >= 20"
                    class="px-6 py-2 text-sm font-medium text-white bg-purple-500 hover:bg-purple-600 disabled:bg-slate-600 disabled:cursor-not-allowed rounded transition-colors"
                >
                    Add Contract
                </button>
            </div>
            <p class="mt-1 text-xs text-slate-500">
                Format: SYMBOL + YYMMDD + C/P + STRIKE (max 20 contracts)
            </p>
        </div>

        <!-- Market Sentiment Analysis -->
        <div x-show="contracts.length > 0" class="mb-6">
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
                            <span class="text-sm font-bold text-emerald-400" x-text="'$' + callStats.netPremium.toLocaleString()"></span>
                        </div>
                        <div class="h-3 bg-slate-700 rounded-full overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-emerald-500 to-emerald-400 transition-all duration-500"
                                 :style="'width: ' + Math.min(100, (Math.abs(callStats.netPremium) / Math.max(Math.abs(callStats.netPremium) + Math.abs(putStats.netPremium), 1)) * 100) + '%'"></div>
                        </div>
                    </div>

                    <!-- PUTS Bar -->
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-xs font-medium text-rose-400">📉 PUTS Net Premium</span>
                            <span class="text-sm font-bold text-rose-400" x-text="'$' + putStats.netPremium.toLocaleString()"></span>
                        </div>
                        <div class="h-3 bg-slate-700 rounded-full overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-rose-500 to-rose-400 transition-all duration-500"
                                 :style="'width: ' + Math.min(100, (Math.abs(putStats.netPremium) / Math.max(Math.abs(callStats.netPremium) + Math.abs(putStats.netPremium), 1)) * 100) + '%'"></div>
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

                    <!-- Key Levels -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div class="bg-slate-900/50 rounded p-3">
                            <p class="text-xs text-slate-400 mb-1">Expected Move</p>
                            <p class="text-sm font-bold text-white" x-text="marketSentiment.expectedMove"></p>
                        </div>
                        <div class="bg-slate-900/50 rounded p-3">
                            <p class="text-xs text-slate-400 mb-1">Key Level (CALLS)</p>
                            <p class="text-sm font-bold text-emerald-400" x-text="marketSentiment.callLevel || 'N/A'"></p>
                        </div>
                        <div class="bg-slate-900/50 rounded p-3">
                            <p class="text-xs text-slate-400 mb-1">Key Level (PUTS)</p>
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
                            <p class="text-xs text-slate-400">Total Premium</p>
                            <p class="text-lg font-bold text-purple-400" x-text="'$' + callStats.totalPremium.toLocaleString()"></p>
                            <p class="text-xs text-slate-500" x-text="callStats.totalTrades + ' trades'"></p>
                        </div>
                        <div class="p-3 bg-emerald-500/10 border border-emerald-500/20 rounded">
                            <p class="text-xs text-slate-400">Buy Premium</p>
                            <p class="text-lg font-bold text-emerald-400" x-text="'$' + callStats.buyPremium.toLocaleString()"></p>
                        </div>
                        <div class="p-3 bg-rose-500/10 border border-rose-500/20 rounded">
                            <p class="text-xs text-slate-400">Sell Premium</p>
                            <p class="text-lg font-bold text-rose-400" x-text="'$' + callStats.sellPremium.toLocaleString()"></p>
                        </div>
                        <div class="p-3 border rounded" :class="{
                            'bg-emerald-500/10 border-emerald-500/20': callStats.netPremium >= 0,
                            'bg-rose-500/10 border-rose-500/20': callStats.netPremium < 0
                        }">
                            <p class="text-xs text-slate-400">Net Premium</p>
                            <p class="text-lg font-bold" :class="{
                                'text-emerald-400': callStats.netPremium >= 0,
                                'text-rose-400': callStats.netPremium < 0
                            }" x-text="(callStats.netPremium >= 0 ? '+' : '') + '$' + callStats.netPremium.toLocaleString()"></p>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <p class="text-xs text-emerald-400 mb-2">Active CALL Contracts</p>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="contract in callContracts" :key="contract.symbol">
                            <div @click="selectContract(contracts.indexOf(contract))"
                                 class="cursor-pointer px-3 py-2 rounded-lg transition-all"
                                 :class="{
                                     'bg-emerald-500/30 border-2 border-emerald-500': contracts.indexOf(contract) === selectedIndex,
                                     'bg-slate-700/30 border border-slate-600 hover:bg-slate-700/50': contracts.indexOf(contract) !== selectedIndex
                                 }">
                                <div class="flex items-center space-x-2">
                                    <span class="text-sm font-mono text-white" x-text="contract.symbol"></span>
                                    <span class="text-xs px-1.5 py-0.5 rounded" :class="{
                                        'bg-emerald-500/20 text-emerald-400': contract.lightStats.netPremium >= 0,
                                        'bg-rose-500/20 text-rose-400': contract.lightStats.netPremium < 0
                                    }" x-text="(contract.lightStats.netPremium >= 0 ? '+' : '') + '$' + (Math.abs(contract.lightStats.netPremium) / 1000).toFixed(1) + 'K'"></span>
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
                <div x-show="activeContract && isCall(activeContract.symbol)">
                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div class="p-3 bg-slate-700/30 rounded">
                            <p class="text-xs text-slate-400">Bid</p>
                            <p class="text-lg font-semibold text-rose-400" x-text="activeContract?.quote.bid || '-'"></p>
                        </div>
                        <div class="p-3 bg-slate-700/30 rounded">
                            <p class="text-xs text-slate-400">Ask</p>
                            <p class="text-lg font-semibold text-emerald-400" x-text="activeContract?.quote.ask || '-'"></p>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-2 mb-3">
                        <div class="p-2 bg-emerald-500/10 border border-emerald-500/20 rounded">
                            <p class="text-xs text-slate-400">Buy Premium</p>
                            <p class="text-sm font-semibold text-emerald-400" x-text="'$' + (activeContract?.detailedStats.buyPremium || 0).toLocaleString()"></p>
                        </div>
                        <div class="p-2 bg-rose-500/10 border border-rose-500/20 rounded">
                            <p class="text-xs text-slate-400">Sell Premium</p>
                            <p class="text-sm font-semibold text-rose-400" x-text="'$' + (activeContract?.detailedStats.sellPremium || 0).toLocaleString()"></p>
                        </div>
                        <div class="p-2 bg-purple-500/10 border border-purple-500/20 rounded">
                            <p class="text-xs text-slate-400">Total Premium</p>
                            <p class="text-sm font-semibold text-purple-400" x-text="'$' + (activeContract?.detailedStats.totalPremium || 0).toLocaleString()"></p>
                        </div>
                    </div>
                    <div class="p-3 border rounded mb-4" :class="{
                        'bg-emerald-500/10 border-emerald-500/20': (activeContract?.detailedStats.netPremium || 0) >= 0,
                        'bg-rose-500/10 border-rose-500/20': (activeContract?.detailedStats.netPremium || 0) < 0
                    }">
                        <p class="text-xs text-slate-400">Net Premium Flow</p>
                        <p class="text-2xl font-bold" :class="{
                            'text-emerald-400': (activeContract?.detailedStats.netPremium || 0) >= 0,
                            'text-rose-400': (activeContract?.detailedStats.netPremium || 0) < 0
                        }" x-text="((activeContract?.detailedStats.netPremium || 0) >= 0 ? '+' : '') + '$' + (activeContract?.detailedStats.netPremium || 0).toLocaleString()"></p>
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
                                <p class="text-sm font-medium text-slate-300" x-text="activeContract?.quote.delta ? Math.abs(activeContract.quote.delta).toFixed(2) : '-'"></p>
                            </div>
                            <div class="p-2 bg-slate-700/20 rounded">
                                <p class="text-xs text-slate-500">Gamma</p>
                                <p class="text-sm font-medium text-slate-300" x-text="activeContract?.quote.gamma ? activeContract.quote.gamma.toFixed(2) : '-'"></p>
                            </div>
                            <div class="p-2 bg-slate-700/20 rounded">
                                <p class="text-xs text-slate-500">Theta</p>
                                <p class="text-sm font-medium text-slate-300" x-text="activeContract?.quote.theta ? activeContract.quote.theta.toFixed(2) : '-'"></p>
                            </div>
                            <div class="p-2 bg-slate-700/20 rounded">
                                <p class="text-xs text-slate-500">Vega</p>
                                <p class="text-sm font-medium text-slate-300" x-text="activeContract?.quote.vega ? activeContract.quote.vega.toFixed(2) : '-'"></p>
                            </div>
                            <div class="p-2 bg-slate-700/20 rounded">
                                <p class="text-xs text-slate-500">IV</p>
                                <p class="text-sm font-medium text-slate-300" x-text="activeContract?.quote.iv ? (activeContract.quote.iv * 100).toFixed(2) + '%' : '-'"></p>
                            </div>
                        </div>

                        <!-- Volume Stats -->
                        <div class="grid grid-cols-3 gap-2">
                            <div class="p-2 bg-emerald-500/10 border border-emerald-500/20 rounded">
                                <p class="text-xs text-slate-400">Ask-Side</p>
                                <p class="text-sm font-semibold text-emerald-400" x-text="activeContract?.detailedStats.askSideVolume || 0"></p>
                                <p class="text-xs text-slate-500" x-text="(activeContract?.detailedStats.askSidePercent || 0) + '%'"></p>
                            </div>
                            <div class="p-2 bg-rose-500/10 border border-rose-500/20 rounded">
                                <p class="text-xs text-slate-400">Bid-Side</p>
                                <p class="text-sm font-semibold text-rose-400" x-text="activeContract?.detailedStats.bidSideVolume || 0"></p>
                                <p class="text-xs text-slate-500" x-text="(activeContract?.detailedStats.bidSidePercent || 0) + '%'"></p>
                            </div>
                            <div class="p-2 bg-blue-500/10 border border-blue-500/20 rounded">
                                <p class="text-xs text-slate-400">Mid</p>
                                <p class="text-sm font-semibold text-blue-400" x-text="activeContract?.detailedStats.midVolume || 0"></p>
                                <p class="text-xs text-slate-500" x-text="(activeContract?.detailedStats.midPercent || 0) + '%'"></p>
                            </div>
                        </div>

                        <!-- Prints Table -->
                        <div class="bg-slate-700/20 rounded-lg overflow-hidden">
                            <div class="px-3 py-2 bg-slate-700/50 border-b border-slate-600">
                                <h4 class="text-xs font-semibold text-white">Live Prints</h4>
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
                                        <template x-for="print in (activeContract?.prints || [])" :key="print.sequence">
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
                                <div x-show="!activeContract || activeContract.prints.length === 0" class="p-4 text-center text-slate-500">
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
                            <p class="text-xs text-slate-400">Total Premium</p>
                            <p class="text-lg font-bold text-purple-400" x-text="'$' + putStats.totalPremium.toLocaleString()"></p>
                            <p class="text-xs text-slate-500" x-text="putStats.totalTrades + ' trades'"></p>
                        </div>
                        <div class="p-3 bg-emerald-500/10 border border-emerald-500/20 rounded">
                            <p class="text-xs text-slate-400">Buy Premium</p>
                            <p class="text-lg font-bold text-emerald-400" x-text="'$' + putStats.buyPremium.toLocaleString()"></p>
                        </div>
                        <div class="p-3 bg-rose-500/10 border border-rose-500/20 rounded">
                            <p class="text-xs text-slate-400">Sell Premium</p>
                            <p class="text-lg font-bold text-rose-400" x-text="'$' + putStats.sellPremium.toLocaleString()"></p>
                        </div>
                        <div class="p-3 border rounded" :class="{
                            'bg-emerald-500/10 border-emerald-500/20': putStats.netPremium >= 0,
                            'bg-rose-500/10 border-rose-500/20': putStats.netPremium < 0
                        }">
                            <p class="text-xs text-slate-400">Net Premium</p>
                            <p class="text-lg font-bold" :class="{
                                'text-emerald-400': putStats.netPremium >= 0,
                                'text-rose-400': putStats.netPremium < 0
                            }" x-text="(putStats.netPremium >= 0 ? '+' : '') + '$' + putStats.netPremium.toLocaleString()"></p>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <p class="text-xs text-rose-400 mb-2">Active PUT Contracts</p>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="contract in putContracts" :key="contract.symbol">
                            <div @click="selectContract(contracts.indexOf(contract))"
                                 class="cursor-pointer px-3 py-2 rounded-lg transition-all"
                                 :class="{
                                     'bg-rose-500/30 border-2 border-rose-500': contracts.indexOf(contract) === selectedIndex,
                                     'bg-slate-700/30 border border-slate-600 hover:bg-slate-700/50': contracts.indexOf(contract) !== selectedIndex
                                 }">
                                <div class="flex items-center space-x-2">
                                    <span class="text-sm font-mono text-white" x-text="contract.symbol"></span>
                                    <span class="text-xs px-1.5 py-0.5 rounded" :class="{
                                        'bg-emerald-500/20 text-emerald-400': contract.lightStats.netPremium >= 0,
                                        'bg-rose-500/20 text-rose-400': contract.lightStats.netPremium < 0
                                    }" x-text="(contract.lightStats.netPremium >= 0 ? '+' : '') + '$' + (Math.abs(contract.lightStats.netPremium) / 1000).toFixed(1) + 'K'"></span>
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
                <div x-show="activeContract && !isCall(activeContract.symbol)">
                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div class="p-3 bg-slate-700/30 rounded">
                            <p class="text-xs text-slate-400">Bid</p>
                            <p class="text-lg font-semibold text-rose-400" x-text="activeContract?.quote.bid || '-'"></p>
                        </div>
                        <div class="p-3 bg-slate-700/30 rounded">
                            <p class="text-xs text-slate-400">Ask</p>
                            <p class="text-lg font-semibold text-emerald-400" x-text="activeContract?.quote.ask || '-'"></p>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-2 mb-3">
                        <div class="p-2 bg-emerald-500/10 border border-emerald-500/20 rounded">
                            <p class="text-xs text-slate-400">Buy Premium</p>
                            <p class="text-sm font-semibold text-emerald-400" x-text="'$' + (activeContract?.detailedStats.buyPremium || 0).toLocaleString()"></p>
                        </div>
                        <div class="p-2 bg-rose-500/10 border border-rose-500/20 rounded">
                            <p class="text-xs text-slate-400">Sell Premium</p>
                            <p class="text-sm font-semibold text-rose-400" x-text="'$' + (activeContract?.detailedStats.sellPremium || 0).toLocaleString()"></p>
                        </div>
                        <div class="p-2 bg-purple-500/10 border border-purple-500/20 rounded">
                            <p class="text-xs text-slate-400">Total Premium</p>
                            <p class="text-sm font-semibold text-purple-400" x-text="'$' + (activeContract?.detailedStats.totalPremium || 0).toLocaleString()"></p>
                        </div>
                    </div>
                    <div class="p-3 border rounded mb-4" :class="{
                        'bg-emerald-500/10 border-emerald-500/20': (activeContract?.detailedStats.netPremium || 0) >= 0,
                        'bg-rose-500/10 border-rose-500/20': (activeContract?.detailedStats.netPremium || 0) < 0
                    }">
                        <p class="text-xs text-slate-400">Net Premium Flow</p>
                        <p class="text-2xl font-bold" :class="{
                            'text-emerald-400': (activeContract?.detailedStats.netPremium || 0) >= 0,
                            'text-rose-400': (activeContract?.detailedStats.netPremium || 0) < 0
                        }" x-text="((activeContract?.detailedStats.netPremium || 0) >= 0 ? '+' : '') + '$' + (activeContract?.detailedStats.netPremium || 0).toLocaleString()"></p>
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
                                <p class="text-sm font-medium text-slate-300" x-text="activeContract?.quote.delta ? Math.abs(activeContract.quote.delta).toFixed(2) : '-'"></p>
                            </div>
                            <div class="p-2 bg-slate-700/20 rounded">
                                <p class="text-xs text-slate-500">Gamma</p>
                                <p class="text-sm font-medium text-slate-300" x-text="activeContract?.quote.gamma ? activeContract.quote.gamma.toFixed(2) : '-'"></p>
                            </div>
                            <div class="p-2 bg-slate-700/20 rounded">
                                <p class="text-xs text-slate-500">Theta</p>
                                <p class="text-sm font-medium text-slate-300" x-text="activeContract?.quote.theta ? activeContract.quote.theta.toFixed(2) : '-'"></p>
                            </div>
                            <div class="p-2 bg-slate-700/20 rounded">
                                <p class="text-xs text-slate-500">Vega</p>
                                <p class="text-sm font-medium text-slate-300" x-text="activeContract?.quote.vega ? activeContract.quote.vega.toFixed(2) : '-'"></p>
                            </div>
                            <div class="p-2 bg-slate-700/20 rounded">
                                <p class="text-xs text-slate-500">IV</p>
                                <p class="text-sm font-medium text-slate-300" x-text="activeContract?.quote.iv ? (activeContract.quote.iv * 100).toFixed(2) + '%' : '-'"></p>
                            </div>
                        </div>

                        <!-- Volume Stats -->
                        <div class="grid grid-cols-3 gap-2">
                            <div class="p-2 bg-emerald-500/10 border border-emerald-500/20 rounded">
                                <p class="text-xs text-slate-400">Ask-Side</p>
                                <p class="text-sm font-semibold text-emerald-400" x-text="activeContract?.detailedStats.askSideVolume || 0"></p>
                                <p class="text-xs text-slate-500" x-text="(activeContract?.detailedStats.askSidePercent || 0) + '%'"></p>
                            </div>
                            <div class="p-2 bg-rose-500/10 border border-rose-500/20 rounded">
                                <p class="text-xs text-slate-400">Bid-Side</p>
                                <p class="text-sm font-semibold text-rose-400" x-text="activeContract?.detailedStats.bidSideVolume || 0"></p>
                                <p class="text-xs text-slate-500" x-text="(activeContract?.detailedStats.bidSidePercent || 0) + '%'"></p>
                            </div>
                            <div class="p-2 bg-blue-500/10 border border-blue-500/20 rounded">
                                <p class="text-xs text-slate-400">Mid</p>
                                <p class="text-sm font-semibold text-blue-400" x-text="activeContract?.detailedStats.midVolume || 0"></p>
                                <p class="text-xs text-slate-500" x-text="(activeContract?.detailedStats.midPercent || 0) + '%'"></p>
                            </div>
                        </div>

                        <!-- Prints Table -->
                        <div class="bg-slate-700/20 rounded-lg overflow-hidden">
                            <div class="px-3 py-2 bg-slate-700/50 border-b border-slate-600">
                                <h4 class="text-xs font-semibold text-white">Live Prints</h4>
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
                                        <template x-for="print in (activeContract?.prints || [])" :key="print.sequence">
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
                                <div x-show="!activeContract || activeContract.prints.length === 0" class="p-4 text-center text-slate-500">
                                    <p class="text-xs">No prints yet...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-card>
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

        // Multiple contracts support
        contracts: [],
        selectedCallIndex: null,
        selectedPutIndex: null,

        // Global stats (sum of all contracts)
        globalStats: {
            totalPremium: 0,
            buyPremium: 0,
            sellPremium: 0,
            netPremium: 0,
            totalTrades: 0
        },

        // Computed properties for active contracts
        get activeCallContract() {
            return this.selectedCallIndex !== null ? this.contracts[this.selectedCallIndex] : null;
        },

        get activePutContract() {
            return this.selectedPutIndex !== null ? this.contracts[this.selectedPutIndex] : null;
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

        isCall(symbol) {
            // SPXW 260508C07390000 - C indica CALL, P indica PUT
            return symbol.includes('C0') || symbol.includes('C1') || symbol.includes('C2') ||
                   symbol.includes('C3') || symbol.includes('C4') || symbol.includes('C5') ||
                   symbol.includes('C6') || symbol.includes('C7') || symbol.includes('C8') || symbol.includes('C9');
        },

        calculateGroupStats(contracts) {
            return contracts.reduce((acc, contract) => {
                acc.totalPremium += contract.lightStats.totalPremium;
                acc.buyPremium += contract.lightStats.buyPremium;
                acc.sellPremium += contract.lightStats.sellPremium;
                acc.netPremium += contract.lightStats.netPremium;
                acc.totalTrades += contract.lightStats.totalTrades;
                return acc;
            }, {
                totalPremium: 0,
                buyPremium: 0,
                sellPremium: 0,
                netPremium: 0,
                totalTrades: 0
            });
        },

        get marketSentiment() {
            const callNet = this.callStats.netPremium;
            const putNet = this.putStats.netPremium;
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

            const ratio = callNet / Math.max(putNet, 1);
            const difference = Math.abs(callNet - putNet);
            const dominance = (difference / total) * 100;

            // Determinar tipo de sentimiento
            let type, label, emoji, explanation, expectedMove;

            if (ratio > 3) {
                type = 'very_bullish';
                label = 'Very Bullish';
                emoji = '🚀';
                explanation = `<strong>Strong bullish pressure detected!</strong><br>
                    CALLS premium is ${ratio.toFixed(1)}x higher than PUTS. Traders are aggressively buying calls,
                    expecting a significant upward move. The strikes with highest CALL activity act as <strong>magnetic resistance levels</strong>.`;
                expectedMove = 'Strong Upward';
            } else if (ratio > 1.5) {
                type = 'bullish';
                label = 'Bullish';
                emoji = '📈';
                explanation = `<strong>Moderate bullish bias.</strong><br>
                    CALLS premium exceeds PUTS by ${((ratio - 1) * 100).toFixed(0)}%. Market participants are positioning for upside,
                    but with less conviction. Watch for breakout above key CALL strikes.`;
                expectedMove = 'Moderate Upward';
            } else if (ratio > 0.67) {
                type = 'neutral';
                label = 'Neutral / Range-Bound';
                emoji = '⚖️';
                explanation = `<strong>Balanced premium flow.</strong><br>
                    CALLS and PUTS are nearly equal, suggesting market indecision. Price likely to consolidate between
                    the most active CALL and PUT strikes. Breakout direction unclear.`;
                expectedMove = 'Sideways / Range';
            } else if (ratio > 0.33) {
                type = 'bearish';
                label = 'Bearish';
                emoji = '📉';
                explanation = `<strong>Moderate bearish bias.</strong><br>
                    PUTS premium exceeds CALLS by ${((1/ratio - 1) * 100).toFixed(0)}%. Traders are buying protection or betting on downside.
                    Key PUT strikes act as <strong>support levels</strong> where buyers may step in.`;
                expectedMove = 'Moderate Downward';
            } else {
                type = 'very_bearish';
                label = 'Very Bearish';
                emoji = '💥';
                explanation = `<strong>Strong bearish pressure!</strong><br>
                    PUTS premium is ${(1/ratio).toFixed(1)}x higher than CALLS. Heavy put buying indicates fear or strong downside conviction.
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

            return {
                type,
                label,
                emoji,
                ratio: `${callNet >= 0 ? '+' : ''}${(callNet/1000).toFixed(0)}K : ${putNet >= 0 ? '+' : ''}${(putNet/1000).toFixed(0)}K`,
                confidence: Math.min(95, Math.round(dominance)),
                explanation,
                expectedMove,
                callLevel,
                putLevel
            };
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
                quote: {
                    bid: null, ask: null, last: null, mark: null, volume: null,
                    delta: null, gamma: null, theta: null, vega: null, iv: null
                },
                lastVolume: null,
                prints: [],
                lightStats: {
                    buyPremium: 0,
                    sellPremium: 0,
                    netPremium: 0,
                    totalPremium: 0,
                    totalTrades: 0
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
                    netPremium: 0
                }
            };
        },

        addContract(symbol) {
            // Check if already exists
            if (this.contracts.some(c => c.symbol === symbol)) {
                console.log('Contract already exists:', symbol);
                return;
            }

            const contract = this.createContract(symbol);
            this.contracts.push(contract);
            this.selectedIndex = this.contracts.length - 1;

            console.log('Contract added:', symbol);

            // Resubscribe if already connected
            if (this.isMonitoring && this.connectionState === 'streaming') {
                this.subscribe();
            }
        },

        removeContract(index) {
            this.contracts.splice(index, 1);

            if (this.selectedIndex === index) {
                this.selectedIndex = this.contracts.length > 0 ? 0 : null;
            } else if (this.selectedIndex > index) {
                this.selectedIndex--;
            }

            // Resubscribe with remaining contracts
            if (this.isMonitoring && this.contracts.length > 0) {
                this.subscribe();
            } else if (this.contracts.length === 0) {
                this.stopMonitor();
            }
        },

        selectContract(index) {
            this.selectedIndex = index;
            console.log('Selected contract:', this.contracts[index].symbol);
        },

        async loadContractQuick() {
            if (!this.contractInput) return;
            if (this.contracts.length >= 20) {
                alert('Maximum 20 contracts reached');
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
            this.addContract(detail.symbol);

            if (!this.isMonitoring) {
                setTimeout(() => {
                    this.startMonitor();
                }, 500);
            }

            this.isLoading = false;
        },

        handleContractSelected(detail) {
            console.log('handleContractSelected called with:', detail);
            if (detail.symbol) {
                this.addContract(detail.symbol);

                if (!this.isMonitoring) {
                    setTimeout(() => {
                        this.startMonitor();
                    }, 500);
                }
            }
        },

        updateCredentials() {
            const socketUrl = @js($streamerSocketUrl);
            const customerId = @js($schwabClientCustomerId);
            const accessToken = @js($accessToken);

            if (socketUrl && socketUrl.length > 0 && customerId && customerId.length > 0 && accessToken && accessToken.length > 0) {
                this.credentials = {
                    streamerSocketUrl: socketUrl,
                    schwabClientCustomerId: customerId,
                    schwabClientCorrelId: @js($schwabClientCorrelId),
                    schwabClientChannel: @js($schwabClientChannel),
                    schwabClientFunctionId: @js($schwabClientFunctionId),
                    accessToken: accessToken
                };
                console.log('✓ Credentials successfully loaded');
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

                this.ws.onclose = () => {
                    console.log('WebSocket closed');
                    if (this.isMonitoring) {
                        setTimeout(() => this.connectWebSocket(), 3000);
                    }
                };
            } catch (error) {
                console.error('Failed to connect:', error);
                this.connectionState = 'error';
            }
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

            console.log('Subscribing to symbols:', symbols);
            this.ws.send(JSON.stringify(subscribeRequest));
        },

        handleMessage(data) {
            if (data.response) {
                data.response.forEach(resp => {
                    if (resp.command === 'LOGIN' && resp.content && resp.content.code === 0) {
                        console.log('Login successful');
                        this.connectionState = 'subscribed';
                        this.subscribe();
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

                if (contractIndex === this.selectedIndex) {
                    this.processFullQuote(contractIndex, quote);
                } else {
                    this.processLightQuote(contractIndex, quote);
                }
            });

            this.updateGlobalStats();
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

            const currentVolume = parseInt(quote['8']) || contract.quote.volume;

            if (contract.lastVolume !== null && currentVolume > contract.lastVolume && contract.quote.last > 0) {
                const size = currentVolume - contract.lastVolume;
                const price = contract.quote.last;
                const side = this.determineSide(contract.quote, price);
                const premium = Math.round(price * size * 100);
                const time = new Date().toLocaleTimeString();

                contract.prints.unshift({
                    time, price, size,
                    sequence: contract.detailedStats.totalPrints + 1,
                    side, premium,
                    volume: currentVolume
                });

                if (contract.prints.length > 100) {
                    contract.prints.pop();
                }

                this.updateDetailedStats(contract, size, side, premium);
                this.updateLightStats(contract.lightStats, side, premium);
            }

            if (contract.lastVolume === null) {
                contract.lastVolume = currentVolume;
            }

            if (currentVolume > 0) {
                contract.quote.volume = currentVolume;
                contract.lastVolume = currentVolume;
            }
        },

        processLightQuote(index, quote) {
            const contract = this.contracts[index];

            contract.quote.bid = parseFloat(quote['2']) || contract.quote.bid;
            contract.quote.ask = parseFloat(quote['3']) || contract.quote.ask;
            contract.quote.last = parseFloat(quote['4']) || contract.quote.last;

            const currentVolume = parseInt(quote['8']) || contract.quote.volume;

            if (contract.lastVolume !== null && currentVolume > contract.lastVolume && contract.quote.last > 0) {
                const size = currentVolume - contract.lastVolume;
                const price = contract.quote.last;
                const side = this.determineSide(contract.quote, price);
                const premium = Math.round(price * size * 100);

                this.updateLightStats(contract.lightStats, side, premium);
            }

            if (contract.lastVolume === null) {
                contract.lastVolume = currentVolume;
            }

            if (currentVolume > 0) {
                contract.quote.volume = currentVolume;
                contract.lastVolume = currentVolume;
            }
        },

        determineSide(quote, price) {
            if (!quote.bid || !quote.ask) return 'MID';

            const spread = quote.ask - quote.bid;
            const threshold = spread * 0.3;

            if (price >= quote.ask - threshold) return 'ASK';
            if (price <= quote.bid + threshold) return 'BID';
            return 'MID';
        },

        updateLightStats(stats, side, premium) {
            stats.totalTrades++;
            stats.totalPremium += premium;

            if (side === 'ASK' || side === 'MID') {
                stats.buyPremium += premium;
                stats.netPremium += premium;
            } else {
                stats.sellPremium += premium;
                stats.netPremium -= premium;
            }
        },

        updateDetailedStats(contract, size, side, premium) {
            const stats = contract.detailedStats;

            stats.totalPrints++;
            stats.totalVolume += size;
            stats.totalPremium += premium;

            if (side === 'ASK') {
                stats.askSideVolume += size;
                stats.buyPremium += premium;
                stats.netPremium += premium;
            } else if (side === 'BID') {
                stats.bidSideVolume += size;
                stats.sellPremium += premium;
                stats.netPremium -= premium;
            } else {
                stats.midVolume += size;
                stats.buyPremium += premium;
                stats.netPremium += premium;
            }

            if (size > stats.largestPrint) stats.largestPrint = size;

            if (stats.totalVolume > 0) {
                stats.askSidePercent = Math.round((stats.askSideVolume / stats.totalVolume) * 100);
                stats.bidSidePercent = Math.round((stats.bidSideVolume / stats.totalVolume) * 100);
                stats.midPercent = Math.round((stats.midVolume / stats.totalVolume) * 100);
            }
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
        }
    };
}
</script>

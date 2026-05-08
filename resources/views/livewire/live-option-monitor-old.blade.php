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

        <!-- Global Summary -->
        <div x-show="contracts.length > 0" class="mb-4 p-4 bg-slate-800/50 rounded-lg border border-slate-700">
            <h3 class="text-sm font-semibold text-slate-400 mb-3">
                Portfolio Summary (<span x-text="contracts.length"></span> contracts)
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="p-3 bg-purple-500/10 border border-purple-500/20 rounded">
                    <p class="text-xs text-slate-400">Total Premium</p>
                    <p class="text-lg font-bold text-purple-400" x-text="'$' + globalStats.totalPremium.toLocaleString()"></p>
                    <p class="text-xs text-slate-500" x-text="globalStats.totalTrades + ' trades'"></p>
                </div>
                <div class="p-3 bg-emerald-500/10 border border-emerald-500/20 rounded">
                    <p class="text-xs text-slate-400">Buy Premium</p>
                    <p class="text-lg font-bold text-emerald-400" x-text="'$' + globalStats.buyPremium.toLocaleString()"></p>
                </div>
                <div class="p-3 bg-rose-500/10 border border-rose-500/20 rounded">
                    <p class="text-xs text-slate-400">Sell Premium</p>
                    <p class="text-lg font-bold text-rose-400" x-text="'$' + globalStats.sellPremium.toLocaleString()"></p>
                </div>
                <div class="p-3 border rounded" :class="{
                    'bg-emerald-500/10 border-emerald-500/20': globalStats.netPremium >= 0,
                    'bg-rose-500/10 border-rose-500/20': globalStats.netPremium < 0
                }">
                    <p class="text-xs text-slate-400">Net Premium</p>
                    <p class="text-lg font-bold" :class="{
                        'text-emerald-400': globalStats.netPremium >= 0,
                        'text-rose-400': globalStats.netPremium < 0
                    }" x-text="(globalStats.netPremium >= 0 ? '+' : '') + '$' + globalStats.netPremium.toLocaleString()"></p>
                </div>
            </div>
        </div>

        <!-- Contract Tags -->
        <div x-show="contracts.length > 0" class="mb-4">
            <p class="text-xs text-slate-400 mb-2">Active Contracts</p>
            <div class="flex flex-wrap gap-2">
                <template x-for="(contract, index) in contracts" :key="contract.symbol">
                    <div @click="selectContract(index)"
                         class="cursor-pointer px-3 py-2 rounded-lg transition-all"
                         :class="{
                             'bg-purple-500/30 border-2 border-purple-500': index === selectedIndex,
                             'bg-slate-700/30 border border-slate-600 hover:bg-slate-700/50': index !== selectedIndex
                         }">
                        <div class="flex items-center space-x-2">
                            <span class="text-sm font-mono text-white" x-text="contract.symbol"></span>
                            <span class="text-xs px-1.5 py-0.5 rounded" :class="{
                                'bg-emerald-500/20 text-emerald-400': contract.lightStats.netPremium >= 0,
                                'bg-rose-500/20 text-rose-400': contract.lightStats.netPremium < 0
                            }" x-text="(contract.lightStats.netPremium >= 0 ? '+' : '') + '$' + (Math.abs(contract.lightStats.netPremium) / 1000).toFixed(1) + 'K'"></span>
                            <button @click.stop="removeContract(index)"
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

        <!-- Contract Details -->
        <div x-show="selectedIndex !== null" x-data="{ showDetails: false }">
            <div class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-4">
                <div class="p-3 bg-slate-700/30 rounded">
                    <p class="text-xs text-slate-400">Bid</p>
                    <p class="text-lg font-semibold text-rose-400" x-text="activeContract?.quote.bid || '-'"></p>
                </div>
                <div class="p-3 bg-slate-700/30 rounded">
                    <p class="text-xs text-slate-400">Ask</p>
                    <p class="text-lg font-semibold text-emerald-400" x-text="activeContract?.quote.ask || '-'"></p>
                </div>
                <div class="p-3 bg-slate-700/30 rounded">
                    <p class="text-xs text-slate-400">Volume</p>
                    <p class="text-lg font-semibold text-white" x-text="activeContract?.quote.volume || '-'"></p>
                </div>
                <div class="p-3 bg-purple-500/10 border border-purple-500/20 rounded">
                    <p class="text-xs text-slate-400">Total Premium</p>
                    <p class="text-base font-semibold text-purple-400" x-text="'$' + (activeContract?.detailedStats.totalPremium || 0).toLocaleString()"></p>
                </div>
                <div class="p-3 bg-emerald-500/10 border border-emerald-500/20 rounded">
                    <p class="text-xs text-slate-400">Buy Premium</p>
                    <p class="text-base font-semibold text-emerald-400" x-text="'$' + (activeContract?.detailedStats.buyPremium || 0).toLocaleString()"></p>
                </div>
                <div class="p-3 bg-rose-500/10 border border-rose-500/20 rounded">
                    <p class="text-xs text-slate-400">Sell Premium</p>
                    <p class="text-base font-semibold text-rose-400" x-text="'$' + (activeContract?.detailedStats.sellPremium || 0).toLocaleString()"></p>
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
            <button @click="showDetails = true" class="w-full px-4 py-2 text-sm font-medium text-slate-400 hover:text-white bg-slate-700/30 hover:bg-slate-700/50 rounded transition-colors">
                View Details
            </button>
        </div>

        <!-- Detailed View -->
        <div x-show="selectedIndex !== null && showDetails">
            <div class="flex justify-end mb-4">
                <button @click="showDetails = false" class="px-4 py-2 text-sm font-medium text-slate-400 hover:text-white bg-slate-700/30 hover:bg-slate-700/50 rounded transition-colors">
                    Hide Details
                </button>
            </div>

            <!-- Live Quote Panel -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
                <div class="p-3 bg-slate-700/30 rounded">
                    <p class="text-xs text-slate-400">Bid</p>
                    <p class="text-lg font-semibold text-rose-400" x-text="activeContract?.quote.bid || '-'"></p>
                </div>
                <div class="p-3 bg-slate-700/30 rounded">
                    <p class="text-xs text-slate-400">Ask</p>
                    <p class="text-lg font-semibold text-emerald-400" x-text="activeContract?.quote.ask || '-'"></p>
                </div>
                <div class="p-3 bg-slate-700/30 rounded">
                    <p class="text-xs text-slate-400">Last</p>
                    <p class="text-lg font-semibold text-white" x-text="activeContract?.quote.last || '-'"></p>
                </div>
                <div class="p-3 bg-slate-700/30 rounded">
                    <p class="text-xs text-slate-400">Mark</p>
                    <p class="text-lg font-semibold text-blue-400" x-text="activeContract?.quote.mark || '-'"></p>
                </div>
                <div class="p-3 bg-slate-700/30 rounded">
                    <p class="text-xs text-slate-400">Volume</p>
                    <p class="text-lg font-semibold text-white" x-text="activeContract?.quote.volume || '-'"></p>
                </div>
            </div>

            <!-- Greeks -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
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

            <!-- Statistics -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                <div class="p-3 bg-emerald-500/10 border border-emerald-500/20 rounded">
                    <p class="text-xs text-slate-400">Ask-Side Volume</p>
                    <p class="text-lg font-semibold text-emerald-400" x-text="activeContract?.detailedStats.askSideVolume || 0"></p>
                    <p class="text-xs text-slate-500" x-text="(activeContract?.detailedStats.askSidePercent || 0) + '%'"></p>
                </div>
                <div class="p-3 bg-rose-500/10 border border-rose-500/20 rounded">
                    <p class="text-xs text-slate-400">Bid-Side Volume</p>
                    <p class="text-lg font-semibold text-rose-400" x-text="activeContract?.detailedStats.bidSideVolume || 0"></p>
                    <p class="text-xs text-slate-500" x-text="(activeContract?.detailedStats.bidSidePercent || 0) + '%'"></p>
                </div>
                <div class="p-3 bg-blue-500/10 border border-blue-500/20 rounded">
                    <p class="text-xs text-slate-400">Mid Volume</p>
                    <p class="text-lg font-semibold text-blue-400" x-text="activeContract?.detailedStats.midVolume || 0"></p>
                    <p class="text-xs text-slate-500" x-text="(activeContract?.detailedStats.midPercent || 0) + '%'"></p>
                </div>
                <div class="p-3 bg-purple-500/10 border border-purple-500/20 rounded">
                    <p class="text-xs text-slate-400">Total Premium</p>
                    <p class="text-lg font-semibold text-purple-400" x-text="'$' + (activeContract?.detailedStats.totalPremium || 0).toLocaleString()"></p>
                    <p class="text-xs text-slate-500" x-text="(activeContract?.detailedStats.totalPrints || 0) + ' trades'"></p>
                </div>
            </div>

            <!-- Premium Flow -->
            <div class="grid grid-cols-3 gap-3 mb-4">
                <div class="p-3 bg-emerald-500/10 border border-emerald-500/20 rounded">
                    <p class="text-xs text-slate-400">Buy Premium (Ask+Mid)</p>
                    <p class="text-xl font-bold text-emerald-400" x-text="'$' + (activeContract?.detailedStats.buyPremium || 0).toLocaleString()"></p>
                </div>
                <div class="p-3 border rounded" :class="{
                    'bg-emerald-500/10 border-emerald-500/20': (activeContract?.detailedStats.netPremium || 0) >= 0,
                    'bg-rose-500/10 border-rose-500/20': (activeContract?.detailedStats.netPremium || 0) < 0
                }">
                    <p class="text-xs text-slate-400">Net Premium Flow</p>
                    <p class="text-xl font-bold" :class="{
                        'text-emerald-400': (activeContract?.detailedStats.netPremium || 0) >= 0,
                        'text-rose-400': (activeContract?.detailedStats.netPremium || 0) < 0
                    }" x-text="((activeContract?.detailedStats.netPremium || 0) >= 0 ? '+' : '') + '$' + (activeContract?.detailedStats.netPremium || 0).toLocaleString()"></p>
                </div>
                <div class="p-3 bg-rose-500/10 border border-rose-500/20 rounded">
                    <p class="text-xs text-slate-400">Sell Premium (Bid)</p>
                    <p class="text-xl font-bold text-rose-400" x-text="'$' + (activeContract?.detailedStats.sellPremium || 0).toLocaleString()"></p>
                </div>
            </div>

            <!-- Prints Table -->
            <div class="bg-slate-700/20 rounded-lg overflow-hidden">
                <div class="px-4 py-2 bg-slate-700/50 border-b border-slate-600">
                    <h3 class="text-sm font-semibold text-white">Live Prints</h3>
                </div>
                <div class="max-h-96 overflow-y-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-slate-700/30 sticky top-0">
                            <tr>
                                <th class="px-3 py-2 text-left text-slate-400 font-medium">Time</th>
                                <th class="px-3 py-2 text-right text-slate-400 font-medium">Price</th>
                                <th class="px-3 py-2 text-right text-slate-400 font-medium">Size</th>
                                <th class="px-3 py-2 text-right text-slate-400 font-medium">Volume</th>
                                <th class="px-3 py-2 text-right text-slate-400 font-medium">Premium</th>
                                <th class="px-3 py-2 text-center text-slate-400 font-medium">Side</th>
                                <th class="px-3 py-2 text-right text-slate-400 font-medium">Seq</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="print in (activeContract?.prints || [])" :key="print.sequence">
                                <tr class="border-b border-slate-700/30 hover:bg-slate-700/20">
                                    <td class="px-3 py-2 text-slate-300" x-text="print.time"></td>
                                    <td class="px-3 py-2 text-right font-mono text-white" x-text="print.price.toFixed(2)"></td>
                                    <td class="px-3 py-2 text-right text-slate-300" x-text="print.size"></td>
                                    <td class="px-3 py-2 text-right text-slate-400" x-text="print.volume || '-'"></td>
                                    <td class="px-3 py-2 text-right text-slate-300" x-text="'$' + print.premium.toLocaleString()"></td>
                                    <td class="px-3 py-2 text-center">
                                        <span :class="{
                                            'px-2 py-0.5 rounded text-xs font-medium': true,
                                            'bg-emerald-500/20 text-emerald-400': print.side === 'ASK',
                                            'bg-rose-500/20 text-rose-400': print.side === 'BID',
                                            'bg-blue-500/20 text-blue-400': print.side === 'MID'
                                        }" x-text="print.side"></span>
                                    </td>
                                    <td class="px-3 py-2 text-right text-slate-500" x-text="print.sequence"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    <div x-show="!activeContract || activeContract.prints.length === 0" class="p-8 text-center text-slate-500">
                        <p class="text-sm">No prints yet. Waiting for market activity...</p>
                    </div>
                </div>
            </div>
        </div>
    </x-card>
</div>

<script>
function optionMonitor() {

            async loadContractQuick() {
                if (!this.contractInput) return;

                // Show loading overlay
                this.isLoading = true;

                // Parse: SPXW260505C7250
                const input = this.contractInput.replace(/^\./, '');
                const match = input.match(/^([A-Z]+)(\d{6})([CP])(\d+)$/);

                if (!match) {
                    alert('Invalid format. Use: SYMBOL + YYMMDD + C/P + STRIKE');
                    this.isLoading = false;
                    return;
                }

                const symbolRoot = match[1]; // SPXW
                const dateStr = match[2];    // 260505
                const type = match[3];       // C or P
                const strike = match[4];     // 7250

                // Convert date to Y-m-d format
                const date = '20' + dateStr.substring(0, 2) + '-' + dateStr.substring(2, 4) + '-' + dateStr.substring(4, 6);

                // Determine underlying symbol (SPX for SPXW)
                const underlying = symbolRoot.startsWith('SPXW') ? '$SPX' : symbolRoot;
            // Show loading overlay
            this.isLoading = true;

            // Parse: SPXW260505C7250
            const input = this.contractInput.replace(/^\./, '');
            const match = input.match(/^([A-Z]+)(\d{6})([CP])(\d+)$/);

            if (!match) {
                alert('Invalid format. Use: SYMBOL + YYMMDD + C/P + STRIKE');
                this.isLoading = false;
                return;
            }

            const symbolRoot = match[1]; // SPXW
            const dateStr = match[2];    // 260505
            const type = match[3];       // C or P
            const strike = match[4];     // 7250

            // Convert date to Y-m-d format
            const date = '20' + dateStr.substring(0, 2) + '-' + dateStr.substring(2, 4) + '-' + dateStr.substring(4, 6);

            // Determine underlying symbol (SPX for SPXW)
            const underlying = symbolRoot.startsWith('SPXW') ? '$SPX' : symbolRoot;

            console.log('Fetching contract from API...', {
                symbol: underlying,
                contractType: type === 'C' ? 'CALL' : 'PUT',
                strike: parseInt(strike),
                fromDate: date,
                toDate: date
            });

            try {
                // Call Livewire to fetch the real symbol from API
                await this.$wire.fetchContractSymbol(underlying, type === 'C' ? 'CALL' : 'PUT', parseInt(strike), date);
            } catch (error) {
                console.error('Error loading contract:', error);
                this.isLoading = false;
            }
        },

        updateCredentials() {
            const socketUrl = @js($streamerSocketUrl);
            const customerId = @js($schwabClientCustomerId);
            const accessToken = @js($accessToken);

            console.log('Checking credentials:', {
                socketUrl: socketUrl || 'EMPTY',
                customerId: customerId || 'EMPTY',
                accessToken: accessToken ? 'SET (' + accessToken.length + ' chars)' : 'EMPTY'
            });

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

        updateContractSymbol() {
            const contract = @js($selectedContract);
            if (contract && contract.length > 0) {
                this.contractSymbol = contract;
                console.log('✓ Contract symbol set:', contract);
            } else {
                console.log('✗ No contract symbol available');
            }
        },

        handleContractLoaded(detail) {
            console.log('handleContractLoaded called with:', detail);
            this.contractSymbol = detail.symbol;
            this.prints = [];
            this.lastVolume = null;
            this.lastPrice = null;
            this.resetStats();
            console.log('Contract symbol set to:', this.contractSymbol);

            // Auto-start monitoring after contract is loaded
            setTimeout(() => {
                this.startMonitor();
            }, 500);
        },

        startMonitor() {
            // Refresh data from Livewire
            this.updateCredentials();
            this.updateContractSymbol();

            if (!this.credentials) {
                alert('Missing streaming credentials. Please ensure Trader API is authenticated.');
                this.isLoading = false;
                return;
            }

            if (!this.contractSymbol) {
                alert('Please load a contract first');
                this.isLoading = false;
                return;
            }

            console.log('Starting monitor for:', this.contractSymbol);
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
                    // Hide loading overlay once connected
                    setTimeout(() => {
                        this.isLoading = false;
                    }, 1000);
                };

                this.ws.onmessage = (event) => {
                    console.log('WebSocket message:', event.data);
                    this.handleMessage(JSON.parse(event.data));
                };

                this.ws.onerror = (error) => {
                    console.error('WebSocket error:', error);
                    this.connectionState = 'error';
                };

                this.ws.onclose = () => {
                    console.log('WebSocket closed');
                    if (this.isMonitoring) {
                        // Auto-reconnect
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
            // Remove leading dot for websocket subscription
            const symbol = this.contractSymbol.replace(/^\./, '');

            const subscribeRequest = {
                "requests": [
                    {
                        "service": "LEVELONE_OPTIONS",
                        "requestid": "1",
                        "command": "SUBS",
                        "SchwabClientCustomerId": this.credentials.schwabClientCustomerId,
                        "SchwabClientCorrelId": this.credentials.schwabClientCorrelId,
                        "parameters": {
                            "keys": symbol,
                            "fields": "0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40,41"
                        }
                    }
                ]
            };

            console.log('Subscribing to symbol:', symbol);
            console.log('Subscribe request:', JSON.stringify(subscribeRequest, null, 2));
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
                    console.log('Data service:', item.service, item);
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

            console.log('LEVELONE_OPTIONS data received:', data);

            data.content.forEach(quote => {
                console.log('Raw quote data:', quote);

                // Schwab streaming API returns object with string keys
                // Actual field mapping from API:
                // '2': bid, '3': ask, '4': last, '8': TOTAL VOLUME (not lastSize!)
                // '11': last size, '16': open interest, '17': volatility
                // '28': delta, '29': gamma, '30': theta, '31': vega

                this.quote.bid = parseFloat(quote['2']) || this.quote.bid;
                this.quote.ask = parseFloat(quote['3']) || this.quote.ask;
                this.quote.last = parseFloat(quote['4']) || this.quote.last;
                this.quote.mark = parseFloat(quote['5']) || this.quote.mark;
                const lastSize = parseInt(quote['11']) || 0; // Field 11 is last size
                const currentVolume = parseInt(quote['8']) || this.quote.volume; // Field 8 is total volume

                console.log('Parsed values:', {
                    lastSize: lastSize,
                    currentVolume: currentVolume,
                    bid: this.quote.bid,
                    ask: this.quote.ask,
                    last: this.quote.last
                });
                this.quote.delta = parseFloat(quote['28']) || this.quote.delta;
                this.quote.gamma = parseFloat(quote['29']) || this.quote.gamma;
                this.quote.theta = parseFloat(quote['30']) || this.quote.theta;
                this.quote.vega = parseFloat(quote['31']) || this.quote.vega;
                this.quote.iv = parseFloat(quote['17']) || this.quote.iv;

                // Detect volume change (trade occurred)
                if (this.lastVolume !== null && currentVolume > this.lastVolume && this.quote.last > 0) {
                    const volumeDelta = currentVolume - this.lastVolume;
                    const tradeSize = volumeDelta; // Use volume delta as the most reliable size
                    const price = this.quote.last;
                    const side = this.determineSide(price);
                    const premium = Math.round(price * tradeSize * 100);
                    const time = new Date().toLocaleTimeString();

                    console.log('Volume change detected:', {
                        lastVolume: this.lastVolume,
                        currentVolume: currentVolume,
                        delta: volumeDelta,
                        tradeSize: tradeSize,
                        price: price,
                        side: side
                    });

                    // Add to prints if not duplicate
                    const isDuplicate = this.prints.length > 0 &&
                                       this.prints[0].price === price &&
                                       this.prints[0].size === tradeSize &&
                                       this.prints[0].time === time;

                    if (!isDuplicate) {
                        this.prints.unshift({
                            time,
                            price,
                            size: tradeSize,
                            sequence: this.stats.totalPrints + 1,
                            side,
                            premium,
                            volume: currentVolume
                        });

                        // Keep only last 100 prints
                        if (this.prints.length > 100) {
                            this.prints.pop();
                        }

                        this.updateStats(tradeSize, side, premium);
                    }
                }

                // Initialize or update volume tracking
                if (this.lastVolume === null) {
                    this.lastVolume = currentVolume;
                    console.log('Initial volume set to:', currentVolume);

                    // If there's already volume on initialization, count it for total stats only
                    if (currentVolume > 0 && this.quote.last > 0) {
                        const side = this.determineSide(this.quote.last);
                        const premium = Math.round(this.quote.last * currentVolume * 100);

                        console.log('Initializing with existing volume:', {
                            volume: currentVolume,
                            price: this.quote.last,
                            side: side,
                            premium: premium
                        });

                        // Update total stats but NOT buy/sell premium flow
                        this.stats.totalVolume += currentVolume;
                        this.stats.totalPremium += premium;

                        if (side === 'ASK') this.stats.askSideVolume += currentVolume;
                        else if (side === 'BID') this.stats.bidSideVolume += currentVolume;
                        else this.stats.midVolume += currentVolume;

                        // Calculate percentages
                        if (this.stats.totalVolume > 0) {
                            this.stats.askSidePercent = Math.round((this.stats.askSideVolume / this.stats.totalVolume) * 100);
                            this.stats.bidSidePercent = Math.round((this.stats.bidSideVolume / this.stats.totalVolume) * 100);
                            this.stats.midPercent = Math.round((this.stats.midVolume / this.stats.totalVolume) * 100);
                        }
                    }
                }

                if (currentVolume > 0) {
                    this.quote.volume = currentVolume;
                    this.lastVolume = currentVolume;
                }

                console.log('Updated quote:', this.quote);
            });
        },

        determineSide(price) {
            if (!this.quote.bid || !this.quote.ask) return 'MID';

            const spread = this.quote.ask - this.quote.bid;
            const threshold = spread * 0.3;

            if (price >= this.quote.ask - threshold) return 'ASK';
            if (price <= this.quote.bid + threshold) return 'BID';
            return 'MID';
        },

        updateStats(size, side, premium) {
            this.stats.totalPrints++;
            this.stats.totalVolume += size;
            this.stats.totalPremium += premium;

            if (side === 'ASK') {
                this.stats.askSideVolume += size;
                this.stats.buyPremium += premium;
                this.stats.netPremium += premium;
            } else if (side === 'BID') {
                this.stats.bidSideVolume += size;
                this.stats.sellPremium += premium;
                this.stats.netPremium -= premium;
            } else {
                this.stats.midVolume += size;
                this.stats.buyPremium += premium;
                this.stats.netPremium += premium;
            }

            if (size > this.stats.largestPrint) this.stats.largestPrint = size;

            // Calculate percentages
            if (this.stats.totalVolume > 0) {
                this.stats.askSidePercent = Math.round((this.stats.askSideVolume / this.stats.totalVolume) * 100);
                this.stats.bidSidePercent = Math.round((this.stats.bidSideVolume / this.stats.totalVolume) * 100);
                this.stats.midPercent = Math.round((this.stats.midVolume / this.stats.totalVolume) * 100);
            }
        },

        resetStats() {
            this.stats = {
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
            };
        }
    };
}
</script>

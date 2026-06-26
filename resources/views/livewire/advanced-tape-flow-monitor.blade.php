<script>
function tapeFlowMonitor() {
    return {
        ws: null,
        connectionState: 'disconnected',
        credentials: null,
        requestId: 0,
        heartbeatInterval: null,
        reconnectTimeout: null,
        reconnectAttempts: 0,
        maxReconnectAttempts: 5,

        init() {
            console.log('🚀 Advanced Tape Flow Monitor initialized');
            this.loadCredentials();
        },

        loadCredentials() {
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
                console.log('✓ URL: ' + this.credentials.streamerSocketUrl);
                this.connectWebSocket();
            } else {
                this.credentials = null;
                console.warn('✗ Missing credentials - cannot start monitor');
                console.log('Debug:', { socketUrl, customerId, accessToken: accessToken ? 'SET' : 'NULL' });
            }
        },

        connectWebSocket() {
            if (!this.credentials || !this.credentials.streamerSocketUrl) {
                console.warn('Cannot connect: missing credentials');
                return;
            }

            this.connectionState = 'connecting';
            this.ws = new WebSocket(this.credentials.streamerSocketUrl);

            this.ws.onopen = () => {
                console.log('✓ WebSocket connected');
                this.connectionState = 'authenticating';
                this.authenticate();
            };

            this.ws.onmessage = (event) => {
                this.handleMessage(JSON.parse(event.data));
            };

            this.ws.onerror = (error) => {
                console.error('WebSocket error:', error);
                this.connectionState = 'error';
            };

            this.ws.onclose = () => {
                console.log('⚠️ WebSocket closed');
                this.connectionState = 'disconnected';
                this.stopHeartbeat();
                this.scheduleReconnect();
            };
        },

        startHeartbeat() {
            // Enviar heartbeat cada 30 segundos para mantener la conexión viva
            this.heartbeatInterval = setInterval(() => {
                if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                    const heartbeat = {
                        requests: [{
                            service: 'ADMIN',
                            command: 'QOS',
                            requestid: this.requestId++,
                            SchwabClientCustomerId: this.credentials.schwabClientCustomerId,
                            SchwabClientCorrelId: this.credentials.schwabClientCorrelId,
                            parameters: {
                                qoslevel: '0'
                            }
                        }]
                    };
                    this.ws.send(JSON.stringify(heartbeat));
                    console.log('💓 Heartbeat sent');
                }
            }, 30000);
        },

        stopHeartbeat() {
            if (this.heartbeatInterval) {
                clearInterval(this.heartbeatInterval);
                this.heartbeatInterval = null;
            }
        },

        scheduleReconnect() {
            if (this.reconnectAttempts >= this.maxReconnectAttempts) {
                console.error('❌ Max reconnect attempts reached');
                return;
            }

            this.reconnectAttempts++;
            const delay = Math.min(1000 * Math.pow(2, this.reconnectAttempts), 30000);
            console.log(`🔄 Reconnecting in ${delay/1000}s (attempt ${this.reconnectAttempts}/${this.maxReconnectAttempts})`);

            this.reconnectTimeout = setTimeout(() => {
                console.log('🔄 Attempting to reconnect...');
                this.connectWebSocket();
            }, delay);
        },

        authenticate() {
            // Verificar que el WebSocket está realmente abierto
            if (!this.ws || this.ws.readyState !== WebSocket.OPEN) {
                console.warn('WebSocket not ready, waiting...');
                setTimeout(() => this.authenticate(), 100);
                return;
            }

            const loginRequest = {
                requests: [{
                    service: 'ADMIN',
                    command: 'LOGIN',
                    requestid: this.requestId++,
                    SchwabClientCustomerId: this.credentials.schwabClientCustomerId,
                    SchwabClientCorrelId: this.credentials.schwabClientCorrelId,
                    parameters: {
                        Authorization: this.credentials.accessToken,
                        SchwabClientChannel: this.credentials.schwabClientChannel,
                        SchwabClientFunctionId: this.credentials.schwabClientFunctionId
                    }
                }]
            };

            console.log('Sending authentication request...');
            this.ws.send(JSON.stringify(loginRequest));
        },

        handleMessage(data) {
            console.log('📨 Message received:', data);

            if (data.response && data.response[0]) {
                const response = data.response[0];
                console.log('Response:', response);

                if (response.command === 'LOGIN' && response.content.code === 0) {
                    console.log('✓ Authenticated successfully');
                    this.connectionState = 'streaming';
                    this.reconnectAttempts = 0; // Reset reconnect counter
                    this.startHeartbeat(); // Start heartbeat to keep connection alive
                    this.subscribeToSPX();
                } else if (response.command === 'SUBS') {
                    console.log('✓ Subscription confirmed');
                } else if (response.command === 'QOS') {
                    // Heartbeat response - connection is alive
                }
            }

            if (data.data) {
                console.log('📊 Trade data received:', data.data.length, 'items');
                this.processTrades(data.data);
            }
        },

        subscribeToSPX() {
            // Generate current week's SPX options symbols
            const today = new Date();
            const currentFriday = new Date(today);
            const daysUntilFriday = (5 - today.getDay() + 7) % 7;
            currentFriday.setDate(today.getDate() + daysUntilFriday);

            const year = currentFriday.getFullYear().toString().slice(-2);
            const month = (currentFriday.getMonth() + 1).toString().padStart(2, '0');
            const day = currentFriday.getDate().toString().padStart(2, '0');
            const dateSuffix = `${year}${month}${day}`;

            // Generate strikes around current SPX level (assuming around 5300-5400)
            const strikes = ['5200', '5250', '5300', '5350', '5400', '5450', '5500'];
            const optionKeys = [];

            strikes.forEach(strike => {
                optionKeys.push(`.SPXW${dateSuffix}C${strike}`);
                optionKeys.push(`.SPXW${dateSuffix}P${strike}`);
            });

            const subscribeRequest = {
                requests: [{
                    service: 'LEVELONE_OPTIONS',
                    command: 'SUBS',
                    requestid: '1',
                    SchwabClientCustomerId: this.credentials.schwabClientCustomerId,
                    SchwabClientCorrelId: this.credentials.schwabClientCorrelId,
                    parameters: {
                        keys: optionKeys.join(','),
                        fields: '0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40,41'
                    }
                }]
            };

            this.ws.send(JSON.stringify(subscribeRequest));
            console.log('✓ Subscribed to SPX options:', optionKeys.join(','));
        },

        async processTrades(trades) {
            console.log('🔄 Processing', trades.length, 'trades');

            let processedCount = 0;
            let errorCount = 0;

            for (const trade of trades) {
                try {
                    // Validate trade data before sending
                    if (!trade.key || !trade.LAST_PRICE || !trade.LAST_SIZE) {
                        console.warn('Skipping invalid trade data:', trade);
                        continue;
                    }

                    // Normalize trade data for backend
                    const normalizedTrade = {
                        key: trade.key,
                        symbol: trade.key,
                        LAST_PRICE: trade.LAST_PRICE,
                        BID_PRICE: trade.BID_PRICE || 0,
                        ASK_PRICE: trade.ASK_PRICE || 0,
                        LAST_SIZE: trade.LAST_SIZE,
                        QUOTE_TIME: trade.QUOTE_TIME || Date.now(),
                        timestamp: trade.timestamp || Date.now()
                    };

                    const response = await fetch('/api/advanced-tape-flow/process-trade', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(normalizedTrade)
                    });

                    if (response.ok) {
                        processedCount++;
                        const result = await response.json();
                        if (!result.success) {
                            console.warn('Trade processing failed:', result.error);
                            errorCount++;
                        }
                    } else {
                        console.warn('Failed to process trade:', response.status, response.statusText);
                        errorCount++;
                    }
                } catch (error) {
                    console.error('Error processing trade:', error);
                    errorCount++;
                }
            }

            console.log(`✅ Processed ${processedCount} trades, ${errorCount} errors`);

            // Refresh UI more frequently when receiving data
            if (processedCount > 0) {
                console.log('🔄 Refreshing UI data...');
                this.$wire.call('refreshData');
            }
        }
    };
}
</script>

<div x-data="tapeFlowMonitor()" x-init="init()"
     class="space-y-6">

    <!-- Header Card -->
    <x-card class="relative">
        <!-- Loading Overlay -->
        @if($isLoading)
        <div class="absolute inset-0 bg-slate-900/80 backdrop-blur-sm z-50 flex items-center justify-center rounded-lg">
            <div class="text-center">
                <div class="inline-block animate-spin rounded-full h-12 w-12 border-4 border-purple-500 border-t-transparent mb-4"></div>
                <p class="text-white font-medium">Loading tape flow data...</p>
                <p class="text-slate-400 text-sm mt-1">Processing real-time trades</p>
            </div>
        </div>
        @endif

        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-white">Advanced Tape Flow Monitor</h2>
            <div class="flex items-center space-x-4">
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
                <div class="text-right">
                    <div class="text-xs text-slate-400">Last Update</div>
                    <div class="text-sm text-white font-mono">{{ $lastUpdate }}</div>
                </div>
                <button
                    wire:click="refreshData"
                    wire:loading.attr="disabled"
                    class="px-4 py-2 bg-purple-500 hover:bg-purple-600 disabled:opacity-50 text-white rounded-lg transition-colors flex items-center space-x-2">
                    <svg wire:loading wire:target="refreshData" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span wire:loading wire:target="refreshData">Refreshing...</span>
                    <span wire:loading.remove wire:target="refreshData">Refresh</span>
                </button>
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
                <p class="text-xs">This feature requires access to Schwab Trader API for real-time streaming.</p>
            </div>
        @endif

        <!-- Controls -->
        <div class="flex flex-wrap items-center gap-4">
            <div class="flex items-center space-x-2">
                <label class="text-sm text-slate-400">Time Window:</label>
                <select wire:model.live="selectedWindow" class="bg-slate-700 text-white rounded px-3 py-1 text-sm">
                    <option value="1m">1 Minute</option>
                    <option value="5m">5 Minutes</option>
                    <option value="15m">15 Minutes</option>
                    <option value="day">Today</option>
                </select>
            </div>

            <div class="flex items-center space-x-2">
                <label class="text-sm text-slate-400">Tape Filter:</label>
                <select wire:model.live="selectedClassification" class="bg-slate-700 text-white rounded px-3 py-1 text-sm">
                    <option value="all">All Trades</option>
                    <option value="BUY">Buy Only</option>
                    <option value="SELL">Sell Only</option>
                    <option value="MID">Mid Only</option>
                </select>
            </div>

            <div class="flex items-center space-x-2">
                <label class="text-sm text-slate-400">Tape Limit:</label>
                <input type="number" wire:model.live="tapeLimit" min="10" max="200" step="10"
                       class="bg-slate-700 text-white rounded px-3 py-1 text-sm w-20">
            </div>

            <div class="flex items-center space-x-4">
                <label class="flex items-center space-x-2 text-sm text-slate-400">
                    <input type="checkbox" wire:model.live="showPositions" class="rounded">
                    <span>Positions</span>
                </label>
                <label class="flex items-center space-x-2 text-sm text-slate-400">
                    <input type="checkbox" wire:model.live="showTape" class="rounded">
                    <span>Tape</span>
                </label>
                <label class="flex items-center space-x-2 text-sm text-slate-400">
                    <input type="checkbox" wire:model.live="showAggressive" class="rounded">
                    <span>Aggressive</span>
                </label>
                <label class="flex items-center space-x-2 text-sm text-slate-400">
                    <input type="checkbox" wire:model.live="showNoise" class="rounded">
                    <span>Noise</span>
                </label>
            </div>
        </div>
    </x-card>

    <!-- Global Flow Summary -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-card>
            <div class="text-sm text-slate-400 mb-1">Total Premium</div>
            <div class="text-2xl font-bold text-white">{{ $this->formatPremium($globalData['total_premium'] ?? 0) }}</div>
            <div class="text-xs text-slate-500 mt-1">{{ $globalData['total_trades'] ?? 0 }} trades</div>
        </x-card>

        <x-card>
            <div class="text-sm text-slate-400 mb-1">Buy vs Sell</div>
            <div class="flex items-center space-x-2">
                <div class="text-lg font-bold text-emerald-400">{{ $this->formatPremium($globalData['buy_premium'] ?? 0) }}</div>
                <div class="text-slate-400">vs</div>
                <div class="text-lg font-bold text-red-400">{{ $this->formatPremium($globalData['sell_premium'] ?? 0) }}</div>
            </div>
            <div class="text-xs text-slate-500 mt-1">{{ $this->formatPremium($globalData['mid_premium'] ?? 0) }} MID</div>
        </x-card>

        <x-card>
            <div class="text-sm text-slate-400 mb-1">Directional Score</div>
            <div class="flex items-center space-x-2">
                <div class="text-lg font-bold text-emerald-400">{{ $this->formatPremium($globalData['bullish_score'] ?? 0) }}</div>
                <div class="text-slate-400">vs</div>
                <div class="text-lg font-bold text-red-400">{{ $this->formatPremium($globalData['bearish_score'] ?? 0) }}</div>
            </div>
            <div class="text-xs {{ $this->getConfidenceColor($globalData['confidence_level'] ?? 'UNKNOWN') }} mt-1">
                {{ $globalData['confidence_level'] ?? 'UNKNOWN' }} Confidence
            </div>
        </x-card>

        <x-card>
            <div class="text-sm text-slate-400 mb-1">Avg Aggressiveness</div>
            <div class="text-2xl font-bold text-white">{{ $this->formatAggressiveness($globalData['avg_aggressiveness'] ?? 0) }}</div>
            <div class="text-xs text-slate-500 mt-1">{{ number_format(($globalData['mid_noise_ratio'] ?? 0) * 100, 1) }}% MID Noise</div>
        </x-card>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Left Column -->
        <div class="space-y-6">
            <!-- Top Bullish Contracts -->
            @if($showAggressive && !empty($topBullish))
                <x-card>
                    <h3 class="text-lg font-semibold text-white mb-3 flex items-center">
                        🟢 Top Bullish Contracts
                    </h3>
                    <div class="space-y-2">
                        @foreach($topBullish as $contract)
                            <div class="flex items-center justify-between p-2 bg-slate-700/30 rounded">
                                <div>
                                    <div class="text-sm font-medium text-white">
                                        {{ $contract['symbol'] }} {{ $contract['strike'] }}{{ $contract['type'] }}
                                    </div>
                                    <div class="text-xs text-slate-400">{{ $contract['expiration'] }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-bold text-emerald-400">
                                        {{ $this->formatPremium($contract['directional_score']) }}
                                    </div>
                                    <div class="text-xs text-slate-400">
                                        {{ $this->formatAggressiveness($contract['avg_aggressiveness']) }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @endif

            <!-- Most Aggressive Flow -->
            @if($showAggressive && !empty($mostAggressive))
                <x-card>
                    <h3 class="text-lg font-semibold text-white mb-3 flex items-center">
                        ⚡ Most Aggressive Flow
                    </h3>
                    <div class="space-y-2">
                        @foreach($mostAggressive as $contract)
                            <div class="flex items-center justify-between p-2 bg-slate-700/30 rounded">
                                <div>
                                    <div class="text-sm font-medium text-white">
                                        {{ $contract['symbol'] }} {{ $contract['strike'] }}{{ $contract['type'] }}
                                    </div>
                                    <div class="text-xs text-slate-400">{{ $contract['expiration'] }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-bold text-yellow-400">
                                        {{ $this->formatAggressiveness($contract['avg_aggressiveness']) }}
                                    </div>
                                    <div class="text-xs text-slate-400">
                                        {{ $this->formatPremium($contract['total_premium']) }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @endif
        </div>

        <!-- Right Column -->
        <div class="space-y-6">
            <!-- Live Tape Stream -->
            @if($showTape && !empty($recentTape))
                <x-card>
                    <h3 class="text-lg font-semibold text-white mb-3 flex items-center">
                        📈 Live Tape Stream
                    </h3>
                    <div class="space-y-1 max-h-96 overflow-y-auto">
                        @foreach(array_slice($recentTape, 0, $tapeLimit) as $trade)
                            <div class="flex items-center justify-between p-2 bg-slate-700/30 rounded text-xs">
                                <div class="flex items-center space-x-3">
                                    <div class="w-16 text-right">
                                        <div class="font-medium text-white">{{ $trade['symbol'] }}</div>
                                        <div class="text-slate-400">{{ $trade['strike'] }}</div>
                                    </div>
                                    <div class="w-8 text-center">
                                        <span class="{{ $this->getClassificationColor($trade['classification']) }} px-1 py-0.5 rounded text-xs">
                                            {{ $trade['type'] }}
                                        </span>
                                    </div>
                                    <div class="w-20">
                                        <div class="font-medium text-white">{{ $this->formatPremium($trade['premium']) }}</div>
                                        <div class="text-slate-400">@{{ $trade['tradePrice'] }}</div>
                                    </div>
                                    <div class="w-16">
                                        <div class="flex items-center space-x-1">
                                            <div class="flex-1 bg-slate-600 rounded-full h-1.5">
                                                <div class="{{ $this->getAggressivenessBarColor($trade['aggressiveness']) }} h-1.5 rounded-full"
                                                     style="width: {{ $trade['aggressiveness'] * 100 }}%"></div>
                                            </div>
                                            <span class="text-slate-400">{{ $this->formatAggressiveness($trade['aggressiveness']) }}</span>
                                        </div>
                                    </div>
                                    <div class="w-12">
                                        <span class="{{ $this->getClassificationColor($trade['classification']) }} px-1 py-0.5 rounded text-xs">
                                            {{ $trade['classification'] }}
                                        </span>
                                    </div>
                                </div>
                                <div class="text-slate-500 text-right">
                                    {{ date('H:i:s', $trade['timestamp'] / 1000) }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @endif
        </div>
    </div>
</div>

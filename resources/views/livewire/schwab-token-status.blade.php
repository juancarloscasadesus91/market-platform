<div class="bg-slate-800/50 rounded-lg p-4 border border-slate-700/50">
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-sm font-semibold text-white">Schwab API Status</h3>
        <button wire:click="checkTokenStatus" class="text-xs text-slate-400 hover:text-white transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
            </svg>
        </button>
    </div>

    @if(session()->has('message'))
        <div class="mb-3 p-2 bg-emerald-500/10 border border-emerald-500/20 rounded text-xs text-emerald-400">
            {{ session('message') }}
        </div>
    @endif

    @if(session()->has('error'))
        <div class="mb-3 p-2 bg-rose-500/10 border border-rose-500/20 rounded text-xs text-rose-400">
            {{ session('error') }}
        </div>
    @endif

    <div class="space-y-2">
        <!-- Market Data API Status -->
        <div class="flex items-center justify-between">
            <span class="text-xs text-slate-400">Market Data API</span>
            <div class="flex items-center space-x-2">
                @if($hasToken && $accessToken)
                    <button
                        onclick="copyToClipboard('{{ $accessToken }}', 'Market Data API token copied!')"
                        class="text-slate-400 hover:text-emerald-400 transition-colors"
                        title="Copy token">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                        </svg>
                    </button>
                @endif
                @if($hasToken)
                    <div class="flex items-center space-x-1">
                        <div class="w-2 h-2 bg-emerald-400 rounded-full"></div>
                        <span class="text-xs text-emerald-400">Connected</span>
                    </div>
                @else
                    <div class="flex items-center space-x-1">
                        <div class="w-2 h-2 bg-rose-400 rounded-full"></div>
                        <span class="text-xs text-rose-400">Disconnected</span>
                    </div>
                @endif
            </div>
        </div>

        <!-- Trader API Status -->
        <div class="flex items-center justify-between">
            <span class="text-xs text-slate-400">Trader API (Accounts & Trading)</span>
            <div class="flex items-center space-x-2">
                @if($traderApiConnected && $traderAccessToken)
                    <button
                        onclick="copyToClipboard('{{ $traderAccessToken }}', 'Trader API token copied!')"
                        class="text-slate-400 hover:text-emerald-400 transition-colors"
                        title="Copy token">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                        </svg>
                    </button>
                @endif
                @if($traderApiConnected)
                    <div class="flex items-center space-x-1">
                        <div class="w-2 h-2 bg-emerald-400 rounded-full"></div>
                        <span class="text-xs text-emerald-400">Connected</span>
                    </div>
                @else
                    <div class="flex items-center space-x-1">
                        <div class="w-2 h-2 bg-rose-400 rounded-full"></div>
                        <span class="text-xs text-rose-400">Not Connected</span>
                    </div>
                @endif
            </div>
        </div>

        @if($traderApiError)
            <div class="p-2 bg-rose-500/10 border border-rose-500/20 rounded text-xs text-rose-400">
                {{ $traderApiError }}
            </div>
        @endif

        @if($traderApiConnected)
            <!-- Streaming Info -->
            <div class="mt-3 pt-3 border-t border-slate-700/50 space-y-2">
                <div class="text-xs font-medium text-slate-300 mb-2">Streaming Credentials</div>

                @if($streamerSocketUrl)
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-500">Socket URL</span>
                        <span class="text-xs text-emerald-400">✓ Available</span>
                    </div>
                @endif

                @if($schwabClientCustomerId)
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-500">Customer ID</span>
                        <span class="text-xs text-emerald-400">✓ Available</span>
                    </div>
                @endif

                @if($schwabClientCorrelId)
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-500">Correlation ID</span>
                        <span class="text-xs text-emerald-400">✓ Available</span>
                    </div>
                @endif

                @if($schwabClientChannel)
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-500">Channel</span>
                        <span class="text-xs text-emerald-400">✓ Available</span>
                    </div>
                @endif

                @if($schwabClientFunctionId)
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-500">Function ID</span>
                        <span class="text-xs text-emerald-400">✓ Available</span>
                    </div>
                @endif
            </div>
        @endif
    </div>

    <script>
        function copyToClipboard(text, message) {
            navigator.clipboard.writeText(text).then(() => {
                // Show toast notification
                const toast = document.createElement('div');
                toast.className = 'fixed bottom-4 right-4 bg-emerald-500 text-white px-4 py-2 rounded-lg shadow-lg text-sm z-50';
                toast.textContent = message;
                document.body.appendChild(toast);

                setTimeout(() => {
                    toast.remove();
                }, 2000);
            });
        }

        let tunnelProcess = null;
        let eventSource = null;

        function toggleTunnelConsole() {
            const console = document.getElementById('tunnelConsole');
            console.classList.toggle('hidden');
        }

        function appendToConsole(text, type = 'info') {
            const output = document.getElementById('consoleOutput');
            const line = document.createElement('div');

            if (type === 'error') {
                line.className = 'text-rose-400';
            } else if (type === 'success') {
                line.className = 'text-emerald-400';
            } else if (type === 'warning') {
                line.className = 'text-yellow-400';
            } else {
                line.className = 'text-slate-300';
            }

            line.textContent = text;
            output.appendChild(line);
            output.scrollTop = output.scrollHeight;
        }

        function clearConsole() {
            const output = document.getElementById('consoleOutput');
            output.innerHTML = '';
        }

        async function startTunnel() {
            const startBtn = document.getElementById('startBtn');
            const stopBtn = document.getElementById('stopBtn');

            startBtn.disabled = true;
            stopBtn.disabled = false;

            clearConsole();
            appendToConsole('[' + new Date().toLocaleTimeString() + '] Starting tunnel...', 'info');

            try {
                const response = await fetch('/api/tunnel/start', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });

                const data = await response.json();

                if (data.success) {
                    appendToConsole('[' + new Date().toLocaleTimeString() + '] ' + data.message, 'success');

                    // Start polling for output
                    pollTunnelOutput();
                } else {
                    appendToConsole('[' + new Date().toLocaleTimeString() + '] Error: ' + data.message, 'error');
                    startBtn.disabled = false;
                    stopBtn.disabled = true;
                }
            } catch (error) {
                appendToConsole('[' + new Date().toLocaleTimeString() + '] Error: ' + error.message, 'error');
                startBtn.disabled = false;
                stopBtn.disabled = true;
            }
        }

        async function stopTunnel() {
            const startBtn = document.getElementById('startBtn');
            const stopBtn = document.getElementById('stopBtn');

            appendToConsole('[' + new Date().toLocaleTimeString() + '] Stopping tunnel...', 'warning');

            try {
                const response = await fetch('/api/tunnel/stop', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });

                const data = await response.json();
                appendToConsole('[' + new Date().toLocaleTimeString() + '] ' + data.message, 'info');

                startBtn.disabled = false;
                stopBtn.disabled = true;
            } catch (error) {
                appendToConsole('[' + new Date().toLocaleTimeString() + '] Error: ' + error.message, 'error');
            }
        }

        async function pollTunnelOutput() {
            try {
                const response = await fetch('/api/tunnel/output');
                const data = await response.json();

                if (data.output) {
                    data.output.forEach(line => {
                        appendToConsole(line, 'info');
                    });
                }

                if (data.running) {
                    setTimeout(pollTunnelOutput, 1000);
                } else {
                    document.getElementById('startBtn').disabled = false;
                    document.getElementById('stopBtn').disabled = true;
                }
            } catch (error) {
                console.error('Polling error:', error);
            }
        }
    </script>

    <!-- Actions -->
    <div class="mt-4 pt-3 border-t border-slate-700/50 space-y-2">
        <a href="{{ $authUrl }}" class="block w-full px-3 py-2 text-xs font-medium text-center text-white bg-blue-500 hover:bg-blue-600 rounded-lg transition-colors">
            🔐 Authenticate Market Data API
        </a>

        <a href="{{ $traderAuthUrl }}" class="block w-full px-3 py-2 text-xs font-medium text-center text-white bg-emerald-500 hover:bg-emerald-600 rounded-lg transition-colors">
            🔐 Authenticate Trader API
        </a>

        <p class="text-xs text-slate-400 text-center">
            ⚠️ Two separate apps required - authenticate both
        </p>

        <!-- Tunnel Console Button -->
        <button
            onclick="toggleTunnelConsole()"
            class="w-full px-3 py-2 text-xs font-medium text-white bg-purple-500 hover:bg-purple-600 rounded-lg transition-colors flex items-center justify-center space-x-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
            <span>Start Tunnel Console</span>
        </button>
    </div>

    <!-- Tunnel Console -->
    <div id="tunnelConsole" class="hidden mt-4 bg-slate-900 rounded-lg border border-slate-700 overflow-hidden">
        <div class="flex items-center justify-between bg-slate-800 px-3 py-2 border-b border-slate-700">
            <div class="flex items-center space-x-2">
                <div class="w-2 h-2 bg-emerald-400 rounded-full animate-pulse"></div>
                <span class="text-xs font-medium text-white">Tunnel Console</span>
            </div>
            <button onclick="toggleTunnelConsole()" class="text-slate-400 hover:text-white transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <div id="consoleOutput" class="p-3 h-64 overflow-y-auto font-mono text-xs text-emerald-400 whitespace-pre-wrap">
            <div class="text-slate-500">Waiting to start tunnel...</div>
        </div>
        <div class="bg-slate-800 px-3 py-2 border-t border-slate-700 flex space-x-2">
            <button
                onclick="startTunnel()"
                id="startBtn"
                class="px-3 py-1.5 text-xs font-medium text-white bg-emerald-500 hover:bg-emerald-600 rounded transition-colors">
                Start
            </button>
            <button
                onclick="stopTunnel()"
                id="stopBtn"
                class="px-3 py-1.5 text-xs font-medium text-white bg-rose-500 hover:bg-rose-600 rounded transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                disabled>
                Stop
            </button>
        </div>
    </div>
</div>

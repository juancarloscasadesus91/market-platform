@extends('layouts.app')

@section('title', $ticker . ' - Symbol Detail')

@section('content')
<style>
    /* Force chart container to be responsive */
    #tradingChart, #tradingChart * {
        max-width: 100% !important;
    }
    #tradingChart canvas {
        max-width: 100% !important;
        height: auto !important;
    }
</style>
<div class="p-6 space-y-6 max-w-full overflow-x-hidden" x-data="{ showChart: false }">
    <!-- Symbol Data & Options Sentiment Side by Side -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Real-Time Symbol Data -->
        @livewire('symbol-detail', ['ticker' => $ticker], key('symbol-detail-'.$ticker))

        <!-- Options & Greeks (Real-Time) - Lazy Load -->
        <livewire:options-sentiment :ticker="$ticker" lazy :key="'options-sentiment-'.$ticker" />
    </div>

    <!-- Premium Flow & Unusual Options Activity Side by Side -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Premium Flow - Lazy Load -->
        <livewire:premium-flow :ticker="$ticker" lazy :key="'premium-flow-'.$ticker" />

        <!-- Unusual Options Activity - Lazy Load -->
        <livewire:unusual-options-activity :ticker="$ticker" lazy :key="'unusual-options-'.$ticker" />
    </div>

    <!-- Live Option Contract Monitor -->
    @livewire('live-option-monitor', ['ticker' => $ticker], key('live-option-monitor-'.$ticker))

    <!-- Option Chain (Real-Time) - Lazy Load -->
    <x-card>
        <h2 class="text-lg font-semibold text-white mb-6">
            Option Chain
            <span class="text-xs text-slate-500 font-normal ml-2">Updates every 5s</span>
        </h2>
        <livewire:option-chain-live :ticker="$ticker" lazy />
    </x-card>

    <!-- Support & Resistance -->
    <x-card>
        <h2 class="text-lg font-semibold text-white mb-4">Support & Resistance Levels</h2>
        @php
            $currentPrice = 100; // Will be updated by Livewire
            $support = [
                round($currentPrice * 0.98, 2),
                round($currentPrice * 0.95, 2),
                round($currentPrice * 0.92, 2),
            ];
            $resistance = [
                round($currentPrice * 1.02, 2),
                round($currentPrice * 1.05, 2),
                round($currentPrice * 1.08, 2),
            ];
        @endphp
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <h3 class="text-sm font-semibold text-emerald-400 mb-3">Support Levels</h3>
                <div class="space-y-2">
                    @foreach($support as $index => $level)
                        <div class="flex items-center justify-between p-3 rounded-lg bg-emerald-500/5 border border-emerald-500/20">
                            <span class="text-xs text-slate-400">S{{ $index + 1 }}</span>
                            <span class="text-sm font-semibold text-white">${{ number_format($level, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            
            <div>
                <h3 class="text-sm font-semibold text-rose-400 mb-3">Resistance Levels</h3>
                <div class="space-y-2">
                    @foreach($resistance as $index => $level)
                        <div class="flex items-center justify-between p-3 rounded-lg bg-rose-500/5 border border-rose-500/20">
                            <span class="text-xs text-slate-400">R{{ $index + 1 }}</span>
                            <span class="text-sm font-semibold text-white">${{ number_format($level, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </x-card>

    <!-- Advanced Trading Chart (Moved to bottom with toggle) -->
    <x-card>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-white">Advanced Chart</h2>
            
            <!-- Chart Controls -->
            <div class="flex items-center space-x-2">
                <!-- Show/Hide Toggle -->
                <button 
                    @click="showChart = !showChart; if(showChart && !window.chartInstance) { setTimeout(() => { if (typeof window.TradingChart === 'function') { const data = window.generateChartData(100); window.chartInstance = new window.TradingChart('tradingChart', data); window.chartInstance.addSMA(20); }}, 100); }" 
                    class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors flex items-center space-x-1"
                    :class="showChart ? 'bg-blue-500 text-white' : 'bg-slate-800/50 text-slate-400 hover:text-white'"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path x-show="!showChart" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path x-show="!showChart" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        <path x-show="showChart" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
                    </svg>
                    <span x-text="showChart ? 'Hide Chart' : 'Show Chart'"></span>
                </button>
                
                <template x-if="showChart">
                    <div class="flex items-center space-x-2">
                <!-- Timeframe Selector -->
                <div class="flex items-center space-x-1 bg-slate-800/50 rounded-lg p-1">
                    <button onclick="window.chartInstance?.setTimeframe(7)" class="px-2 py-1 text-xs font-medium text-slate-400 hover:text-white hover:bg-slate-700/50 rounded transition-colors">1W</button>
                    <button onclick="window.chartInstance?.setTimeframe(30)" class="px-2 py-1 text-xs font-medium text-white bg-slate-700/50 rounded transition-colors">1M</button>
                    <button onclick="window.chartInstance?.setTimeframe(90)" class="px-2 py-1 text-xs font-medium text-slate-400 hover:text-white hover:bg-slate-700/50 rounded transition-colors">3M</button>
                    <button onclick="window.chartInstance?.setTimeframe(180)" class="px-2 py-1 text-xs font-medium text-slate-400 hover:text-white hover:bg-slate-700/50 rounded transition-colors">6M</button>
                    <button onclick="window.chartInstance?.setTimeframe(365)" class="px-2 py-1 text-xs font-medium text-slate-400 hover:text-white hover:bg-slate-700/50 rounded transition-colors">1Y</button>
                </div>

                <!-- Indicators Dropdown -->
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" class="px-2 py-1.5 text-xs font-medium text-slate-300 bg-slate-800/50 hover:bg-slate-700/50 rounded-lg transition-colors flex items-center space-x-1">
                        <span>Indicators</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    
                    <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-48 bg-slate-800 rounded-lg shadow-lg border border-slate-700 py-1 z-10">
                        <button onclick="window.chartInstance?.addSMA(20)" class="w-full px-4 py-2 text-left text-sm text-slate-300 hover:bg-slate-700 hover:text-white transition-colors">
                            SMA (20)
                        </button>
                        <button onclick="window.chartInstance?.addEMA(20)" class="w-full px-4 py-2 text-left text-sm text-slate-300 hover:bg-slate-700 hover:text-white transition-colors">
                            EMA (20)
                        </button>
                        <button onclick="window.chartInstance?.addBollingerBands(20, 2)" class="w-full px-4 py-2 text-left text-sm text-slate-300 hover:bg-slate-700 hover:text-white transition-colors">
                            Bollinger Bands
                        </button>
                        <div class="border-t border-slate-700 my-1"></div>
                        <button onclick="window.chartInstance?.removeSMA()" class="w-full px-4 py-2 text-left text-sm text-rose-400 hover:bg-slate-700 hover:text-rose-300 transition-colors">
                            Remove SMA
                        </button>
                        <button onclick="window.chartInstance?.removeEMA()" class="w-full px-4 py-2 text-left text-sm text-rose-400 hover:bg-slate-700 hover:text-rose-300 transition-colors">
                            Remove EMA
                        </button>
                        <button onclick="window.chartInstance?.removeBB()" class="w-full px-4 py-2 text-left text-sm text-rose-400 hover:bg-slate-700 hover:text-rose-300 transition-colors">
                            Remove BB
                        </button>
                    </div>
                </div>
                    </div>
                </template>
            </div>
        </div>
        
        <div x-show="showChart" x-transition id="tradingChart" class="w-full h-96 overflow-hidden"></div>
        <div x-show="!showChart" class="text-center py-12 text-slate-500 text-sm">
            Click "Show Chart" to load the trading chart
        </div>
    </x-card>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Listen for Livewire updates to get current price
    let currentPrice = 100;
    
    Livewire.on('quote-updated', (price) => {
        currentPrice = price;
    });
    
    // Function to generate chart data
    window.generateChartData = function(basePrice) {
        const data = [];
        const now = new Date();
        let price = basePrice || currentPrice;
        
        for (let i = 364; i >= 0; i--) {
            const date = new Date(now);
            date.setDate(date.getDate() - i);
            
            // Generate realistic OHLC data with trending
            const volatility = price * 0.02; // 2% daily volatility
            const trend = (Math.random() - 0.48) * volatility * 0.3; // Slight upward bias
            
            const open = price;
            const close = open + trend + (Math.random() - 0.5) * volatility;
            const high = Math.max(open, close) + Math.random() * volatility * 0.5;
            const low = Math.min(open, close) - Math.random() * volatility * 0.5;
            
            // Random volume
            const volume = Math.floor(Math.random() * 5000000) + 1000000;
            
            data.push({
                time: Math.floor(date.getTime() / 1000),
                open: parseFloat(open.toFixed(2)),
                high: parseFloat(high.toFixed(2)),
                low: parseFloat(low.toFixed(2)),
                close: parseFloat(close.toFixed(2)),
                volume: volume
            });
            
            price = close; // Update price for next candle
        }
        
        return data;
    };
});
</script>
@endpush

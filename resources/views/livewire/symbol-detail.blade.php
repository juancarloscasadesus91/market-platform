<div wire:poll.3s class="space-y-6">
    @if($error)
        <div class="bg-rose-500/10 border border-rose-500/50 rounded-lg p-4">
            <p class="text-rose-400">{{ $error }}</p>
        </div>
    @endif

    @if($quoteData)
        <!-- Symbol Header with Real-Time Data -->
        <x-card>
            <div class="flex items-start justify-between mb-6">
                <div>
                    <div class="flex items-center space-x-3">
                        <h1 class="text-3xl font-bold text-white">{{ $ticker }}</h1>
                        <div class="w-2 h-2 bg-emerald-400 rounded-full animate-pulse"></div>
                    </div>
                    <p class="text-slate-400 mt-1">{{ $quoteData['quote']['description'] ?? $symbol?->name ?? 'S&P 500 Index' }}</p>
                    <div class="flex items-center space-x-2 mt-2 text-xs text-slate-600">
                        <span>Updates every 3s</span>
                    </div>
                </div>

                <div class="text-right">
                    <div class="text-4xl font-bold text-white mb-2">
                        ${{ number_format($quoteData['quote']['lastPrice'] ?? 0, 2) }}
                    </div>
                    @php
                        $change = $quoteData['quote']['netChange'] ?? 0;
                        $changePercent = $quoteData['quote']['netPercentChange'] ?? 0;
                    @endphp
                    <div class="flex items-center justify-end space-x-2">
                        <span class="text-lg font-semibold {{ $change >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                            {{ $change >= 0 ? '+' : '' }}{{ number_format($change, 2) }}
                        </span>
                        <span class="px-3 py-1 text-sm font-medium rounded-lg {{ $changePercent >= 0 ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400' }}">
                            {{ $changePercent >= 0 ? '+' : '' }}{{ number_format($changePercent, 2) }}%
                        </span>
                    </div>
                </div>
            </div>

            <!-- Real-Time Quote Summary -->
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 pt-6 border-t border-slate-700/50">
                <div>
                    <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">Open</p>
                    <p class="text-sm font-semibold text-white">${{ number_format($quoteData['quote']['openPrice'] ?? 0, 2) }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">High</p>
                    <p class="text-sm font-semibold text-emerald-400">${{ number_format($quoteData['quote']['highPrice'] ?? 0, 2) }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">Low</p>
                    <p class="text-sm font-semibold text-rose-400">${{ number_format($quoteData['quote']['lowPrice'] ?? 0, 2) }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">Volume</p>
                    <p class="text-sm font-semibold text-white">{{ number_format($quoteData['quote']['totalVolume'] ?? 0) }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">Bid/Ask</p>
                    <p class="text-sm font-semibold text-white">
                        {{ number_format($quoteData['quote']['bidPrice'] ?? 0, 2) }} /
                        {{ number_format($quoteData['quote']['askPrice'] ?? 0, 2) }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">52W Range</p>
                    <p class="text-sm font-semibold text-white">
                        {{ number_format($quoteData['quote']['52WkLow'] ?? 0, 2) }} -
                        {{ number_format($quoteData['quote']['52WkHigh'] ?? 0, 2) }}
                    </p>
                </div>
            </div>
        </x-card>

        <!-- Fundamentals (if available) -->
        @if(isset($quoteData['fundamental']))
            <x-card>
                <h2 class="text-lg font-semibold text-white mb-4">Fundamentals</h2>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    @if(isset($quoteData['fundamental']['marketCap']))
                        <div>
                            <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">Market Cap</p>
                            <p class="text-sm font-semibold text-white">
                                ${{ number_format($quoteData['fundamental']['marketCap'] / 1000000000, 2) }}B
                            </p>
                        </div>
                    @endif
                    @if(isset($quoteData['fundamental']['peRatio']))
                        <div>
                            <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">P/E Ratio</p>
                            <p class="text-sm font-semibold text-white">{{ number_format($quoteData['fundamental']['peRatio'], 2) }}</p>
                        </div>
                    @endif
                    @if(isset($quoteData['fundamental']['divYield']))
                        <div>
                            <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">Div Yield</p>
                            <p class="text-sm font-semibold text-white">{{ number_format($quoteData['fundamental']['divYield'], 2) }}%</p>
                        </div>
                    @endif
                    @if(isset($quoteData['fundamental']['beta']))
                        <div>
                            <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">Beta</p>
                            <p class="text-sm font-semibold text-white">{{ number_format($quoteData['fundamental']['beta'], 2) }}</p>
                        </div>
                    @endif
                </div>
            </x-card>
        @endif
    @else
        <!-- Loading State -->
        <x-card>
            <div class="flex items-center justify-center py-12">
                <div class="text-center">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto mb-4"></div>
                    <p class="text-slate-400">Loading real-time data...</p>
                    @if($error)
                        <p class="text-rose-400 text-sm mt-4">{{ $error }}</p>
                    @endif
                </div>
            </div>
        </x-card>
    @endif

    <!-- Debug Info -->
    @if(config('app.debug'))
        <div class="text-xs text-slate-600 mt-4">
            <p>Ticker: {{ $ticker }}</p>
            <p>Has Quote Data: {{ $quoteData ? 'Yes' : 'No' }}</p>
            <p>Error: {{ $error ?? 'None' }}</p>
        </div>
    @endif
</div>

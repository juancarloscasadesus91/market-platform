<div wire:poll.5s class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Options Sentiment -->
    <x-card>
        <div class="flex items-center justify-between mb-2">
            <h2 class="text-base font-semibold text-white">Options Sentiment</h2>
            @if($isLoading)
                <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-500"></div>
            @else
                <div class="w-2 h-2 bg-emerald-400 rounded-full animate-pulse"></div>
            @endif
        </div>
        
        @php
            $totalVolume = $this->optionData['callVolume'] + $this->optionData['putVolume'];
        @endphp
        
        <div class="space-y-1.5">
            <div>
                <div class="flex items-center justify-between mb-0.5">
                    <span class="text-xs text-slate-400">Call Volume</span>
                    <span class="text-sm font-semibold text-emerald-400">{{ number_format($this->optionData['callVolume']) }}</span>
                </div>
                <div class="h-1.5 bg-slate-800 rounded-full overflow-hidden">
                    <div class="h-full bg-emerald-500 transition-all duration-500" style="width: {{ $totalVolume > 0 ? ($this->optionData['callVolume'] / $totalVolume * 100) : 0 }}%"></div>
                </div>
            </div>
            
            <div>
                <div class="flex items-center justify-between mb-0.5">
                    <span class="text-xs text-slate-400">Put Volume</span>
                    <span class="text-sm font-semibold text-rose-400">{{ number_format($this->optionData['putVolume']) }}</span>
                </div>
                <div class="h-1.5 bg-slate-800 rounded-full overflow-hidden">
                    <div class="h-full bg-rose-500 transition-all duration-500" style="width: {{ $totalVolume > 0 ? ($this->optionData['putVolume'] / $totalVolume * 100) : 0 }}%"></div>
                </div>
            </div>
            
            <div class="pt-1.5 border-t border-slate-800/50">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-400">Call/Put Ratio</span>
                    <span class="text-base font-bold text-white">{{ number_format($this->optionData['callPutRatio'], 2) }}</span>
                </div>
            </div>
        </div>
    </x-card>

    <!-- Volatility Summary -->
    <x-card>
        <div class="flex items-center justify-between mb-2">
            <h2 class="text-base font-semibold text-white">Volatility Summary</h2>
            @if($isLoading)
                <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-500"></div>
            @else
                <div class="w-2 h-2 bg-emerald-400 rounded-full animate-pulse"></div>
            @endif
        </div>
        
        <div class="space-y-1.5">
            <div>
                <p class="text-xs text-slate-500 uppercase tracking-wide">Average IV</p>
                <p class="text-2xl font-bold text-white">{{ number_format($this->optionData['avgIV'] * 100, 1) }}%</p>
            </div>
            
            <div class="grid grid-cols-2 gap-3 pt-1.5 border-t border-slate-800/50">
                <div>
                    <p class="text-xs text-slate-500 uppercase tracking-wide">IV Rank</p>
                    <p class="text-base font-semibold text-white">{{ $this->optionData['ivRank'] }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500 uppercase tracking-wide">IV Percentile</p>
                    <p class="text-base font-semibold text-white">{{ $this->optionData['ivPercentile'] }}</p>
                </div>
            </div>
        </div>
    </x-card>
</div>

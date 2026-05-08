<div wire:poll.2s>
    <div class="flex items-center space-x-2 mb-4">
        <button 
            wire:click="setType('gainers')"
            class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors {{ $type === 'gainers' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800/50' }}"
        >
            Top Gainers
        </button>
        <button 
            wire:click="setType('losers')"
            class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors {{ $type === 'losers' ? 'bg-rose-500/10 text-rose-400 border border-rose-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800/50' }}"
        >
            Top Losers
        </button>
        <button 
            wire:click="setType('active')"
            class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors {{ $type === 'active' ? 'bg-blue-500/10 text-blue-400 border border-blue-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800/50' }}"
        >
            Most Active
        </button>
    </div>

    <div class="space-y-2">
        @forelse($movers as $symbol)
            <a href="{{ route('symbol.show', $symbol->ticker) }}" 
               class="block p-3 rounded-lg bg-slate-800/30 hover:bg-slate-800/50 transition-colors border border-slate-800/50">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <div class="flex items-center space-x-2">
                            <span class="text-sm font-semibold text-white">{{ $symbol->ticker }}</span>
                            @if($type === 'active')
                                <span class="text-xs text-slate-500">Vol: {{ number_format($symbol->quote->volume ?? 0) }}</span>
                            @endif
                        </div>
                        <p class="text-xs text-slate-400 mt-0.5">${{ number_format($symbol->quote->last_price ?? 0, 2) }}</p>
                    </div>
                    
                    @if($symbol->quote)
                        <div class="text-right">
                            <div class="text-sm font-semibold {{ $symbol->quote->change >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                                {{ $symbol->quote->change >= 0 ? '+' : '' }}{{ number_format($symbol->quote->change, 2) }}
                            </div>
                            <div class="text-xs font-medium {{ $symbol->quote->change >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                                {{ $symbol->quote->change >= 0 ? '+' : '' }}{{ number_format($symbol->quote->change_percent, 2) }}%
                            </div>
                        </div>
                    @endif
                </div>
            </a>
        @empty
            <div class="text-center py-8">
                <p class="text-sm text-slate-400">No data available</p>
            </div>
        @endforelse
    </div>
</div>

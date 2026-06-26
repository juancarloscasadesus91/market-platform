<div class="relative" x-data="{ open: @entangle('showResults') }">
    <input 
        type="text" 
        wire:model.live.debounce.300ms="search"
        placeholder="Search symbols..."
        class="w-full px-4 py-2 bg-slate-800/50 border border-slate-700/50 rounded-lg text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500/50"
        @focus="open = true"
        @click.away="open = false"
    />

    @if($showResults && $results->isNotEmpty())
        <div class="absolute top-full left-0 right-0 mt-2 glass rounded-lg border border-slate-700/50 shadow-xl max-h-96 overflow-y-auto z-50">
            @foreach($results as $symbol)
                <button 
                    wire:click="selectSymbol('{{ $symbol->ticker }}')"
                    class="w-full px-4 py-3 flex items-center justify-between hover:bg-slate-800/50 transition-colors border-b border-slate-800/30 last:border-b-0"
                >
                    <div class="flex items-center space-x-3">
                        <div class="flex flex-col items-start">
                            <span class="text-sm font-semibold text-white">{{ $symbol->ticker }}</span>
                            <span class="text-xs text-slate-400">{{ $symbol->name }}</span>
                        </div>
                    </div>
                    
                    @if($symbol->quote)
                        <div class="flex items-center space-x-3">
                            <span class="text-sm font-medium text-white">${{ number_format($symbol->quote->last_price, 2) }}</span>
                            <span class="text-xs font-medium {{ $symbol->quote->change >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                                {{ $symbol->quote->change >= 0 ? '+' : '' }}{{ number_format($symbol->quote->change_percent, 2) }}%
                            </span>
                        </div>
                    @endif
                </button>
            @endforeach
        </div>
    @endif
</div>

<div class="p-6">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-semibold text-white">Watchlist</h2>
        <span class="text-xs font-medium text-slate-400">{{ $watchlist->count() }} symbols</span>
    </div>

    @if($watchlist->isEmpty())
        <div class="text-center py-12">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-slate-800/50 flex items-center justify-center">
                <svg class="w-8 h-8 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path>
                </svg>
            </div>
            <p class="text-sm text-slate-400">Your watchlist is empty</p>
            <p class="text-xs text-slate-500 mt-1">Add symbols to track them here</p>
        </div>
    @else
        <div class="space-y-2">
            @foreach($watchlist as $item)
                <div class="group relative p-3 rounded-lg bg-slate-800/30 hover:bg-slate-800/50 transition-colors border border-slate-800/50">
                    <a href="{{ route('symbol.show', $item->symbol->ticker) }}" class="block">
                        <div class="flex items-start justify-between mb-2">
                            <div>
                                <h3 class="text-sm font-semibold text-white">{{ $item->symbol->ticker }}</h3>
                                <p class="text-xs text-slate-400 truncate">{{ Str::limit($item->symbol->name, 20) }}</p>
                            </div>
                            
                            <button 
                                wire:click.prevent="removeFromWatchlist({{ $item->id }})"
                                class="opacity-0 group-hover:opacity-100 transition-opacity text-slate-400 hover:text-rose-400"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>

                        @if($item->symbol->quote)
                            <div class="flex items-center justify-between">
                                <span class="text-lg font-bold text-white">${{ number_format($item->symbol->quote->last_price, 2) }}</span>
                                <div class="flex items-center space-x-1">
                                    <span class="text-xs font-medium {{ $item->symbol->quote->change >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                                        {{ $item->symbol->quote->change >= 0 ? '+' : '' }}{{ number_format($item->symbol->quote->change, 2) }}
                                    </span>
                                    <span class="text-xs font-medium px-1.5 py-0.5 rounded {{ $item->symbol->quote->change >= 0 ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400' }}">
                                        {{ $item->symbol->quote->change >= 0 ? '+' : '' }}{{ number_format($item->symbol->quote->change_percent, 2) }}%
                                    </span>
                                </div>
                            </div>

                            <!-- Mini sparkline placeholder -->
                            <div class="mt-2 h-8 flex items-end space-x-0.5">
                                @for($i = 0; $i < 20; $i++)
                                    <div class="flex-1 {{ $item->symbol->quote->change >= 0 ? 'bg-emerald-500/20' : 'bg-rose-500/20' }} rounded-sm" 
                                         style="height: {{ rand(20, 100) }}%"></div>
                                @endfor
                            </div>
                        @endif
                    </a>
                </div>
            @endforeach
        </div>
    @endif
</div>

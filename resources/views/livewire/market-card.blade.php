<div wire:poll.1s>
    @if($symbol && $symbol->quote)
        <x-card hover>
            <div class="flex items-start justify-between mb-4">
                <div>
                    <h3 class="text-2xl font-bold text-white">{{ $symbol->ticker }}</h3>
                    <p class="text-sm text-slate-400 mt-1">{{ $symbol->name }}</p>
                </div>

                <button
                    wire:click="refresh"
                    class="p-2 rounded-lg hover:bg-slate-800/50 transition-colors text-slate-400 hover:text-white"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                </button>
            </div>

            <div class="space-y-4">
                <!-- Price -->
                <div>
                    <div class="flex items-baseline space-x-3">
                        <span class="text-4xl font-bold text-white">${{ number_format($symbol->quote->last_price, 2) }}</span>
                        <span class="text-lg font-semibold {{ $symbol->quote->change >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                            {{ $symbol->quote->change >= 0 ? '+' : '' }}{{ number_format($symbol->quote->change, 2) }}
                        </span>
                        <span class="px-2 py-1 text-sm font-medium rounded {{ $symbol->quote->change >= 0 ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400' }}">
                            {{ $symbol->quote->change >= 0 ? '+' : '' }}{{ number_format($symbol->quote->change_percent, 2) }}%
                        </span>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="grid grid-cols-2 gap-4 pt-4 border-t border-slate-800/50">
                    <div>
                        <p class="text-xs text-slate-500 uppercase tracking-wide">Open</p>
                        <p class="text-sm font-semibold text-white mt-1">${{ number_format($symbol->quote->open ?? 0, 2) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 uppercase tracking-wide">High</p>
                        <p class="text-sm font-semibold text-emerald-400 mt-1">${{ number_format($symbol->quote->high ?? 0, 2) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 uppercase tracking-wide">Low</p>
                        <p class="text-sm font-semibold text-rose-400 mt-1">${{ number_format($symbol->quote->low ?? 0, 2) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 uppercase tracking-wide">Volume</p>
                        <p class="text-sm font-semibold text-white mt-1">{{ number_format($symbol->quote->volume ?? 0) }}</p>
                    </div>
                </div>

                <!-- Action Button -->
                <a href="{{ route('symbol.show', $symbol->ticker) }}"
                   class="block w-full mt-4 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg text-center transition-colors">
                    View Details
                </a>
            </div>
        </x-card>
    @else
        <x-card>
            <div class="text-center py-8">
                <p class="text-sm text-slate-400">No data available</p>
            </div>
        </x-card>
    @endif
</div>

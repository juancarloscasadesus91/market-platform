<div wire:poll.1s class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
    @foreach($tickers as $ticker)
        @php
            $symbol = $symbols->get($ticker);
        @endphp
        
        @if($symbol && $symbol->quote)
            <x-card hover>
                <a href="{{ route('symbol.show', $ticker) }}" class="block">
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <h3 class="text-lg font-bold text-white">{{ $ticker }}</h3>
                            <p class="text-xs text-slate-400">{{ Str::limit($symbol->name, 25) }}</p>
                        </div>
                    </div>
                    
                    <div class="space-y-2">
                        <div class="flex items-baseline space-x-2">
                            <span class="text-2xl font-bold text-white">${{ number_format($symbol->quote->last_price, 2) }}</span>
                            <span class="text-sm font-medium {{ $symbol->quote->change >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                                {{ $symbol->quote->change >= 0 ? '+' : '' }}{{ number_format($symbol->quote->change_percent, 2) }}%
                            </span>
                        </div>
                        
                        <div class="flex items-center justify-between text-xs text-slate-400">
                            <span>Vol: {{ number_format($symbol->quote->volume ?? 0) }}</span>
                            <span>H: ${{ number_format($symbol->quote->high ?? 0, 2) }}</span>
                            <span>L: ${{ number_format($symbol->quote->low ?? 0, 2) }}</span>
                        </div>
                    </div>
                </a>
            </x-card>
        @endif
    @endforeach
</div>

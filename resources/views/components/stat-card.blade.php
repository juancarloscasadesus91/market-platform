@props(['label', 'value', 'change' => null, 'changePercent' => null])

<x-card hover>
    <div class="flex items-start justify-between">
        <div class="flex-1">
            <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">{{ $label }}</p>
            <p class="mt-2 text-2xl font-bold text-slate-100">{{ $value }}</p>
            
            @if($change !== null || $changePercent !== null)
                <div class="mt-2 flex items-center space-x-2">
                    @if($change !== null)
                        <span class="text-sm font-medium {{ $change >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                            {{ $change >= 0 ? '+' : '' }}{{ number_format($change, 2) }}
                        </span>
                    @endif
                    
                    @if($changePercent !== null)
                        <span class="text-xs font-medium px-2 py-0.5 rounded {{ $changePercent >= 0 ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400' }}">
                            {{ $changePercent >= 0 ? '+' : '' }}{{ number_format($changePercent, 2) }}%
                        </span>
                    @endif
                </div>
            @endif
        </div>
        
        @if(isset($icon))
            <div class="ml-4">
                {{ $icon }}
            </div>
        @endif
    </div>
</x-card>

@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="p-6 space-y-6 max-w-[1920px] mx-auto">
    <!-- Hero Section - SPX Card -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            @livewire('market-card', ['ticker' => 'SPX'])
        </div>
        
        <div class="space-y-4">
            @livewire('schwab-token-status')
            
            <x-stat-card 
                label="Advancing" 
                value="1,247"
                :change="null"
                :changePercent="null"
            />
        </div>
    </div>

    <!-- Market Overview Cards -->
    @livewire('market-overview-cards')

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Movers Section -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Market Movers -->
            <x-card>
                <h2 class="text-lg font-semibold text-white mb-4">Market Movers</h2>
                @livewire('movers-list')
            </x-card>

            <!-- Unusual Options Activity -->
            <x-card>
                <h2 class="text-lg font-semibold text-white mb-4">Unusual Options Activity</h2>
                <div class="space-y-2">
                    @php
                        $unusualActivity = \App\Models\OptionContract::with('symbol')
                            ->whereNotNull('volume')
                            ->where('volume', '>', 1000)
                            ->orderByDesc('volume')
                            ->limit(10)
                            ->get();
                    @endphp
                    
                    @forelse($unusualActivity as $contract)
                        <div class="p-3 rounded-lg bg-slate-800/30 hover:bg-slate-800/50 transition-colors border border-slate-800/50">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-2 mb-1">
                                        <span class="text-sm font-semibold text-white">{{ $contract->symbol->ticker }}</span>
                                        <span class="px-2 py-0.5 text-xs font-medium rounded {{ $contract->option_type->value === 'call' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400' }}">
                                            {{ $contract->option_type->label() }}
                                        </span>
                                        <span class="text-xs text-slate-400">${{ number_format($contract->strike, 0) }}</span>
                                        <span class="text-xs text-slate-500">{{ $contract->expiration_date->format('M d') }}</span>
                                    </div>
                                    <div class="flex items-center space-x-4 text-xs text-slate-400">
                                        <span>Vol: {{ number_format($contract->volume) }}</span>
                                        <span>OI: {{ number_format($contract->open_interest ?? 0) }}</span>
                                        <span>IV: {{ number_format(($contract->implied_volatility ?? 0) * 100, 1) }}%</span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-semibold text-white">${{ number_format($contract->last ?? 0, 2) }}</div>
                                    <div class="text-xs text-slate-400">Premium</div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8">
                            <p class="text-sm text-slate-400">No unusual activity detected</p>
                        </div>
                    @endforelse
                </div>
            </x-card>
        </div>

        <!-- Mini Heatmap Widget -->
        <div>
            <x-card>
                <h2 class="text-lg font-semibold text-white mb-4">Quick Heatmap</h2>
                <div class="space-y-2">
                    @foreach(['SPY', 'QQQ', 'NVDA'] as $ticker)
                        @php
                            $symbol = \App\Models\Symbol::where('ticker', $ticker)->first();
                            $contracts = $symbol ? $symbol->optionContracts()->limit(5)->get() : collect();
                        @endphp
                        
                        <div>
                            <h3 class="text-xs font-semibold text-slate-400 mb-2">{{ $ticker }}</h3>
                            <div class="grid grid-cols-5 gap-1">
                                @foreach($contracts as $contract)
                                    <div 
                                        class="h-12 rounded {{ $contract->volume_heat }} flex items-center justify-center"
                                        title="Strike: ${{ number_format($contract->strike, 0) }} | Vol: {{ number_format($contract->volume ?? 0) }}"
                                    >
                                        <span class="text-xs font-medium text-white">${{ number_format($contract->strike, 0) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
                <a href="{{ route('heatmap') }}" class="block w-full mt-4 px-4 py-2 bg-slate-800/50 hover:bg-slate-800 text-white text-sm font-medium rounded-lg text-center transition-colors">
                    View Full Heatmap
                </a>
            </x-card>
        </div>
    </div>
</div>
@endsection

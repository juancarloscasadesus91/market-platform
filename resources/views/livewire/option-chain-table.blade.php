<div>
    <!-- Controls -->
    <div class="mb-6 flex flex-wrap items-center gap-4">
        <!-- Expiration Selector -->
        <div class="flex items-center space-x-2">
            <label class="text-xs font-medium text-slate-400 uppercase">Expiration:</label>
            <select 
                wire:model.live="selectedExpiration"
                class="px-3 py-1.5 bg-slate-800/50 border border-slate-700/50 rounded-lg text-sm text-white focus:outline-none focus:ring-2 focus:ring-blue-500/50"
            >
                @foreach($expirations as $exp)
                    <option value="{{ $exp }}">{{ \Carbon\Carbon::parse($exp)->format('M d, Y') }}</option>
                @endforeach
            </select>
        </div>

        <!-- Strike Filter -->
        <div class="flex items-center space-x-2">
            <label class="text-xs font-medium text-slate-400 uppercase">Strike:</label>
            <div class="flex items-center space-x-1">
                <button 
                    wire:click="setStrikeFilter('all')"
                    class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors {{ $strikeFilter === 'all' ? 'bg-blue-500/10 text-blue-400 border border-blue-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800/50' }}"
                >
                    All
                </button>
                <button 
                    wire:click="setStrikeFilter('itm')"
                    class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors {{ $strikeFilter === 'itm' ? 'bg-blue-500/10 text-blue-400 border border-blue-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800/50' }}"
                >
                    ITM
                </button>
                <button 
                    wire:click="setStrikeFilter('atm')"
                    class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors {{ $strikeFilter === 'atm' ? 'bg-blue-500/10 text-blue-400 border border-blue-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800/50' }}"
                >
                    ATM
                </button>
                <button 
                    wire:click="setStrikeFilter('otm')"
                    class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors {{ $strikeFilter === 'otm' ? 'bg-blue-500/10 text-blue-400 border border-blue-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800/50' }}"
                >
                    OTM
                </button>
            </div>
        </div>
    </div>

    <!-- Option Chain Table -->
    <div class="glass rounded-xl border border-slate-800/50 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-800/50">
                        <!-- Calls Header -->
                        <th colspan="7" class="px-4 py-3 text-left bg-emerald-500/5">
                            <span class="text-xs font-semibold text-emerald-400 uppercase tracking-wide">Calls</span>
                        </th>
                        
                        <!-- Strike Header -->
                        <th class="px-4 py-3 bg-slate-800/50">
                            <span class="text-xs font-semibold text-white uppercase tracking-wide">Strike</span>
                        </th>
                        
                        <!-- Puts Header -->
                        <th colspan="7" class="px-4 py-3 text-right bg-rose-500/5">
                            <span class="text-xs font-semibold text-rose-400 uppercase tracking-wide">Puts</span>
                        </th>
                    </tr>
                    <tr class="border-b border-slate-800/50 text-xs text-slate-400 uppercase tracking-wide">
                        <!-- Calls Columns -->
                        <th class="px-3 py-2 text-left font-medium">Vol</th>
                        <th class="px-3 py-2 text-left font-medium">OI</th>
                        <th class="px-3 py-2 text-left font-medium">IV</th>
                        <th class="px-3 py-2 text-left font-medium">Delta</th>
                        <th class="px-3 py-2 text-left font-medium">Bid</th>
                        <th class="px-3 py-2 text-left font-medium">Ask</th>
                        <th class="px-3 py-2 text-left font-medium">Last</th>
                        
                        <!-- Strike -->
                        <th class="px-4 py-2 bg-slate-800/30 font-semibold text-white">Price</th>
                        
                        <!-- Puts Columns -->
                        <th class="px-3 py-2 text-right font-medium">Last</th>
                        <th class="px-3 py-2 text-right font-medium">Ask</th>
                        <th class="px-3 py-2 text-right font-medium">Bid</th>
                        <th class="px-3 py-2 text-right font-medium">Delta</th>
                        <th class="px-3 py-2 text-right font-medium">IV</th>
                        <th class="px-3 py-2 text-right font-medium">OI</th>
                        <th class="px-3 py-2 text-right font-medium">Vol</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($strikes as $strike)
                        @php
                            $call = $calls->firstWhere('strike', $strike);
                            $put = $puts->firstWhere('strike', $strike);
                            $isAtm = $symbol && $symbol->quote && abs($strike - $symbol->quote->last_price) < ($symbol->quote->last_price * 0.02);
                        @endphp
                        <tr class="border-b border-slate-800/30 hover:bg-slate-800/20 transition-colors {{ $isAtm ? 'bg-blue-500/5' : '' }}">
                            <!-- Call Data -->
                            @if($call)
                                <td class="px-3 py-2 text-white {{ $call->volume_heat }}">{{ number_format($call->volume ?? 0) }}</td>
                                <td class="px-3 py-2 text-slate-300">{{ number_format($call->open_interest ?? 0) }}</td>
                                <td class="px-3 py-2 text-slate-300">{{ number_format(($call->implied_volatility ?? 0) * 100, 1) }}%</td>
                                <td class="px-3 py-2 text-emerald-400">{{ number_format($call->delta ?? 0, 3) }}</td>
                                <td class="px-3 py-2 text-slate-300">${{ number_format($call->bid ?? 0, 2) }}</td>
                                <td class="px-3 py-2 text-slate-300">${{ number_format($call->ask ?? 0, 2) }}</td>
                                <td class="px-3 py-2 font-semibold text-white">${{ number_format($call->last ?? 0, 2) }}</td>
                            @else
                                <td colspan="7" class="px-3 py-2 text-center text-slate-600">-</td>
                            @endif
                            
                            <!-- Strike -->
                            <td class="px-4 py-2 bg-slate-800/30 text-center font-bold text-white {{ $isAtm ? 'text-blue-400' : '' }}">
                                ${{ number_format($strike, 2) }}
                            </td>
                            
                            <!-- Put Data -->
                            @if($put)
                                <td class="px-3 py-2 font-semibold text-white text-right">${{ number_format($put->last ?? 0, 2) }}</td>
                                <td class="px-3 py-2 text-slate-300 text-right">${{ number_format($put->ask ?? 0, 2) }}</td>
                                <td class="px-3 py-2 text-slate-300 text-right">${{ number_format($put->bid ?? 0, 2) }}</td>
                                <td class="px-3 py-2 text-rose-400 text-right">{{ number_format($put->delta ?? 0, 3) }}</td>
                                <td class="px-3 py-2 text-slate-300 text-right">{{ number_format(($put->implied_volatility ?? 0) * 100, 1) }}%</td>
                                <td class="px-3 py-2 text-slate-300 text-right">{{ number_format($put->open_interest ?? 0) }}</td>
                                <td class="px-3 py-2 text-white text-right {{ $put->volume_heat }}">{{ number_format($put->volume ?? 0) }}</td>
                            @else
                                <td colspan="7" class="px-3 py-2 text-center text-slate-600">-</td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="15" class="px-4 py-12 text-center text-slate-400">
                                No option data available for this expiration
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Legend -->
    <div class="mt-4 flex items-center space-x-6 text-xs text-slate-400">
        <div class="flex items-center space-x-2">
            <div class="w-3 h-3 rounded bg-blue-500/20"></div>
            <span>At The Money</span>
        </div>
        <div class="flex items-center space-x-2">
            <div class="w-3 h-3 rounded bg-emerald-500/20"></div>
            <span>High Volume</span>
        </div>
        <div class="flex items-center space-x-2">
            <span>Vol = Volume</span>
            <span>•</span>
            <span>OI = Open Interest</span>
            <span>•</span>
            <span>IV = Implied Volatility</span>
        </div>
    </div>
</div>

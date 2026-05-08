<div wire:poll.5s class="space-y-4">
    @if($optionChainData)
        <!-- Controls -->
        <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
            <!-- Strike Count Selector -->
            <div class="flex items-center space-x-2">
                <span class="text-sm text-slate-400">Strikes:</span>
                <div class="flex items-center space-x-1 bg-slate-800/50 rounded-lg p-1">
                    <button wire:click="setStrikeCount(10)" class="px-3 py-1 text-xs font-medium rounded transition-colors {{ $strikeCount === 10 ? 'bg-blue-500 text-white' : 'text-slate-400 hover:text-white' }}">10</button>
                    <button wire:click="setStrikeCount(20)" class="px-3 py-1 text-xs font-medium rounded transition-colors {{ $strikeCount === 20 ? 'bg-blue-500 text-white' : 'text-slate-400 hover:text-white' }}">20</button>
                    <button wire:click="setStrikeCount(30)" class="px-3 py-1 text-xs font-medium rounded transition-colors {{ $strikeCount === 30 ? 'bg-blue-500 text-white' : 'text-slate-400 hover:text-white' }}">30</button>
                    <button wire:click="setStrikeCount(50)" class="px-3 py-1 text-xs font-medium rounded transition-colors {{ $strikeCount === 50 ? 'bg-blue-500 text-white' : 'text-slate-400 hover:text-white' }}">50</button>
                    <button wire:click="setStrikeCount(999)" class="px-3 py-1 text-xs font-medium rounded transition-colors {{ $strikeCount === 999 ? 'bg-blue-500 text-white' : 'text-slate-400 hover:text-white' }}">All</button>
                </div>
            </div>
            
            <!-- Column Filter -->
            <div class="relative" x-data="{ open: @entangle('showColumnFilter') }">
                <button @click="open = !open" class="px-3 py-1.5 text-xs font-medium text-slate-300 bg-slate-800/50 hover:bg-slate-700/50 rounded-lg transition-colors flex items-center space-x-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path>
                    </svg>
                    <span>Columns</span>
                </button>
                
                <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-56 bg-slate-800 rounded-lg shadow-xl border border-slate-700 py-2 z-50 max-h-96 overflow-y-auto">
                    @foreach($availableColumns as $key => $label)
                        <label class="flex items-center px-4 py-2 hover:bg-slate-700 cursor-pointer transition-colors">
                            <input 
                                type="checkbox" 
                                wire:click="toggleColumn('{{ $key }}')"
                                {{ in_array($key, $visibleColumns) ? 'checked' : '' }}
                                class="w-4 h-4 text-blue-500 bg-slate-900 border-slate-600 rounded focus:ring-blue-500 focus:ring-2"
                            >
                            <span class="ml-3 text-sm text-slate-300">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>
        
        <!-- Expiration Selector -->
        <div class="flex items-center space-x-2 overflow-x-auto pb-2">
            @foreach(array_keys($optionChainData['callExpDateMap'] ?? []) as $expiration)
                @php
                    try {
                        $expirationDate = \Carbon\Carbon::parse(str_replace(':', '-', explode(':', $expiration)[0]));
                        $displayDate = $expirationDate->format('M d, Y');
                    } catch (\Exception $e) {
                        $displayDate = $expiration;
                    }
                @endphp
                <button 
                    wire:click="selectExpiration('{{ $expiration }}')"
                    class="px-4 py-2 text-sm font-medium rounded-lg transition-colors whitespace-nowrap {{ $selectedExpiration === $expiration ? 'bg-blue-500 text-white' : 'bg-slate-800/50 text-slate-400 hover:bg-slate-700/50' }}">
                    {{ $displayDate }}
                </button>
            @endforeach
        </div>

        <!-- Option Chain Table -->
        @if($selectedExpiration && isset($optionChainData['callExpDateMap'][$selectedExpiration]))
            @php
                $calls = $optionChainData['callExpDateMap'][$selectedExpiration] ?? [];
                $puts = $optionChainData['putExpDateMap'][$selectedExpiration] ?? [];
                $underlyingPrice = $optionChainData['underlyingPrice'] ?? 0;
            @endphp

            <div class="overflow-x-auto -mx-6 px-6">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="border-b border-slate-700">
                            <!-- Calls Header -->
                            @foreach($visibleColumns as $col)
                                @if($col !== 'strike')
                                    <th class="px-2 py-2 text-left text-[10px] font-medium text-emerald-400 uppercase">{{ $availableColumns[$col] }}</th>
                                @endif
                            @endforeach
                            
                            <!-- Strike -->
                            @if(in_array('strike', $visibleColumns))
                                <th class="px-3 py-2 text-center text-xs font-bold text-white uppercase bg-slate-800/50">Strike</th>
                            @endif
                            
                            <!-- Puts Header (reverse order) -->
                            @foreach(array_reverse($visibleColumns) as $col)
                                @if($col !== 'strike')
                                    <th class="px-2 py-2 text-left text-[10px] font-medium text-rose-400 uppercase">{{ $availableColumns[$col] }}</th>
                                @endif
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($calls as $strike => $callStrikes)
                            @php
                                $call = reset($callStrikes);
                                $put = isset($puts[$strike]) ? reset($puts[$strike]) : null;
                                $isITM = $underlyingPrice > $strike;
                                
                                $columnMap = [
                                    'bid' => 'bid',
                                    'ask' => 'ask',
                                    'last' => 'last',
                                    'mark' => 'mark',
                                    'volume' => 'totalVolume',
                                    'openInterest' => 'openInterest',
                                    'impliedVolatility' => 'volatility',
                                    'delta' => 'delta',
                                    'gamma' => 'gamma',
                                    'theta' => 'theta',
                                    'vega' => 'vega',
                                    'rho' => 'rho',
                                    'intrinsicValue' => 'intrinsicValue',
                                    'extrinsicValue' => 'extrinsicValue',
                                ];
                            @endphp
                            <tr class="border-b border-slate-800/30 hover:bg-slate-800/20 transition-colors {{ abs($underlyingPrice - $strike) < 5 ? 'bg-blue-500/5' : '' }}">
                                <!-- Call Data -->
                                @foreach($visibleColumns as $col)
                                    @if($col !== 'strike')
                                        @php
                                            $apiKey = $columnMap[$col] ?? $col;
                                            $value = $call[$apiKey] ?? 0;
                                            if ($col === 'impliedVolatility') {
                                                $display = number_format($value * 100, 0) . '%';
                                            } elseif (in_array($col, ['volume', 'openInterest'])) {
                                                $display = number_format($value);
                                            } elseif (in_array($col, ['delta', 'gamma', 'theta', 'vega', 'rho'])) {
                                                $display = number_format($value, 4);
                                            } else {
                                                $display = number_format($value, 2);
                                            }
                                        @endphp
                                        <td class="px-2 py-1.5 {{ in_array($col, ['bid', 'ask', 'last', 'mark']) ? 'font-semibold text-white' : 'text-slate-400' }} text-[11px]">
                                            {{ $display }}
                                        </td>
                                    @endif
                                @endforeach
                                
                                <!-- Strike Price -->
                                @if(in_array('strike', $visibleColumns))
                                    <td class="px-3 py-1.5 text-center font-bold {{ $isITM ? 'text-emerald-400' : 'text-slate-400' }} bg-slate-800/30">
                                        ${{ number_format($strike, 0) }}
                                    </td>
                                @endif
                                
                                <!-- Put Data (reverse order) -->
                                @foreach(array_reverse($visibleColumns) as $col)
                                    @if($col !== 'strike')
                                        @php
                                            $apiKey = $columnMap[$col] ?? $col;
                                            $value = $put[$apiKey] ?? 0;
                                            if ($col === 'impliedVolatility') {
                                                $display = $put ? number_format($value * 100, 0) . '%' : '-';
                                            } elseif (in_array($col, ['volume', 'openInterest'])) {
                                                $display = $put ? number_format($value) : '-';
                                            } elseif (in_array($col, ['delta', 'gamma', 'theta', 'vega', 'rho'])) {
                                                $display = $put ? number_format($value, 4) : '-';
                                            } else {
                                                $display = $put ? number_format($value, 2) : '-';
                                            }
                                        @endphp
                                        <td class="px-2 py-1.5 {{ in_array($col, ['bid', 'ask', 'last', 'mark']) ? 'font-semibold text-white' : 'text-slate-400' }} text-[11px]">
                                            {{ $display }}
                                        </td>
                                    @endif
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Summary Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 pt-4 border-t border-slate-700/50">
                <div>
                    <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">Underlying Price</p>
                    <p class="text-lg font-bold text-white">${{ number_format($underlyingPrice, 2) }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">Expiration</p>
                    @php
                        try {
                            $expDate = \Carbon\Carbon::parse(str_replace(':', '-', explode(':', $selectedExpiration)[0]));
                            $daysToExpiry = $expDate->diffInDays(now());
                        } catch (\Exception $e) {
                            $daysToExpiry = 0;
                        }
                    @endphp
                    <p class="text-lg font-bold text-white">{{ $daysToExpiry }} days</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">Total Calls</p>
                    <p class="text-lg font-bold text-emerald-400">{{ count($calls) }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">Total Puts</p>
                    <p class="text-lg font-bold text-rose-400">{{ count($puts) }}</p>
                </div>
            </div>
        @endif
    @else
        <div class="flex items-center justify-center py-12">
            <div class="text-center">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto mb-3"></div>
                <p class="text-slate-400 text-sm">Loading option chain...</p>
            </div>
        </div>
    @endif
</div>

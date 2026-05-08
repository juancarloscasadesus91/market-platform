<div class="space-y-6">
    <!-- Header with Actions -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-white">Trading Journal</h2>
            <p class="text-sm text-slate-400 mt-1">Track your daily trading performance</p>
        </div>
        
        <div class="flex items-center space-x-3">
            <button 
                wire:click="addEntry"
                class="px-4 py-2 bg-amber-600/80 hover:bg-amber-600 text-white text-sm font-medium rounded-lg transition-colors flex items-center space-x-2 shadow-sm"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                <span>Add Entry</span>
            </button>
            
            <button 
                wire:click="exportToExcel"
                class="px-4 py-2 bg-slate-700/80 hover:bg-slate-700 text-slate-200 text-sm font-medium rounded-lg transition-colors flex items-center space-x-2 border border-slate-600/50"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span>Export Excel</span>
            </button>
            
            <button 
                wire:click="exportToPDF"
                class="px-4 py-2 bg-slate-700/80 hover:bg-slate-700 text-slate-200 text-sm font-medium rounded-lg transition-colors flex items-center space-x-2 border border-slate-600/50"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                <span>Export PDF</span>
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-slate-800/50 rounded-lg p-4 border border-slate-700/50">
        <div class="space-y-4">
            <!-- Filter Type Buttons -->
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-2">Filter Type</label>
                <div class="flex flex-wrap gap-2">
                    <button 
                        wire:click="$set('filterType', 'all')"
                        class="px-4 py-2 text-sm rounded-lg transition-all duration-200 {{ $filterType === 'all' ? 'bg-amber-600 text-white shadow-lg' : 'bg-slate-700/50 text-slate-300 hover:bg-slate-700' }}"
                    >
                        All Entries
                    </button>
                    <button 
                        wire:click="$set('filterType', 'date_range')"
                        class="px-4 py-2 text-sm rounded-lg transition-all duration-200 {{ $filterType === 'date_range' ? 'bg-amber-600 text-white shadow-lg' : 'bg-slate-700/50 text-slate-300 hover:bg-slate-700' }}"
                    >
                        📅 Date Range
                    </button>
                    <button 
                        wire:click="$set('filterType', 'week')"
                        class="px-4 py-2 text-sm rounded-lg transition-all duration-200 {{ $filterType === 'week' ? 'bg-amber-600 text-white shadow-lg' : 'bg-slate-700/50 text-slate-300 hover:bg-slate-700' }}"
                    >
                        📆 By Week
                    </button>
                </div>
            </div>
            
            <!-- Filter Inputs with smooth transition -->
            <div class="transition-all duration-300 ease-in-out">
                @if($filterType === 'date_range')
                    <div class="flex flex-wrap gap-3 animate-fadeIn">
                        <div class="w-auto">
                            <label class="block text-sm font-medium text-slate-300 mb-2">Start Date</label>
                            <input 
                                type="date" 
                                wire:model.live="startDate"
                                class="bg-slate-900/50 border border-slate-700/50 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 transition-all duration-200 cursor-pointer"
                                style="color-scheme: dark;"
                            >
                        </div>
                        
                        <div class="w-auto">
                            <label class="block text-sm font-medium text-slate-300 mb-2">End Date</label>
                            <input 
                                type="date" 
                                wire:model.live="endDate"
                                class="bg-slate-900/50 border border-slate-700/50 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 transition-all duration-200 cursor-pointer"
                                style="color-scheme: dark;"
                            >
                        </div>
                        
                        @if($startDate && $endDate)
                            <div class="flex items-end">
                                <button 
                                    wire:click="clearFilters"
                                    class="px-4 py-2 bg-rose-600/20 hover:bg-rose-600/30 text-rose-400 text-sm rounded-lg transition-all duration-200 border border-rose-500/30"
                                >
                                    Clear
                                </button>
                            </div>
                        @endif
                    </div>
                @endif
                
                @if($filterType === 'week')
                    <div class="space-y-3 animate-fadeIn">
                        <!-- Week Selector Dropdown -->
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">Select Weeks</label>
                            <select 
                                wire:change="addWeek($event.target.value); $event.target.value = ''"
                                class="w-auto max-w-xs bg-slate-900/50 border border-slate-700/50 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 transition-all duration-200 cursor-pointer"
                            >
                                <option value="">+ Add a week...</option>
                                @foreach($availableWeeks as $week)
                                    @if(!in_array($week['value'], $selectedWeeks))
                                        <option value="{{ $week['value'] }}">{{ $week['label'] }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        
                        <!-- Selected Weeks as Chips/Tags -->
                        @if(count($selectedWeeks) > 0)
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-medium text-slate-400">Selected Weeks ({{ count($selectedWeeks) }})</span>
                                    <button 
                                        wire:click="clearFilters"
                                        class="text-xs text-rose-400 hover:text-rose-300 transition-colors"
                                    >
                                        Clear All
                                    </button>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($selectedWeeks as $selectedWeek)
                                        @php
                                            $year = (int) substr($selectedWeek, 0, 4);
                                            $week = (int) substr($selectedWeek, 6);
                                            $weekStart = \Carbon\Carbon::now()->setISODate($year, $week)->startOfWeek();
                                            $weekEnd = \Carbon\Carbon::now()->setISODate($year, $week)->endOfWeek();
                                        @endphp
                                        <div class="group flex items-center space-x-2 bg-amber-600/20 border border-amber-500/30 rounded-lg px-3 py-1.5 transition-all duration-200 hover:bg-amber-600/30">
                                            <span class="text-sm text-amber-300">
                                                Week {{ $week }}, {{ $year }}
                                                <span class="text-xs text-amber-400/70">({{ $weekStart->format('M d') }} - {{ $weekEnd->format('M d') }})</span>
                                            </span>
                                            <button 
                                                wire:click="removeWeek('{{ $selectedWeek }}')"
                                                class="text-amber-400 hover:text-amber-200 transition-colors"
                                                title="Remove week"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
            
            <!-- Filter Status Message -->
            @if($filterType !== 'all' && (($filterType === 'date_range' && $startDate && $endDate) || ($filterType === 'week' && count($selectedWeeks) > 0)))
                <div class="flex items-center space-x-2 text-sm text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 rounded-lg px-3 py-2 animate-fadeIn">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                    <span>
                        @if($filterType === 'date_range' && $startDate && $endDate)
                            Filtered: {{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}
                        @elseif($filterType === 'week' && count($selectedWeeks) > 0)
                            Filtered by {{ count($selectedWeeks) }} week{{ count($selectedWeeks) > 1 ? 's' : '' }}
                        @endif
                    </span>
                </div>
            @endif
        </div>
    </div>
    
    <style>
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .animate-fadeIn {
            animation: fadeIn 0.3s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
    </style>

    <!-- Stats Summary -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        @php
            // REAL stats
            $totalProfitReal = $allEntries->sum('profit_diario_real');
            $avgProfitReal = $allEntries->count() > 0 ? $totalProfitReal / $allEntries->count() : 0;
            $winningDays = $allEntries->where('profit_diario_real', '>', 0)->count();
            $losingDays = $allEntries->where('profit_diario_real', '<', 0)->count();
            $winRateReal = $allEntries->count() > 0 ? ($winningDays / $allEntries->count()) * 100 : 0;
            
            // PLAN stats
            $totalProfitPlan = $allEntries->sum('profit_diario_plan');
            $avgProfitPlan = $allEntries->count() > 0 ? $totalProfitPlan / $allEntries->count() : 0;
            $winningDaysPlan = $allEntries->where('profit_diario_plan', '>', 0)->count();
            $winRatePlan = $allEntries->count() > 0 ? ($winningDaysPlan / $allEntries->count()) * 100 : 0;
            
            // Portfolio stats (global)
            $portfolio = \App\Models\PortfolioSetting::getPortfolio();
            $currentPortfolio = $portfolio->current_value;
            $initialPortfolio = $portfolio->initial_value;
            $portfolioDiff = $currentPortfolio - $initialPortfolio;
            $portfolioStatus = $portfolioDiff >= 0 ? 'up' : 'down';
            
            // Comparisons
            $totalProfitStatus = $totalProfitReal >= $totalProfitPlan ? 'up' : 'down';
            $avgProfitStatus = $avgProfitReal >= $avgProfitPlan ? 'up' : 'down';
            $winRateStatus = $winRateReal >= $winRatePlan ? 'up' : 'down';
        @endphp
        
        <!-- Portfolio Value Card -->
        <div class="bg-slate-800/50 rounded-lg p-4 border border-slate-700/50 relative overflow-hidden">
            <div class="flex items-center justify-between mb-1">
                <div class="flex items-center space-x-2">
                    <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                    </svg>
                    <span class="text-xs text-slate-400">Portfolio Value</span>
                </div>
                <div class="absolute top-2 right-2">
                    @if($portfolioStatus === 'up')
                        <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                        </svg>
                    @else
                        <svg class="w-5 h-5 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"/>
                        </svg>
                    @endif
                </div>
            </div>
            <div class="text-2xl font-bold {{ $portfolioStatus === 'up' ? 'text-emerald-400' : 'text-rose-400' }}">
                ${{ number_format($currentPortfolio, 2) }}
            </div>
            <div class="mt-2 flex items-center justify-between text-xs">
                <span class="text-slate-500">Initial: ${{ number_format($initialPortfolio, 2) }}</span>
                <span class="font-semibold {{ $portfolioStatus === 'up' ? 'text-emerald-400' : 'text-rose-400' }}">
                    {{ $portfolioDiff >= 0 ? '+' : '' }}${{ number_format($portfolioDiff, 2) }}
                </span>
            </div>
        </div>
        
        <!-- Total Profit Card -->
        <div class="bg-slate-800/50 rounded-lg p-4 border border-slate-700/50 relative overflow-hidden">
            <div class="flex items-center justify-between mb-1">
                <div class="flex items-center space-x-2">
                    <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="text-xs text-slate-400">Total Profit</span>
                </div>
                <div class="absolute top-2 right-2">
                    @if($totalProfitStatus === 'up')
                        <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                        </svg>
                    @else
                        <svg class="w-5 h-5 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"/>
                        </svg>
                    @endif
                </div>
            </div>
            <div class="text-2xl font-bold {{ $totalProfitStatus === 'up' ? 'text-emerald-400' : 'text-rose-400' }}">
                ${{ number_format($totalProfitReal, 2) }}
            </div>
            <div class="mt-2 flex items-center justify-between text-xs">
                <span class="text-slate-500">Plan: ${{ number_format($totalProfitPlan, 2) }}</span>
                <span class="font-semibold {{ $totalProfitStatus === 'up' ? 'text-emerald-400' : 'text-rose-400' }}">
                    {{ $totalProfitReal >= $totalProfitPlan ? '+' : '-' }}${{ number_format(abs($totalProfitReal - $totalProfitPlan), 2) }}
                </span>
            </div>
        </div>
        
        <!-- Avg Daily Profit Card -->
        <div class="bg-slate-800/50 rounded-lg p-4 border border-slate-700/50 relative overflow-hidden">
            <div class="flex items-center justify-between mb-1">
                <div class="flex items-center space-x-2">
                    <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    <span class="text-xs text-slate-400">Avg Daily Profit</span>
                </div>
                <div class="absolute top-2 right-2">
                    @if($avgProfitStatus === 'up')
                        <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                        </svg>
                    @else
                        <svg class="w-5 h-5 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"/>
                        </svg>
                    @endif
                </div>
            </div>
            <div class="text-2xl font-bold {{ $avgProfitStatus === 'up' ? 'text-emerald-400' : 'text-rose-400' }}">
                ${{ number_format($avgProfitReal, 2) }}
            </div>
            <div class="mt-2 flex items-center justify-between text-xs">
                <span class="text-slate-500">Plan: ${{ number_format($avgProfitPlan, 2) }}</span>
                <span class="font-semibold {{ $avgProfitStatus === 'up' ? 'text-emerald-400' : 'text-rose-400' }}">
                    {{ $avgProfitReal >= $avgProfitPlan ? '+' : '-' }}${{ number_format(abs($avgProfitReal - $avgProfitPlan), 2) }}
                </span>
            </div>
        </div>
        
        <!-- Win Rate Card -->
        <div class="bg-slate-800/50 rounded-lg p-4 border border-slate-700/50 relative overflow-hidden">
            <div class="flex items-center justify-between mb-1">
                <div class="flex items-center space-x-2">
                    <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="text-xs text-slate-400">Win Rate</span>
                </div>
                <div class="absolute top-2 right-2">
                    @if($winRateStatus === 'up')
                        <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                        </svg>
                    @else
                        <svg class="w-5 h-5 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"/>
                        </svg>
                    @endif
                </div>
            </div>
            <div class="text-2xl font-bold {{ $winRateStatus === 'up' ? 'text-emerald-400' : 'text-rose-400' }}">
                {{ number_format($winRateReal, 1) }}%
            </div>
            <div class="mt-2 flex items-center justify-between text-xs">
                <span class="text-slate-500">{{ $winningDays }}W / {{ $losingDays }}L</span>
                <span class="font-semibold {{ $winRateStatus === 'up' ? 'text-emerald-400' : 'text-rose-400' }}">
                    {{ $winRateReal >= $winRatePlan ? '+' : '-' }}{{ number_format(abs($winRateReal - $winRatePlan), 1) }}%
                </span>
            </div>
        </div>
        
        <!-- Total Entries Card -->
        <div class="bg-slate-800/50 rounded-lg p-4 border border-slate-700/50 relative overflow-hidden">
            <div class="flex items-center space-x-2 mb-1">
                <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                <span class="text-xs text-slate-400">Total Entries</span>
            </div>
            <div class="text-2xl font-bold text-white">
                {{ $totalEntries }}
            </div>
            <div class="mt-2 text-xs text-slate-500">
                Trading days recorded
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-slate-800/50 rounded-lg border border-slate-700/50 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-slate-900/50 border-b border-slate-700/50">
                        <th class="px-2 py-3 text-center w-12">
                            <span class="text-xs font-semibold text-slate-300">Plan</span>
                        </th>
                        <th class="px-4 py-3 text-left">
                            <button 
                                wire:click="sortBy('fecha')"
                                class="flex items-center space-x-1 text-xs font-semibold text-slate-300 hover:text-white transition-colors"
                            >
                                <span>Fecha</span>
                                @if($sortField === 'fecha')
                                    <svg class="w-3 h-3 {{ $sortDirection === 'asc' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                @endif
                            </button>
                        </th>
                        <th class="px-4 py-3 text-left">
                            <button 
                                wire:click="sortBy('capital_inicial_real')"
                                class="flex items-center space-x-1 text-xs font-semibold text-slate-300 hover:text-white transition-colors"
                            >
                                <span>Capital Inicial</span>
                                @if($sortField === 'capital_inicial_real')
                                    <svg class="w-3 h-3 {{ $sortDirection === 'asc' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                @endif
                            </button>
                        </th>
                        <th class="px-4 py-3 text-left">
                            <button 
                                wire:click="sortBy('num_trades_real')"
                                class="flex items-center space-x-1 text-xs font-semibold text-slate-300 hover:text-white transition-colors"
                            >
                                <span>Num Trades</span>
                                @if($sortField === 'num_trades_real')
                                    <svg class="w-3 h-3 {{ $sortDirection === 'asc' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                @endif
                            </button>
                        </th>
                        <th class="px-4 py-3 text-left">
                            <button 
                                wire:click="sortBy('profit_diario_real')"
                                class="flex items-center space-x-1 text-xs font-semibold text-slate-300 hover:text-white transition-colors"
                            >
                                <span>Profit Diario</span>
                                @if($sortField === 'profit_diario_real')
                                    <svg class="w-3 h-3 {{ $sortDirection === 'asc' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                @endif
                            </button>
                        </th>
                        <th class="px-4 py-3 text-left">
                            <button 
                                wire:click="sortBy('profit_percent_real')"
                                class="flex items-center space-x-1 text-xs font-semibold text-slate-300 hover:text-white transition-colors"
                            >
                                <span>% Profit</span>
                                @if($sortField === 'profit_percent_real')
                                    <svg class="w-3 h-3 {{ $sortDirection === 'asc' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                @endif
                            </button>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-300">Formula</th>
                        <th class="px-4 py-3 text-left">
                            <button 
                                wire:click="sortBy('capital_final_real')"
                                class="flex items-center space-x-1 text-xs font-semibold text-slate-300 hover:text-white transition-colors"
                            >
                                <span>Capital Final</span>
                                @if($sortField === 'capital_final_real')
                                    <svg class="w-3 h-3 {{ $sortDirection === 'asc' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                @endif
                            </button>
                        </th>
                        <th class="px-4 py-3 text-left">
                            <button 
                                wire:click="sortBy('capital_real')"
                                class="flex items-center space-x-1 text-xs font-semibold text-slate-300 hover:text-white transition-colors"
                            >
                                <span>Capital Real</span>
                                @if($sortField === 'capital_real')
                                    <svg class="w-3 h-3 {{ $sortDirection === 'asc' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                @endif
                            </button>
                        </th>
                        <th class="px-2 py-3 text-center text-xs font-semibold text-slate-300 w-16">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entries as $entry)
                        <!-- Main Row (REAL data) -->
                        <tr class="border-b border-slate-700/30 hover:bg-slate-800/30 transition-colors">
                            <!-- Expand/Collapse Button -->
                            <td class="px-2 py-3 text-center">
                                <button 
                                    wire:click="toggleRow({{ $entry->id }})"
                                    class="text-amber-400 hover:text-amber-300 transition-all duration-200 transform {{ in_array($entry->id, $expandedRows) ? 'rotate-90' : '' }}"
                                    title="Show plan"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </button>
                            </td>
                            
                            <!-- Fecha -->
                            <td class="px-4 py-3">
                                <input 
                                    type="date" 
                                    value="{{ $entry->fecha->format('Y-m-d') }}"
                                    wire:change="updateCell({{ $entry->id }}, 'fecha', $event.target.value)"
                                    class="bg-slate-900/50 border border-slate-700/50 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 transition-all duration-200 cursor-pointer w-full"
                                    style="color-scheme: dark;"
                                >
                            </td>
                            
                            <!-- Capital Inicial REAL -->
                            <td class="px-4 py-3">
                                <input 
                                    type="number" 
                                    step="0.01"
                                    value="{{ $entry->capital_inicial_real }}"
                                    wire:change="updateCell({{ $entry->id }}, 'capital_inicial_real', $event.target.value)"
                                    class="bg-slate-900/50 border border-slate-700/50 rounded px-2 py-1 text-sm text-white focus:outline-none focus:border-amber-500 w-full"
                                >
                            </td>
                            
                            <!-- Num Trades REAL -->
                            <td class="px-4 py-3">
                                <input 
                                    type="number" 
                                    step="1"
                                    value="{{ $entry->num_trades_real }}"
                                    wire:change="updateCell({{ $entry->id }}, 'num_trades_real', $event.target.value)"
                                    class="bg-slate-900/50 border border-slate-700/50 rounded px-2 py-1 text-sm text-white focus:outline-none focus:border-amber-500 w-20"
                                >
                            </td>
                            
                            <!-- Profit Diario REAL -->
                            <td class="px-4 py-3">
                                <div class="flex items-center space-x-1">
                                    <span class="text-sm {{ $entry->profit_diario_real >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">$</span>
                                    <input 
                                        type="number" 
                                        step="0.01"
                                        value="{{ $entry->profit_diario_real }}"
                                        wire:change="updateCell({{ $entry->id }}, 'profit_diario_real', $event.target.value)"
                                        class="bg-slate-900/50 border border-slate-700/50 rounded px-2 py-1 text-sm {{ $entry->profit_diario_real >= 0 ? 'text-emerald-400' : 'text-rose-400' }} focus:outline-none focus:border-amber-500 w-28"
                                    >
                                </div>
                            </td>
                            
                            <!-- % Profit REAL -->
                            <td class="px-4 py-3">
                                <div class="flex items-center space-x-1">
                                    <input 
                                        type="number" 
                                        step="0.01"
                                        value="{{ $entry->profit_percent_real }}"
                                        wire:change="updateCell({{ $entry->id }}, 'profit_percent_real', $event.target.value)"
                                        class="bg-slate-900/50 border border-slate-700/50 rounded px-2 py-1 text-sm {{ $entry->profit_percent_real >= 0 ? 'text-emerald-400' : 'text-rose-400' }} focus:outline-none focus:border-amber-500 w-24"
                                    >
                                    <span class="text-sm {{ $entry->profit_percent_real >= 0 ? 'text-emerald-400' : 'text-rose-400' }} whitespace-nowrap">%</span>
                                </div>
                            </td>
                            
                            <!-- Formula REAL -->
                            <td class="px-4 py-3">
                                <div class="text-xs text-slate-400 font-mono">{{ $entry->formula_real }}</div>
                            </td>
                            
                            <!-- Capital Final REAL -->
                            <td class="px-4 py-3">
                                <div class="text-sm font-medium text-white">
                                    ${{ number_format($entry->capital_final_real, 2) }}
                                </div>
                            </td>
                            
                            <!-- Capital Real (Auto-calculated) -->
                            <td class="px-4 py-3">
                                <div class="text-sm font-medium text-emerald-400">
                                    ${{ number_format($entry->capital_real, 2) }}
                                </div>
                                <div class="text-xs text-slate-500 mt-0.5">Auto-calculated</div>
                            </td>
                            
                            <!-- Actions -->
                            <td class="px-2 py-3 text-center">
                                <button 
                                    wire:click="confirmDelete({{ $entry->id }})"
                                    class="text-rose-400 hover:text-rose-300 transition-colors"
                                    title="Delete entry"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>
                        
                        <!-- Expanded Row with Tabs -->
                        @if(in_array($entry->id, $expandedRows))
                            <tr class="bg-slate-900/30 border-b border-slate-700/20">
                                <td class="px-2 py-2"></td>
                                <td colspan="9" class="px-4 py-3">
                                    <!-- Tabs -->
                                    <div class="flex space-x-2 mb-4 border-b border-slate-700/30">
                                        <button 
                                            wire:click="setExpandedTab({{ $entry->id }}, 'plan')"
                                            class="px-4 py-2 text-sm font-medium transition-all duration-200 border-b-2 {{ ($expandedRowTabs[$entry->id] ?? 'plan') === 'plan' ? 'text-amber-400 border-amber-400' : 'text-slate-400 border-transparent hover:text-slate-300' }}"
                                        >
                                            <div class="flex items-center space-x-2">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                                </svg>
                                                <span>Planificación</span>
                                            </div>
                                        </button>
                                        <button 
                                            wire:click="setExpandedTab({{ $entry->id }}, 'trades')"
                                            class="px-4 py-2 text-sm font-medium transition-all duration-200 border-b-2 {{ ($expandedRowTabs[$entry->id] ?? 'plan') === 'trades' ? 'text-blue-400 border-blue-400' : 'text-slate-400 border-transparent hover:text-slate-300' }}"
                                        >
                                            <div class="flex items-center space-x-2">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                                                </svg>
                                                <span>Trades ({{ $entry->trades->count() }})</span>
                                            </div>
                                        </button>
                                    </div>
                                    
                                    <!-- Tab Content: Planificación -->
                                    @if(($expandedRowTabs[$entry->id] ?? 'plan') === 'plan')
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                        <div>
                                            <label class="text-xs text-slate-400 block mb-1">Capital Inicial Plan</label>
                                            <input 
                                                type="number" 
                                                step="0.01"
                                                value="{{ $entry->capital_inicial_plan }}"
                                                wire:change="updateCell({{ $entry->id }}, 'capital_inicial_plan', $event.target.value)"
                                                class="bg-slate-900/50 border border-amber-700/30 rounded px-2 py-1 text-sm text-amber-300 focus:outline-none focus:border-amber-500 w-full"
                                            >
                                        </div>
                                        <div>
                                            <label class="text-xs text-slate-400 block mb-1">Num Trades Plan</label>
                                            <input 
                                                type="number" 
                                                step="1"
                                                value="{{ $entry->num_trades_plan }}"
                                                wire:change="updateCell({{ $entry->id }}, 'num_trades_plan', $event.target.value)"
                                                class="bg-slate-900/50 border border-amber-700/30 rounded px-2 py-1 text-sm text-amber-300 focus:outline-none focus:border-amber-500 w-full"
                                            >
                                        </div>
                                        <div>
                                            <label class="text-xs text-slate-400 block mb-1">% Profit Plan</label>
                                            <div class="flex items-center space-x-1">
                                                <input 
                                                    type="number" 
                                                    step="0.01"
                                                    value="{{ $entry->profit_percent_plan }}"
                                                    wire:change="updateCell({{ $entry->id }}, 'profit_percent_plan', $event.target.value)"
                                                    class="bg-slate-900/50 border border-amber-700/30 rounded px-2 py-1 text-sm text-amber-300 focus:outline-none focus:border-amber-500 w-full"
                                                >
                                                <span class="text-sm text-amber-300">%</span>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="text-xs text-slate-400 block mb-1">Profit Diario Plan</label>
                                            <div class="text-sm font-medium text-amber-300">
                                                ${{ number_format($entry->profit_diario_plan, 2) }}
                                            </div>
                                        </div>
                                        <div class="col-span-2">
                                            <label class="text-xs text-slate-400 block mb-1">Formula Plan</label>
                                            <div class="text-xs text-amber-400/70 font-mono">{{ $entry->formula_plan }}</div>
                                        </div>
                                        <div>
                                            <label class="text-xs text-slate-400 block mb-1">Capital Final Plan</label>
                                            <div class="text-sm font-medium text-amber-300">
                                                ${{ number_format($entry->capital_final_plan, 2) }}
                                            </div>
                                        </div>
                                    </div>
                                    @endif
                                    
                                    <!-- Tab Content: Trades -->
                                    @if(($expandedRowTabs[$entry->id] ?? 'plan') === 'trades')
                                    <div class="space-y-3">
                                        <!-- Add Trade Button -->
                                        <button 
                                            wire:click="addTrade({{ $entry->id }})"
                                            class="w-full py-3 border-2 border-dashed border-slate-600 hover:border-blue-500 rounded-lg text-slate-400 hover:text-blue-400 transition-all duration-200 flex items-center justify-center space-x-2"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                            </svg>
                                            <span class="text-sm font-medium">Add Trade</span>
                                        </button>
                                        
                                        @forelse($entry->trades as $trade)
                                            <div class="bg-slate-800/50 rounded-lg p-4 border border-slate-700/30 relative group">
                                                <!-- Edit/Delete Buttons -->
                                                <div class="absolute top-2 right-2 flex space-x-1">
                                                    <button 
                                                        wire:click="toggleEditTrade({{ $trade->id }})"
                                                        class="p-1.5 text-blue-400 hover:text-blue-300 hover:bg-blue-900/20 rounded transition-colors"
                                                        title="Edit trade"
                                                    >
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                        </svg>
                                                    </button>
                                                    <button 
                                                        wire:click="deleteTrade({{ $trade->id }})"
                                                        class="p-1.5 text-rose-400 hover:text-rose-300 hover:bg-rose-900/20 rounded transition-colors"
                                                        title="Delete trade"
                                                    >
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                        </svg>
                                                    </button>
                                                </div>
                                                
                                                @if(isset($editingTrades[$trade->id]) && $editingTrades[$trade->id])
                                                    <!-- Edit Mode -->
                                                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm pr-16">
                                                        <div>
                                                            <label class="text-xs text-slate-400 block mb-1">Symbol</label>
                                                            <input 
                                                                type="text" 
                                                                value="{{ $trade->symbol }}"
                                                                wire:change="updateTrade({{ $trade->id }}, 'symbol', $event.target.value)"
                                                                class="w-full bg-slate-900/50 border border-blue-700/30 rounded px-2 py-1 text-sm text-blue-300 focus:outline-none focus:border-blue-500"
                                                                placeholder="SPY"
                                                            >
                                                        </div>
                                                        <div>
                                                            <label class="text-xs text-slate-400 block mb-1">Strike Price</label>
                                                            <input 
                                                                type="number" 
                                                                step="0.01"
                                                                value="{{ $trade->strike_price }}"
                                                                wire:change="updateTrade({{ $trade->id }}, 'strike_price', $event.target.value)"
                                                                class="w-full bg-slate-900/50 border border-blue-700/30 rounded px-2 py-1 text-sm text-white focus:outline-none focus:border-blue-500"
                                                            >
                                                        </div>
                                                        <div>
                                                            <label class="text-xs text-slate-400 block mb-1">Capital Usado</label>
                                                            <input 
                                                                type="number" 
                                                                step="0.01"
                                                                value="{{ $trade->capital_usado }}"
                                                                wire:change="updateTrade({{ $trade->id }}, 'capital_usado', $event.target.value)"
                                                                class="w-full bg-slate-900/50 border border-blue-700/30 rounded px-2 py-1 text-sm text-white focus:outline-none focus:border-blue-500"
                                                            >
                                                        </div>
                                                        <div>
                                                            <label class="text-xs text-slate-400 block mb-1">Ganancia</label>
                                                            <div class="flex items-center space-x-1">
                                                                <span class="text-sm text-emerald-400">$</span>
                                                                <input 
                                                                    type="number" 
                                                                    step="0.01"
                                                                    value="{{ $trade->ganancia }}"
                                                                    wire:change="updateTrade({{ $trade->id }}, 'ganancia', $event.target.value)"
                                                                    class="w-full bg-slate-900/50 border border-blue-700/30 rounded px-2 py-1 text-sm text-emerald-300 focus:outline-none focus:border-blue-500"
                                                                >
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <label class="text-xs text-slate-400 block mb-1">% Ganancia</label>
                                                            <div class="text-sm font-medium text-emerald-400">{{ number_format($trade->profit_percent, 2) }}%</div>
                                                        </div>
                                                        <div>
                                                            <label class="text-xs text-slate-400 block mb-1">Fee</label>
                                                            <input 
                                                                type="number" 
                                                                step="0.01"
                                                                value="{{ $trade->fee }}"
                                                                wire:change="updateTrade({{ $trade->id }}, 'fee', $event.target.value)"
                                                                class="w-full bg-slate-900/50 border border-blue-700/30 rounded px-2 py-1 text-sm text-yellow-300 focus:outline-none focus:border-blue-500"
                                                            >
                                                        </div>
                                                    </div>
                                                @else
                                                    <!-- View Mode -->
                                                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm pr-16">
                                                        <div>
                                                            <label class="text-xs text-slate-400 block mb-1">Symbol</label>
                                                            <div class="text-sm font-medium text-blue-400">{{ $trade->symbol ?: '-' }}</div>
                                                        </div>
                                                        <div>
                                                            <label class="text-xs text-slate-400 block mb-1">Strike Price</label>
                                                            <div class="text-sm font-medium text-white">${{ number_format($trade->strike_price, 2) }}</div>
                                                        </div>
                                                        <div>
                                                            <label class="text-xs text-slate-400 block mb-1">Capital Usado</label>
                                                            <div class="text-sm font-medium text-white">${{ number_format($trade->capital_usado, 2) }}</div>
                                                        </div>
                                                        <div>
                                                            <label class="text-xs text-slate-400 block mb-1">% Ganancia</label>
                                                            <div class="text-sm font-medium text-emerald-400">{{ number_format($trade->profit_percent, 2) }}%</div>
                                                        </div>
                                                        <div>
                                                            <label class="text-xs text-slate-400 block mb-1">Ganancia</label>
                                                            <div class="text-sm font-medium text-emerald-400">${{ number_format($trade->ganancia, 2) }}</div>
                                                        </div>
                                                        <div>
                                                            <label class="text-xs text-slate-400 block mb-1">Fee</label>
                                                            <div class="text-sm font-medium text-yellow-400">${{ number_format($trade->fee, 2) }}</div>
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        @empty
                                            <div class="text-center py-8 text-slate-400">
                                                <svg class="w-12 h-12 mx-auto mb-2 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                </svg>
                                                <p class="text-sm">No trades recorded for this day</p>
                                                <p class="text-xs text-slate-500 mt-1">Click "+ Add Trade" to start tracking</p>
                                            </div>
                                        @endforelse
                                    </div>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-8 text-center text-slate-400">
                                No entries found. Click "Add Entry" to get started.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="px-4 py-3 bg-slate-900/30 border-t border-slate-700/50 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <div class="text-sm text-slate-400">
                    Showing {{ $entries->firstItem() ?? 0 }} to {{ $entries->lastItem() ?? 0 }} of {{ $entries->total() }} entries
                </div>
                
                <div class="flex items-center space-x-2">
                    <label class="text-sm text-slate-400">Rows per page:</label>
                    <select 
                        wire:model.live="perPage"
                        class="bg-slate-800/50 border border-slate-700/50 rounded px-2 py-1 text-sm text-white focus:outline-none focus:border-amber-500"
                    >
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
            </div>
            
            <div class="flex items-center space-x-2">
                @if ($entries->onFirstPage())
                    <button disabled class="px-3 py-1 bg-slate-800/30 text-slate-600 text-sm rounded cursor-not-allowed">
                        Previous
                    </button>
                @else
                    <button 
                        wire:click="previousPage" 
                        class="px-3 py-1 bg-slate-800/50 hover:bg-slate-700 text-white text-sm rounded transition-colors"
                    >
                        Previous
                    </button>
                @endif
                
                <!-- Page Numbers -->
                <div class="flex items-center space-x-1">
                    @php
                        $currentPage = $entries->currentPage();
                        $lastPage = $entries->lastPage();
                        $start = max(1, $currentPage - 2);
                        $end = min($lastPage, $currentPage + 2);
                    @endphp
                    
                    @if($start > 1)
                        <button 
                            wire:click="gotoPage(1)" 
                            class="px-3 py-1 bg-slate-800/50 hover:bg-slate-700 text-white text-sm rounded transition-colors"
                        >
                            1
                        </button>
                        @if($start > 2)
                            <span class="text-slate-400 px-2">...</span>
                        @endif
                    @endif
                    
                    @for($i = $start; $i <= $end; $i++)
                        @if($i == $currentPage)
                            <button class="px-3 py-1 bg-amber-600 text-white text-sm rounded font-semibold">
                                {{ $i }}
                            </button>
                        @else
                            <button 
                                wire:click="gotoPage({{ $i }})" 
                                class="px-3 py-1 bg-slate-800/50 hover:bg-slate-700 text-white text-sm rounded transition-colors"
                            >
                                {{ $i }}
                            </button>
                        @endif
                    @endfor
                    
                    @if($end < $lastPage)
                        @if($end < $lastPage - 1)
                            <span class="text-slate-400 px-2">...</span>
                        @endif
                        <button 
                            wire:click="gotoPage({{ $lastPage }})" 
                            class="px-3 py-1 bg-slate-800/50 hover:bg-slate-700 text-white text-sm rounded transition-colors"
                        >
                            {{ $lastPage }}
                        </button>
                    @endif
                </div>
                
                @if ($entries->hasMorePages())
                    <button 
                        wire:click="nextPage" 
                        class="px-3 py-1 bg-slate-800/50 hover:bg-slate-700 text-white text-sm rounded transition-colors"
                    >
                        Next
                    </button>
                @else
                    <button disabled class="px-3 py-1 bg-slate-800/30 text-slate-600 text-sm rounded cursor-not-allowed">
                        Next
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    @if($showDeleteModal)
        <div 
            class="fixed inset-0 z-50 overflow-y-auto animate-fadeIn" 
            aria-labelledby="modal-title" 
            role="dialog" 
            aria-modal="true"
            style="animation: fadeIn 0.2s ease-out;"
        >
            <!-- Background overlay with blur and dark tint -->
            <div 
                class="fixed inset-0 bg-black/60 backdrop-blur-md transition-all duration-300"
                wire:click="cancelDelete"
                style="animation: fadeIn 0.2s ease-out;"
            ></div>

            <!-- Modal container -->
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:p-0">
                <!-- Center modal -->
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <!-- Modal panel -->
                <div 
                    class="inline-block align-bottom bg-slate-800 rounded-lg text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-slate-700/50 relative z-10"
                    style="animation: slideUp 0.3s ease-out;"
                >
                    <div class="bg-slate-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <!-- Icon -->
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-rose-900/20 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                            </div>
                            
                            <!-- Content -->
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                <h3 class="text-lg leading-6 font-medium text-white" id="modal-title">
                                    Delete Entry
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-slate-400">
                                        Are you sure you want to delete this trading journal entry? This action cannot be undone.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="bg-slate-900/50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                        <button 
                            wire:click="deleteEntry"
                            type="button" 
                            class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-rose-600 text-base font-medium text-white hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-rose-500 sm:ml-3 sm:w-auto sm:text-sm transition-colors"
                        >
                            Delete
                        </button>
                        <button 
                            wire:click="cancelDelete"
                            type="button" 
                            class="mt-3 w-full inline-flex justify-center rounded-lg border border-slate-600 shadow-sm px-4 py-2 bg-slate-700 text-base font-medium text-slate-300 hover:bg-slate-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm transition-colors"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

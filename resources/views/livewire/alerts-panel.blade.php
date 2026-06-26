<div>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-semibold text-white">Alerts</h2>
        <button 
            wire:click="toggleCreateForm"
            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors"
        >
            {{ $showCreateForm ? 'Cancel' : '+ New Alert' }}
        </button>
    </div>

    <!-- Create Form -->
    @if($showCreateForm)
        <x-card class="mb-6">
            <h3 class="text-sm font-semibold text-white mb-4">Create New Alert</h3>
            
            <form wire:submit="createAlert" class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-2">Symbol</label>
                    <input 
                        type="text" 
                        wire:model="ticker"
                        placeholder="e.g., AAPL"
                        class="w-full px-3 py-2 bg-slate-800/50 border border-slate-700/50 rounded-lg text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/50"
                    />
                    @error('ticker') <span class="text-xs text-rose-400 mt-1">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-2">Alert Type</label>
                    <select 
                        wire:model="alertType"
                        class="w-full px-3 py-2 bg-slate-800/50 border border-slate-700/50 rounded-lg text-sm text-white focus:outline-none focus:ring-2 focus:ring-blue-500/50"
                    >
                        <option value="">Select type...</option>
                        @foreach($alertTypes as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('alertType') <span class="text-xs text-rose-400 mt-1">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-2">Condition</label>
                    <input 
                        type="text" 
                        wire:model="condition"
                        placeholder="e.g., Price above $150"
                        class="w-full px-3 py-2 bg-slate-800/50 border border-slate-700/50 rounded-lg text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/50"
                    />
                    @error('condition') <span class="text-xs text-rose-400 mt-1">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-2">Threshold Value (optional)</label>
                    <input 
                        type="number" 
                        step="0.01"
                        wire:model="thresholdValue"
                        placeholder="e.g., 150.00"
                        class="w-full px-3 py-2 bg-slate-800/50 border border-slate-700/50 rounded-lg text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/50"
                    />
                    @error('thresholdValue') <span class="text-xs text-rose-400 mt-1">{{ $message }}</span> @enderror
                </div>

                <button 
                    type="submit"
                    class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors"
                >
                    Create Alert
                </button>
            </form>
        </x-card>
    @endif

    <!-- Alerts List -->
    <div class="space-y-3">
        @forelse($alerts as $alert)
            <x-card>
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center space-x-2 mb-2">
                            <span class="text-lg">{{ $alert->alert_type->icon() }}</span>
                            <h3 class="text-sm font-semibold text-white">{{ $alert->symbol->ticker }}</h3>
                            <span class="px-2 py-0.5 text-xs font-medium rounded {{ $alert->is_active ? 'bg-emerald-500/10 text-emerald-400' : 'bg-slate-500/10 text-slate-400' }}">
                                {{ $alert->is_active ? 'Active' : 'Inactive' }}
                            </span>
                            @if($alert->is_triggered)
                                <span class="px-2 py-0.5 text-xs font-medium rounded bg-yellow-500/10 text-yellow-400">
                                    Triggered
                                </span>
                            @endif
                        </div>

                        <p class="text-xs text-slate-400 mb-1">{{ $alert->alert_type->label() }}</p>
                        <p class="text-sm text-slate-300">{{ $alert->condition }}</p>
                        
                        @if($alert->threshold_value)
                            <p class="text-xs text-slate-500 mt-2">Threshold: ${{ number_format($alert->threshold_value, 2) }}</p>
                        @endif

                        @if($alert->triggered_at)
                            <p class="text-xs text-yellow-400 mt-2">
                                Triggered {{ $alert->triggered_at->diffForHumans() }}
                            </p>
                        @endif
                    </div>

                    <div class="flex items-center space-x-2 ml-4">
                        <button 
                            wire:click="toggleAlert({{ $alert->id }})"
                            class="p-2 rounded-lg hover:bg-slate-800/50 transition-colors {{ $alert->is_active ? 'text-emerald-400' : 'text-slate-400' }}"
                            title="{{ $alert->is_active ? 'Deactivate' : 'Activate' }}"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                            </svg>
                        </button>
                        
                        <button 
                            wire:click="deleteAlert({{ $alert->id }})"
                            wire:confirm="Are you sure you want to delete this alert?"
                            class="p-2 rounded-lg hover:bg-slate-800/50 transition-colors text-slate-400 hover:text-rose-400"
                            title="Delete"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </x-card>
        @empty
            <div class="text-center py-12">
                <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-slate-800/50 flex items-center justify-center">
                    <svg class="w-8 h-8 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                    </svg>
                </div>
                <p class="text-sm text-slate-400">No alerts configured</p>
                <p class="text-xs text-slate-500 mt-1">Create your first alert to get started</p>
            </div>
        @endforelse
    </div>
</div>

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Market Platform') }} - @yield('title', 'Dashboard')</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />

    <!-- Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>
        /* Prevent horizontal overflow globally */
        html, body {
            max-width: 100vw;
            overflow-x: hidden;
        }
        * {
            box-sizing: border-box;
        }
    </style>
</head>
<body class="bg-[#0a0b0d] text-slate-100 antialiased" x-data="{ sidebarOpen: true, watchlistOpen: false }">
    <div class="min-h-screen">
        <!-- Top Navigation -->
        <nav class="fixed top-0 right-0 z-50 glass border-b border-slate-700/50 transition-all duration-300" :class="sidebarOpen ? 'left-64' : 'left-16'">
            <div class="mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <!-- Search & Actions -->
                    <div class="flex items-center space-x-4">
                        <!-- Symbol Search -->
                        <div class="hidden md:block w-64">
                            @livewire('symbol-search')
                        </div>
                    </div>

                    <!-- Market Status -->
                    <div class="hidden lg:flex items-center space-x-2 px-3 py-1.5 rounded-lg bg-emerald-500/10 border border-emerald-500/20">
                        <div class="w-2 h-2 bg-emerald-400 rounded-full animate-pulse"></div>
                        <span class="text-xs font-medium text-emerald-400">Market Open</span>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Sidebar -->
        <aside class="fixed top-0 left-0 bottom-0 z-50 glass border-r border-slate-700/50 transition-all duration-300" :class="sidebarOpen ? 'w-64' : 'w-16'">
            <!-- Logo & Toggle -->
            <div class="h-16 flex items-center justify-between px-4 border-b border-slate-700/50">
                <a href="{{ route('dashboard') }}" class="flex items-center space-x-2" x-show="sidebarOpen" x-transition>
                    <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg flex items-center justify-center">
                        <span class="text-white font-bold text-sm">MP</span>
                    </div>
                    <span class="text-lg font-semibold text-slate-200">Market Platform</span>
                </a>
                <div x-show="!sidebarOpen" class="w-full flex justify-center">
                    <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg flex items-center justify-center">
                        <span class="text-white font-bold text-sm">MP</span>
                    </div>
                </div>
                <button @click="sidebarOpen = !sidebarOpen" class="p-2 rounded-lg hover:bg-slate-700/50 transition-colors" x-show="sidebarOpen">
                    <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"></path>
                    </svg>
                </button>
            </div>

            <!-- Menu Items -->
            <nav class="p-4 space-y-2">
                <!-- Home -->
                <a href="{{ route('dashboard') }}"
                   class="flex items-center space-x-3 px-3 py-2.5 rounded-lg transition-colors {{ request()->routeIs('dashboard') ? 'bg-blue-500/20 text-blue-400' : 'text-slate-400 hover:bg-slate-700/50 hover:text-white' }}"
                   :title="!sidebarOpen ? 'Home' : ''">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                    </svg>
                    <span x-show="sidebarOpen" x-transition class="font-medium">Home</span>
                </a>

                <!-- Heatmap -->
                <a href="{{ route('heatmap') }}"
                   class="flex items-center space-x-3 px-3 py-2.5 rounded-lg transition-colors {{ request()->routeIs('heatmap') ? 'bg-blue-500/20 text-blue-400' : 'text-slate-400 hover:bg-slate-700/50 hover:text-white' }}"
                   :title="!sidebarOpen ? 'Heatmap' : ''">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h4a1 1 0 011 1v7a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM14 5a1 1 0 011-1h4a1 1 0 011 1v3a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 16a1 1 0 011-1h4a1 1 0 011 1v3a1 1 0 01-1 1H5a1 1 0 01-1-1v-3zM14 13a1 1 0 011-1h4a1 1 0 011 1v7a1 1 0 01-1 1h-4a1 1 0 01-1-1v-7z"></path>
                    </svg>
                    <span x-show="sidebarOpen" x-transition class="font-medium">Heatmap</span>
                </a>

                <!-- Alerts -->
                <a href="{{ route('alerts.index') }}"
                   class="flex items-center space-x-3 px-3 py-2.5 rounded-lg transition-colors {{ request()->routeIs('alerts.*') ? 'bg-blue-500/20 text-blue-400' : 'text-slate-400 hover:bg-slate-700/50 hover:text-white' }}"
                   :title="!sidebarOpen ? 'Alerts' : ''">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                    </svg>
                    <span x-show="sidebarOpen" x-transition class="font-medium">Alerts</span>
                </a>

                <!-- Advanced Tape Flow -->
                <a href="{{ route('advanced-tape-flow') }}"
                   class="flex items-center space-x-3 px-3 py-2.5 rounded-lg transition-colors {{ request()->routeIs('advanced-tape-flow') ? 'bg-blue-500/20 text-blue-400' : 'text-slate-400 hover:bg-slate-700/50 hover:text-white' }}"
                   :title="!sidebarOpen ? 'Advanced Tape Flow' : ''">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    <span x-show="sidebarOpen" x-transition class="font-medium">Tape Flow</span>
                </a>

                <!-- Trading Journal -->
                <a href="{{ route('trading-journal') }}"
                   class="flex items-center space-x-3 px-3 py-2.5 rounded-lg transition-colors {{ request()->routeIs('trading-journal') ? 'bg-blue-500/20 text-blue-400' : 'text-slate-400 hover:bg-slate-700/50 hover:text-white' }}"
                   :title="!sidebarOpen ? 'Trading Journal' : ''">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                    </svg>
                    <span x-show="sidebarOpen" x-transition class="font-medium">Trading Journal</span>
                </a>

                <!-- Backtester -->
                <a href="{{ route('backtest') }}"
                   class="flex items-center space-x-3 px-3 py-2.5 rounded-lg transition-colors {{ request()->routeIs('backtest') ? 'bg-blue-500/20 text-blue-400' : 'text-slate-400 hover:bg-slate-700/50 hover:text-white' }}"
                   :title="!sidebarOpen ? 'Backtester' : ''">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                    </svg>
                    <span x-show="sidebarOpen" x-transition class="font-medium">Backtester</span>
                </a>

                <!-- Strategy Lab -->
                <a href="{{ route('strategy-lab') }}"
                   class="flex items-center space-x-3 px-3 py-2.5 rounded-lg transition-colors {{ request()->routeIs('strategy-lab*') ? 'bg-purple-500/20 text-purple-400' : 'text-slate-400 hover:bg-slate-700/50 hover:text-white' }}"
                   :title="!sidebarOpen ? 'Strategy Lab' : ''">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/>
                    </svg>
                    <span x-show="sidebarOpen" x-transition class="font-medium">Strategy Lab</span>
                </a>

                <!-- Strategy Bots -->
                <a href="{{ route('strategy-bots') }}"
                   class="flex items-center space-x-3 px-3 py-2.5 rounded-lg transition-colors {{ request()->routeIs('strategy-bots') ? 'bg-violet-500/20 text-violet-400' : 'text-slate-400 hover:bg-slate-700/50 hover:text-white' }}"
                   :title="!sidebarOpen ? 'Strategy Bots' : ''">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    <span x-show="sidebarOpen" x-transition class="font-medium">Strategy Bots</span>
                </a>

                <!-- Schwab Account -->
                <a href="{{ route('schwab.account') }}"
                   class="flex items-center space-x-3 px-3 py-2.5 rounded-lg transition-colors {{ request()->routeIs('schwab.account') ? 'bg-blue-500/20 text-blue-400' : 'text-slate-400 hover:bg-slate-700/50 hover:text-white' }}"
                   :title="!sidebarOpen ? 'Schwab Account' : ''">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                    </svg>
                    <span x-show="sidebarOpen" x-transition class="font-medium">Schwab Account</span>
                </a>

                <!-- Alpaca Trading -->
                <a href="{{ route('alpaca.paper') }}"
                   class="flex items-center space-x-3 px-3 py-2.5 rounded-lg transition-colors {{ request()->routeIs('alpaca.paper') ? 'bg-emerald-500/20 text-emerald-400' : 'text-slate-400 hover:bg-slate-700/50 hover:text-white' }}"
                   :title="!sidebarOpen ? 'Alpaca Trading' : ''">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6h18M3 12h18M3 18h18M7 6v12m10-12v12"/>
                    </svg>
                    <span x-show="sidebarOpen" x-transition class="font-medium">Alpaca Trading</span>
                </a>

                <!-- Alpaca Strategy Lab -->
                <a href="{{ route('alpaca.strategy-lab') }}"
                   class="flex items-center space-x-3 px-3 py-2.5 rounded-lg transition-colors {{ request()->routeIs('alpaca.strategy-lab') ? 'bg-emerald-500/20 text-emerald-400' : 'text-slate-400 hover:bg-slate-700/50 hover:text-white' }}"
                   :title="!sidebarOpen ? 'Alpaca Lab' : ''">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 17l6-6 4 4 6-8M4 21h16M4 3h16"/>
                    </svg>
                    <span x-show="sidebarOpen" x-transition class="font-medium">Alpaca Lab</span>
                </a>
            </nav>

            <!-- Collapse Button (when collapsed) -->
            <div x-show="!sidebarOpen" class="absolute bottom-4 left-0 right-0 flex justify-center">
                <button @click="sidebarOpen = true" class="p-2 rounded-lg hover:bg-slate-700/50 transition-colors">
                    <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"></path>
                    </svg>
                </button>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="pt-16 w-full transition-all duration-300" :class="sidebarOpen ? 'pl-64' : 'pl-16'">
            <!-- Main Content Area -->
            <main class="w-full min-h-screen transition-all duration-300" :class="watchlistOpen ? 'xl:pr-80' : ''">
                @yield('content')
            </main>

            <!-- Watchlist Toggle Button -->
            @unless(request()->routeIs('trading-journal'))
                <button
                    @click="watchlistOpen = !watchlistOpen"
                    class="hidden xl:flex fixed top-1/2 -translate-y-1/2 z-40 items-center justify-center p-2 rounded-lg hover:bg-slate-700/50 transition-all duration-300"
                    :class="watchlistOpen ? 'right-[19.5rem]' : 'right-0'"
                >
                    <svg class="w-5 h-5 text-slate-400 transition-transform" :class="watchlistOpen ? 'rotate-0' : 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>

                <!-- Watchlist Sidebar -->
                <aside
                    class="hidden xl:block w-80 border-l border-slate-700/50 glass fixed top-16 bottom-0 overflow-y-auto transition-transform duration-300 z-30"
                    :class="watchlistOpen ? 'right-0' : '-right-80'"
                >
                    @livewire('watchlist-panel')
                </aside>
            @endunless
        </div>
    </div>

    @livewireScripts
    @stack('scripts')

    {{-- Render debugbar after Livewire to avoid conflicts --}}
    @if(app()->bound('debugbar') && app('debugbar')->isEnabled())
        {!! app('debugbar')->renderHead() !!}
        {!! app('debugbar')->render() !!}
    @endif
</body>
</html>

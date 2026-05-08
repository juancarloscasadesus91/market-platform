<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark" x-data="{ darkMode: localStorage.getItem('theme') !== 'light' }" x-init="$watch('darkMode', val => localStorage.setItem('theme', val ? 'dark' : 'light'))" :class="{ 'dark': darkMode }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Market Platform') }} - @yield('title', 'Dashboard')</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />

    <!-- Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
        <!-- Top Navigation -->
        <nav class="fixed top-0 left-0 right-0 z-50 glass border-b border-slate-800/50">
            <div class="mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <!-- Logo -->
                        <a href="{{ route('dashboard') }}" class="flex items-center space-x-2">
                            <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg flex items-center justify-center">
                                <span class="text-white font-bold text-sm">MP</span>
                            </div>
                            <span class="text-lg font-semibold text-white">Market Platform</span>
                        </a>

                        <!-- Main Navigation -->
                        <div class="hidden md:flex items-center space-x-1">
                            <x-nav-link href="{{ route('dashboard') }}" :active="request()->routeIs('dashboard')">
                                Dashboard
                            </x-nav-link>
                            <x-nav-link href="{{ route('heatmap') }}" :active="request()->routeIs('heatmap')">
                                Heatmap
                            </x-nav-link>
                            <x-nav-link href="{{ route('alerts.index') }}" :active="request()->routeIs('alerts.*')">
                                Alerts
                            </x-nav-link>
                        </div>
                    </div>

                    <!-- Search & Actions -->
                    <div class="flex items-center space-x-4">
                        <div class="hidden md:block w-64">
                            @livewire('symbol-search')
                        </div>

                        <!-- Theme Toggle -->
                        <button 
                            @click="darkMode = !darkMode"
                            class="p-2 rounded-lg hover:bg-slate-800/50 transition-colors text-slate-400 hover:text-white dark:text-slate-400 dark:hover:text-white"
                            title="Toggle theme"
                        >
                            <!-- Sun Icon (Light Mode) -->
                            <svg x-show="!darkMode" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                            <!-- Moon Icon (Dark Mode) -->
                            <svg x-show="darkMode" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                            </svg>
                        </button>

                        <!-- Market Status -->
                        <div class="hidden lg:flex items-center space-x-2 px-3 py-1.5 rounded-lg bg-emerald-500/10 border border-emerald-500/20">
                            <div class="w-2 h-2 bg-emerald-400 rounded-full animate-pulse"></div>
                            <span class="text-xs font-medium text-emerald-400">Market Open</span>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="pt-16 flex">
            <!-- Main Content Area -->
            <main class="flex-1 min-h-screen">
                @yield('content')
            </main>

            <!-- Watchlist Sidebar -->
            <aside class="hidden xl:block w-80 border-l border-slate-800/50 glass fixed right-0 top-16 bottom-0 overflow-y-auto">
                @livewire('watchlist-panel')
            </aside>
        </div>
    </div>

    @livewireScripts
    @stack('scripts')
</body>
</html>

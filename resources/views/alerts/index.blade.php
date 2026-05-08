@extends('layouts.app')

@section('title', 'Alerts')

@section('content')
<div class="p-6 xl:pr-[22rem]">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-white mb-2">Market Alerts</h1>
        <p class="text-slate-400">Configure alerts for unusual premium, volume spikes, and price movements</p>
    </div>

    @livewire('alerts-panel')
</div>
@endsection

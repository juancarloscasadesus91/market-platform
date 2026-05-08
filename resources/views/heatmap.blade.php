@extends('layouts.app')

@section('title', 'Options Heatmap')

@section('content')
<div class="p-6 xl:pr-[22rem]">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-white mb-2">Options Flow Heatmap</h1>
        <p class="text-slate-400">Visualize premium flow, volume clusters, and IV spikes across strikes</p>
    </div>

    @livewire('heatmap-grid')
</div>
@endsection

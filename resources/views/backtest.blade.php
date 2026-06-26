@extends('layouts.app')

@section('title', 'Backtester')

@section('content')
<div class="p-6">
    @livewire('backtest-manager')
</div>
@endsection

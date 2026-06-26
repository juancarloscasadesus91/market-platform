@props(['active' => false])

@php
$classes = $active
    ? 'px-4 py-2 text-sm font-medium text-white bg-slate-700/60 rounded-lg'
    : 'px-4 py-2 text-sm font-medium text-slate-300 hover:text-white hover:bg-slate-700/40 rounded-lg transition-colors';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>

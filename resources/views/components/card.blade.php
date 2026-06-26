@props(['hover' => false])

@php
$classes = 'glass rounded-xl p-6 border border-slate-700/50';
if ($hover) {
    $classes .= ' card-hover';
}
@endphp

<div {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</div>

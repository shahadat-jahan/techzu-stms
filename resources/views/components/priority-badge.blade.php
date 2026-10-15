@props(['priority'])

@php
    $classes = match ($priority->value) {
        'low' => 'bg-slate-100 text-slate-700',
        'medium' => 'bg-sky-100 text-sky-800',
        'high' => 'bg-orange-100 text-orange-800',
        'critical' => 'bg-red-100 text-red-800',
        default => 'bg-gray-100 text-gray-700',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {$classes}"]) }}>
    {{ $priority->label() }}
</span>

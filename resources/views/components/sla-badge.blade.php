@props(['status'])

@php
    $classes = match ($status->value) {
        'within' => 'bg-green-100 text-green-800',
        'approaching' => 'bg-amber-100 text-amber-800',
        'breached' => 'bg-red-100 text-red-800',
        'met' => 'bg-emerald-100 text-emerald-800',
        default => 'bg-gray-100 text-gray-700',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {$classes}"]) }}>
    {{ $status->label() }}
</span>

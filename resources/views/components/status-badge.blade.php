@props(['status'])

@php
    $classes = match ($status->value) {
        'open' => 'bg-blue-100 text-blue-800',
        'in_progress' => 'bg-indigo-100 text-indigo-800',
        'waiting_for_customer' => 'bg-purple-100 text-purple-800',
        'resolved' => 'bg-green-100 text-green-800',
        'closed' => 'bg-gray-200 text-gray-700',
        default => 'bg-gray-100 text-gray-700',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {$classes}"]) }}>
    {{ $status->label() }}
</span>

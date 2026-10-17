@props([
    'value',
    'format' => 'M j, Y H:i',
])

@php
    $formatted = $value?->tz(config('support.business_timezone'))->format($format);
@endphp

<span {{ $attributes->merge(['class' => 'text-gray-500']) }}>{{ $formatted ?? '—' }}</span>

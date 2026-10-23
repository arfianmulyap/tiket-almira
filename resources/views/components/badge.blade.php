@props(['variant' => 'neutral'])

@php
    $variantClass = match ($variant) {
        'info' => 'badge-info',
        'warning' => 'badge-warning',
        'success' => 'badge-success',
        'danger' => 'badge-danger',
        default => 'badge-neutral',
    };
@endphp

{{-- Status is carried by the text content, not by color alone. --}}
<span {{ $attributes->class(['badge', $variantClass]) }}>{{ $slot }}</span>

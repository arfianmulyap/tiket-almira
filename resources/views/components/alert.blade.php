@props([
    'variant' => 'info',
    'title' => null,
])

@php
    $variantClass = match ($variant) {
        'success' => 'alert-success',
        'warning' => 'alert-warning',
        'error' => 'alert-error',
        default => 'alert-info',
    };

    // Errors and warnings are announced immediately; neutral messages wait
    // for a natural pause in screen reader output.
    $isUrgent = in_array($variant, ['error', 'warning'], true);
@endphp

<div
    {{ $attributes->class(['alert', $variantClass]) }}
    role="{{ $isUrgent ? 'alert' : 'status' }}"
>
    <div>
        @if ($title)
            <p class="alert-title">{{ $title }}</p>
        @endif

        {{ $slot }}
    </div>
</div>

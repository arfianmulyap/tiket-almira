@props([
    'variant' => 'primary',
    'size' => null,
    'block' => false,
    'loading' => false,
    'href' => null,
])

@php
    $variantClass = match ($variant) {
        'secondary' => 'button-secondary',
        'ghost' => 'button-ghost',
        'ghost-inverse' => 'button-ghost-inverse',
        'danger' => 'button-danger',
        default => '',
    };

    $classes = array_filter([
        'button',
        $variantClass,
        $size === 'sm' ? 'button-sm' : null,
        $block ? 'button-block' : null,
    ]);

    $classes = implode(' ', $classes);
@endphp

@if ($href)
    {{-- Link-style action. Anchors cannot be natively disabled, so a loading
         link is marked aria-disabled and loses its href. --}}
    <a
        @if (! $loading) href="{{ $href }}" @endif
        {{ $attributes->class($classes) }}
        @if ($loading) aria-disabled="true" data-loading="true" @endif
    >
        {{ $slot }}
    </a>
@else
    <button
        {{ $attributes->class($classes)->merge(['type' => 'submit']) }}
        @if ($loading) disabled data-loading="true" @endif
    >
        {{ $slot }}
    </button>
@endif

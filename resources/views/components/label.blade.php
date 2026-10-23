@props([
    'for' => null,
    'required' => false,
])

<label
    @if ($for) for="{{ $for }}" @endif
    {{ $attributes->class(['label', 'label-required' => $required]) }}
>
    {{ $slot }}
</label>

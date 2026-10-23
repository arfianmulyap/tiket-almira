@props([
    'name',
    'hasError' => null,
])

@php
    // Mirrors x-input: fall back to the validation bag so callers rarely
    // need to pass hasError explicitly.
    $invalid = $hasError ?? $errors->has($name);
@endphp

<select
    name="{{ $name }}"
    id="{{ $attributes->get('id', $name) }}"
    {{ $attributes->class('select')->except('id') }}
    @if ($invalid)
        aria-invalid="true"
        aria-describedby="{{ $name }}-error"
    @endif
>
    {{ $slot }}
</select>

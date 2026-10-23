@props([
    'name',
    'type' => 'text',
    'value' => null,
    'hasError' => null,
])

@php
    // Fall back to the validation bag so callers rarely need to pass hasError.
    $invalid = $hasError ?? $errors->has($name);
@endphp

<input
    type="{{ $type }}"
    name="{{ $name }}"
    id="{{ $attributes->get('id', $name) }}"
    @if (! is_null($value)) value="{{ $value }}" @endif
    {{ $attributes->class('input')->except('id') }}
    @if ($invalid)
        aria-invalid="true"
        aria-describedby="{{ $name }}-error"
    @endif
>

@props([
    'name',
    'value' => null,
    'rows' => 5,
    'hasError' => null,
])

@php
    $invalid = $hasError ?? $errors->has($name);
@endphp

<textarea
    name="{{ $name }}"
    id="{{ $attributes->get('id', $name) }}"
    rows="{{ $rows }}"
    {{ $attributes->class('textarea')->except(['id', 'rows']) }}
    @if ($invalid)
        aria-invalid="true"
        aria-describedby="{{ $name }}-error"
    @endif
>{{ $value }}</textarea>

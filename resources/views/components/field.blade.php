@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'required' => false,
    'hint' => null,
])

{{--
    Convenience wrapper for the common label + input + error + hint grouping.
    Use the individual components directly when a field needs a custom control
    (select, textarea, checkbox, input group).
--}}
<div class="field">
    @if ($label)
        <x-label :for="$name" :required="$required">{{ $label }}</x-label>
    @endif

    <x-input
        :name="$name"
        :type="$type"
        :value="$value ?? old($name)"
        :required="$required"
        {{ $attributes }}
    />

    @if ($hint)
        <p class="field-hint">{{ $hint }}</p>
    @endif

    <x-field-error :name="$name" />
</div>

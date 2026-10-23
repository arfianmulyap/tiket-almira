@props(['name'])

@error($name)
    {{-- id is referenced by the control's aria-describedby. --}}
    <p class="field-error" id="{{ $name }}-error">{{ $message }}</p>
@enderror

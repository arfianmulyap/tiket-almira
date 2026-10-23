{{--
    Shared flash + validation summary region. Included near the top of the
    content area in both layouts so every page reports feedback consistently.
--}}

@if (session('status'))
    <x-alert variant="success" class="u-mb-4">{{ session('status') }}</x-alert>
@endif

@if (session('success'))
    <x-alert variant="success" class="u-mb-4">{{ session('success') }}</x-alert>
@endif

@if (session('warning'))
    <x-alert variant="warning" class="u-mb-4">{{ session('warning') }}</x-alert>
@endif

@if (session('error'))
    <x-alert variant="error" class="u-mb-4">{{ session('error') }}</x-alert>
@endif

{{--
    Error summary for forms with several fields. Individual messages still
    render beside their control via <x-field-error>; this block is skipped
    when the only errors belong to a single field.
--}}
@if ($errors->any() && $errors->count() > 1)
    <x-alert variant="error" title="Periksa kembali data berikut:" class="u-mb-4">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </x-alert>
@endif

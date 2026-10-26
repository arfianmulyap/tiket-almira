@extends('layouts.admin')

@section('title', 'Ubah Permission')

@section('content')
    <x-page-header
        title="Ubah Permission"
        description="Ubah teks yang tampil pada matriks permission role."
    />

    <x-card>
        <div class="field">
            <x-label for="permission-name">Kunci Permission</x-label>
            <p id="permission-name"><code>{{ $permission->name }}</code></p>
            <p class="field-hint">
                Kunci tidak dapat diubah karena menjadi acuan pemeriksaan di backend.
            </p>
        </div>

        <form method="POST" action="{{ route('admin.permissions.update', $permission) }}" novalidate>
            @csrf
            @method('PUT')

            <div class="field">
                <x-label for="label" required>Label</x-label>
                <x-input
                    name="label"
                    :value="old('label', $permission->label)"
                    required
                    maxlength="120"
                    autocomplete="off"
                />
                <x-field-error name="label" />
            </div>

            <div class="field">
                <x-label for="description">Deskripsi</x-label>
                <x-textarea
                    name="description"
                    :value="old('description', $permission->description)"
                    rows="3"
                    maxlength="255"
                />
                <x-field-error name="description" />
            </div>

            <div class="cluster u-mt-6">
                <x-button>Simpan Perubahan</x-button>
                <x-button variant="secondary" :href="route('admin.permissions.index')">
                    Batal
                </x-button>
            </div>
        </form>
    </x-card>
@endsection

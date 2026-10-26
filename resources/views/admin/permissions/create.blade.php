@extends('layouts.admin')

@section('title', 'Pulihkan Permission')

@section('content')
    <x-page-header
        title="Pulihkan Permission"
        description="Daftarkan kembali permission yang definisinya sudah dihapus."
    />

    <x-card>
        @if (empty($available))
            <x-empty-state
                title="Semua permission sudah terdaftar"
                description="Katalog permission dari kode sudah lengkap di database."
            >
                <x-slot:actions>
                    <x-button variant="secondary" :href="route('admin.permissions.index')">
                        Kembali
                    </x-button>
                </x-slot:actions>
            </x-empty-state>
        @else
            <form method="POST" action="{{ route('admin.permissions.store') }}" novalidate>
                @csrf

                <div class="field">
                    <x-label for="name" required>Permission</x-label>
                    <x-select name="name" required>
                        <option value="">Pilih permission</option>
                        @foreach ($available as $permission)
                            <option
                                value="{{ $permission->value }}"
                                @selected(old('name') === $permission->value)
                            >
                                {{ $permission->group() }} — {{ $permission->label() }} ({{ $permission->value }})
                            </option>
                        @endforeach
                    </x-select>
                    <p class="field-hint">
                        Label, grup, dan deskripsi diambil dari katalog kode dan
                        dapat diubah setelah permission dipulihkan.
                    </p>
                    <x-field-error name="name" />
                </div>

                <div class="cluster u-mt-6">
                    <x-button>Pulihkan Permission</x-button>
                    <x-button variant="secondary" :href="route('admin.permissions.index')">
                        Batal
                    </x-button>
                </div>
            </form>
        @endif
    </x-card>
@endsection

@extends('layouts.admin')

@section('title', 'Ubah Role')

@section('content')
    <x-page-header
        title="Ubah Role"
        :description="'Perbarui nama dan permission untuk role ' . $role->name . '.'"
    />

    <x-card>
        <form method="POST" action="{{ route('admin.roles.update', $role) }}" novalidate>
            @csrf
            @method('PUT')

            @include('admin.roles._form', ['role' => $role, 'selected' => $selected])

            <div class="cluster u-mt-6">
                <x-button>Simpan Perubahan</x-button>
                <x-button variant="secondary" :href="route('admin.roles.index')">
                    Batal
                </x-button>
            </div>
        </form>
    </x-card>

    <x-card title="Hapus Role" class="u-mt-6">
        <p class="text-sm text-muted u-mb-4">
            Role yang masih dipakai akun admin tidak dapat dihapus. Lepaskan role
            tersebut dari akunnya terlebih dahulu.
        </p>

        @can('delete', $role)
            <form
                method="POST"
                action="{{ route('admin.roles.destroy', $role) }}"
                onsubmit="return confirm('Hapus role ini? Tindakan ini tidak dapat dibatalkan.')"
            >
                @csrf
                @method('DELETE')

                <x-button variant="danger">Hapus Role</x-button>
            </form>
        @endcan
    </x-card>
@endsection

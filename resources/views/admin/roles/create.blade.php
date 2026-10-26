@extends('layouts.admin')

@section('title', 'Buat Role')

@section('content')
    <x-page-header
        title="Buat Role"
        description="Tentukan nama role dan tindakan yang boleh dilakukan."
    />

    <x-card>
        <form method="POST" action="{{ route('admin.roles.store') }}" novalidate>
            @csrf

            @include('admin.roles._form', ['role' => $role, 'selected' => $selected])

            <div class="cluster u-mt-6">
                <x-button>Simpan Role</x-button>
                <x-button variant="secondary" :href="route('admin.roles.index')">
                    Batal
                </x-button>
            </div>
        </form>
    </x-card>
@endsection

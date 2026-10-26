@extends('layouts.admin')

@section('title', 'Undang Admin')

@section('content')
    <x-page-header
        title="Undang Admin"
        description="Akun dibuat dalam status menunggu undangan; password ditentukan sendiri oleh penerima."
    />

    <x-card>
        <form method="POST" action="{{ route('admin.accounts.store') }}" novalidate>
            @csrf

            <div class="field">
                <x-label for="name" required>Nama</x-label>
                <x-input
                    name="name"
                    :value="old('name')"
                    required
                    maxlength="120"
                    autocomplete="off"
                />
                <x-field-error name="name" />
            </div>

            <div class="field">
                <x-label for="email" required>Email</x-label>
                <x-input
                    name="email"
                    type="email"
                    :value="old('email')"
                    required
                    maxlength="160"
                    autocomplete="off"
                />
                <p class="field-hint">Tautan undangan dikirim ke alamat ini.</p>
                <x-field-error name="email" />
            </div>

            <div class="field">
                <x-label for="role_id" required>Role</x-label>

                {{-- Only roles the acting admin may hand out are listed; the
                     controller re-checks the protected role on submit. --}}
                <x-select name="role_id" required>
                    <option value="">Pilih role</option>
                    @foreach ($roles as $role)
                        <option
                            value="{{ $role->id }}"
                            @selected((string) old('role_id') === (string) $role->id)
                        >{{ $role->name }}{{ $role->isProtected() ? ' — akses penuh' : '' }}</option>
                    @endforeach
                </x-select>

                <p class="field-hint">Role menentukan menu dan tindakan yang boleh dilakukan akun ini.</p>
                <x-field-error name="role_id" />
            </div>

            <x-alert variant="info" class="u-mb-6">
                Undangan berlaku {{ $lifetimeHours }} jam dan hanya dapat dipakai satu
                kali. Sebelum password dibuat, akun ini belum dapat masuk ke panel.
            </x-alert>

            <div class="cluster">
                <x-button>Kirim Undangan</x-button>
                <x-button variant="secondary" :href="route('admin.accounts.index')">Batal</x-button>
            </div>
        </form>
    </x-card>
@endsection

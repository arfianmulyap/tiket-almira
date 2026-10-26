@extends('layouts.admin')

@section('title', 'Profil Saya')

@section('content')
    <x-page-header
        title="Profil Saya"
        description="Perbarui nama dan password akun yang sedang Anda gunakan."
    />

    <div class="grid grid-cols-2">
        <x-card title="Data Akun">
            <p class="text-sm u-mb-4">
                <strong>Email:</strong> {{ $user->email }}<br>
                <strong>Role:</strong> {{ $user->role?->name ?? 'Tanpa role' }}<br>
                <strong>Status:</strong> {{ $user->status->label() }}
            </p>

            <p class="field-hint u-mb-6">
                Email dan role tidak dapat diubah dari halaman ini. Hubungi Super
                Admin bila perlu mengubahnya.
            </p>

            <form method="POST" action="{{ route('admin.profile.update') }}" novalidate>
                @csrf
                @method('PUT')

                <div class="field">
                    <x-label for="name" required>Nama</x-label>
                    <x-input
                        name="name"
                        :value="old('name', $user->name)"
                        required
                        maxlength="120"
                        autocomplete="name"
                    />
                    <p class="field-hint">Nama ini tampil pada catatan audit tindakan Anda.</p>
                    <x-field-error name="name" />
                </div>

                <x-button>Simpan Nama</x-button>
            </form>
        </x-card>

        <x-card title="Ubah Password">
            <p class="field-hint u-mb-6">
                Password saat ini diperlukan sebagai konfirmasi. Setelah disimpan,
                sesi Anda di perangkat lain akan diakhiri.
            </p>

            <form method="POST" action="{{ route('admin.profile.password') }}" novalidate>
                @csrf
                @method('PUT')

                <div class="field">
                    <x-label for="current_password" required>Password Saat Ini</x-label>
                    <x-input
                        name="current_password"
                        type="password"
                        required
                        autocomplete="current-password"
                    />
                    <x-field-error name="current_password" />
                </div>

                <div class="field">
                    <x-label for="password" required>Password Baru</x-label>
                    <x-input
                        name="password"
                        type="password"
                        required
                        minlength="8"
                        autocomplete="new-password"
                    />
                    <p class="field-hint">Minimal 8 karakter.</p>
                    <x-field-error name="password" />
                </div>

                <div class="field">
                    <x-label for="password_confirmation" required>Ulangi Password Baru</x-label>
                    <x-input
                        name="password_confirmation"
                        type="password"
                        required
                        minlength="8"
                        autocomplete="new-password"
                    />
                    <x-field-error name="password_confirmation" />
                </div>

                <x-button>Simpan Password</x-button>
            </form>
        </x-card>
    </div>
@endsection

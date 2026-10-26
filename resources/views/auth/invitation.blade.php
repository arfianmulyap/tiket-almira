@extends('layouts.app')

@section('title', 'Aktivasi Akun Admin')

@section('container-class', 'container-narrow')

@section('content')
    @if ($invitation === null)
        {{-- One message for unknown, used, revoked, and expired links, so a
             wrong token cannot be used to probe which accounts exist. --}}
        <x-card elevated>
            <div class="card-header">
                <h1 class="page-title">Undangan Tidak Berlaku</h1>
                <p class="page-description">
                    Tautan undangan ini tidak valid, sudah dipakai, atau sudah
                    kedaluwarsa.
                </p>
            </div>

            <x-alert variant="error">
                Undangan hanya berlaku 24 jam dan hanya dapat dipakai satu kali.
                Minta Super Admin mengirim ulang undangan dari menu Akun Admin,
                lalu buka tautan terbaru di email Anda.
            </x-alert>

            <div class="cluster u-mt-6">
                <x-button variant="secondary" :href="route('login')">Ke Halaman Login</x-button>
            </div>
        </x-card>
    @else
        <x-card elevated>
            <div class="card-header">
                <h1 class="page-title">Aktivasi Akun Admin</h1>
                <p class="page-description">
                    Halo {{ $invitation->user->name }}, buat password untuk akun
                    {{ $invitation->email }}.
                </p>
            </div>

            <p class="text-sm text-muted u-mb-6">
                Role: <strong>{{ $invitation->role?->name ?? 'Belum ditentukan' }}</strong><br>
                Tautan berlaku sampai
                <strong>{{ $invitation->expires_at->translatedFormat('d M Y, H:i') }} WIB</strong>.
            </p>

            <form method="POST" action="{{ route('invitations.accept.store', $token) }}" novalidate>
                @csrf

                <div class="field">
                    <x-label for="password" required>Password Baru</x-label>
                    <x-input
                        name="password"
                        type="password"
                        required
                        minlength="8"
                        autocomplete="new-password"
                        autofocus
                    />
                    <p class="field-hint">Minimal 8 karakter.</p>
                    <x-field-error name="password" />
                </div>

                <div class="field">
                    <x-label for="password_confirmation" required>Ulangi Password</x-label>
                    <x-input
                        name="password_confirmation"
                        type="password"
                        required
                        minlength="8"
                        autocomplete="new-password"
                    />
                    <x-field-error name="password_confirmation" />
                </div>

                <x-button block>Simpan Password dan Aktifkan Akun</x-button>
            </form>
        </x-card>
    @endif
@endsection

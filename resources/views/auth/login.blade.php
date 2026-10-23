@extends('layouts.app')

@section('title', 'Login Admin')

@section('container-class', 'container-narrow')

@section('content')
    <x-card elevated>
        {{-- Heading is an h1 here: the login card *is* the page. --}}
        <div class="card-header">
            <h1 class="page-title">Login Admin</h1>
            <p class="page-description">
                Masuk untuk mengelola event, pesanan, dan pembayaran.
            </p>
        </div>

        {{--
            Authentication failures are thrown against the `email` key by
            LoginRequest, so the single-error case is rendered beside the
            field. partials.flash covers the multi-error summary.
        --}}
        <form method="POST" action="{{ route('login.store') }}" novalidate>
            @csrf

            <div class="field">
                <x-label for="email" required>Email</x-label>
                <x-input
                    name="email"
                    type="email"
                    :value="old('email')"
                    required
                    autofocus
                    autocomplete="username"
                />
                <x-field-error name="email" />
            </div>

            <div class="field">
                <x-label for="password" required>Password</x-label>
                <x-input
                    name="password"
                    type="password"
                    required
                    autocomplete="current-password"
                />
                <x-field-error name="password" />
            </div>

            <label class="choice u-mb-6">
                <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                <span>Ingat saya di perangkat ini</span>
            </label>

            <x-button block>Login</x-button>
        </form>
    </x-card>
@endsection

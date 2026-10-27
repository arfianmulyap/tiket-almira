<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body>
    <a class="skip-link" href="#main-content">Lewati ke konten utama</a>

    <header class="public-header">
        <div class="container public-header-inner">
            <a class="public-brand" href="{{ route('home') }}">{{ config('app.name') }}</a>

            <div class="cluster">
                @auth
                    {{-- Only shown to an admin whose role can open the
                         dashboard, so the link never leads to a 403. --}}
                    @can('dashboard.view')
                        <x-button variant="ghost-inverse" size="sm" :href="route('admin.dashboard')">
                            Dashboard
                        </x-button>
                    @endcan

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-button variant="ghost-inverse" size="sm">Logout</x-button>
                    </form>
                @endauth
            </div>
        </div>
    </header>

    <main class="public-main" id="main-content">
        <div class="container @yield('container-class')">
            @include('partials.flash')

            @yield('content')
        </div>
    </main>

    <footer class="public-footer">
        <div class="container">
            &copy; {{ now()->year }} {{ config('app.name') }}
        </div>
    </footer>
</body>
</html>

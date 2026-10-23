<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body>
    <a class="skip-link" href="#main-content">Lewati ke konten utama</a>

    <div class="app-shell">
        {{-- Backdrop is rendered by the drawer script only when it is open. --}}
        <aside class="app-sidebar" id="app-sidebar" data-open="false" aria-label="Navigasi admin">
            <a class="app-sidebar-brand" href="{{ route('admin.dashboard') }}">
                {{ config('app.name') }}
            </a>

            <p class="app-sidebar-section">Operasional</p>

            <ul class="app-nav">
                <li>
                    <a
                        class="app-nav-link"
                        href="{{ route('admin.dashboard') }}"
                        @if (request()->routeIs('admin.dashboard')) aria-current="page" @endif
                    >
                        Dashboard
                    </a>
                </li>
                <li>
                    <a
                        class="app-nav-link"
                        href="{{ route('admin.events.index') }}"
                        @if (request()->routeIs('admin.events.*')) aria-current="page" @endif
                    >
                        Event
                    </a>
                </li>
                <li>
                    <a
                        class="app-nav-link"
                        href="{{ route('admin.orders.index') }}"
                        @if (request()->routeIs('admin.orders.*')) aria-current="page" @endif
                    >
                        Pesanan
                    </a>
                </li>
                <li>
                    <a
                        class="app-nav-link"
                        href="{{ route('admin.check-in.show') }}"
                        @if (request()->routeIs('admin.check-in.*')) aria-current="page" @endif
                    >
                        Check-in
                    </a>
                </li>
            </ul>

            <div class="app-sidebar-footer">
                <p class="app-sidebar-footer-label">Masuk sebagai</p>
                <p class="app-sidebar-footer-name">{{ auth()->user()->name }}</p>
            </div>
        </aside>

        <div class="app-main">
            <header class="app-topbar">
                <div class="app-topbar-start">
                    <button
                        type="button"
                        class="app-sidebar-toggle"
                        id="app-sidebar-toggle"
                        aria-controls="app-sidebar"
                        aria-expanded="false"
                    >
                        <span class="u-visually-hidden">Buka navigasi</span>
                        <span aria-hidden="true">&#9776;</span>
                    </button>

                    <span class="text-sm text-muted">@yield('title', 'Dashboard')</span>
                </div>

                <div class="app-topbar-end">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-button variant="secondary" size="sm">Logout</x-button>
                    </form>
                </div>
            </header>

            <main class="app-content" id="main-content">
                @include('partials.flash')

                @yield('content')
            </main>
        </div>
    </div>

    <script>
        // Progressive enhancement: the sidebar is a drawer below 1024px.
        // Without JS the layout still renders and, at desktop widths, the
        // sidebar is permanently visible via CSS.
        (function () {
            var toggle = document.getElementById('app-sidebar-toggle');
            var sidebar = document.getElementById('app-sidebar');

            if (!toggle || !sidebar) {
                return;
            }

            var backdrop = null;

            function close() {
                sidebar.setAttribute('data-open', 'false');
                toggle.setAttribute('aria-expanded', 'false');

                if (backdrop) {
                    backdrop.remove();
                    backdrop = null;
                }

                toggle.focus();
            }

            function open() {
                sidebar.setAttribute('data-open', 'true');
                toggle.setAttribute('aria-expanded', 'true');

                backdrop = document.createElement('button');
                backdrop.type = 'button';
                backdrop.className = 'app-sidebar-backdrop';
                backdrop.setAttribute('aria-label', 'Tutup navigasi');
                backdrop.addEventListener('click', close);
                document.body.appendChild(backdrop);
            }

            toggle.addEventListener('click', function () {
                sidebar.getAttribute('data-open') === 'true' ? close() : open();
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && sidebar.getAttribute('data-open') === 'true') {
                    close();
                }
            });
        })();
    </script>
</body>
</html>

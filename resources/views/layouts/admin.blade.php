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

            {{-- Menu entries follow the permissions of the signed-in admin's
                 role, so a role without a permission never sees a screen it
                 would be refused. The routes and policies enforce the same
                 rules independently of this menu (Phase 10). --}}
            <p class="app-sidebar-section">Operasional</p>

            <ul class="app-nav">
                @can('dashboard.view')
                    <li>
                        <a
                            class="app-nav-link"
                            href="{{ route('admin.dashboard') }}"
                            @if (request()->routeIs('admin.dashboard')) aria-current="page" @endif
                        >
                            Dashboard
                        </a>
                    </li>
                @endcan

                @can('events.view')
                    <li>
                        <a
                            class="app-nav-link"
                            href="{{ route('admin.events.index') }}"
                            @if (request()->routeIs('admin.events.*')) aria-current="page" @endif
                        >
                            Event
                        </a>
                    </li>
                @endcan

                @can('orders.view')
                    <li>
                        <a
                            class="app-nav-link"
                            href="{{ route('admin.orders.index') }}"
                            @if (request()->routeIs('admin.orders.*')) aria-current="page" @endif
                        >
                            Pesanan
                        </a>
                    </li>
                @endcan

                @can('check-in.perform')
                    <li>
                        <a
                            class="app-nav-link"
                            href="{{ route('admin.check-in.show') }}"
                            @if (request()->routeIs('admin.check-in.*')) aria-current="page" @endif
                        >
                            Check-in
                        </a>
                    </li>
                @endcan
            </ul>

            {{-- Payment settings (Phase 12, extended in Phase 12B): viewing follows
                 payment_methods.view; creating, editing details, toggling, and
                 deleting follow their own permissions, and publishing
                 unconfirmed details as official is a Super Admin decision
                 enforced on the edit screen. --}}
            @can('payment_methods.view')
                <p class="app-sidebar-section">Pengaturan</p>

                <ul class="app-nav">
                    <li>
                        <a
                            class="app-nav-link"
                            href="{{ route('admin.payment-methods.index') }}"
                            @if (request()->routeIs('admin.payment-methods.*')) aria-current="page" @endif
                        >
                            Metode Pembayaran
                        </a>
                    </li>
                </ul>
            @endcan

            {{-- Audit trail (Phase 12). Super Admin only, deliberately not a
                 grantable permission: it exposes every access and
                 configuration change in the panel. --}}
            @if (auth()->user()->isSuperAdmin())
                <p class="app-sidebar-section">Keamanan</p>

                <ul class="app-nav">
                    <li>
                        <a
                            class="app-nav-link"
                            href="{{ route('admin.audit-logs.index') }}"
                            @if (request()->routeIs('admin.audit-logs.*')) aria-current="page" @endif
                        >
                            Audit Log
                        </a>
                    </li>
                </ul>
            @endif

            @canany(['roles.view', 'permissions.manage', 'admin-accounts.view'])
                <p class="app-sidebar-section">Hak Akses</p>

                <ul class="app-nav">
                    @can('roles.view')
                        <li>
                            <a
                                class="app-nav-link"
                                href="{{ route('admin.roles.index') }}"
                                @if (request()->routeIs('admin.roles.*')) aria-current="page" @endif
                            >
                                Role &amp; Permission
                            </a>
                        </li>
                    @endcan

                    @can('permissions.manage')
                        <li>
                            <a
                                class="app-nav-link"
                                href="{{ route('admin.permissions.index') }}"
                                @if (request()->routeIs('admin.permissions.*')) aria-current="page" @endif
                            >
                                Permission
                            </a>
                        </li>
                    @endcan

                    {{-- Account list and invitations (Phase 11). Distinct from
                         role management: handling people is not the same
                         permission as granting access. --}}
                    @can('admin-accounts.view')
                        <li>
                            <a
                                class="app-nav-link"
                                href="{{ route('admin.accounts.index') }}"
                                @if (request()->routeIs('admin.accounts.*')) aria-current="page" @endif
                            >
                                Akun Admin
                            </a>
                        </li>
                    @endcan
                </ul>
            @endcanany

            <div class="app-sidebar-footer">
                <p class="app-sidebar-footer-label">Masuk sebagai</p>
                <p class="app-sidebar-footer-name">{{ auth()->user()->name }}</p>
                <p class="text-xs text-muted">{{ auth()->user()->role?->name }}</p>
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
                    {{-- Self-service profile: available to every admin, so it is
                         not permission-gated (Phase 11). --}}
                    <x-button variant="secondary" size="sm" :href="route('admin.profile.edit')">
                        Profil
                    </x-button>

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

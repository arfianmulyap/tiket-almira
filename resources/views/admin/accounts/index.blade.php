@extends('layouts.admin')

@section('title', 'Akun Admin')

@section('content')
    <x-page-header
        title="Akun Admin"
        description="Undang admin baru, pantau status akun, dan cabut akses tanpa menghapus data."
    >
        <x-slot:actions>
            @can('admin-accounts.manage')
                <x-button :href="route('admin.accounts.create')">Undang Admin</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-card>
        @if ($accounts->isEmpty())
            <x-empty-state
                title="Belum ada akun admin"
                description="Undang admin pertama untuk membantu operasional event."
            />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">Admin</th>
                            <th scope="col">Role</th>
                            <th scope="col">Status</th>
                            <th scope="col">Undangan</th>
                            <th scope="col"><span class="u-visually-hidden">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($accounts as $account)
                            @php
                                $pending = $account->invitations->first();
                                $isSelf = auth()->id() === $account->id;
                            @endphp
                            <tr>
                                <td>
                                    <p>
                                        {{ $account->name }}
                                        @if ($isSelf)
                                            <span class="text-xs text-muted">(Anda)</span>
                                        @endif
                                    </p>
                                    <p class="text-xs text-muted">{{ $account->email }}</p>
                                </td>
                                <td>
                                    <p>{{ $account->role?->name ?? 'Tanpa role' }}</p>
                                    @if ($account->isSuperAdmin())
                                        <x-badge variant="info">Super Admin</x-badge>
                                    @endif
                                </td>
                                <td>
                                    <x-badge :variant="$account->status->badgeVariant()">
                                        {{ $account->status->label() }}
                                    </x-badge>
                                    @if ($account->deactivated_at)
                                        <p class="text-xs text-muted">
                                            Sejak {{ $account->deactivated_at->translatedFormat('d M Y, H:i') }}
                                        </p>
                                    @endif
                                </td>
                                <td>
                                    @if ($pending)
                                        <p class="text-xs">
                                            Berlaku sampai {{ $pending->expires_at->translatedFormat('d M Y, H:i') }}
                                        </p>
                                        @if ($pending->expires_at->isPast())
                                            <p class="text-xs text-muted">Sudah kedaluwarsa</p>
                                        @endif

                                        {{--
                                            Phase 13: whether the invitation email
                                            actually left the server. A failed
                                            send is the usual reason a new admin
                                            never received a link, so it belongs
                                            here next to the resend button.
                                        --}}
                                        @php
                                            $invitationDelivery = $pending->emailDeliveries->first();
                                        @endphp

                                        @if ($invitationDelivery)
                                            <p class="u-mt-4">
                                                <x-badge :variant="$invitationDelivery->status->badgeVariant()">
                                                    {{ $invitationDelivery->status->label() }}
                                                </x-badge>
                                            </p>
                                            <p class="text-xs text-muted">
                                                {{ $invitationDelivery->created_at->translatedFormat('d M Y, H:i') }} WIB
                                            </p>
                                        @endif
                                    @else
                                        <span class="text-xs text-muted">&mdash;</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="cluster">
                                        @if ($account->isInvited())
                                            @can('admin-accounts.manage')
                                                <form method="POST" action="{{ route('admin.accounts.invitation.resend', $account) }}">
                                                    @csrf
                                                    <x-button variant="secondary" size="sm">Kirim ulang undangan</x-button>
                                                </form>
                                            @else
                                                <span class="text-xs text-muted">Menunggu undangan</span>
                                            @endcan
                                        @elseif ($account->isActive())
                                            @if ($isSelf)
                                                <span class="text-xs text-muted">Akun Anda</span>
                                            @else
                                                @can('admin-accounts.manage')
                                                    <form
                                                        method="POST"
                                                        action="{{ route('admin.accounts.deactivate', $account) }}"
                                                        onsubmit="return confirm('Nonaktifkan akun ini? Sesi yang sedang aktif akan langsung dihentikan.')"
                                                    >
                                                        @csrf
                                                        <x-button variant="danger" size="sm">Nonaktifkan</x-button>
                                                    </form>
                                                @endcan
                                            @endif
                                        @else
                                            @can('admin-accounts.manage')
                                                <form method="POST" action="{{ route('admin.accounts.activate', $account) }}">
                                                    @csrf
                                                    <x-button variant="secondary" size="sm">Aktifkan</x-button>
                                                </form>
                                            @endcan
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="text-sm text-muted u-mt-4">
                Undangan berlaku 24 jam dan hanya dapat dipakai satu kali. Akun baru
                dapat masuk setelah pemiliknya membuat password sendiri.
            </p>
        @endif
    </x-card>
@endsection


@extends('layouts.admin')

@section('title', 'Metode Pembayaran')

@section('content')
    <x-page-header
        title="Metode Pembayaran"
        description="Atur metode pembayaran manual yang ditawarkan kepada peserta."
    >
        <x-slot:actions>
            @can('create', \App\Models\PaymentMethod::class)
                <x-button :href="route('admin.payment-methods.create')">
                    Tambah Metode
                </x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-alert variant="info" class="u-mb-6">
        Detail metode bersifat <strong>global</strong> dan dipakai semua event.
        Perubahan hanya berlaku untuk <strong>pesanan baru</strong>: setiap pesanan
        menyimpan salinan instruksi pembayaran saat pesanan dibuat. Status
        aktif/nonaktif dapat diatur per event pada layar aktivasi event.
    </x-alert>

    @if ($events->isNotEmpty())
        <x-card
            title="Status per event"
            description="Pilih satu event untuk melihat metode mana yang ditawarkan kepadanya. Pengaturan aktif/nonaktif per event dilakukan pada layar event tersebut."
            class="u-mb-6"
        >
            <form method="GET" action="{{ route('admin.payment-methods.index') }}" class="cluster">
                <div class="field">
                    <x-label for="event">Event</x-label>

                    <x-select name="event" id="event">
                        <option value="">Tanpa event (katalog global)</option>

                        @foreach ($events as $event)
                            <option value="{{ $event->id }}" @selected($selectedEvent?->getKey() === $event->getKey())>
                                {{ $event->name }}
                            </option>
                        @endforeach
                    </x-select>
                </div>

                <x-button variant="secondary">Tampilkan</x-button>

                @if ($selectedEvent)
                    <x-button
                        variant="secondary"
                        :href="route('admin.events.payment-methods', $selectedEvent)"
                    >
                        Kelola Aktivasi Event
                    </x-button>
                @endif
            </form>
        </x-card>
    @endif

    <x-card>
        @if ($methods->isEmpty())
            <x-empty-state
                title="Belum ada metode pembayaran"
                description="Tambahkan metode pembayaran pertama. Bila katalog dari config/payments.php belum dimuat, jalankan php artisan db:seed --class=PaymentMethodSeeder --force."
            >
                <x-slot:actions>
                    @can('create', \App\Models\PaymentMethod::class)
                        <x-button :href="route('admin.payment-methods.create')">
                            Tambah Metode
                        </x-button>
                    @endcan
                </x-slot:actions>
            </x-empty-state>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">Metode</th>
                            <th scope="col">Tipe</th>
                            <th scope="col">Konfigurasi</th>
                            <th scope="col">Status global</th>

                            @if ($selectedEvent)
                                <th scope="col">Status untuk {{ $selectedEvent->name }}</th>
                            @endif

                            <th scope="col"><span class="u-visually-hidden">Aksi</span></th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($methods as $method)
                            <tr>
                                <td>
                                    <p>{{ $method->label }}</p>
                                    <p class="text-xs text-muted"><code>{{ $method->key }}</code></p>

                                    @if ($method->providerValue())
                                        <p class="text-xs text-muted">
                                            Penyedia: {{ $method->providerValue() }}
                                        </p>
                                    @endif

                                    <p class="text-xs text-muted">
                                        @if ($method->updated_at)
                                            Diubah {{ $method->updated_at->translatedFormat('d M Y, H:i') }} WIB
                                            @if ($method->updatedBy)
                                                oleh {{ $method->updatedBy->name }}
                                            @endif
                                        @else
                                            Belum diubah
                                        @endif
                                    </p>
                                </td>

                                <td>
                                    <p class="text-sm">{{ $method->type->label() }}</p>
                                    <p class="text-xs text-muted">{{ $method->type->description() }}</p>
                                </td>

                                <td>
                                    @if ($method->isConfigured())
                                        <x-badge variant="success">Lengkap</x-badge>
                                    @else
                                        <x-badge variant="warning">Belum lengkap</x-badge>
                                        <p class="text-xs text-muted">
                                            Kurang: {{ implode(', ', $method->missingDetails()) }}
                                        </p>
                                    @endif

                                    @if ($method->is_dummy)
                                        <p class="u-mt-4"><x-badge variant="warning">Data contoh</x-badge></p>
                                        <p class="text-xs text-muted">
                                            Tidak ditampilkan di production sampai ditandai
                                            sebagai data resmi.
                                        </p>
                                    @else
                                        <p class="u-mt-4"><x-badge variant="success">Data resmi</x-badge></p>
                                    @endif
                                </td>
<!-- REST -->
                                <td>
                                    @if ($method->is_active)
                                        <x-badge variant="success">Aktif</x-badge>
                                    @else
                                        <x-badge>Nonaktif</x-badge>
                                        <p class="text-xs text-muted">
                                            Tidak ditawarkan ke pesanan baru, termasuk di
                                            event yang mengikuti pengaturan global.
                                        </p>
                                    @endif
                                </td>

                                @if ($selectedEvent)
                                    @php
                                        $override = $eventOverrides[$method->getKey()] ?? null;
                                    @endphp

                                    <td>
                                        @if ($override === true)
                                            <x-badge variant="success">Diaktifkan khusus</x-badge>
                                        @elseif ($override === false)
                                            <x-badge variant="danger">Dinonaktifkan khusus</x-badge>
                                        @else
                                            <x-badge>Mengikuti global</x-badge>
                                        @endif

                                        <p class="text-xs text-muted">
                                            Tanpa pengaturan khusus, event ini memakai status
                                            global ({{ $method->is_active ? 'aktif' : 'nonaktif' }}).
                                        </p>
                                    </td>
                                @endif

                                <td>
                                    <div class="cluster">
                                        @can('updateDetails', $method)
                                            <x-button
                                                variant="secondary"
                                                size="sm"
                                                :href="route('admin.payment-methods.edit', $method)"
                                            >
                                                Ubah Detail
                                            </x-button>
                                        @endcan

                                        @can('toggle', $method)
                                            <form
                                                method="POST"
                                                action="{{ route('admin.payment-methods.toggle', $method) }}"
                                                @unless ($method->is_active)
                                                    onsubmit="return confirm('Aktifkan metode {{ $method->label }} untuk semua event yang mengikuti pengaturan global?')"
                                                @endunless
                                            >
                                                @csrf
                                                <input
                                                    type="hidden"
                                                    name="is_active"
                                                    value="{{ $method->is_active ? 0 : 1 }}"
                                                >

                                                <x-button
                                                    size="sm"
                                                    :variant="$method->is_active ? 'secondary' : 'primary'"
                                                >
                                                    {{ $method->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                                </x-button>
                                            </form>
                                        @endcan

                                        @if ($selectedEvent)
                                            <x-button
                                                size="sm"
                                                variant="ghost"
                                                :href="route('admin.events.payment-methods', $selectedEvent)"
                                            >
                                                Atur per Event
                                            </x-button>
                                        @endif

                                        @cannot('toggle', $method)
                                            @cannot('updateDetails', $method)
                                                @cannot('delete', $method)
                                                    <span class="text-xs text-muted">Hanya lihat</span>
                                                @endcannot
                                            @endcannot
                                        @endcannot
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="text-sm text-muted u-mt-4">
                Peserta hanya ditawari metode yang <strong>aktif</strong>,
                <strong>lengkap</strong> detailnya, dan <strong>tidak dimatikan</strong>
                untuk event yang dipesan. Mengubah detail rekening, VA, atau QRIS
                tidak mengubah instruksi pada pesanan yang sudah ada.
            </p>
        @endif
    </x-card>
@endsection
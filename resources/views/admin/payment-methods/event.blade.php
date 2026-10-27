@extends('layouts.admin')

@section('title', 'Metode Pembayaran Event')

@section('content')
    <x-page-header
        title="Metode Pembayaran"
        :description="$event->name"
    >
        <x-slot:actions>
            <x-button variant="secondary" :href="route('admin.payment-methods.index', ['event' => $event->id])">
                Katalog Metode
            </x-button>

            <x-button variant="secondary" :href="route('admin.events.show', $event)">
                Detail Event
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <x-alert variant="info" class="u-mb-6">
        Pengaturan di halaman ini hanya berlaku untuk <strong>{{ $event->name }}</strong>.
        Metode yang dinonaktifkan di sini tetap tersedia untuk event lain. Detail
        rekening, VA, dan QRIS bersifat global dan diubah pada katalog metode.
        Pesanan yang sudah dibuat tetap memakai instruksi yang sudah diberikan.
    </x-alert>

    <x-card
        title="Metode yang ditawarkan"
        description="Yang benar-benar dilihat peserta event ini: aktif secara global, detailnya lengkap, dan tidak dinonaktifkan untuk event ini."
        class="u-mb-6"
    >
        @if ($offerableKeys === [])
            <x-empty-state
                title="Belum ada metode yang ditawarkan"
                description="Peserta belum dapat memilih metode pembayaran untuk event ini. Periksa status global, kelengkapan detail, dan pengaturan khusus event ini pada tabel di bawah."
            />
        @else
            <ul class="stack">
                @foreach ($methods as $method)
                    @if (in_array($method->key, $offerableKeys, true))
                        <li class="text-sm">
                            <x-badge variant="success">Ditawarkan</x-badge>
                            {{ $method->label }}
                            <span class="text-xs text-muted">({{ $method->type->label() }})</span>
                        </li>
                    @endif
                @endforeach
            </ul>
        @endif
    </x-card>

    <x-card>
        @if ($methods->isEmpty())
            <x-empty-state
                title="Belum ada metode pembayaran"
                description="Tambahkan metode pada katalog metode pembayaran terlebih dahulu."
            >
                <x-slot:actions>
                    <x-button :href="route('admin.payment-methods.index')">Buka Katalog Metode</x-button>
                </x-slot:actions>
            </x-empty-state>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">Metode</th>
                            <th scope="col">Status global</th>
                            <th scope="col">Status event ini</th>
                            <th scope="col">Ditawarkan</th>
                            <th scope="col"><span class="u-visually-hidden">Aksi</span></th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($methods as $method)
                            @php
                                $override = $overrides[$method->getKey()] ?? null;
                                $offered = in_array($method->key, $offerableKeys, true);
                            @endphp
                            <tr>
                                <td>
                                    <p>{{ $method->label }}</p>
                                    <p class="text-xs text-muted"><code>{{ $method->key }}</code></p>
                                    <p class="text-xs text-muted">{{ $method->type->label() }}</p>
                                </td>

                                <td>
                                    @if ($method->is_active)
                                        <x-badge variant="success">Aktif</x-badge>
                                    @else
                                        <x-badge>Nonaktif</x-badge>
                                    @endif

                                    @unless ($method->isConfigured())
                                        <p class="text-xs text-muted">
                                            Belum lengkap: {{ implode(', ', $method->missingDetails()) }}
                                        </p>
                                    @endunless
                                </td>

                                <td>
                                    @if ($override === true)
                                        <x-badge variant="success">Diaktifkan khusus</x-badge>
                                    @elseif ($override === false)
                                        <x-badge variant="danger">Dinonaktifkan khusus</x-badge>
                                    @else
                                        <x-badge>Mengikuti global</x-badge>
                                    @endif
                                </td>

                                <td>
                                    @if ($offered)
                                        <x-badge variant="success">Ya</x-badge>
                                    @else
                                        <x-badge variant="warning">Tidak</x-badge>
                                    @endif
                                </td>

                                <td><!-- EVENT ACTIONS -->
                                    @can('toggle', $method)
                                        @unless ($method->is_active && $method->isConfigured())
                                            {{-- Turning the method on for this event while
                                                 it is globally off, or incomplete, would
                                                 look like a broken switch. Saying why is
                                                 cheaper than a support question: the
                                                 override is saved either way, it just
                                                 cannot reach a participant yet. --}}
                                            <p class="text-xs text-muted u-mb-4">
                                                @unless ($method->is_active)
                                                    Status global masih nonaktif.
                                                @endunless
                                                @unless ($method->isConfigured())
                                                    Detail belum lengkap: {{ implode(', ', $method->missingDetails()) }}.
                                                @endunless
                                                Aktifkan di sini pun tidak akan menawarkannya
                                                sampai keduanya beres.
                                            </p>
                                        @endunless

                                        <div class="cluster">
                                            {{-- The button describes the change it makes,
                                                 so it is driven by the effective state
                                                 (`$offered`), not by the raw override. --}}
                                            @if ($offered)
                                                <form
                                                    method="POST"
                                                    action="{{ route('admin.events.payment-methods.toggle', [$event, $method]) }}"
                                                    onsubmit="return confirm('Nonaktifkan {{ $method->label }} untuk {{ $event->name }}? Peserta event ini tidak lagi dapat memilih metode tersebut. Event lain tidak terpengaruh.')"
                                                >
                                                    @csrf
                                                    <input type="hidden" name="is_active" value="0">

                                                    <x-button size="sm" variant="secondary">
                                                        Nonaktifkan di Event Ini
                                                    </x-button>
                                                </form>
                                            @else
                                                <form
                                                    method="POST"
                                                    action="{{ route('admin.events.payment-methods.toggle', [$event, $method]) }}"
                                                >
                                                    @csrf
                                                    <input type="hidden" name="is_active" value="1">

                                                    <x-button size="sm">
                                                        Aktifkan di Event Ini
                                                    </x-button>
                                                </form>
                                            @endif

                                            @if ($override !== null)
                                                <form
                                                    method="POST"
                                                    action="{{ route('admin.events.payment-methods.inherit', [$event, $method]) }}"
                                                    onsubmit="return confirm('Kembalikan {{ $method->label }} agar mengikuti pengaturan global untuk {{ $event->name }}?')"
                                                >
                                                    @csrf
                                                    @method('DELETE')

                                                    <x-button size="sm" variant="ghost">
                                                        Ikuti Global
                                                    </x-button>
                                                </form>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-xs text-muted">Hanya lihat</span>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
@endsection
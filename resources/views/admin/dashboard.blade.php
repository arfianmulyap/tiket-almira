@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
    <x-page-header
        title="Dashboard"
        description="Ringkasan operasional Almira Tiket Event."
    />

    @if (! $hasAnyData)
        {{-- Fresh install: a row of zeroes would look like a defect, so say
             plainly that no event exists yet and point at the next action. --}}
        <x-card>
            <x-empty-state
                title="Belum ada event"
                description="Buat event pertama untuk mulai menerima pesanan. Metrik pesanan, pembayaran, tiket, dan check-in akan muncul di sini setelah ada aktivitas."
            >
                <x-slot:actions>
                    <x-button :href="route('admin.events.create')">Buat Event</x-button>
                </x-slot:actions>
            </x-empty-state>
        </x-card>
    @else
        <div class="stack-lg">
            {{-- Operational alerts come before the counters: an admin opening
                 this page needs to know what requires action today. --}}
            @if ($summary['awaiting_verification'] > 0)
                <x-alert variant="info" title="Menunggu verifikasi pembayaran">
                    Ada {{ $summary['awaiting_verification'] }} pesanan yang menunggu keputusan Anda.
                    <a href="{{ route('admin.orders.index', ['status' => \App\Enums\OrderStatus::PendingVerification->value]) }}">
                        Buka antrean verifikasi
                    </a>.
                </x-alert>
            @endif

            @if ($summary['overdue_unswept'] > 0)
                {{-- The expiry sweep runs every minute. A persistent non-zero
                     figure means the scheduler is not running, which would
                     keep quota locked up, so it is reported rather than
                     hidden. --}}
                <x-alert variant="warning" title="Pesanan melewati tenggat belum diproses">
                    {{ $summary['overdue_unswept'] }} pesanan sudah melewati batas 60 menit tetapi belum ditandai
                    kedaluwarsa. Kuota tetap dihitung bebas, namun periksa bahwa scheduler
                    (<code>orders:expire-overdue</code>) berjalan.
                </x-alert>
            @endif

            @if ($soldOutEvents->isNotEmpty())
                <x-alert variant="warning" title="Event dipublikasikan tanpa kuota tersisa">
                    {{ $soldOutEvents->pluck('name')->join(', ') }} masih tampil di halaman publik tetapi kuotanya habis.
                </x-alert>
            @endif

            {{-- Headline counters. Each card links to the list it came from so
                 a figure can always be traced to its rows. --}}
            <div class="grid grid-cols-4">
                <x-kpi
                    label="Event aktif"
                    :value="$summary['events_published']"
                    :meta="$summary['events_total'] . ' event total'"
                    :href="route('admin.events.index')"
                />

                <x-kpi
                    label="Menunggu verifikasi"
                    :value="$summary['awaiting_verification']"
                    meta="Pesanan dengan bukti yang perlu diputuskan"
                    :href="route('admin.orders.index', ['status' => \App\Enums\OrderStatus::PendingVerification->value])"
                />

                <x-kpi
                    label="Tiket terbit"
                    :value="$summary['tickets_issued']"
                    meta="Hanya dari pembayaran yang disetujui"
                />

                <x-kpi
                    label="Check-in"
                    :value="$summary['check_ins_total']"
                    :meta="$summary['tickets_issued'] > 0
                        ? $summary['check_ins_total'] . ' dari ' . $summary['tickets_issued'] . ' tiket'
                        : 'Belum ada tiket terbit'"
                    :href="route('admin.check-in.show')"
                />
            </div>

            {{-- Status breakdown. Every status is listed even at zero so the
                 set of figures is stable and sums to the order total. --}}
            <x-card
                title="Pesanan menurut status"
                description="Total {{ $summary['orders_total'] }} pesanan. Nilai pembayaran disetujui: Rp{{ number_format($approvedRevenue, 0, ',', '.') }}."
            >
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th scope="col">Status</th>
                                <th scope="col" class="is-numeric">Jumlah</th>
                                <th scope="col">Menahan kuota</th>
                                <th scope="col"><span class="u-visually-hidden">Aksi</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($statuses as $status)
                                <tr>
                                    <td>
                                        <x-badge :variant="$status->badgeVariant()">
                                            {{ $status->label() }}
                                        </x-badge>
                                    </td>
                                    <td class="is-numeric">{{ $ordersByStatus[$status->value] }}</td>
                                    <td class="text-sm text-muted">
                                        {{-- Read from the enum rather than restated here, so
                                             this column cannot contradict the quota rule the
                                             ordering flow enforces. --}}
                                        {{ $status->holdsQuota() ? 'Ya' : 'Tidak' }}
                                    </td>
                                    <td>
                                        <x-button
                                            variant="secondary"
                                            size="sm"
                                            :href="route('admin.orders.index', ['status' => $status->value])"
                                        >
                                            Lihat
                                        </x-button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>

            {{-- Per-event operational view: the quota figure here uses the same
                 scope as the public availability display. --}}
            <x-card
                title="Ringkasan per event"
                description="Menampilkan maksimal 10 event terbaru."
            >
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th scope="col">Event</th>
                                <th scope="col">Status</th>
                                <th scope="col" class="is-numeric">Pesanan</th>
                                <th scope="col" class="is-numeric">Perlu verifikasi</th>
                                <th scope="col" class="is-numeric">Tiket</th>
                                <th scope="col" class="is-numeric">Check-in</th>
                                <th scope="col" class="is-numeric">Sisa kuota</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($eventRows as $row)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.events.edit', $row['event']) }}">
                                            {{ $row['event']->name }}
                                        </a>
                                    </td>
                                    <td>
                                        <x-badge :variant="$row['event']->status->badgeVariant()">
                                            {{ $row['event']->status->label() }}
                                        </x-badge>
                                    </td>
                                    <td class="is-numeric">{{ $row['orders_count'] }}</td>
                                    <td class="is-numeric">{{ $row['awaiting_verification'] }}</td>
                                    <td class="is-numeric">{{ $row['tickets_issued'] }}</td>
                                    <td class="is-numeric">{{ $row['checked_in'] }}</td>
                                    <td class="is-numeric">
                                        {{ $row['quota_remaining'] }}
                                        <span class="text-xs text-muted">/ {{ $row['quota'] }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>
    @endif
@endsection

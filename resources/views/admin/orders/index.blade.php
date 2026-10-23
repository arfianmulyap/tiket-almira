@extends('layouts.admin')

@section('title', 'Pesanan')

@section('content')
    <x-page-header
        title="Pesanan"
        description="Tinjau pembayaran manual dan terbitkan tiket."
    />

    <x-card>
        {{-- Search and filters (PRD FR-11). GET so a filtered view is
             bookmarkable and shareable between admins. --}}
        <form method="GET" action="{{ route('admin.orders.index') }}" class="stack">
            {{-- Keeps the active status tab when only the filters change. --}}
            <input type="hidden" name="status" value="{{ $activeStatus }}">

            <div class="grid grid-cols-4">
                <x-field
                    name="q"
                    label="Cari"
                    :value="$filters['q']"
                    hint="Kode pesanan, nama, email, atau WhatsApp."
                />

                {{-- Built from the primitives rather than x-field: that wrapper
                     always renders a text input, and this control is a select. --}}
                <div class="field">
                    <x-label for="event_id">Event</x-label>

                    <x-select name="event_id">
                        <option value="">Semua event</option>
                        @foreach ($events as $event)
                            <option
                                value="{{ $event->id }}"
                                @selected($filters['event_id'] === $event->id)
                            >{{ $event->name }}</option>
                        @endforeach
                    </x-select>

                    <x-field-error name="event_id" />
                </div>

                <x-field
                    name="from"
                    type="date"
                    label="Dibuat dari"
                    :value="$filters['from']"
                />

                <x-field
                    name="to"
                    type="date"
                    label="Dibuat sampai"
                    :value="$filters['to']"
                />
            </div>

            <div class="cluster">
                <x-button type="submit">Terapkan</x-button>

                @if ($filters['q'] || $filters['event_id'] || $filters['from'] || $filters['to'])
                    <x-button
                        variant="secondary"
                        :href="route('admin.orders.index', ['status' => $activeStatus])"
                    >
                        Reset filter
                    </x-button>
                @endif
            </div>
        </form>

        {{-- Status tabs. The per-tab count reflects the active filters, so a
             tab showing (0) means "none in this filtered set", not "none at
             all". The filters are carried into each tab link so switching
             status does not silently discard a search. --}}
        <div class="table-toolbar">
            <nav class="table-toolbar-grow" aria-label="Filter status pesanan">
                @foreach ($statuses as $status)
                    <a
                        href="{{ route('admin.orders.index', array_filter([
                            'status' => $status->value,
                            'q' => $filters['q'],
                            'event_id' => $filters['event_id'],
                            'from' => $filters['from'],
                            'to' => $filters['to'],
                        ])) }}"
                        class="button button-sm {{ $activeStatus === $status->value ? '' : 'button-ghost' }}"
                        @if ($activeStatus === $status->value) aria-current="page" @endif
                    >
                        {{ $status->label() }}
                        ({{ $countsByStatus[$status->value] ?? 0 }})
                    </a>
                @endforeach
            </nav>
        </div>

        @if ($orders->isEmpty())
            <x-empty-state
                title="Tidak ada pesanan yang cocok"
                description="Tidak ada pesanan pada status ini untuk filter yang dipilih. Ubah atau reset filter untuk memperluas hasil."
            />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">Kode Pesanan</th>
                            <th scope="col">Peserta</th>
                            <th scope="col">Event</th>
                            <th scope="col" class="is-numeric">Tagihan</th>
                            <th scope="col">Dibuat</th>
                            <th scope="col"><span class="u-visually-hidden">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($orders as $order)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.orders.show', $order) }}">
                                        <code class="text-sm">{{ $order->order_reference }}</code>
                                    </a>
                                </td>
                                <td>
                                    {{ $order->participant_name }}
                                    <p class="text-xs text-muted">{{ $order->participant_email }}</p>
                                </td>
                                <td>{{ $order->event->name }}</td>
                                <td class="is-numeric">
                                    Rp{{ number_format($order->ticket_price_snapshot, 0, ',', '.') }}
                                </td>
                                <td>{{ $order->created_at->translatedFormat('d M Y, H:i') }} WIB</td>
                                <td>
                                    <x-button
                                        variant="secondary"
                                        size="sm"
                                        :href="route('admin.orders.show', $order)"
                                    >
                                        Tinjau
                                    </x-button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($orders->hasPages())
                <div class="table-footer">
                    {{ $orders->links() }}
                </div>
            @endif
        @endif
    </x-card>
@endsection

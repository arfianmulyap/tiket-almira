@extends('layouts.app')

@section('title', 'E-Ticket')

@section('content')
    <x-page-header
        title="E-Ticket"
        :description="$order->event->name"
    />

    <x-alert variant="info" class="u-mb-6">
        Tunjukkan QR ini kepada petugas saat check-in. Simpan tautan halaman
        ini, karena inilah satu-satunya cara membuka e-ticket kamu.
    </x-alert>

    <x-card>
        <div class="ticket">
            <div class="ticket-qr">
                {{--
                    Rendered server-side as inline SVG so the ticket needs no
                    extra request and stays printable. The QR encodes only the
                    opaque ticket token, never personal data (PRD FR-09).
                --}}
                {!! $qrSvg !!}
            </div>

            <dl class="detail-list">
                <div class="detail-row">
                    <dt>Status Tiket</dt>
                    <dd>
                        <x-badge :variant="$ticket->status->badgeVariant()">
                            {{ $ticket->status->label() }}
                        </x-badge>
                    </dd>
                </div>

                <div class="detail-row">
                    <dt>Kode Pesanan</dt>
                    <dd><code class="text-sm">{{ $order->order_reference }}</code></dd>
                </div>

                <div class="detail-row">
                    <dt>Nama Peserta</dt>
                    <dd>{{ $order->participant_name }}</dd>
                </div>

                <div class="detail-row">
                    <dt>Event</dt>
                    <dd>{{ $order->event->name }}</dd>
                </div>

                <div class="detail-row">
                    <dt>Waktu</dt>
                    <dd>{{ $order->event->starts_at->translatedFormat('d M Y, H:i') }} WIB</dd>
                </div>

                <div class="detail-row">
                    <dt>Lokasi</dt>
                    <dd>{{ $order->event->location }}</dd>
                </div>

                <div class="detail-row">
                    <dt>Diterbitkan</dt>
                    <dd>{{ $ticket->issued_at->translatedFormat('d M Y, H:i') }} WIB</dd>
                </div>
            </dl>
        </div>

        <x-slot:footer>
            {{-- Phase 13 renders the ticket server-side as a PDF, so the file is
                 the same document the participant receives by email rather than
                 whatever the browser decides to print. --}}
            <x-button size="sm" :href="route('tickets.pdf', $order->access_token)">
                Unduh PDF
            </x-button>

            <x-button variant="secondary" size="sm" onclick="window.print()">
                Cetak
            </x-button>

            <x-button variant="ghost" size="sm" :href="route('orders.show', $order->access_token)">
                Lihat Status Pesanan
            </x-button>
        </x-slot:footer>
    </x-card>
@endsection

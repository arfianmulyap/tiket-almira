@extends('layouts.app')

@section('title', $event->name)

@section('content')
    {{--
        PRD FR-03: the poster sits at the very top of the ordering page,
        above the title and the form.
    --}}
    @if ($event->posterUrl())
        <img
            src="{{ $event->posterUrl() }}"
            alt="Poster {{ $event->name }}"
            class="event-poster-hero"
        >
    @endif

    <x-page-header :title="$event->name" :description="$event->location" />

    <div class="order-layout">
        <div>
            <x-card title="Informasi Event">
                <dl class="detail-list">
                    <div class="detail-row">
                        <dt>Waktu</dt>
                        <dd>{{ $event->starts_at->translatedFormat('l, d F Y, H:i') }} WIB</dd>
                    </div>

                    <div class="detail-row">
                        <dt>Lokasi</dt>
                        <dd>{{ $event->location }}</dd>
                    </div>

                    <div class="detail-row">
                        <dt>Harga Tiket</dt>
                        <dd>Rp{{ number_format($event->ticket_price, 0, ',', '.') }}</dd>
                    </div>

                    <div class="detail-row">
                        <dt>Ketersediaan</dt>
                        <dd>
                            @if ($event->hasAvailableQuota())
                                <x-badge variant="success">
                                    Sisa {{ number_format($event->remainingQuota(), 0, ',', '.') }} tiket
                                </x-badge>
                            @else
                                <x-badge variant="neutral">Tiket habis</x-badge>
                            @endif
                        </dd>
                    </div>
                </dl>

                @if (filled($event->description))
                    <div class="u-mt-6">
                        <p class="section-title">Deskripsi</p>
                        {{-- nl2br on an escaped value: newlines render, markup does not. --}}
                        <p class="text-sm">{!! nl2br(e($event->description)) !!}</p>
                    </div>
                @endif
            </x-card>
        </div>

        <div>
            @if ($event->hasAvailableQuota())
                <x-card title="Pesan Tiket" description="Satu pesanan berlaku untuk satu tiket.">
                    <form method="POST" action="{{ route('orders.store', $event->slug) }}">
                        @csrf

                        <x-field
                            name="participant_name"
                            label="Nama Lengkap"
                            required
                            autocomplete="name"
                            placeholder="Nama sesuai identitas"
                        />

                        <x-field
                            name="participant_whatsapp"
                            label="Nomor WhatsApp"
                            type="tel"
                            required
                            autocomplete="tel"
                            placeholder="081234567890"
                            hint="Digunakan untuk konfirmasi pesanan."
                        />

                        <x-field
                            name="participant_email"
                            label="Email"
                            type="email"
                            required
                            autocomplete="email"
                            placeholder="nama@email.com"
                            hint="E-ticket dikirim ke alamat ini."
                        />

                        <x-alert variant="info" class="u-mb-4">
                            Setelah pesanan dibuat, kamu punya waktu
                            {{ $paymentWindowMinutes }} menit
                            untuk menyelesaikan pembayaran.
                        </x-alert>

                        <x-button type="submit" block>Buat Pesanan</x-button>
                    </form>
                </x-card>
            @else
                <x-card title="Pesan Tiket">
                    <x-empty-state
                        title="Tiket sudah habis"
                        description="Seluruh kuota untuk event ini telah terisi, sehingga pemesanan ditutup."
                    >
                        <x-slot:actions>
                            <x-button variant="secondary" size="sm" :href="route('events.index')">
                                Lihat Event Lain
                            </x-button>
                        </x-slot:actions>
                    </x-empty-state>
                </x-card>
            @endif
        </div>
    </div>
@endsection

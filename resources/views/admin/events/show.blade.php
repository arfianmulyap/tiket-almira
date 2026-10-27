@extends('layouts.admin')

@section('title', 'Detail Event')

@section('content')
    <x-page-header :title="$event->name" :description="$event->location">
        <x-slot:actions>
            <x-button variant="secondary" :href="route('admin.events.edit', $event)">
                Ubah Event
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @if ($event->isDraft())
        <x-alert variant="warning" class="u-mb-6">
            Event ini masih berstatus draft, sehingga belum dapat dipesan peserta.
        </x-alert>
    @endif

    <div class="grid">
        <x-card title="Informasi Event">
            <dl class="detail-list">
                <div class="detail-row">
                    <dt>Status</dt>
                    <dd>
                        <x-badge :variant="$event->status->badgeVariant()">
                            {{ $event->status->label() }}
                        </x-badge>
                    </dd>
                </div>

                <div class="detail-row">
                    <dt>Harga Tiket</dt>
                    <dd>Rp{{ number_format($event->ticket_price, 0, ',', '.') }}</dd>
                </div>

                <div class="detail-row">
                    <dt>Kuota</dt>
                    <dd>{{ number_format($event->quota, 0, ',', '.') }} tiket</dd>
                </div>

                <div class="detail-row">
                    <dt>Waktu Mulai</dt>
                    <dd>{{ $event->starts_at->format('d M Y, H:i') }} WIB</dd>
                </div>

                <div class="detail-row">
                    <dt>Lokasi</dt>
                    <dd>{{ $event->location }}</dd>
                </div>

                <div class="detail-row">
                    <dt>Slug</dt>
                    <dd><code class="text-sm">{{ $event->slug }}</code></dd>
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

        <x-card title="Poster">
            @if ($event->posterUrl())
                <img
                    src="{{ $event->posterUrl() }}"
                    alt="Poster {{ $event->name }}"
                    class="event-poster-preview"
                >
            @else
                <x-empty-state
                    title="Belum ada poster"
                    description="Poster wajib diunggah sebelum event dipublikasikan."
                >
                    <x-slot:actions>
                        <x-button
                            variant="secondary"
                            size="sm"
                            :href="route('admin.events.edit', $event)"
                        >
                            Unggah Poster
                        </x-button>
                    </x-slot:actions>
                </x-empty-state>
            @endif
        </x-card>
    </div>

    {{-- Payment method activation for this event (Phase 12B). Details are
         global, so this screen only decides which methods this event offers. --}}
    @can('payment_methods.view')
        <x-card title="Metode Pembayaran" class="u-mt-6">
            <p class="text-sm text-muted u-mb-4">
                Metode pembayaran bersifat global: rekening, VA, dan QRIS-nya sama
                untuk semua event. Yang diatur per event adalah apakah metode itu
                ditawarkan kepada peserta event ini. Peserta hanya melihat metode
                yang aktif, detailnya lengkap, dan tidak dimatikan untuk event ini.
            </p>

            <x-button
                variant="secondary"
                :href="route('admin.events.payment-methods', $event)"
            >
                Atur Metode untuk Event Ini
            </x-button>
        </x-card>
    @endcan

    {{--
        Order, payment, ticket, and check-in summaries for this event belong to
        the phases that own that data. Nothing is shown here yet rather than
        rendering placeholder numbers.
    --}}
@endsection

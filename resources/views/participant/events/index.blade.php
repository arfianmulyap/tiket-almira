@extends('layouts.app')

@section('title', 'Daftar Event')

@section('content')
    <x-page-header
        title="Event Almira"
        description="Pilih event yang ingin kamu ikuti dan pesan tiketmu."
    />

    @if ($events->isEmpty())
        <x-card>
            <x-empty-state
                title="Belum ada event yang dipublikasikan"
                description="Saat ini tidak ada event yang dibuka untuk pemesanan. Silakan cek kembali nanti."
            />
        </x-card>
    @else
        <div class="event-grid">
            @foreach ($events as $event)
                @php
                    // reserved_quota_count comes from withCount() in the
                    // controller, so this does not query per card.
                    $remaining = max(0, $event->quota - $event->reserved_quota_count);
                @endphp

                <article class="event-card">
                    <a href="{{ route('events.show', $event->slug) }}" class="event-card-media">
                        @if ($event->posterUrl())
                            <img
                                src="{{ $event->posterUrl() }}"
                                alt="Poster {{ $event->name }}"
                                loading="lazy"
                            >
                        @else
                            {{-- Published events always have a poster, but a
                                 missing file must not break the layout. --}}
                            <span class="event-card-media-fallback">{{ $event->name }}</span>
                        @endif
                    </a>

                    <div class="event-card-body">
                        <h2 class="event-card-title">
                            <a href="{{ route('events.show', $event->slug) }}">{{ $event->name }}</a>
                        </h2>

                        <p class="event-card-meta">
                            {{ $event->starts_at->translatedFormat('d M Y, H:i') }}
                            &middot; {{ $event->location }}
                        </p>

                        <p class="event-card-price">
                            Rp{{ number_format($event->ticket_price, 0, ',', '.') }}
                        </p>

                        <div class="event-card-footer">
                            @if ($remaining > 0)
                                <x-badge variant="success">
                                    Sisa {{ number_format($remaining, 0, ',', '.') }} tiket
                                </x-badge>
                            @else
                                <x-badge variant="neutral">Tiket habis</x-badge>
                            @endif

                            <x-button
                                size="sm"
                                :variant="$remaining > 0 ? 'primary' : 'secondary'"
                                :href="route('events.show', $event->slug)"
                            >
                                {{ $remaining > 0 ? 'Pesan Tiket' : 'Lihat Detail' }}
                            </x-button>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        @if ($events->hasPages())
            <div class="u-mt-6">
                {{ $events->links() }}
            </div>
        @endif
    @endif
@endsection

@extends('layouts.admin')

@section('title', 'Event')

@section('content')
    <x-page-header
        title="Event"
        description="Kelola event, harga tiket, kuota, dan publikasi."
    >
        <x-slot:actions>
            <x-button :href="route('admin.events.create')">Buat Event</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card>
        @if ($events->isEmpty())
            <x-empty-state
                title="Belum ada event"
                description="Buat event pertama untuk mulai menjual tiket."
            >
                <x-slot:actions>
                    <x-button :href="route('admin.events.create')">Buat Event</x-button>
                </x-slot:actions>
            </x-empty-state>
        @else
            <div class="table-toolbar">
                <p class="table-toolbar-grow text-sm text-muted">
                    Menampilkan {{ $events->count() }} dari {{ $events->total() }} event
                </p>
            </div>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">Event</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="is-numeric">Harga</th>
                            <th scope="col" class="is-numeric">Kuota</th>
                            <th scope="col">Waktu Mulai</th>
                            <th scope="col"><span class="u-visually-hidden">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($events as $event)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.events.show', $event) }}">
                                        {{ $event->name }}
                                    </a>
                                    <p class="text-xs text-muted">{{ $event->location }}</p>
                                </td>
                                <td>
                                    <x-badge :variant="$event->status->badgeVariant()">
                                        {{ $event->status->label() }}
                                    </x-badge>
                                </td>
                                <td class="is-numeric">
                                    Rp{{ number_format($event->ticket_price, 0, ',', '.') }}
                                </td>
                                <td class="is-numeric">{{ number_format($event->quota, 0, ',', '.') }}</td>
                                <td>{{ $event->starts_at->format('d M Y H:i') }}</td>
                                <td>
                                    <x-button
                                        variant="secondary"
                                        size="sm"
                                        :href="route('admin.events.edit', $event)"
                                    >
                                        Ubah
                                    </x-button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($events->hasPages())
                <div class="table-footer">
                    {{ $events->links() }}
                </div>
            @endif
        @endif
    </x-card>
@endsection

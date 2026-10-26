@extends('layouts.admin')

@section('title', 'Audit Log')

@section('content')
    <x-page-header
        title="Audit Log"
        description="Jejak tindakan operasional, perubahan akses, dan perubahan konfigurasi. Hanya Super Admin yang dapat membuka halaman ini."
    />

    <x-card>
        <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="stack">
            <div class="grid grid-cols-4">
                <div class="field">
                    <x-label for="action">Tindakan</x-label>

                    <x-select name="action">
                        <option value="">Semua tindakan</option>

                        @foreach ($actions as $action)
                            <option
                                value="{{ $action->value }}"
                                @selected($filters['action'] === $action->value)
                            >{{ $action->label() }}</option>
                        @endforeach
                    </x-select>

                    <x-field-error name="action" />
                </div>

                <x-field
                    name="actor"
                    label="Pelaku"
                    :value="$filters['actor']"
                    hint="Nama admin. Kosongkan untuk semua pelaku."
                />

                <x-field
                    name="from"
                    type="date"
                    label="Dari tanggal"
                    :value="$filters['from']"
                />

                <x-field
                    name="to"
                    type="date"
                    label="Sampai tanggal"
                    :value="$filters['to']"
                />
            </div>

            <div class="cluster">
                <x-button type="submit">Terapkan</x-button>

                @if ($filters['action'] || $filters['actor'] || $filters['from'] || $filters['to'])
                    <x-button variant="secondary" :href="route('admin.audit-logs.index')">
                        Reset filter
                    </x-button>
                @endif
            </div>
        </form>

        @if ($logs->isEmpty())
            <x-empty-state
                title="Tidak ada catatan yang cocok"
                description="Belum ada aktivitas yang tercatat untuk filter ini. Ubah atau reset filter untuk memperluas hasil."
            />
        @else
            <p class="text-sm text-muted u-mb-4">
                Menampilkan {{ $logs->firstItem() }}–{{ $logs->lastItem() }}
                dari {{ $logs->total() }} catatan, terbaru lebih dahulu.
            </p>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">Waktu</th>
                            <th scope="col">Tindakan</th>
                            <th scope="col">Pelaku</th>
                            <th scope="col">Objek</th>
                            <th scope="col"><span class="u-visually-hidden">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr>
                                <td>
                                    {{ $log->created_at->translatedFormat('d M Y, H:i:s') }} WIB
                                </td>
                                <td>
                                    <p>{{ $log->action->label() }}</p>
                                    <p class="text-xs text-muted">
                                        <code>{{ $log->action->value }}</code>
                                    </p>
                                </td>
                                <td>
                                    {{ $log->actorName() }}

                                    @if ($log->user_id)
                                        <p class="text-xs text-muted">Akun #{{ $log->user_id }}</p>
                                    @endif
                                </td>
                                <td>
                                    @php($subjectUrl = $log->subjectUrl())

                                    @if ($subjectUrl)
                                        <a href="{{ $subjectUrl }}">{{ $log->subjectLabel() }}</a>
                                    @else
                                        {{ $log->subjectLabel() }}
                                    @endif
                                </td>
                                <td>
                                    <x-button
                                        variant="secondary"
                                        size="sm"
                                        :href="route('admin.audit-logs.show', $log)"
                                    >
                                        Detail
                                    </x-button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($logs->hasPages())
                <div class="table-footer">
                    {{ $logs->links() }}
                </div>
            @endif
        @endif
    </x-card>
@endsection

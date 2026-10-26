@extends('layouts.admin')

@section('title', 'Detail Audit Log')

@section('content')
    <x-page-header
        title="Detail Audit Log"
        description="Satu catatan append-only. Tidak ada tindakan ubah atau hapus pada halaman ini."
    >
        <x-slot:actions>
            <x-button variant="secondary" :href="route('admin.audit-logs.index')">
                Kembali
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card title="Ringkasan">
        <dl class="detail-list">
            <div class="detail-row">
                <dt>Waktu</dt>
                <dd>{{ $log->created_at->translatedFormat('d M Y, H:i:s') }} WIB</dd>
            </div>

            <div class="detail-row">
                <dt>Tindakan</dt>
                <dd>
                    {{ $log->action->label() }}
                    <span class="text-xs text-muted">(<code>{{ $log->action->value }}</code>)</span>
                </dd>
            </div>

            <div class="detail-row">
                <dt>Pelaku</dt>
                <dd>
                    {{ $log->actorName() }}

                    @if ($log->user_id)
                        <span class="text-xs text-muted">Akun #{{ $log->user_id }}</span>
                    @endif
                </dd>
            </div>

            <div class="detail-row">
                <dt>Objek</dt>
                <dd>
                    @php($subjectUrl = $log->subjectUrl())

                    @if ($subjectUrl)
                        <a href="{{ $subjectUrl }}">{{ $log->subjectLabel() }}</a>
                    @else
                        {{ $log->subjectLabel() }}
                    @endif

                    <p class="text-xs text-muted">
                        {{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}
                    </p>
                </dd>
            </div>
        </dl>
    </x-card>

    <x-card title="Metadata" class="u-mt-6">
        @if (filled($log->metadata))
            <pre class="code-block">{{ json_encode($log->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
        @else
            <p class="text-sm text-muted">
                Catatan ini tidak menyimpan metadata tambahan.
            </p>
        @endif
    </x-card>
@endsection

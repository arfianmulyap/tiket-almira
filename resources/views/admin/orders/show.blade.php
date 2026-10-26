@extends('layouts.admin')

@section('title', 'Detail Pesanan')

@section('content')
    <x-page-header
        :title="$order->order_reference"
        :description="$order->event->name"
    >
        <x-slot:actions>
            <x-button variant="secondary" size="sm" :href="route('admin.orders.index')">
                Kembali
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="order-layout">
        <div>
            <x-card title="Rincian Pesanan">
                <dl class="detail-list">
                    <div class="detail-row">
                        <dt>Status</dt>
                        <dd>
                            <x-badge :variant="$order->status->badgeVariant()">
                                {{ $order->status->label() }}
                            </x-badge>
                        </dd>
                    </div>

                    <div class="detail-row">
                        <dt>Tagihan</dt>
                        {{-- The snapshot taken when the order was created. --}}
                        <dd>Rp{{ number_format($order->ticket_price_snapshot, 0, ',', '.') }}</dd>
                    </div>

                    <div class="detail-row">
                        <dt>Dibuat</dt>
                        <dd>{{ $order->created_at->translatedFormat('d M Y, H:i') }} WIB</dd>
                    </div>

                    <div class="detail-row">
                        <dt>Batas Pembayaran</dt>
                        <dd>{{ $order->expires_at->translatedFormat('d M Y, H:i') }} WIB</dd>
                    </div>

                    @if ($order->reviewed_at)
                        <div class="detail-row">
                            <dt>Diputuskan</dt>
                            <dd>
                                {{ $order->reviewed_at->translatedFormat('d M Y, H:i') }} WIB
                                oleh {{ $order->reviewer?->name ?? 'admin yang sudah dihapus' }}
                            </dd>
                        </div>
                    @endif

                    @if ($order->rejection_reason)
                        <div class="detail-row">
                            <dt>Alasan Penolakan</dt>
                            <dd>{{ $order->rejection_reason }}</dd>
                        </div>
                    @endif
                </dl>
            </x-card>

            <x-card title="Data Peserta" class="u-mt-6">
                <dl class="detail-list">
                    <div class="detail-row">
                        <dt>Nama</dt>
                        <dd>{{ $order->participant_name }}</dd>
                    </div>

                    <div class="detail-row">
                        <dt>WhatsApp</dt>
                        <dd>{{ $order->participant_whatsapp }}</dd>
                    </div>

                    <div class="detail-row">
                        <dt>Email</dt>
                        <dd>{{ $order->participant_email }}</dd>
                    </div>
                </dl>
            </x-card>

            <x-card title="Riwayat Tindakan" class="u-mt-6">
                @if ($auditLogs->isEmpty())
                    <x-empty-state
                        title="Belum ada tindakan"
                        description="Keputusan pembayaran dan penerbitan tiket akan tercatat di sini."
                    />
                @else
                    <dl class="detail-list">
                        @foreach ($auditLogs as $log)
                            <div class="detail-row">
                                <dt>{{ $log->action->label() }}</dt>
                                <dd>
                                    {{ $log->created_at->translatedFormat('d M Y, H:i') }} WIB
                                    @if ($log->user_name)
                                        — {{ $log->user_name }}
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </x-card>
        </div>

        <div>
            <x-card title="Bukti Pembayaran">
                @if ($order->payment)
                    <dl class="detail-list u-mb-4">
                        <div class="detail-row">
                            <dt>Metode</dt>
                            {{-- The label snapshotted at submission, not the
                                 current config value. --}}
                            <dd>{{ $order->payment->method_label }}</dd>
                        </div>

                        <div class="detail-row">
                            <dt>Nominal Ditagihkan</dt>
                            <dd>Rp{{ number_format($order->payment->amount, 0, ',', '.') }}</dd>
                        </div>

                        <div class="detail-row">
                            <dt>Bukti Dikirim</dt>
                            <dd>{{ $order->payment->submitted_at->translatedFormat('d M Y, H:i') }} WIB</dd>
                        </div>
                    </dl>

                    {{-- Streamed by Admin\PaymentProofController, which checks
                         OrderPolicy::viewPaymentProof() on every request. The
                         file has no public URL. --}}
                    <a
                        href="{{ route('admin.orders.payment-proof', $order) }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="payment-proof-preview"
                    >
                        <img
                            src="{{ route('admin.orders.payment-proof', $order) }}"
                            alt="Bukti pembayaran {{ $order->order_reference }}"
                            loading="lazy"
                        >
                    </a>

                    <x-button
                        variant="secondary"
                        size="sm"
                        class="u-mt-4"
                        :href="route('admin.orders.payment-proof', $order)"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        Buka Berkas Bukti
                    </x-button>
                @else
                    <x-empty-state
                        title="Bukti pembayaran belum dikirim"
                        description="Peserta belum mengunggah bukti pembayaran untuk pesanan ini."
                    />
                @endif
            </x-card>

            <x-card title="Keputusan Pembayaran" class="u-mt-6">
                @can('decide', $order)
                    <x-alert variant="warning" class="u-mb-4">
                        Pastikan dana benar-benar sudah masuk sebelum menyetujui.
                        Bukti yang diunggah bukan konfirmasi bahwa dana diterima.
                    </x-alert>

                    <form method="POST" action="{{ route('admin.orders.approve', $order) }}">
                        @csrf

                        <x-button block>Setujui &amp; Terbitkan Tiket</x-button>
                    </form>

                    <form
                        method="POST"
                        action="{{ route('admin.orders.reject', $order) }}"
                        class="u-mt-6"
                    >
                        @csrf

                        <div class="field">
                            <x-label for="rejection_reason" :required="true">
                                Alasan Penolakan
                            </x-label>

                            <x-textarea
                                name="rejection_reason"
                                :value="old('rejection_reason')"
                                rows="3"
                                required
                            />

                            <x-field-error name="rejection_reason" />
                        </div>

                        <x-button variant="danger" block>Tolak Pembayaran</x-button>
                    </form>
                @elseif ($order->isPaid())
                    <x-alert variant="success" class="u-mb-4">
                        Pembayaran sudah disetujui dan tiket telah diterbitkan.
                    </x-alert>

                    @if ($order->ticket)
                        <dl class="detail-list">
                            <div class="detail-row">
                                <dt>Status Tiket</dt>
                                <dd>
                                    <x-badge :variant="$order->ticket->status->badgeVariant()">
                                        {{ $order->ticket->status->label() }}
                                    </x-badge>
                                </dd>
                            </div>

                            <div class="detail-row">
                                <dt>Diterbitkan</dt>
                                <dd>{{ $order->ticket->issued_at->translatedFormat('d M Y, H:i') }} WIB</dd>
                            </div>
                        </dl>
                    @endif
                @elseif ($order->isRejected())
                    <x-alert variant="error">
                        Pembayaran ditolak. Kebijakan unggah ulang bukti belum
                        ditetapkan, sehingga peserta belum dapat mengajukan
                        bukti baru.
                    </x-alert>
                @else
                    <x-alert variant="info">
                        Pesanan ini belum siap diverifikasi. Status saat ini:
                        {{ $order->status->label() }}.
                    </x-alert>
                @endcan
            </x-card>

            @if ($order->ticket)
                {{--
                    Phase 13: what happened to the e-ticket email. Only rendered
                    once a ticket exists, because before approval there is
                    nothing to send. The history is append-only, so a failed
                    attempt stays visible after a successful resend — that is
                    what makes the trail trustworthy.
                --}}
                <x-card title="Email E-Ticket" class="u-mt-6">
                    @php
                        $deliveries = $order->ticket->emailDeliveries;
                        $latest = $deliveries->first();
                    @endphp

                    @unless (config('tickets.email_enabled'))
                        <x-alert variant="warning" class="u-mb-4">
                            Pengiriman email e-ticket belum diaktifkan
                            (<code class="text-xs">TICKET_EMAIL_ENABLED=false</code>).
                            Peserta tetap dapat membuka tiketnya lewat halaman
                            status pesanan.
                        </x-alert>
                    @endunless

                    <dl class="detail-list">
                        <div class="detail-row">
                            <dt>Penerima</dt>
                            <dd>{{ $order->participant_email }}</dd>
                        </div>

                        <div class="detail-row">
                            <dt>Status Terakhir</dt>
                            <dd>
                                @if ($latest)
                                    <x-badge :variant="$latest->status->badgeVariant()">
                                        {{ $latest->status->label() }}
                                    </x-badge>
                                    <p class="text-xs text-muted">
                                        {{ $latest->created_at->translatedFormat('d M Y, H:i') }} WIB
                                        oleh {{ $latest->actorName() }}
                                    </p>
                                @else
                                    <span class="text-xs text-muted">Belum pernah dikirim</span>
                                @endif
                            </dd>
                        </div>
                    </dl>

                    @if ($latest?->error_message)
                        <x-alert variant="error" class="u-mt-4">
                            {{ $latest->error_message }}
                        </x-alert>
                    @endif

                    @if ($deliveries->isNotEmpty())
                        <p class="section-title u-mt-6">Riwayat Pengiriman</p>

                        <dl class="detail-list">
                            @foreach ($deliveries->take(5) as $delivery)
                                <div class="detail-row">
                                    <dt>{{ $delivery->status->label() }}</dt>
                                    <dd>
                                        {{ $delivery->created_at->translatedFormat('d M Y, H:i') }} WIB
                                        — {{ $delivery->actorName() }}
                                    </dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif

                    @can('resendTicket', $order)
                        <form
                            method="POST"
                            action="{{ route('admin.orders.ticket-email', $order) }}"
                            class="u-mt-6"
                        >
                            @csrf

                            <x-button variant="secondary" block>Kirim Ulang Tiket</x-button>
                        </form>

                        <p class="field-hint">
                            Mengirim ulang memakai tiket dan QR yang sama. Tidak ada
                            tiket baru yang dibuat, jadi kode lama tetap berlaku.
                        </p>
                    @endcan
                </x-card>
            @endif

            {{--
                Phase 14: scheduled WhatsApp reminders. This card stays visible
                for every order, including ones that already moved on, because
                the skipped rows explain why no reminder was sent.
            --}}
            <x-card title="Pengingat WhatsApp" class="u-mt-6">
                @unless (config('whatsapp.enabled'))
                    <x-alert variant="warning" class="u-mb-4">
                        Pengingat WhatsApp belum diaktifkan
                        (<code class="text-xs">WHATSAPP_ENABLED=false</code>).
                        Tidak ada pesan yang dikirim ke peserta.
                    </x-alert>
                @elseif (config('whatsapp.driver') === 'log')
                    <x-alert variant="info" class="u-mb-4">
                        Driver <code class="text-xs">log</code> aktif: pesan hanya
                        dicatat di log aplikasi dan tidak dikirim ke provider
                        WhatsApp. Gunakan hanya untuk QA lokal atau staging.
                    </x-alert>
                @endunless

                <dl class="detail-list">
                    <div class="detail-row">
                        <dt>Penerima</dt>
                        <dd>{{ $order->participant_whatsapp }}</dd>
                    </div>
                </dl>

                <p class="section-title u-mt-6">Jadwal Pengingat</p>

                <dl class="detail-list">
                    @foreach ($whatsappStages as $stage)
                        @php
                            $delivery = $order->whatsappDeliveries->first(
                                fn ($item) => $item->kind === $stage
                            );
                            $scheduledAt = $order->created_at->copy()->addMinutes($stage->minute());
                        @endphp

                        <div class="detail-row">
                            <dt>{{ $stage->label() }}</dt>
                            <dd>
                                @if ($delivery)
                                    <x-badge :variant="$delivery->status->badgeVariant()">
                                        {{ $delivery->status->label() }}
                                    </x-badge>

                                    @if ($delivery->attempts > 0)
                                        <p class="text-xs text-muted">
                                            {{ $delivery->attempts }} percobaan
                                            @if ($delivery->driver)
                                                &middot; driver {{ $delivery->driver }}
                                            @endif
                                            @if ($delivery->sent_at)
                                                &middot; {{ $delivery->sent_at->translatedFormat('d M Y, H:i') }} WIB
                                            @endif
                                        </p>
                                    @endif

                                    @if ($delivery->error_message)
                                        <p class="text-xs text-muted">{{ $delivery->error_message }}</p>
                                    @endif
                                @elseif ($order->acceptsPaymentProof() && $scheduledAt->isFuture())
                                    <span class="text-xs text-muted">
                                        Dijadwalkan {{ $scheduledAt->translatedFormat('d M Y, H:i') }} WIB
                                    </span>
                                @else
                                    <span class="text-xs text-muted">Tidak dikirim</span>
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>

                <p class="field-hint">
                    Pengingat hanya dikirim selama pesanan masih menunggu pembayaran,
                    belum ada bukti yang diunggah, dan tenggat belum lewat. Setiap
                    tahap dicatat satu kali agar pesan yang sama tidak terkirim ganda;
                    percobaan ulang dibatasi
                    {{ config('whatsapp.reminders.max_attempts') }} kali.
                </p>
            </x-card>
        </div>
    </div>
@endsection

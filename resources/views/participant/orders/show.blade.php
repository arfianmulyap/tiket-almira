@extends('layouts.app')

@section('title', 'Status Pesanan')

@section('content')
    <x-page-header
        title="Status Pesanan"
        :description="$order->event->name"
    />

    <x-alert variant="info" class="u-mb-6">
        Simpan halaman ini. Tautan rahasia inilah satu-satunya cara untuk
        membuka status pesanan dan e-ticket kamu.
    </x-alert>

    <div class="order-layout">
        <div>
            <x-card title="Rincian Pesanan">
                <dl class="detail-list">
                    <div class="detail-row">
                        <dt>Kode Pesanan</dt>
                        <dd><code class="text-sm">{{ $order->order_reference }}</code></dd>
                    </div>

                    <div class="detail-row">
                        <dt>Status</dt>
                        <dd>
                            <x-badge :variant="$order->status->badgeVariant()">
                                {{ $order->status->label() }}
                            </x-badge>
                        </dd>
                    </div>

                    <div class="detail-row">
                        <dt>Jumlah Tagihan</dt>
                        {{-- The snapshot, not the event's current price. --}}
                        <dd>Rp{{ number_format($order->ticket_price_snapshot, 0, ',', '.') }}</dd>
                    </div>

                    <div class="detail-row">
                        <dt>Jumlah Tiket</dt>
                        <dd>1 tiket</dd>
                    </div>

                    <div class="detail-row">
                        <dt>Dibuat</dt>
                        <dd>{{ $order->created_at->translatedFormat('d M Y, H:i') }} WIB</dd>
                    </div>

                    <div class="detail-row">
                        <dt>Batas Pembayaran</dt>
                        <dd>{{ $order->expires_at->translatedFormat('d M Y, H:i') }} WIB</dd>
                    </div>
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
        </div>

        <div>
            <x-card title="Pembayaran">
                @if ($order->acceptsPaymentProof())
                    <x-alert variant="warning" class="u-mb-4">
                        Selesaikan pembayaran sebelum
                        <strong>{{ $order->expires_at->translatedFormat('d M Y, H:i') }} WIB</strong>.
                        Pesanan yang belum disertai bukti pembayaran akan kedaluwarsa
                        dan kuotanya dilepas.
                    </x-alert>

                    @if ($paymentMethods->isEmpty())
                        {{--
                            Expected state in production while Phase 05C is
                            blocked: the business has not confirmed which
                            methods are active or supplied account, VA, and
                            QRIS details (PRD section 15 questions 1 and 2),
                            so PaymentMethodRegistry offers nothing rather
                            than showing invented instructions.
                        --}}
                        <x-empty-state
                            title="Instruksi pembayaran belum tersedia"
                            description="Metode pembayaran sedang difinalisasi. Hubungi admin untuk menyelesaikan pembayaran pesanan ini."
                        />
                    @else
                        @if ($showsDummyPaymentData)
                            <x-alert variant="error" title="Mode pengembangan" class="u-mb-4">
                                Metode di bawah ini berisi data contoh dan bukan
                                rekening sebenarnya. Jangan melakukan transfer.
                            </x-alert>
                        @endif

                        <form
                            method="POST"
                            action="{{ route('orders.payment-proof.store', $order->access_token) }}"
                            enctype="multipart/form-data"
                        >
                            @csrf

                            <div class="field">
                                <x-label for="payment_method" :required="true">
                                    Metode Pembayaran
                                </x-label>

                                <x-select name="payment_method" required>
                                    <option value="">Pilih metode pembayaran</option>

                                    @foreach ($paymentMethods as $method)
                                        <option
                                            value="{{ $method->key }}"
                                            @selected(old('payment_method') === $method->key)
                                        >
                                            {{ $method->label }}
                                        </option>
                                    @endforeach
                                </x-select>

                                <x-field-error name="payment_method" />
                            </div>

                            {{-- Instructions for every method are listed
                                 unconditionally rather than revealed by the
                                 selection. There is no asset build in this
                                 layout (see partials/head), so a JS-driven
                                 reveal would leave the instructions
                                 permanently hidden for everyone. --}}
                            @foreach ($paymentMethods as $method)
                                <x-card variant="muted" class="u-mt-4">
                                    <h3 class="card-title">{{ $method->label }}</h3>

                                    <x-payment-instructions :method="$method" />
                                </x-card>
                            @endforeach

                            <div class="field u-mt-6">
                                <x-label for="proof" :required="true">
                                    Bukti Pembayaran
                                </x-label>

                                <x-input
                                    name="proof"
                                    type="file"
                                    accept="{{ $proofAcceptAttribute }}"
                                    required
                                />

                                <p class="field-hint">
                                    Format {{ $proofFormatHint }}, maksimal {{ $proofMaxMegabytes }} MB.
                                    Pastikan nominal dan waktu transfer terbaca jelas.
                                </p>

                                <x-field-error name="proof" />
                            </div>

                            <x-alert variant="info" class="u-mb-4">
                                Mengunggah bukti tidak langsung melunasi pesanan.
                                Admin akan memeriksa pembayaran terlebih dahulu.
                            </x-alert>

                            <x-button block>Kirim Bukti Pembayaran</x-button>
                        </form>
                    @endif
                @elseif ($order->isAwaitingVerification())
                    <x-alert variant="info">
                        Bukti pembayaran kamu sudah kami terima dan sedang diperiksa
                        admin. Kuota tiket tetap kami tahan selama proses ini.
                    </x-alert>
                @elseif ($order->isPaid())
                    <x-alert variant="success" class="u-mb-4">
                        Pembayaran sudah disetujui dan e-ticket kamu sudah terbit.
                    </x-alert>

                    @if ($order->ticket)
                        <x-button :href="route('tickets.show', $order->access_token)">
                            Lihat E-Ticket
                        </x-button>
                    @endif
                @elseif ($order->isRejected())
                    <x-alert variant="error">
                        Pembayaran kamu ditolak admin.
                        @if ($order->rejection_reason)
                            Alasan: {{ $order->rejection_reason }}
                        @endif
                    </x-alert>
                @elseif ($order->hasExpired())
                    <x-alert variant="error" class="u-mb-4">
                        Batas waktu pembayaran sudah terlewati, sehingga pesanan ini
                        tidak lagi berlaku dan kuotanya telah dilepas.
                    </x-alert>

                    <x-button variant="secondary" size="sm" :href="route('events.index')">
                        Pesan Ulang
                    </x-button>
                @else
                    <x-alert variant="error">
                        Pesanan ini tidak dapat dilanjutkan. Status saat ini:
                        {{ $order->status->label() }}.
                    </x-alert>
                @endif
            </x-card>
        </div>
    </div>
@endsection

{{--
    Plain Blade rather than a markdown mail component: the project has not
    published Laravel's mail views, and this keeps the template dependency
    free. The QR is not embedded; the secret link below opens it.
--}}
<p>Halo {{ $order->participant_name }},</p>

<p>
    Pembayaran kamu untuk <strong>{{ $event->name }}</strong> sudah disetujui
    dan e-ticket kamu telah diterbitkan.
</p>

<p>
    <strong>Kode Pesanan:</strong> {{ $order->order_reference }}<br>
    <strong>Waktu:</strong> {{ $event->starts_at->translatedFormat('d M Y, H:i') }} WIB<br>
    <strong>Lokasi:</strong> {{ $event->location }}
</p>

<p>
    Buka e-ticket kamu di tautan berikut, lalu tunjukkan QR-nya saat check-in:
</p>

<p><a href="{{ $ticketUrl }}">{{ $ticketUrl }}</a></p>

<p>
    Simpan tautan ini dan jangan dibagikan kepada orang lain, karena tautan
    tersebut adalah satu-satunya akses ke e-ticket kamu.
</p>

<p>Terima kasih,<br>{{ config('app.name') }}</p>

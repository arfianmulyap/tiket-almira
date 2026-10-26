{{--
    Plain Blade rather than a markdown mail component: the project has not
    published Laravel's mail views, and this keeps the template dependency
    free.

    Phase 13 attaches the e-ticket as a PDF, so the message stays useful when
    the participant is at the gate with no connection. The QR image itself is
    not inlined here; it is inside the attachment and on the linked page, which
    keeps the token out of the message body where it is easiest to forward by
    accident.
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
    E-ticket kamu terlampir pada email ini dalam bentuk PDF. Simpan berkasnya
    atau tunjukkan langsung dari ponsel saat check-in — cukup QR di dalamnya yang
    dipindai petugas.
</p>

<p>
    Versi terbaru juga selalu dapat dibuka di tautan berikut:
</p>

<p><a href="{{ $ticketUrl }}">{{ $ticketUrl }}</a></p>

<p>
    Simpan tautan ini dan jangan dibagikan kepada orang lain, karena tautan
    tersebut adalah satu-satunya akses ke e-ticket kamu. Satu QR hanya berlaku
    untuk satu kali check-in.
</p>

<p>
    Jika email ini bukan untuk kamu, atau kamu merasa menerima tiket yang salah,
    hubungi penyelenggara. Jangan teruskan email ini kepada orang lain.
</p>

<p>Terima kasih,<br>{{ config('app.name') }}</p>

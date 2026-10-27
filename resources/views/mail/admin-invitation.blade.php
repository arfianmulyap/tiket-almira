{{--
    Plain Blade rather than a markdown mail component, matching the e-ticket
    mail: the project has not published Laravel's mail views.

    The link below contains the one-time token. It is not repeated anywhere in
    the database, so this message is the only place it exists.
--}}
<p>Halo {{ $name ?? 'Admin' }},</p>

<p>
    @if ($inviterName)
        {{ $inviterName }} mengundang Anda
    @else
        Anda diundang
    @endif
    untuk mengelola <strong>{{ config('app.name') }}</strong>
    @if ($roleName)
        dengan role <strong>{{ $roleName }}</strong>
    @endif
    .
</p>

<p>
    Buat password Anda melalui tautan berikut. Tautan ini hanya dapat dipakai
    sekali dan berlaku sampai
    <strong>{{ $expiresAt?->translatedFormat('d M Y, H:i') }} WIB</strong>.
</p>

<p><a href="{{ $acceptUrl }}">{{ $acceptUrl }}</a></p>

<p>
    Jika tautan sudah kedaluwarsa, minta Super Admin mengirim ulang undangan
    dari menu Akun Admin. Jangan teruskan email ini kepada orang lain.
</p>

<p>Terima kasih,<br>{{ config('app.name') }}</p>

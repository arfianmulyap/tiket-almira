@extends('layouts.admin')

@section('title', 'Check-in Peserta')

@section('content')
    <x-page-header
        title="Check-in Peserta"
        description="Pindai QR pada e-ticket peserta, atau masukkan token tiket secara manual."
    />

    <div class="checkin-layout">
        <div>
            <x-card title="Pindai QR">
                {{--
                    The scanner is progressive enhancement. It is announced as
                    unsupported by default and only switched on by the script
                    below once the browser proves it has a barcode decoder, so
                    a device that cannot scan never shows a dead camera panel.
                --}}
                <div
                    id="scanner"
                    class="scanner"
                    data-state="unsupported"
                    data-endpoint="{{ route('admin.check-in.store') }}"
                >
                    <div class="scanner-viewport">
                        {{-- muted + playsinline are required for autoplay on
                             mobile Safari and Chrome. --}}
                        <video id="scanner-video" playsinline muted></video>

                        <p class="scanner-hint" id="scanner-hint">
                            Arahkan kamera ke QR pada e-ticket peserta.
                        </p>
                    </div>

                    <div class="scanner-actions">
                        <x-button type="button" id="scanner-start">
                            Nyalakan Kamera
                        </x-button>

                        <x-button
                            type="button"
                            variant="secondary"
                            id="scanner-stop"
                            hidden
                        >
                            Matikan Kamera
                        </x-button>
                    </div>

                    {{-- Rendered server-side, shown only when the script
                         confirms the browser has no decoder. --}}
                    <div class="scanner-unsupported">
                        <x-alert variant="warning">
                            <p class="alert-title">Pemindai kamera tidak tersedia di perangkat ini</p>

                            Browser ini belum mendukung pembacaan QR secara
                            langsung. Gunakan Chrome di Android, atau masukkan
                            token tiket melalui formulir manual di bawah.
                        </x-alert>
                    </div>
                </div>

                {{-- Scan results land here. aria-live so the outcome is
                     announced without the officer having to look for it. --}}
                <div id="scan-result" class="scan-result" aria-live="assertive" role="status"></div>
            </x-card>

            <x-card title="Check-in Manual" class="u-mt-6">
                {{--
                    Always rendered, never hidden behind JavaScript. This is
                    the only path that works when the camera is unavailable,
                    permission is denied, or a QR is too damaged to read, so
                    hiding it would strand the officer at the gate.
                --}}
                <p class="text-sm text-muted u-mb-4">
                    Salin token dari e-ticket peserta jika QR tidak dapat
                    dipindai. Token terdiri dari 64 karakter huruf dan angka.
                </p>

                <form method="POST" action="{{ route('admin.check-in.store') }}">
                    @csrf

                    <div class="field">
                        <x-label for="token" :required="true">Token Tiket</x-label>

                        {{-- autocomplete off: a ticket token is a credential
                             and must not be retained by the browser on a
                             shared gate device. --}}
                        <x-input
                            name="token"
                            id="token"
                            :value="old('token')"
                            autocomplete="off"
                            autocapitalize="none"
                            spellcheck="false"
                            required
                        />

                        <x-field-error name="token" />
                    </div>

                    <x-button block>Check-in Peserta</x-button>
                </form>
            </x-card>
        </div>

        <div>
            <x-card title="Check-in Terakhir">
                @if ($recent->isEmpty())
                    <x-empty-state
                        title="Belum ada check-in"
                        description="Peserta yang berhasil check-in akan muncul di sini."
                    />
                @else
                    <ul class="checkin-log">
                        @foreach ($recent as $checkIn)
                            <li class="checkin-log-item">
                                <p class="checkin-log-name">
                                    {{ $checkIn->ticket->order->participant_name }}
                                </p>

                                <p class="checkin-log-meta">
                                    {{ $checkIn->ticket->order->order_reference }}
                                    &middot;
                                    {{ $checkIn->ticket->order->event->name }}
                                </p>

                                <p class="checkin-log-meta">
                                    {{ $checkIn->checked_in_at->translatedFormat('d M Y, H:i') }} WIB
                                    &middot;
                                    {{ $checkIn->operatorName() }}
                                </p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>
    </div>

    <script>
        /*
         * Camera QR scanner, written as progressive enhancement.
         *
         * Uses the native BarcodeDetector API rather than a bundled decoder
         * library: this project has no asset build (see partials/head), so a
         * library would have to be vendored by hand or loaded from a CDN. A
         * CDN script on an admin page that handles ticket credentials is not
         * a dependency worth adding.
         *
         * The trade-off is real and is handled, not hidden: BarcodeDetector
         * is absent on desktop Safari and Firefox, so those browsers are told
         * plainly that scanning is unavailable and are pointed at the manual
         * form, which always works.
         *
         * Nothing here decides whether a participant may enter. The server
         * validates the token, takes the lock, and returns the outcome; this
         * script only shows what the server said.
         */
        (function () {
            var root = document.getElementById('scanner');
            var video = document.getElementById('scanner-video');
            var hint = document.getElementById('scanner-hint');
            var startButton = document.getElementById('scanner-start');
            var stopButton = document.getElementById('scanner-stop');
            var resultBox = document.getElementById('scan-result');

            if (!root || !video || !startButton || !stopButton || !resultBox) {
                return;
            }

            var supportsCamera = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
            var supportsDetector = typeof window.BarcodeDetector !== 'undefined';

            if (!supportsCamera || !supportsDetector) {
                // Leave data-state="unsupported": CSS hides the viewport and
                // the camera buttons, and reveals the explanatory alert.
                return;
            }

            root.setAttribute('data-state', 'idle');

            var endpoint = root.getAttribute('data-endpoint');
            var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            var detector = new window.BarcodeDetector({ formats: ['qr_code'] });
            var stream = null;
            var timer = null;
            var busy = false;

            // Tokens already handled in this session. A camera re-reads the
            // same QR many times per second, and without this every frame
            // would POST again, flooding the server and making the result
            // panel flicker between "admitted" and "already used" for a
            // participant who did nothing wrong.
            var seen = Object.create(null);

            function escapeHtml(value) {
                return String(value === null || value === undefined ? '' : value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function alertHtml(variant, title, body) {
                return '<div class="alert alert-' + variant + '" role="alert"><div>' +
                    '<p class="alert-title">' + escapeHtml(title) + '</p>' +
                    '<p>' + escapeHtml(body) + '</p>' +
                    '</div></div>';
            }

            function renderResult(payload, variant) {
                var lines = [];

                if (payload.participant_name) {
                    lines.push('<p class="scan-result-name">' + escapeHtml(payload.participant_name) + '</p>');
                }

                if (payload.order_reference) {
                    lines.push('<p class="scan-result-meta">' + escapeHtml(payload.order_reference) + '</p>');
                }

                if (payload.event_name) {
                    lines.push('<p class="scan-result-meta">' + escapeHtml(payload.event_name) + '</p>');
                }

                if (payload.checked_in_at) {
                    lines.push(
                        '<p class="scan-result-meta">Tercatat ' + escapeHtml(payload.checked_in_at) +
                        ' WIB oleh ' + escapeHtml(payload.operator_name || '-') + '</p>'
                    );
                }

                resultBox.innerHTML =
                    '<div class="alert alert-' + variant + '" role="alert">' +
                        '<div>' +
                            '<p class="alert-title">' + escapeHtml(payload.label) + '</p>' +
                            '<p>' + escapeHtml(payload.description) + '</p>' +
                            lines.join('') +
                        '</div>' +
                    '</div>';
            }

            function submit(token) {
                busy = true;

                fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({ token: token }),
                })
                    .then(function (response) {
                        return response.json().then(function (payload) {
                            // The server's variant is used as-is. The client
                            // never upgrades a refusal into a success.
                            renderResult(payload, payload.variant || 'error');
                        });
                    })
                    .catch(function () {
                        resultBox.innerHTML = alertHtml(
                            'error',
                            'Gagal menghubungi server',
                            'Periksa koneksi, lalu pindai ulang atau gunakan formulir manual.'
                        );
                    })
                    .then(function () {
                        busy = false;
                    });
            }

            function tick() {
                if (busy || video.readyState !== video.HAVE_ENOUGH_DATA) {
                    return;
                }

                detector
                    .detect(video)
                    .then(function (codes) {
                        if (!codes.length) {
                            return;
                        }

                        var token = (codes[0].rawValue || '').trim();

                        if (!token || seen[token]) {
                            return;
                        }

                        seen[token] = true;

                        submit(token);
                    })
                    .catch(function () {
                        // A single undecodable frame is normal while the
                        // camera focuses; the next tick tries again.
                    });
            }

            function start() {
                navigator.mediaDevices
                    .getUserMedia({ video: { facingMode: 'environment' } })
                    .then(function (mediaStream) {
                        stream = mediaStream;
                        video.srcObject = mediaStream;

                        return video.play();
                    })
                    .then(function () {
                        root.setAttribute('data-state', 'scanning');
                        startButton.hidden = true;
                        stopButton.hidden = false;
                        hint.textContent = 'Arahkan kamera ke QR pada e-ticket peserta.';

                        timer = window.setInterval(tick, 400);
                    })
                    .catch(function (error) {
                        // Permission denied is the common case and must be
                        // explained rather than failing silently (Phase 08
                        // acceptance criteria).
                        root.setAttribute('data-state', 'error');

                        var message = error && error.name === 'NotAllowedError'
                            ? 'Izin kamera ditolak. Berikan izin kamera pada browser, atau gunakan formulir check-in manual di bawah.'
                            : 'Kamera tidak dapat diakses. Pastikan tidak ada aplikasi lain yang memakai kamera, atau gunakan formulir manual.';

                        resultBox.innerHTML = alertHtml('warning', 'Kamera tidak aktif', message);
                    });
            }

            function stop() {
                if (timer) {
                    window.clearInterval(timer);
                    timer = null;
                }

                if (stream) {
                    stream.getTracks().forEach(function (track) {
                        track.stop();
                    });
                    stream = null;
                }

                video.srcObject = null;
                root.setAttribute('data-state', 'idle');
                startButton.hidden = false;
                stopButton.hidden = true;
            }

            startButton.addEventListener('click', start);
            stopButton.addEventListener('click', stop);

            // Release the camera when leaving the page so the indicator light
            // does not stay on.
            window.addEventListener('pagehide', stop);
        })();
    </script>
@endsection

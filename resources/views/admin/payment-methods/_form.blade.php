{{--
    Shared payment method form for create and edit (Phase 12B).

    `$method` is null on create; `$types` is every case of PaymentMethodType.

    Detail fields are rendered one panel per type as `details[<type>][<field>]`.
    Only the selected type's panel is stored, so switching type never requires
    clearing what was already typed, and the server validates exactly the fields
    the selected type requires.

    A Phase 12 method may still carry detail rows whose label no screen defines
    ("Biaya Admin"). Those are shown read-only and preserved on save rather than
    silently dropped.
--}}
@php
    $method = $method ?? null;

    // Details are global and shared by every event; only activation is per
    // event, which is managed on the event's own screen.
    $selectedType = old('type', $method?->type?->value)
        ?? \App\Enums\PaymentMethodType::BankTransfer->value;

    // Values for the stored type, looked up by the field a row belongs to rather
    // than by exact label, so a row typed as "No. Rekening" is still recognised.
    $storedValues = [];
    $extraRows = [];

    foreach ($method?->account ?? [] as $row) {
        if (! is_array($row)) {
            continue;
        }

        $label = trim((string) ($row['label'] ?? ''));
        $value = trim((string) ($row['value'] ?? ''));

        if ($label === '' || $value === '') {
            continue;
        }

        $field = \App\Enums\PaymentMethodType::fieldForLabel($label);

        if ($field === null || isset($storedValues[$field])) {
            $extraRows[] = ['label' => $label, 'value' => $value];

            continue;
        }

        $storedValues[$field] = $value;
    }

    $qrMimes = collect(config('payments.qr_image.mimes', ['jpg', 'jpeg', 'png', 'webp']))
        ->map(fn (string $mime) => strtoupper($mime))
        ->implode(', ');
    $qrAccept = collect(config('payments.qr_image.mimes', ['jpg', 'jpeg', 'png', 'webp']))
        ->map(fn (string $mime) => '.'.$mime)
        ->implode(',');
    $qrMaxMegabytes = round(((int) config('payments.qr_image.max_kilobytes', 2048)) / 1024, 1);
@endphp

<x-card title="Identitas Metode">
    <div class="field">
        <x-label for="label" required>Nama metode</x-label>

    <x-input
            name="label"
            :value="old('label', $method?->label)"
            required
            maxlength="120"
            autocomplete="off"
        />

        <p class="field-hint">
            Nama ini yang tampil pada pilihan metode pembayaran peserta, misalnya
            Transfer Bank BSI. Untuk metode yang belum dikonfirmasi, tandai sebagai
            data contoh di bawah.
        </p>

        <x-field-error name="label" />
    </div>

@if ($method === null)
    <div class="field">
        <x-label for="key" required>Kunci metode</x-label>

        <x-input
            name="key"
            :value="old('key')"
            required
            maxlength="64"
            autocomplete="off"
            placeholder="Contoh: bank_bsi"
        />

        <p class="field-hint">
            Pengenal tetap yang dipakai pembayaran dan snapshot pesanan. Gunakan
            huruf kecil, angka, dan garis bawah. Kunci tidak dapat diubah setelah
            metode dibuat.
        </p>

        <x-field-error name="key" />
    </div>
@else
    <div class="field">
        <p class="label">Kunci metode</p>
        <p class="text-sm"><code>{{ $method->key }}</code></p>
        <p class="field-hint">
            Kunci tidak dapat diubah karena dipakai oleh pembayaran dan snapshot
            pesanan yang sudah ada.
        </p>
    </div>
@endif

<div class="field">
        <x-label for="type" required>Tipe pembayaran</x-label>

        <x-select name="type" required>
            @foreach ($types as $type)
                <option value="{{ $type->value }}" @selected($selectedType === $type->value)>
                    {{ $type->label() }}
                </option>
            @endforeach
        </x-select>

        <p class="field-hint">
            Tipe menentukan detail mana yang wajib diisi. Hanya detail untuk tipe
            yang dipilih yang disimpan.
        </p>

        <x-field-error name="type" />
    </div>
</x-card>

<x-card
    title="Detail Penerima"
    description="Kolom yang wajib diisi mengikuti tipe pembayaran yang dipilih. Hanya detail untuk tipe terpilih yang disimpan."
    variant="muted"
>
    @error('details')
        <p class="field-error u-mb-4">{{ $message }}</p>
    @enderror

    {{-- One panel per type. The script further down hides the panels that do not
         match the selected type; without JavaScript every panel stays visible
         and the server still validates only the selected one. --}}
    @foreach ($types as $type)
        <div class="stack u-mt-4" data-payment-type-panel="{{ $type->value }}">
            <p class="section-title">{{ $type->label() }}</p>
            <p class="text-sm text-muted u-mb-4">{{ $type->description() }}</p>

            @foreach ($type->details() as $field => $spec)
                <div class="field">
                    <x-label
                        :for="'details-'.$type->value.'-'.$field"
                        :required="$spec['required']"
                    >
                        {{ $spec['label'] }}
                    </x-label>

                    <x-input
                        :name="'details['.$type->value.']['.$field.']'"
                        :id="'details-'.$type->value.'-'.$field"
                        :value="old('details.'.$type->value.'.'.$field, $storedValues[$field] ?? null)"
                        :placeholder="$spec['placeholder']"
                        maxlength="200"
                        autocomplete="off"
                    />

                    @if ($spec['hint'])
                        <p class="field-hint">{{ $spec['hint'] }}</p>
                    @endif

                    <x-field-error :name="'details.'.$type->value.'.'.$field" />
                </div>
            @endforeach
        </div>
    @endforeach

    @if ($extraRows !== [])
        <div class="u-mt-6">
            <p class="section-title">Detail tambahan dari data lama</p>

            <p class="field-hint u-mb-4">
                Baris berikut tidak cocok dengan kolom tipe mana pun, jadi tidak
                dapat diubah di sini. Baris ini tetap disimpan dan tetap tampil
                pada instruksi pembayaran, termasuk pada pesanan lama.
            </p>

            <dl class="detail-list">
                @foreach ($extraRows as $row)
                    <div class="detail-row">
                        <dt>{{ $row['label'] }}</dt>
                        <dd>{{ $row['value'] }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    @endif
</x-card>

<x-card title="Langkah Pembayaran">
    <div class="field">
        <x-label for="steps" required>Langkah pembayaran</x-label>

        <x-textarea
            name="steps"
            :value="old('steps', implode(\PHP_EOL, $method?->steps ?? []))"
            rows="6"
        />

        <p class="field-hint">
            Satu langkah per baris, maksimal 10 langkah. Ditampilkan berurutan di
            bawah detail penerima.
        </p>

        <x-field-error name="steps" />
    </div>
</x-card>

<x-card
    title="Gambar QR Merchant"
    description="Hanya dipakai tipe QRIS. Gambar ini ditampilkan kepada peserta agar dapat dipindai."
>
    <div class="field">
        <x-label for="qr_image" :required="$selectedType === 'qris'">Gambar QR</x-label>

        <x-input name="qr_image" type="file" :accept="$qrAccept" />

        <p class="field-hint">
            Format {{ $qrMimes }}, maksimal {{ $qrMaxMegabytes }} MB. Untuk tipe
            QRIS, unggah gambar QR atau isi ID Merchant / NMID.
        </p>

        <x-field-error name="qr_image" />
    </div>

    @if ($method?->qr_image_path)
        <div class="field u-mt-4">
            <p class="label">Gambar tersimpan</p>

            <img
                src="{{ $method->qrImageUrl() }}"
                alt="Gambar QR {{ $method->label }}"
                class="payment-qr-thumb"
            >

            <label class="choice u-mt-4" for="remove_qr_image">
                <input
                    type="checkbox"
                    id="remove_qr_image"
                    name="remove_qr_image"
                    value="1"
                    @checked((bool) old('remove_qr_image'))
                >
                <span>Hapus gambar QR yang tersimpan</span>
            </label>

            <p class="field-hint">
                Biarkan tidak dicentang bila hanya ingin mengganti gambar lewat
                unggahan di atas. Untuk QRIS, gambar tidak boleh dihapus bila ID
                Merchant juga kosong.
            </p>
        </div>
    @endif
</x-card>

<x-card title="Status Data">
    @php
        // Only a Super Admin may clear the placeholder flag, so the control is
        // not rendered for anyone else. Leaving the field out means "keep the
        // current status", which is what makes an ordinary edit by an admin with
        // `payment_methods.update` unable to publish details by accident — or to
        // hide a method the business already confirmed.
        $canPublishOfficial = auth()->user()?->can(
            'publishOfficialDetails',
            $method ?? new \App\Models\PaymentMethod
        ) === true;
    @endphp

    <div class="field">
        @if ($canPublishOfficial)
            {{-- Hidden field first: clearing the checkbox must submit an explicit
                 false, not omit the key. --}}
            <input type="hidden" name="is_dummy" value="0">

            <label class="choice" for="is_dummy">
                <input
                    type="checkbox"
                    id="is_dummy"
                    name="is_dummy"
                    value="1"
                    @checked((bool) old('is_dummy', $method?->is_dummy ?? true))
                >
                <span>Tandai sebagai data contoh (belum dikonfirmasi)</span>
            </label>

            <p class="field-hint">
                Metode bertanda data contoh tidak pernah ditampilkan kepada peserta
                di production. Hapus centang ini hanya setelah rekening, VA, atau
                QRIS dikonfirmasi pemilik bisnis.
            </p>

            <x-field-error name="is_dummy" />
        @else
            <p class="label">Status data</p>

            <p>
                @if ($method?->is_dummy ?? true)
                    <x-badge variant="warning">Data contoh</x-badge>
                @else
                    <x-badge variant="success">Data resmi</x-badge>
                @endif
            </p>

            <p class="field-hint">
                Hanya Super Admin yang dapat mengubah status ini. Perubahan lain
                pada metode ini tidak mengubah status data.
            </p>
        @endif
    </div>
</x-card>

<script>
    // Progressive enhancement only. Without JavaScript every detail panel stays
    // visible — nothing is hidden behind a feature the dashboard otherwise does
    // not require — and the server still stores one type's details.
    (function () {
        var select = document.getElementById('type');
        var panels = document.querySelectorAll('[data-payment-type-panel]');

        if (!select || !panels.length) {
            return;
        }

        function apply() {
            panels.forEach(function (panel) {
                panel.hidden = panel.getAttribute('data-payment-type-panel') !== select.value;
            });
        }

        select.addEventListener('change', apply);
        apply();
    })();
</script>
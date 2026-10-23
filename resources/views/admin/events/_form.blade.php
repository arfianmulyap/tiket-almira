{{--
    Shared event form fields for create and edit.

    `$event` is null on create. Values fall back to old() so a failed
    validation round-trip never loses the admin's input.
--}}
@php
    $event = $event ?? null;
@endphp

<div class="field">
    <x-label for="name" required>Nama Event</x-label>
    <x-input
        name="name"
        :value="old('name', $event?->name)"
        required
        maxlength="160"
        autocomplete="off"
    />
    <x-field-error name="name" />
</div>

<div class="field">
    <x-label for="location" required>Lokasi</x-label>
    <x-input
        name="location"
        :value="old('location', $event?->location)"
        required
        maxlength="200"
        autocomplete="off"
    />
    <x-field-error name="location" />
</div>

<div class="field">
    <x-label for="starts_at" required>Waktu Mulai</x-label>
    <x-input
        name="starts_at"
        type="datetime-local"
        :value="old('starts_at', $event?->starts_at?->format('Y-m-d\TH:i'))"
        required
    />
    <p class="field-hint">Gunakan waktu WIB (GMT+7).</p>
    <x-field-error name="starts_at" />
</div>

<div class="field">
    <x-label for="ticket_price" required>Harga Tiket</x-label>
    <x-input
        name="ticket_price"
        type="number"
        :value="old('ticket_price', $event?->ticket_price)"
        required
        min="0"
        step="1"
        inputmode="numeric"
    />
    <p class="field-hint">Dalam rupiah penuh tanpa titik atau koma. Contoh: 200000.</p>
    <x-field-error name="ticket_price" />
</div>

<div class="field">
    <x-label for="quota" required>Kuota Tiket</x-label>
    <x-input
        name="quota"
        type="number"
        :value="old('quota', $event?->quota)"
        required
        min="1"
        step="1"
        inputmode="numeric"
    />
    @if ($event && $event->reservedQuota() > 0)
        <p class="field-hint">
            {{ $event->reservedQuota() }} slot sudah terpakai dan tidak dapat dikurangi.
        </p>
    @endif
    <x-field-error name="quota" />
</div>

<div class="field">
    <x-label for="status" required>Status</x-label>
    <x-select name="status" required>
        @foreach ($statuses as $status)
            <option
                value="{{ $status->value }}"
                @selected(old('status', $event?->status?->value ?? 'draft') === $status->value)
            >
                {{ $status->label() }}
            </option>
        @endforeach
    </x-select>
    <p class="field-hint">
        Hanya event berstatus "Dipublikasikan" yang dapat dipesan peserta.
    </p>
    <x-field-error name="status" />
</div>

<div class="field">
    <x-label for="description">Deskripsi</x-label>
    <x-textarea name="description" :value="old('description', $event?->description)" />
    <x-field-error name="description" />
</div>

<div class="field">
    <x-label for="poster">Poster</x-label>

    @if ($event?->posterUrl())
        <p class="text-sm text-muted u-mb-4">Poster saat ini:</p>
        <img
            src="{{ $event->posterUrl() }}"
            alt="Poster {{ $event->name }}"
            class="event-poster-thumb u-mb-4"
        >
    @endif

    <x-input name="poster" type="file" accept="image/jpeg,image/png,image/webp" />
    <p class="field-hint">
        JPG, PNG, atau WebP. Maksimal 4 MB. Wajib diunggah sebelum event dipublikasikan.
        @if ($event?->poster_path)
            Biarkan kosong untuk mempertahankan poster saat ini.
        @endif
    </p>
    <x-field-error name="poster" />
</div>

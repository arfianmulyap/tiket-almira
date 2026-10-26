@extends('layouts.admin')

@section('title', 'Ubah Metode Pembayaran')

@section('content')
    @php
        // old() first so a validation error never discards what the Super
        // Admin typed. Two blank rows are always offered so a line can be
        // added without JavaScript; blank rows are dropped on save.
        $rows = old('account', $method->account ?? []);

        if (! is_array($rows)) {
            $rows = [];
        }

        $rows = array_values($rows);
        $rows[] = ['label' => '', 'value' => ''];
        $rows[] = ['label' => '', 'value' => ''];

        $stepsValue = old('steps', implode("\n", $method->steps ?? []));
    @endphp

    <x-page-header
        title="Ubah {{ $method->label }}"
        description="Detail penerima dan langkah pembayaran yang dilihat peserta pada pesanan baru."
    >
        <x-slot:actions>
            <x-button variant="secondary" :href="route('admin.payment-methods.index')">
                Kembali
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @if ($method->is_dummy && app()->environment('production'))
        <x-alert variant="error" title="Metode ini masih data contoh" class="u-mb-6">
            Selama ditandai sebagai data contoh, metode ini tidak pernah ditampilkan
            kepada peserta di production. Isi detail resmi lalu hapus centang
            &ldquo;Tandai sebagai data contoh&rdquo;.
        </x-alert>
    @endif

    <form
        method="POST"
        action="{{ route('admin.payment-methods.update', $method) }}"
        class="stack"
    >
        @csrf
        @method('PUT')

        <x-card title="Identitas Metode">
            <x-field
                name="label"
                label="Nama metode"
                :value="$method->label"
                required
                hint="Nama ini tampil pada pilihan metode pembayaran peserta, misalnya Transfer Bank BSI."
            />

            <div class="field">
                <p class="label">Kunci metode</p>
                <p class="text-sm"><code>{{ $method->key }}</code></p>
                <p class="field-hint">
                    Kunci tidak dapat diubah karena dipakai oleh pembayaran dan
                    snapshot pesanan yang sudah ada.
                </p>
            </div>
        </x-card>

        <x-card
            title="Detail Penerima"
            description="Rekening bank, nomor VA, atau identitas merchant QRIS. Satu baris berisi label dan nilainya."
        >
            @error('account')
                <p class="field-error u-mb-4">{{ $message }}</p>
            @enderror

            <div class="grid grid-cols-2">
                @foreach ($rows as $index => $row)
                    @php
                        $labelValue = is_array($row) ? ($row['label'] ?? '') : '';
                        $detailValue = is_array($row) ? ($row['value'] ?? '') : '';
                    @endphp

                    <div class="field">
                        <x-label :for="'account-'.$index.'-label'">Label</x-label>

                        <x-input
                            :name="'account['.$index.'][label]'"
                            :id="'account-'.$index.'-label'"
                            :value="$labelValue"
                            placeholder="Contoh: Bank"
                        />

                        @error('account.'.$index.'.label')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <x-label :for="'account-'.$index.'-value'">Nilai</x-label>

                        <x-input
                            :name="'account['.$index.'][value]'"
                            :id="'account-'.$index.'-value'"
                            :value="$detailValue"
                            placeholder="Contoh: BSI 1234567890"
                        />
                    </div>
                @endforeach
            </div>

            <p class="field-hint">
                Baris kosong diabaikan. Maksimal 8 baris terisi. Jangan mencantumkan
                data yang belum dikonfirmasi pemilik bisnis sebagai data resmi.
            </p>
        </x-card>

        <x-card
            title="Langkah Pembayaran"
            description="Ditampilkan berurutan di bawah detail penerima."
        >
            <div class="field">
                <x-label for="steps" :required="true">Langkah</x-label>

                <x-textarea name="steps" :value="$stepsValue" :rows="7" required />

                <p class="field-hint">
                    Satu langkah per baris. Maksimal 10 langkah, 200 karakter per langkah.
                </p>

                <x-field-error name="steps" />
            </div>
        </x-card>

        <x-card title="Status Data">
            <div class="field">
                {{-- Hidden field makes an unchecked box submit an explicit
                     "0", so the request can distinguish "cleared" from
                     "field missing" without guessing. --}}
                <input type="hidden" name="is_dummy" value="0">

                <label class="cluster" for="is_dummy">
                    <input
                        type="checkbox"
                        id="is_dummy"
                        name="is_dummy"
                        value="1"
                        @checked((bool) old('is_dummy', $method->is_dummy))
                    >

                    <span>Tandai sebagai data contoh (belum dikonfirmasi)</span>
                </label>

                <p class="field-hint">
                    Metode yang ditandai data contoh tidak pernah ditampilkan kepada
                    peserta di production. Hapus centang hanya setelah rekening, VA,
                    atau QRIS di atas dikonfirmasi pemilik bisnis.
                </p>

                <x-field-error name="is_dummy" />
            </div>
        </x-card>

        <div class="cluster">
            <x-button>Simpan Perubahan</x-button>

            <x-button variant="secondary" :href="route('admin.payment-methods.index')">
                Batal
            </x-button>
        </div>
    </form>
@endsection

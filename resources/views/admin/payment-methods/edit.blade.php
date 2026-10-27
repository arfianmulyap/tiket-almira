@extends('layouts.admin')

@section('title', 'Ubah Detail Metode Pembayaran')

@section('content')
    <x-page-header
        title="Ubah Detail Metode"
        :description="$method->label"
    >
        <x-slot:actions>
            <x-button variant="secondary" :href="route('admin.payment-methods.index')">
                Kembali
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <x-alert variant="info" class="u-mb-6">
        Perubahan berlaku untuk <strong>pesanan baru</strong> pada semua event.
        Setiap pesanan menyimpan salinan instruksi pembayaran saat dibuat, sehingga
        pesanan lama tetap menampilkan instruksi yang sudah diberikan.
        Kunci metode <code>{{ $method->key }}</code> tidak diubah.
    </x-alert>

    @if ($method->is_dummy && app()->environment('production'))
        <x-alert variant="error" title="Metode ini masih data contoh" class="u-mb-6">
            Selama ditandai sebagai data contoh, metode ini tidak pernah ditampilkan
            kepada peserta di production. Isi detail resmi lalu hapus centang
            &ldquo;Tandai sebagai data contoh&rdquo;.
        </x-alert>
    @endif

    @unless ($method->isConfigured())
        <x-alert variant="warning" title="Detail belum lengkap" class="u-mb-6">
            Metode ini belum ditawarkan kepada peserta karena belum lengkap:
            {{ implode(', ', $method->missingDetails()) }}.
        </x-alert>
    @endunless

    {{--
        Confirmation on submit: this form decides where participant money is
        sent, and an account number is exactly the kind of field that is
        plausible-looking when mistyped.
    --}}
    <form
        method="POST"
        action="{{ route('admin.payment-methods.update', $method) }}"
        enctype="multipart/form-data"
        class="stack"
        novalidate
        onsubmit="return confirm('Simpan perubahan detail metode ini? Detail yang berubah hanya berlaku untuk pesanan baru.')"
    >
        @csrf
        @method('PUT')

        @include('admin.payment-methods._form', ['method' => $method, 'types' => $types])

        <div class="cluster">
            <x-button>Simpan Perubahan</x-button>

            <x-button variant="secondary" :href="route('admin.payment-methods.index')">
                Batal
            </x-button>
        </div>
    </form>

    @can('delete', $method)
        <x-card title="Hapus Metode" class="u-mt-6">
            @if ($method->hasHistoricalDependencies())
                <x-alert variant="warning">
                    Metode ini sudah dipakai pembayaran atau tersimpan pada snapshot
                    pesanan, sehingga tidak dapat dihapus. Nonaktifkan saja agar
                    tidak lagi ditawarkan kepada pesanan baru; riwayat tetap utuh.
                </x-alert>
            @else
                <p class="text-sm text-muted u-mb-4">
                    Metode ini belum pernah dipakai pesanan atau pembayaran mana pun.
                    Menghapusnya juga menghapus pengaturan aktif/nonaktifnya pada
                    setiap event dan gambar QR yang tersimpan.
                </p>

                <form
                    method="POST"
                    action="{{ route('admin.payment-methods.destroy', $method) }}"
                    onsubmit="return confirm('Hapus metode {{ $method->label }}? Tindakan ini tidak dapat dibatalkan.')"
                >
                    @csrf
                    @method('DELETE')

                    <x-button variant="danger">Hapus Metode</x-button>
                </form>
            @endif
        </x-card>
    @endcan
@endsection
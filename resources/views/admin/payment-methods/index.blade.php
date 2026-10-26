@extends('layouts.admin')

@section('title', 'Metode Pembayaran')

@section('content')
    <x-page-header
        title="Metode Pembayaran"
        description="Atur metode pembayaran manual yang ditawarkan kepada peserta."
    />

    <x-alert variant="info" class="u-mb-6">
        Perubahan di halaman ini hanya berlaku untuk <strong>pesanan baru</strong>.
        Setiap pesanan menyimpan salinan instruksi pembayaran saat pesanan dibuat,
        sehingga instruksi pada pesanan lama tidak ikut berubah.
    </x-alert>

    <x-card>
        @if ($methods->isEmpty())
            <x-empty-state
                title="Belum ada metode pembayaran"
                description="Tabel metode pembayaran belum diisi. Jalankan php artisan db:seed --class=PaymentMethodSeeder --force untuk memuat katalog awal."
            />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">Metode</th>
                            <th scope="col">Status</th>
                            <th scope="col">Data</th>
                            <th scope="col">Rincian</th>
                            <th scope="col">Terakhir diubah</th>
                            <th scope="col"><span class="u-visually-hidden">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($methods as $method)
                            <tr>
                                <td>
                                    <p>{{ $method->label }}</p>
                                    <p class="text-xs text-muted">
                                        <code>{{ $method->key }}</code>
                                    </p>
                                </td>
                                <td>
                                    @if ($method->is_active)
                                        <x-badge variant="success">Aktif</x-badge>
                                    @else
                                        <x-badge>Nonaktif</x-badge>
                                    @endif

                                    @unless ($method->is_active)
                                        <p class="text-xs text-muted">
                                            Tidak ditawarkan ke pesanan baru.
                                        </p>
                                    @endunless
                                </td>
                                <td>
                                    @if ($method->is_dummy)
                                        <x-badge variant="warning">Data contoh</x-badge>

                                        @if ($method->is_active)
                                            <p class="text-xs text-muted">
                                                Disembunyikan di production sampai
                                                ditandai sebagai data resmi.
                                            </p>
                                        @endif
                                    @else
                                        <x-badge variant="success">Data resmi</x-badge>
                                    @endif
                                </td>
                                <td>
                                    {{ count($method->account ?? []) }} baris detail,
                                    {{ count($method->steps ?? []) }} langkah
                                </td>
                                <td>
                                    @if ($method->updated_at)
                                        {{ $method->updated_at->translatedFormat('d M Y, H:i') }} WIB
                                        @if ($method->updatedBy)
                                            <p class="text-xs text-muted">
                                                oleh {{ $method->updatedBy->name }}
                                            </p>
                                        @endif
                                    @else
                                        <span class="text-xs text-muted">Belum diubah</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="cluster">
                                        @can('updateDetails', $method)
                                            <x-button
                                                variant="secondary"
                                                size="sm"
                                                :href="route('admin.payment-methods.edit', $method)"
                                            >
                                                Ubah Detail
                                            </x-button>
                                        @endcan

                                        @can('toggle', $method)
                                            <form
                                                method="POST"
                                                action="{{ route('admin.payment-methods.toggle', $method) }}"
                                            >
                                                @csrf
                                                <input
                                                    type="hidden"
                                                    name="is_active"
                                                    value="{{ $method->is_active ? 0 : 1 }}"
                                                >

                                                <x-button
                                                    size="sm"
                                                    :variant="$method->is_active ? 'secondary' : 'primary'"
                                                >
                                                    {{ $method->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                                </x-button>
                                            </form>
                                        @endcan

                                        @cannot('toggle', $method)
                                            @cannot('updateDetails', $method)
                                                <span class="text-xs text-muted">Hanya lihat</span>
                                            @endcannot
                                        @endcannot
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="text-sm text-muted u-mt-4">
                Hanya Super Admin yang dapat mengubah detail rekening, VA, dan QRIS
                serta menandai sebuah metode sebagai data resmi. Menandai data contoh
                sebagai resmi berarti detail tersebut sudah dikonfirmasi pemilik bisnis.
            </p>
        @endif
    </x-card>
@endsection

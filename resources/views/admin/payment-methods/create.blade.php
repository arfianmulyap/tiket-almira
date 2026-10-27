@extends('layouts.admin')

@section('title', 'Tambah Metode Pembayaran')

@section('content')
    <x-page-header
        title="Tambah Metode Pembayaran"
        description="Tentukan nama, tipe, dan detail penerima. Detail rekening, VA, atau QRIS bersifat global: berlaku untuk semua event."
    >
        <x-slot:actions>
            <x-button variant="secondary" :href="route('admin.payment-methods.index')">
                Kembali
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <x-alert variant="warning" class="u-mb-6">
        Jangan mengarang detail pembayaran. Metode baru disimpan sebagai
        <strong>data contoh</strong> dan <strong>belum ditawarkan</strong> kepada
        peserta sampai detail resmi dikonfirmasi pemilik bisnis, ditandai sebagai
        data resmi oleh Super Admin, lalu diaktifkan.
    </x-alert>

    <form
        method="POST"
        action="{{ route('admin.payment-methods.store') }}"
        enctype="multipart/form-data"
        class="stack"
        novalidate
    >
        @csrf

        @include('admin.payment-methods._form', ['method' => null, 'types' => $types])

        <div class="cluster">
            <x-button>Tambah Metode</x-button>

            <x-button variant="secondary" :href="route('admin.payment-methods.index')">
                Batal
            </x-button>
        </div>
    </form>
@endsection
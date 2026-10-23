@extends('layouts.admin')

@section('title', 'Buat Event')

@section('content')
    <x-page-header
        title="Buat Event"
        description="Tentukan informasi, harga, dan kuota untuk event baru."
    />

    <x-card>
        {{-- enctype is required: the form carries the poster upload. --}}
        <form
            method="POST"
            action="{{ route('admin.events.store') }}"
            enctype="multipart/form-data"
            novalidate
        >
            @csrf

            @include('admin.events._form', ['event' => null, 'statuses' => $statuses])

            <div class="cluster u-mt-6">
                <x-button>Simpan Event</x-button>
                <x-button variant="secondary" :href="route('admin.events.index')">
                    Batal
                </x-button>
            </div>
        </form>
    </x-card>
@endsection

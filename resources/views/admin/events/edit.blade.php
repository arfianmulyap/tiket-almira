@extends('layouts.admin')

@section('title', 'Ubah Event')

@section('content')
    <x-page-header
        title="Ubah Event"
        :description="$event->name"
    />

    <x-card>
        <form
            method="POST"
            action="{{ route('admin.events.update', $event) }}"
            enctype="multipart/form-data"
            novalidate
        >
            @csrf
            @method('PUT')

            @include('admin.events._form', ['event' => $event, 'statuses' => $statuses])

            <div class="cluster u-mt-6">
                <x-button>Simpan Perubahan</x-button>
                <x-button variant="secondary" :href="route('admin.events.show', $event)">
                    Batal
                </x-button>
            </div>
        </form>
    </x-card>
@endsection

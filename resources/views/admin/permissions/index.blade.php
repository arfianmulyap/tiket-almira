@extends('layouts.admin')

@section('title', 'Permission')

@section('content')
    <x-page-header
        title="Permission"
        description="Katalog tindakan yang dapat diberikan ke sebuah role."
    >
        <x-slot:actions>
            @can('create', \App\Models\Permission::class)
                <x-button :href="route('admin.permissions.create')">Pulihkan Permission</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-alert variant="info" class="u-mb-4">
        Kunci permission ditentukan oleh kode aplikasi karena setiap kunci harus
        benar-benar diperiksa oleh backend. Layar ini hanya mengelola label,
        deskripsi, dan ketersediaan definisinya.
    </x-alert>

    <x-card>
        @if ($permissionGroups->isEmpty())
            <x-empty-state
                title="Belum ada permission"
                description="Jalankan php artisan db:seed --class=RolePermissionSeeder untuk memuat katalog permission."
            />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">Permission</th>
                            <th scope="col">Kunci</th>
                            <th scope="col" class="is-numeric">Dipakai</th>
                            <th scope="col"><span class="u-visually-hidden">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($permissionGroups as $group => $permissions)
                            <tr>
                                <th scope="colgroup" colspan="4">{{ $group }}</th>
                            </tr>

                            @foreach ($permissions as $permission)
                                <tr>
                                    <td>
                                        <p>{{ $permission->label }}</p>
                                        @if ($permission->description)
                                            <p class="text-xs text-muted">{{ $permission->description }}</p>
                                        @endif
                                    </td>
                                    <td><span class="text-xs text-muted">{{ $permission->name }}</span></td>
                                    <td class="is-numeric">{{ $permission->roles_count }} role</td>
                                    <td>
                                        <div class="cluster">
                                            @can('update', $permission)
                                                <x-button
                                                    variant="secondary"
                                                    size="sm"
                                                    :href="route('admin.permissions.edit', $permission)"
                                                >
                                                    Ubah
                                                </x-button>
                                            @endcan

                                            @can('delete', $permission)
                                                <form
                                                    method="POST"
                                                    action="{{ route('admin.permissions.destroy', $permission) }}"
                                                    onsubmit="return confirm('Hapus permission ini? Role yang memakainya akan kehilangan akses.')"
                                                >
                                                    @csrf
                                                    @method('DELETE')

                                                    <x-button variant="danger" size="sm">Hapus</x-button>
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
@endsection

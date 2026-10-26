@extends('layouts.admin')

@section('title', 'Role & Permission')

@section('content')
    <x-page-header
        title="Role & Permission"
        description="Setiap admin memiliki satu role, dan setiap tindakan di panel mengikuti permission role tersebut."
    >
        <x-slot:actions>
            @can('create', \App\Models\Role::class)
                <x-button :href="route('admin.roles.create')">Buat Role</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-card>
        @if ($roles->isEmpty())
            <x-empty-state
                title="Belum ada role"
                description="Role Super Admin dibuat otomatis oleh migrasi. Buat role baru untuk admin lain."
            >
                <x-slot:actions>
                    <x-button :href="route('admin.roles.create')">Buat Role</x-button>
                </x-slot:actions>
            </x-empty-state>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">Role</th>
                            <th scope="col">Tipe</th>
                            <th scope="col" class="is-numeric">Permission</th>
                            <th scope="col" class="is-numeric">Admin</th>
                            <th scope="col"><span class="u-visually-hidden">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($roles as $role)
                            <tr>
                                <td>
                                    <p>{{ $role->name }}</p>
                                    @if ($role->description)
                                        <p class="text-xs text-muted">{{ $role->description }}</p>
                                    @endif
                                </td>
                                <td>
                                    @if ($role->isProtected())
                                        <x-badge variant="info">Super Admin</x-badge>
                                    @else
                                        <x-badge>Kustom</x-badge>
                                    @endif
                                </td>
                                <td class="is-numeric">
                                    @if ($role->isProtected())
                                        Semua
                                    @else
                                        {{ $role->permissions_count }}
                                    @endif
                                </td>
                                <td class="is-numeric">{{ $role->users_count }}</td>
                                <td>
                                    <div class="cluster">
                                        @can('update', $role)
                                            <x-button
                                                variant="secondary"
                                                size="sm"
                                                :href="route('admin.roles.edit', $role)"
                                            >
                                                Ubah
                                            </x-button>
                                        @endcan

                                        @if ($role->isProtected())
                                            <span class="text-xs text-muted">
                                                Permanen, tidak dapat diubah
                                            </span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="text-sm text-muted u-mt-4">
                Role Super Admin selalu memiliki seluruh permission, termasuk
                permission yang ditambahkan kemudian, dan tidak dapat dihapus.
            </p>
        @endif
    </x-card>
@endsection

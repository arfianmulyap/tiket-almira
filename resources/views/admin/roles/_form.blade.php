{{--
    Shared role form fields for create and edit.

    `$role` is a Role (possibly unsaved), `$selected` is the list of permission
    ids currently granted, and `$permissionGroups` holds the catalog grouped by
    section. Values fall back to old() so a failed validation round-trip never
    loses the admin's input.
--}}
@php
    $selectedIds = array_map('intval', old('permissions', $selected) ?? []);
@endphp

<div class="field">
    <x-label for="name" required>Nama Role</x-label>
    <x-input
        name="name"
        :value="old('name', $role->name)"
        required
        maxlength="80"
        autocomplete="off"
    />
    <p class="field-hint">Contoh: Admin Operasional, Petugas Check-in.</p>
    <x-field-error name="name" />
</div>

<div class="field">
    <x-label for="description">Deskripsi</x-label>
    <x-textarea
        name="description"
        :value="old('description', $role->description)"
        rows="3"
        maxlength="255"
    />
    <x-field-error name="description" />
</div>

<fieldset class="fieldset">
    <legend class="fieldset-legend">Permission</legend>

    <p class="field-hint">
        Centang tindakan yang boleh dilakukan role ini. Perubahan berlaku pada
        request berikutnya tanpa perlu login ulang.
    </p>

    <x-field-error name="permissions" />

    @forelse ($permissionGroups as $group => $permissions)
        <p class="text-sm text-muted u-mt-6">{{ $group }}</p>

        <div class="stack u-mt-4">
            @foreach ($permissions as $permission)
                <label class="choice" for="permission-{{ $permission->id }}">
                    <input
                        type="checkbox"
                        id="permission-{{ $permission->id }}"
                        name="permissions[]"
                        value="{{ $permission->id }}"
                        @checked(in_array($permission->id, $selectedIds, true))
                    >
                    <span>
                        {{ $permission->label }}
                        <span class="text-xs text-muted">{{ $permission->name }}</span>
                    </span>
                </label>
            @endforeach
        </div>
    @empty
        <p class="text-sm text-muted u-mt-4">
            Katalog permission masih kosong. Jalankan
            <code>php artisan db:seed --class=RolePermissionSeeder</code>.
        </p>
    @endforelse
</fieldset>

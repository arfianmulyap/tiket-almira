<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    /**
     * Authorisation is delegated to RolePolicy::create().
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Role::class) ?? false;
    }

    /**
     * `is_super_admin` is deliberately absent: the protected role is created
     * by the migration and can never be manufactured through the form.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80', 'unique:roles,name'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama role',
            'description' => 'deskripsi',
            'permissions' => 'permission',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama role wajib diisi.',
            'name.unique' => 'Nama role sudah digunakan.',
            'permissions.*.exists' => 'Permission yang dipilih tidak dikenali.',
        ];
    }
}

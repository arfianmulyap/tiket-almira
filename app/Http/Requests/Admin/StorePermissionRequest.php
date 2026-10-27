<?php

namespace App\Http\Requests\Admin;

use App\Enums\AdminPermission;
use App\Models\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePermissionRequest extends FormRequest
{
    /**
     * Authorisation is delegated to PermissionPolicy::create().
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Permission::class) ?? false;
    }

    /**
     * Only keys from the code catalog may be restored. A free-form key would
     * create a permission that no backend action checks, which would look like
     * control without being control.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                Rule::in(AdminPermission::values()),
                Rule::unique('permissions', 'name'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'permission',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Permission wajib dipilih.',
            'name.in' => 'Permission tidak dikenali.',
            'name.unique' => 'Permission tersebut sudah terdaftar.',
        ];
    }
}

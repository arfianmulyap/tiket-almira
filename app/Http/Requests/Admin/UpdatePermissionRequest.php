<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePermissionRequest extends FormRequest
{
    /**
     * Authorisation is delegated to PermissionPolicy::update().
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('permission')) ?? false;
    }

    /**
     * Only the display metadata may change. The `name` key is immutable
     * because it is the string the backend actually checks.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'label' => 'label permission',
            'description' => 'deskripsi',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'label.required' => 'Label permission wajib diisi.',
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Invite a new admin (Phase 11).
 *
 * Only the identity and the role are collected: the password is chosen by the
 * recipient through the invitation link, so no password field exists here and
 * no account can be created with credentials an inviter knows.
 */
class StoreAdminAccountRequest extends FormRequest
{
    /**
     * Authorisation is delegated to the `admin-accounts.manage` ability
     * registered in AppServiceProvider.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('admin-accounts.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:160', 'unique:users,email'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ];
    }

    /**
     * Normalise before validating so `Admin@Example.test` and
     * `admin@example.test` cannot become two accounts.
     */
    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        $email = $this->input('email');

        $this->merge([
            'name' => is_string($name) ? trim($name) : $name,
            'email' => is_string($email) ? Str::lower(trim($email)) : $email,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama',
            'email' => 'email',
            'role_id' => 'role',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama admin wajib diisi.',
            'name.min' => 'Nama admin minimal 3 karakter.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Masukkan alamat email yang valid.',
            'email.unique' => 'Email ini sudah dipakai akun lain.',
            'role_id.required' => 'Pilih role untuk akun ini.',
            'role_id.exists' => 'Role yang dipilih tidak tersedia.',
        ];
    }
}

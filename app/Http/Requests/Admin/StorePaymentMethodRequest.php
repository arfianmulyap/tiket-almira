<?php

namespace App\Http\Requests\Admin;

use App\Models\PaymentMethod;
use Closure;
use Illuminate\Validation\Rule;

/**
 * Add a new payment method (Phase 12B).
 *
 * Creation is the only time the internal key is accepted. Afterwards it is
 * immutable, because `payments.method` and order snapshots reference it as a
 * plain string: renaming it would orphan history, and reusing it would silently
 * re-attach history to a different method.
 *
 * All the type-dependent validation lives in PaymentMethodRequest, so the
 * create form and the edit form enforce exactly the same rules.
 */
class StorePaymentMethodRequest extends PaymentMethodRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PaymentMethod::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'key' => [
                'required',
                'string',
                'max:64',
                // Machine key: lower case, digits, and underscores only. Kept
                // strict so it stays safe in URLs, logs, and config files.
                'regex:/^[a-z][a-z0-9_]*$/',

                // The database index is the real guarantee; this gives the
                // admin a readable message instead of a query exception.
                Rule::unique('payment_methods', 'key'),

                function (string $attribute, mixed $value, Closure $fail): void {
                    if (PaymentMethod::keyIsUsedByHistory((string) $value)) {
                        $fail('Kunci ini masih dirujuk riwayat pembayaran atau snapshot pesanan dan tidak dapat dipakai ulang.');
                    }
                },
            ],
        ] + parent::rules();
    }

    /**
     * The normalised key for the new record.
     */
    public function key(): string
    {
        return strtolower(trim((string) $this->validated('key')));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['key' => 'kunci metode'] + parent::attributes();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'key.required' => 'Kunci metode wajib diisi.',
            'key.max' => 'Kunci metode maksimal 64 karakter.',
            'key.regex' => 'Kunci hanya boleh berisi huruf kecil, angka, dan garis bawah, serta diawali huruf.',
            'key.unique' => 'Kunci metode sudah dipakai metode lain.',
        ] + parent::messages();
    }
}

<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Edit the recipient details of a payment method (Phase 12).
 *
 * Only a Super Admin reaches this request: the account, VA, and QRIS details
 * decide where participant money is sent, so they are not governed by a
 * grantable permission.
 *
 * The form sends an unordered set of label/value rows, several of which may be
 * left blank. Blank rows are dropped; a row with only one half filled is
 * rejected rather than silently discarded, because that is almost always a
 * typo in an account number.
 */
class UpdatePaymentMethodRequest extends FormRequest
{
    /**
     * Maximum number of detail rows kept after normalisation.
     */
    private const MAX_ACCOUNT_ROWS = 8;

    /**
     * Maximum number of instruction steps kept after normalisation.
     */
    private const MAX_STEPS = 10;

    /**
     * Maximum length of one instruction step.
     */
    private const MAX_STEP_LENGTH = 200;

    public function authorize(): bool
    {
        $method = $this->route('paymentMethod');

        return $method !== null
            && $this->user()?->can('updateDetails', $method) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:120'],

            // Deliberately generous at this layer so the fixed set of blank
            // rows rendered by the form does not trip the limit. The number of
            // *filled* rows is checked in withValidator().
            'account' => ['nullable', 'array', 'max:12'],
            'account.*.label' => ['nullable', 'string', 'max:60'],
            'account.*.value' => ['nullable', 'string', 'max:200'],

            // One step per line; split and counted in withValidator().
            'steps' => ['nullable', 'string', 'max:3000'],

            // Hidden input submits 0 when the checkbox is cleared.
            'is_dummy' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            // A malformed field is reported on its own; piling normalisation
            // errors on top would only obscure the actual problem.
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $rows = $this->input('account', []);
            $filled = 0;

            foreach (is_array($rows) ? $rows : [] as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $label = trim((string) ($row['label'] ?? ''));
                $value = trim((string) ($row['value'] ?? ''));

                if ($label === '' && $value === '') {
                    continue;
                }

                if ($label === '' || $value === '') {
                    $validator->errors()->add(
                        "account.{$index}.label",
                        'Lengkapi label dan nilainya, atau kosongkan keduanya.'
                    );

                    continue;
                }

                $filled++;
            }

            if ($filled === 0) {
                $validator->errors()->add(
                    'account',
                    'Isi minimal satu baris detail penerima, misalnya nama bank dan nomor rekening.'
                );
            }

            if ($filled > self::MAX_ACCOUNT_ROWS) {
                $validator->errors()->add(
                    'account',
                    'Maksimal '.self::MAX_ACCOUNT_ROWS.' baris detail penerima.'
                );
            }

            $steps = $this->normalisedSteps();

            if ($steps === []) {
                $validator->errors()->add('steps', 'Isi minimal satu langkah pembayaran.');
            }

            if (count($steps) > self::MAX_STEPS) {
                $validator->errors()->add('steps', 'Maksimal '.self::MAX_STEPS.' langkah pembayaran.');
            }

            foreach ($steps as $step) {
                if (mb_strlen($step) > self::MAX_STEP_LENGTH) {
                    $validator->errors()->add(
                        'steps',
                        'Setiap langkah maksimal '.self::MAX_STEP_LENGTH.' karakter.'
                    );

                    break;
                }
            }
        });
    }

    /**
     * Normalised payload for the model.
     *
     * @return array{
     *     label: string,
     *     account: array<int, array{label: string, value: string}>,
     *     steps: array<int, string>,
     *     is_dummy: bool
     * }
     */
    public function paymentMethodData(): array
    {
        return [
            'label' => trim((string) $this->validated('label')),
            'account' => $this->normalisedAccount(),
            'steps' => $this->normalisedSteps(),
            'is_dummy' => $this->boolean('is_dummy'),
        ];
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private function normalisedAccount(): array
    {
        $rows = $this->input('account', []);
        $account = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));

            if ($label === '' || $value === '') {
                continue;
            }

            $account[] = ['label' => $label, 'value' => $value];
        }

        return $account;
    }

    /**
     * @return array<int, string>
     */
    private function normalisedSteps(): array
    {
        $raw = (string) $this->input('steps', '');
        $steps = [];

        foreach (preg_split('/\R/', $raw) ?: [] as $line) {
            $line = trim($line);

            if ($line !== '') {
                $steps[] = $line;
            }
        }

        return $steps;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'label' => 'nama metode',
            'account' => 'detail penerima',
            'account.*.label' => 'label detail',
            'account.*.value' => 'nilai detail',
            'steps' => 'langkah pembayaran',
            'is_dummy' => 'penanda data contoh',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'label.required' => 'Nama metode wajib diisi.',
            'label.max' => 'Nama metode maksimal 120 karakter.',
            'account.max' => 'Terlalu banyak baris detail penerima.',
            'account.*.label.max' => 'Label detail maksimal 60 karakter.',
            'account.*.value.max' => 'Nilai detail maksimal 200 karakter.',
            'steps.max' => 'Langkah pembayaran terlalu panjang.',
            'is_dummy.boolean' => 'Penanda data contoh tidak valid.',
        ];
    }
}

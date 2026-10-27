<?php

namespace App\Http\Requests\Admin;

use App\Enums\PaymentMethodType;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shared validation for adding and editing a payment method (Phase 12B).
 *
 * The rules that depend on the selected type live here so the create form and
 * the edit form cannot drift apart: an account number is mandatory for a bank
 * transfer, a QR image or merchant reference for QRIS, and a half-filled detail
 * field is always a typo rather than an instruction.
 *
 * Detail fields arrive as `details[<type>][<field>]` because the form renders a
 * panel per type while only the selected panel is stored. Values submitted for
 * another type are ignored rather than rejected: changing the type in the
 * browser should not require clearing what was already typed.
 *
 * The `type` is a stored attribute rather than something inferred from labels,
 * so validation can be precise. It is also checked against the enum, which is
 * what keeps a method's type from becoming a value no screen understands.
 */
abstract class PaymentMethodRequest extends FormRequest
{
    /**
     * Maximum number of instruction steps kept after normalisation.
     */
    public const MAX_STEPS = 10;

    /**
     * Maximum length of one instruction step.
     */
    public const MAX_STEP_LENGTH = 200;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::enum(PaymentMethodType::class)],

            // Deliberately loose at this layer: only the selected type's panel
            // is stored, and the required fields inside it are checked in
            // withValidator() where the type is known.
            'details' => ['nullable', 'array'],
            'details.*' => ['nullable', 'array'],
            'details.*.*' => ['nullable', 'string', 'max:200'],

            // One step per line; split and counted in withValidator().
            'steps' => ['nullable', 'string', 'max:3000'],

            // `image` in addition to `mimes`: mimes trusts the extension while
            // image inspects the contents, so a renamed executable with a .png
            // extension is rejected.
            'qr_image' => [
                'nullable',
                'file',
                'image',
                'mimes:'.implode(',', (array) config('payments.qr_image.mimes', ['jpg', 'jpeg', 'png', 'webp'])),
                'max:'.(int) config('payments.qr_image.max_kilobytes', 2048),
            ],
            'remove_qr_image' => ['nullable', 'boolean'],

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

            $type = $this->paymentMethodType();

            if ($type === null) {
                return; // The `type` rule has already reported this.
            }

            $this->validateDetails($validator, $type);
            $this->validateQrCode($validator, $type);
            $this->validateOfficialFlag($validator);
            $this->validateSteps($validator);
        });
    }

    /**
     * Every field the selected type requires must actually be filled.
     *
     * This is what makes the type meaningful: without it, `type` would only be a
     * label on a form and a bank transfer could still be saved with no account
     * number.
     */
    private function validateDetails(Validator $validator, PaymentMethodType $type): void
    {
        $values = $this->detailValues($type);

        foreach ($type->details() as $field => $spec) {
            if (! $spec['required'] || filled($values[$field] ?? null)) {
                continue;
            }

            $validator->errors()->add(
                "details.{$type->value}.{$field}",
                $spec['label'].' wajib diisi untuk tipe '.$type->label().'.'
            );
        }
    }

    /**
     * A QRIS method must carry either an image or a merchant reference.
     *
     * Accepting neither would publish a method whose instructions say "scan the
     * code" with no code to scan, which is worse than refusing to save it.
     */
    private function validateQrCode(Validator $validator, PaymentMethodType $type): void
    {
        if (! $type->usesQrImage() || $this->hasQrImage()) {
            return;
        }

        $reference = $type->merchantReferenceField();

        if ($reference !== null && filled($this->detailValues($type)[$reference] ?? null)) {
            return;
        }

        $validator->errors()->add(
            'qr_image',
            'Unggah gambar QR merchant atau isi ID Merchant / NMID.'
        );
    }

    /**
     * Only a Super Admin may mark unconfirmed details as official.
     *
     * Reported as a validation error on the field the admin touched, because a
     * bare 403 would leave them guessing which control was refused.
     */
    private function validateOfficialFlag(Validator $validator): void
    {
        if (! $this->requestsOfficialDetails() || $this->canPublishOfficial()) {
            return;
        }

        $validator->errors()->add(
            'is_dummy',
            'Hanya Super Admin yang dapat menandai detail sebagai data resmi.'
        );
    }

    /**
     * Whether the request explicitly asks to clear the placeholder flag.
     *
     * An absent field is not a request: the form always submits an explicit
     * false when the checkbox is cleared, so "absent" means the key was never
     * offered, and the safe reading of an unstated intent is "keep it as
     * placeholder".
     */
    private function requestsOfficialDetails(): bool
    {
        return $this->has('is_dummy') && ! $this->boolean('is_dummy');
    }

    /**
     * Between one and MAX_STEPS instructions, none of them overly long.
     */
    private function validateSteps(Validator $validator): void
    {
        $steps = $this->stepsList();

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
    }

    /**
     * The selected type, or null when the submitted value is not a known type.
     *
     * Reads `type` even when validation has failed, which is what lets the form
     * re-render the detail panel for the type the admin actually chose.
     */
    public function paymentMethodType(): ?PaymentMethodType
    {
        $type = $this->input('type');

        return is_string($type) ? PaymentMethodType::tryFrom(trim($type)) : null;
    }

    /**
     * Filled detail values for one type, keyed by field name.
     *
     * Values belonging to another type, and fields left blank, are dropped.
     *
     * @return array<string, string>
     */
    public function detailValues(PaymentMethodType $type): array
    {
        $panel = $this->input('details.'.$type->value);
        $values = [];

        if (! is_array($panel)) {
            return $values;
        }

        foreach (array_keys($type->details()) as $field) {
            $value = trim((string) ($panel[$field] ?? ''));

            if ($value !== '') {
                $values[$field] = $value;
            }
        }

        return $values;
    }

    /**
     * Detail rows in the shape stored on the method and snapshotted onto orders.
     *
     * Built from the type's own specification rather than from submitted labels,
     * so a participant can never be shown a label no screen defines, and row
     * order always follows the type's definition.
     *
     * @return array<int, array{label: string, value: string}>
     */
    public function detailRows(): array
    {
        $type = $this->paymentMethodType();

        if ($type === null) {
            return [];
        }

        $values = $this->detailValues($type);
        $rows = [];

        foreach ($type->details() as $field => $spec) {
            if (filled($values[$field] ?? null)) {
                $rows[] = ['label' => $spec['label'], 'value' => $values[$field]];
            }
        }

        return $rows;
    }

    /**
     * Instruction steps, one per non-empty line.
     *
     * @return array<int, string>
     */
    public function stepsList(): array
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
     * Whether the saved method keeps placeholder status.
     *
     * Three cases, and the middle one is the reason this is not a one-liner:
     *
     *  - The request explicitly asks to publish (the checkbox was cleared): only
     *    a Super Admin reaches this point at all, because
     *    validateOfficialFlag() refuses everyone else.
     *  - The request explicitly keeps placeholder status.
     *  - The flag is absent. That is what a non-Super-Admin's form submits,
     *    since the control is not rendered for them — and it must mean "leave it
     *    as it is", not "make it a placeholder". Reading it as a placeholder
     *    would let an ordinary edit silently hide a method the business had
     *    already confirmed.
     */
    public function isDummy(): bool
    {
        if ($this->requestsOfficialDetails()) {
            // Fails closed: validation already refuses everyone but a Super
            // Admin, and if that ever stopped being true the safe reading of
            // "publish" is still to keep the method a placeholder.
            return ! $this->canPublishOfficial();
        }

        if ($this->has('is_dummy')) {
            return true;
        }

        $method = $this->route('paymentMethod');

        // A new method has nothing to preserve, and placeholder is the safe
        // starting state.
        return $method instanceof PaymentMethod ? (bool) $method->is_dummy : true;
    }

    /**
     * Whether this admin may clear the placeholder flag.
     */
    public function canPublishOfficial(): bool
    {
        $method = $this->route('paymentMethod');

        return $this->user()?->can(
            'publishOfficialDetails',
            $method instanceof PaymentMethod ? $method : new PaymentMethod
        ) === true;
    }

    /**
     * Whether the method will have a QR image once this request is saved.
     *
     * A new upload counts, and so does an already stored image the request does
     * not ask to remove. Overridden where an existing record is in scope.
     */
    public function hasQrImage(): bool
    {
        return $this->hasFile('qr_image') || $this->retainsExistingQrImage();
    }

    /**
     * Whether an existing stored image survives this request.
     */
    protected function retainsExistingQrImage(): bool
    {
        return false;
    }

    /**
     * Detail rows from the existing record that match no known field.
     *
     * Preserved so editing a method — or switching its type — never silently
     * deletes a detail row an admin typed in Phase 12 under their own label.
     * Empty here; the edit request overrides it.
     *
     * @return array<int, array{label: string, value: string}>
     */
    protected function preservedExtraRows(): array
    {
        return [];
    }

    /**
     * Normalised payload for the model.
     *
     * @return array{
     *     label: string,
     *     type: string,
     *     account: array<int, array{label: string, value: string}>,
     *     steps: array<int, string>,
     *     is_dummy: bool
     * }
     */
    public function paymentMethodData(): array
    {
        return [
            'label' => trim((string) $this->validated('label')),
            'type' => (string) $this->paymentMethodType()?->value,
            'account' => [...$this->detailRows(), ...$this->preservedExtraRows()],
            'steps' => $this->stepsList(),
            'is_dummy' => $this->isDummy(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'label' => 'nama metode',
            'type' => 'tipe pembayaran',
            'details' => 'detail penerima',
            'steps' => 'langkah pembayaran',
            'qr_image' => 'gambar QR merchant',
            'remove_qr_image' => 'penghapusan gambar QR',
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
            'type.required' => 'Pilih tipe pembayaran.',
            'type.enum' => 'Tipe pembayaran tidak dikenal.',
            'details.max' => 'Detail penerima terlalu panjang.',
            'details.*.max' => 'Detail penerima terlalu panjang.',
            'details.*.*.string' => 'Detail penerima harus berupa teks.',
            'details.*.*.max' => 'Setiap detail penerima maksimal 200 karakter.',
            'steps.max' => 'Langkah pembayaran terlalu panjang.',
            'qr_image.image' => 'Gambar QR harus berupa file gambar.',
            'qr_image.mimes' => 'Gambar QR harus berformat JPG, JPEG, PNG, atau WebP.',
            'qr_image.max' => 'Gambar QR terlalu besar.',
            'is_dummy.boolean' => 'Penanda data contoh tidak valid.',
        ];
    }
}

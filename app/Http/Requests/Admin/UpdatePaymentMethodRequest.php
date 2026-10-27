<?php

namespace App\Http\Requests\Admin;

use App\Enums\PaymentMethodType;
use App\Models\PaymentMethod;

/**
 * Edit the details of an existing payment method (Phase 12B).
 *
 * Reached only by an admin holding `payment_methods.update`. The account, VA,
 * and QRIS details decide where participant money is sent, but that is now a
 * grantable permission rather than a Super Admin-only rule; the one part that
 * stays Super Admin-only is publishing unconfirmed details as official (clearing
 * `is_dummy`), enforced in the shared parent request.
 *
 * The internal key is not accepted here at all. It is referenced by
 * `payments.method` and by every order snapshot, so it is immutable by design.
 *
 * Type-dependent rules live in PaymentMethodRequest, which is what keeps this
 * form and the create form in step.
 */
class UpdatePaymentMethodRequest extends PaymentMethodRequest
{
    public function authorize(): bool
    {
        $method = $this->route('paymentMethod');

        return $method instanceof PaymentMethod
            && $this->user()?->can('updateDetails', $method) === true;
    }

    /**
     * An already stored QR image survives unless the admin asks to remove it.
     *
     * Without this, saving any QRIS method that has an image but no new upload
     * would fail the "image or merchant reference" rule, which would make the
     * form impossible to submit.
     */
    protected function retainsExistingQrImage(): bool
    {
        $method = $this->route('paymentMethod');

        return $method instanceof PaymentMethod
            && ! $this->boolean('remove_qr_image')
            && filled($method->qr_image_path);
    }

    /**
     * Keep detail rows that match no field of any known type.
     *
     * Phase 12 let an admin type arbitrary label/value rows. Those rows are
     * still shown to participants through existing snapshots, so rewriting the
     * method must not quietly drop them. Rows that *do* match a known field are
     * rebuilt from the type's specification instead, which is what makes the
     * stored labels canonical from now on.
     *
     * @return array<int, array{label: string, value: string}>
     */
    protected function preservedExtraRows(): array
    {
        $method = $this->route('paymentMethod');

        if (! $method instanceof PaymentMethod) {
            return [];
        }

        $rows = [];

        foreach ($method->account ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));

            if ($label === '' || $value === '') {
                continue;
            }

            if (PaymentMethodType::fieldForLabel($label) !== null) {
                continue;
            }

            $rows[] = ['label' => $label, 'value' => $value];
        }

        return $rows;
    }
}

<?php

namespace App\Http\Requests;

use App\Services\PaymentMethodRegistry;
use App\Support\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentProofRequest extends FormRequest
{
    /**
     * Submitting a proof is open to whoever holds the order's access token,
     * which the route resolves. Whether this specific order may still accept
     * a proof is decided by PaymentProofService under a row lock, not here:
     * a check at validation time would be stale by the time the file is
     * written.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Server-side file validation is mandatory (PRD FR-06), and the limits
     * come from config/payments.php so there is one place to change them.
     *
     * `image` is required in addition to `mimes`: `mimes` trusts the
     * extension, while `image` makes Laravel inspect the actual file
     * contents. Without it, a renamed executable with a .jpg extension would
     * pass. The size rule is last so a huge file is still reported clearly.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var array<int, string> $mimes */
        $mimes = config('payments.proof.mimes', ['jpg', 'jpeg', 'png']);

        return [
            // Rule::in over the registry's keys, so an inactive method — or a
            // development placeholder in production — cannot be selected by
            // posting its key directly.
            'payment_method' => [
                'required',
                'string',
                Rule::in($this->registry()->availableKeys()),
            ],
            'proof' => [
                'required',
                'file',
                'image',
                'mimes:'.implode(',', $mimes),
                'max:'.(int) config('payments.proof.max_kilobytes', 4096),
            ],
        ];
    }

    /**
     * The validated method, resolved through the registry.
     *
     * Returns the typed object rather than the raw key so the service cannot
     * be handed a method that was never offered. Validation has already run,
     * so this is non-null by the time a controller calls it.
     */
    public function paymentMethod(): PaymentMethod
    {
        /** @var string $key */
        $key = $this->validated('payment_method');

        /** @var PaymentMethod $method */
        $method = $this->registry()->find($key);

        return $method;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'payment_method' => 'metode pembayaran',
            'proof' => 'bukti pembayaran',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxMegabytes = round(((int) config('payments.proof.max_kilobytes', 4096)) / 1024, 1);

        return [
            'payment_method.required' => 'Pilih metode pembayaran yang kamu gunakan.',
            'payment_method.in' => 'Metode pembayaran yang dipilih tidak tersedia.',
            'proof.required' => 'Bukti pembayaran wajib diunggah.',
            'proof.image' => 'Bukti pembayaran harus berupa gambar (JPG, PNG, atau WebP).',
            'proof.mimes' => 'Bukti pembayaran harus berformat JPG, PNG, atau WebP.',
            'proof.max' => "Bukti pembayaran tidak boleh lebih besar dari {$maxMegabytes} MB.",
        ];
    }

    private function registry(): PaymentMethodRegistry
    {
        return app(PaymentMethodRegistry::class);
    }
}

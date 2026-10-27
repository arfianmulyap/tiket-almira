<?php

namespace App\Http\Requests;

use App\Models\Order;
use App\Services\PaymentMethodRegistry;
use App\Support\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class StorePaymentProofRequest extends FormRequest
{
    /**
     * Memoised so validation and the controller share one lookup.
     */
    private ?Order $resolvedOrder = null;

    private bool $orderResolved = false;

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
     * The method key cannot be checked against one global list here: a
     * Phase 12 order is bound to the snapshot it was created with, so the
     * membership check runs in withValidator() against that order.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var array<int, string> $mimes */
        $mimes = config('payments.proof.mimes', ['jpg', 'jpeg', 'png']);

        return [
            'payment_method' => ['required', 'string', 'max:64'],
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
     * Reject a method that is not part of this order's snapshot.
     *
     * Checking here rather than in rules() keeps the order lookup in one
     * place. If the token does not resolve, this check steps aside and the
     * controller returns 404, which is the correct answer for an unknown
     * order.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('payment_method')) {
                return;
            }

            $order = $this->order();

            if ($order === null) {
                return;
            }

            $key = $this->input('payment_method');

            if (! is_string($key) || ! $this->methodsForOrder($order)->has($key)) {
                $validator->errors()->add(
                    'payment_method',
                    'Metode pembayaran yang dipilih tidak tersedia untuk pesanan ini.'
                );
            }
        });
    }

    /**
     * The order whose access token is in the route.
     *
     * Returns null rather than aborting so validation can stay a pure
     * validation concern; the controller decides the HTTP response.
     */
    public function order(): ?Order
    {
        if ($this->orderResolved) {
            return $this->resolvedOrder;
        }

        $this->orderResolved = true;

        $token = $this->route('token');

        if (! is_string($token)) {
            return null;
        }

        return $this->resolvedOrder = Order::query()
            ->where('access_token', $token)
            ->first();
    }

    /**
     * The validated method, resolved from this order's snapshot.
     *
     * Returns the typed object rather than the raw key so the service cannot
     * be handed a method that was never offered.
     *
     * @throws ValidationException
     */
    public function paymentMethodFor(Order $order): PaymentMethod
    {
        $key = (string) $this->validated('payment_method');

        $method = $this->methodsForOrder($order)->get($key);

        if (! $method instanceof PaymentMethod) {
            throw ValidationException::withMessages([
                'payment_method' => 'Metode pembayaran yang dipilih tidak tersedia untuk pesanan ini.',
            ]);
        }

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
            'payment_method.string' => 'Metode pembayaran yang dipilih tidak valid.',
            'proof.required' => 'Bukti pembayaran wajib diunggah.',
            'proof.image' => 'Bukti pembayaran harus berupa gambar (JPG, PNG, atau WebP).',
            'proof.mimes' => 'Bukti pembayaran harus berformat JPG, PNG, atau WebP.',
            'proof.max' => "Bukti pembayaran tidak boleh lebih besar dari {$maxMegabytes} MB.",
        ];
    }

    /**
     * Methods this order may still be paid with.
     *
     * The order's own snapshot, not the live catalog: a participant who was
     * quoted a method must be able to finish that payment even after the
     * method is retired for new orders (Phase 12).
     *
     * @return Collection<string, PaymentMethod>
     */
    private function methodsForOrder(Order $order): Collection
    {
        return app(PaymentMethodRegistry::class)->forOrder($order);
    }
}

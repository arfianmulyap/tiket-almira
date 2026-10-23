<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // A payment exists because a proof was submitted, so the default
            // parent is an order already awaiting verification.
            'order_id' => Order::factory()->pendingVerification(),
            'method' => 'dev_bank_transfer',
            'method_label' => '[CONTOH] Transfer Bank',
            'amount' => 200000,
            'proof_path' => 'payment-proofs/'.Str::random(32).'.jpg',
            'proof_mime_type' => 'image/jpeg',
            'submitted_at' => now(),
        ];
    }

    /**
     * Attach the payment to a specific order and snapshot its amount.
     */
    public function forOrder(Order $order): static
    {
        return $this->state(fn (array $attributes) => [
            'order_id' => $order->id,
            'amount' => $order->ticket_price_snapshot,
        ]);
    }
}

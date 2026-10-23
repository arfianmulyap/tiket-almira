<?php

namespace Database\Factories;

use App\Enums\TicketStatus;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // A ticket only ever exists for an approved payment, so the
            // default parent is a paid order.
            'order_id' => Order::factory()->paid(),
            'qr_token' => Str::random(64),
            'status' => TicketStatus::Valid,
            'issued_at' => now(),
        ];
    }

    public function checkedIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatus::CheckedIn,
        ]);
    }

    public function void(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatus::Void,
        ]);
    }

    /**
     * Attach the ticket to a specific order.
     */
    public function forOrder(Order $order): static
    {
        return $this->state(fn (array $attributes) => [
            'order_id' => $order->id,
        ]);
    }
}

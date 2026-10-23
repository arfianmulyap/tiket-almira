<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_reference' => 'ALM-'.Str::upper(Str::random(10)),
            'access_token' => Str::random(64),
            'event_id' => Event::factory()->published(),
            'participant_name' => fake()->name(),
            'participant_whatsapp' => '08'.fake()->numerify('##########'),
            'participant_email' => fake()->safeEmail(),
            'ticket_price_snapshot' => fake()->numberBetween(50, 500) * 1000,
            'status' => OrderStatus::PendingPayment,
            'expires_at' => now()->addMinutes(60),
        ];
    }

    /**
     * An order whose payment deadline has already passed but whose status
     * has not been swept yet. Used to prove the quota count ignores it.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::PendingPayment,
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function pendingVerification(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::PendingVerification,
            // Deliberately in the past: a submitted proof keeps the slot
            // even after minute 60 (PRD FR-08).
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Paid,
            'expires_at' => now()->subHours(2),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Expired,
            'expires_at' => now()->subHours(2),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Rejected,
            'expires_at' => now()->subHours(2),
        ]);
    }

    /**
     * Attach the order to a specific event and snapshot that event's price.
     */
    public function forEvent(Event $event): static
    {
        return $this->state(fn (array $attributes) => [
            'event_id' => $event->id,
            'ticket_price_snapshot' => $event->ticket_price,
        ]);
    }
}

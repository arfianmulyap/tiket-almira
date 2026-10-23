<?php

namespace Database\Factories;

use App\Models\CheckIn;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CheckIn>
 */
class CheckInFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // A check-in only ever exists for a ticket that has been used,
            // so the default parent is already CHECKED_IN. Seeding a VALID
            // ticket with a check-in row would represent a state the service
            // cannot produce.
            'ticket_id' => Ticket::factory()->checkedIn(),
            'checked_in_by' => User::factory()->admin(),
            'operator_name' => fn (array $attributes) => User::find($attributes['checked_in_by'])?->name,
            'checked_in_at' => now(),
        ];
    }

    /**
     * Attach the check-in to a specific ticket.
     */
    public function forTicket(Ticket $ticket): static
    {
        return $this->state(fn (array $attributes) => [
            'ticket_id' => $ticket->id,
        ]);
    }

    /**
     * Record the check-in against a specific operator.
     */
    public function byOperator(User $operator): static
    {
        return $this->state(fn (array $attributes) => [
            'checked_in_by' => $operator->id,
            'operator_name' => $operator->name,
        ]);
    }
}

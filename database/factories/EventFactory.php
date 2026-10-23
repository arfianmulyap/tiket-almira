<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->sentence(3);

        return [
            'name' => rtrim($name, '.'),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'description' => fake()->paragraph(),
            'poster_path' => null,
            // Whole rupiah. PRD lists Rp200.000 as a reference price only.
            'ticket_price' => fake()->numberBetween(50, 500) * 1000,
            'quota' => fake()->numberBetween(10, 200),
            'status' => EventStatus::Draft,
            'starts_at' => fake()->dateTimeBetween('+1 week', '+3 months'),
            'location' => fake()->city(),
        ];
    }

    /**
     * A published event, which is the only status participants may order.
     *
     * A poster path is set because publishing requires a poster (PRD FR-03).
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EventStatus::Published,
            'poster_path' => 'posters/example-poster.jpg',
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EventStatus::Draft,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EventStatus::Closed,
            'poster_path' => 'posters/example-poster.jpg',
        ]);
    }
}

<?php

namespace Tests\Feature\Console;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

class ExpireOverdueOrdersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_command_expires_overdue_orders(): void
    {
        $order = Order::factory()->overdue()->create();

        $this->artisan('orders:expire-overdue')
            ->expectsOutputToContain('1 pesanan ditandai kedaluwarsa.')
            ->assertSuccessful();

        $this->assertSame(OrderStatus::Expired, $order->fresh()->status);
    }

    public function test_the_command_reports_when_there_is_nothing_to_do(): void
    {
        Order::factory()->create(['expires_at' => now()->addMinutes(30)]);

        $this->artisan('orders:expire-overdue')
            ->expectsOutputToContain('Tidak ada pesanan yang perlu dikedaluwarsakan.')
            ->assertSuccessful();
    }

    public function test_the_command_succeeds_on_an_empty_database(): void
    {
        $this->artisan('orders:expire-overdue')->assertSuccessful();
    }

    /**
     * Guards the wiring, not the logic. A correct service that is never
     * scheduled would leave every order stuck at PENDING_PAYMENT, and that
     * failure is invisible to the other tests.
     */
    public function test_the_sweep_is_registered_on_the_scheduler(): void
    {
        $commands = collect(Schedule::events())
            ->map(fn ($event) => $event->command)
            ->filter();

        $this->assertTrue(
            $commands->contains(fn (string $command) => str_contains($command, 'orders:expire-overdue')),
            'orders:expire-overdue is not registered on the scheduler.',
        );
    }
}

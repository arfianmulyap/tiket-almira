<?php

namespace Tests\Feature\Participant;

use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PRD FR-04: a participant must not be able to reach another participant's
 * order.
 */
class OrderAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_status_page_is_reachable_with_the_access_token(): void
    {
        $order = Order::factory()->create(['participant_name' => 'Budi Santoso']);

        $this->get(route('orders.show', $order->access_token))
            ->assertOk()
            ->assertSee($order->order_reference)
            ->assertSee('Budi Santoso');
    }

    public function test_order_status_page_rejects_an_unknown_token(): void
    {
        Order::factory()->create();

        $this->get(route('orders.show', Str::random(64)))->assertNotFound();
    }

    /**
     * The order reference is quotable over WhatsApp, so it must not be
     * usable as a credential on its own.
     */
    public function test_order_reference_cannot_be_used_in_place_of_the_token(): void
    {
        $order = Order::factory()->create();

        $this->get(route('orders.show', $order->order_reference))->assertNotFound();
    }

    public function test_sequential_ids_cannot_be_used_to_enumerate_orders(): void
    {
        $order = Order::factory()->create();

        $this->get(route('orders.show', (string) $order->id))->assertNotFound();
    }

    public function test_one_participant_cannot_open_another_participants_order(): void
    {
        $mine = Order::factory()->create(['participant_name' => 'Peserta Satu']);
        $theirs = Order::factory()->create([
            'participant_name' => 'Peserta Dua',
            'participant_email' => 'dua@example.com',
        ]);

        // My token shows only my data.
        $this->get(route('orders.show', $mine->access_token))
            ->assertOk()
            ->assertSee('Peserta Satu')
            ->assertDontSee('Peserta Dua')
            ->assertDontSee($theirs->participant_email);
    }

    public function test_status_page_shows_the_price_snapshot_not_the_current_event_price(): void
    {
        $event = Event::factory()->published()->create(['ticket_price' => 200000]);
        $order = Order::factory()->forEvent($event)->create();

        $event->update(['ticket_price' => 500000]);

        $this->get(route('orders.show', $order->access_token))
            ->assertOk()
            ->assertSee('Rp200.000')
            ->assertDontSee('Rp500.000');
    }

    /**
     * Phase 06: the status page must not show a live countdown for an order
     * whose deadline has passed, even when the scheduled sweep has not run
     * yet. Otherwise the participant is invited to pay for a released slot.
     */
    public function test_opening_an_overdue_order_expires_it_on_read(): void
    {
        $order = Order::factory()->overdue()->create();

        $this->get(route('orders.show', $order->access_token))->assertOk();

        $this->assertSame(OrderStatus::Expired, $order->fresh()->status);
    }

    public function test_opening_an_order_before_its_deadline_does_not_expire_it(): void
    {
        $order = Order::factory()->create(['expires_at' => now()->addMinutes(30)]);

        $this->get(route('orders.show', $order->access_token))->assertOk();

        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_status_page_reports_an_expired_order(): void
    {
        $order = Order::factory()->expired()->create();

        $this->get(route('orders.show', $order->access_token))
            ->assertOk()
            ->assertSee('Kedaluwarsa');
    }

    public function test_status_page_reports_an_order_awaiting_verification(): void
    {
        $order = Order::factory()->pendingVerification()->create();

        $this->get(route('orders.show', $order->access_token))
            ->assertOk()
            ->assertSee('Menunggu Verifikasi');
    }
}

<?php

namespace Tests\Feature\Participant;

use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'participant_name' => 'Siti Rahma',
            'participant_whatsapp' => '081234567890',
            'participant_email' => 'siti@example.com',
        ], $overrides);
    }

    public function test_participant_can_create_an_order_for_a_published_event(): void
    {
        $event = Event::factory()->published()->create([
            'ticket_price' => 200000,
            'quota' => 10,
        ]);

        $response = $this->post(route('orders.store', $event->slug), $this->validPayload());

        $order = Order::query()->sole();

        $response->assertRedirect(route('orders.show', $order->access_token));

        $this->assertSame($event->id, $order->event_id);
        $this->assertSame('Siti Rahma', $order->participant_name);
        $this->assertSame('siti@example.com', $order->participant_email);
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
    }

    public function test_order_stores_a_price_snapshot_that_survives_event_price_changes(): void
    {
        $event = Event::factory()->published()->create(['ticket_price' => 200000]);

        $this->post(route('orders.store', $event->slug), $this->validPayload());

        $order = Order::query()->sole();
        $this->assertSame(200000, $order->ticket_price_snapshot);

        // PRD business rule 12: repricing the event must not touch existing
        // orders.
        $event->update(['ticket_price' => 350000]);

        $this->assertSame(200000, $order->fresh()->ticket_price_snapshot);
    }

    public function test_order_deadline_is_sixty_minutes_after_creation(): void
    {
        $event = Event::factory()->published()->create();

        $this->post(route('orders.store', $event->slug), $this->validPayload());

        $order = Order::query()->sole();

        $this->assertSame(
            OrderService::PAYMENT_WINDOW_MINUTES,
            (int) $order->created_at->diffInMinutes($order->expires_at)
        );
    }

    public function test_each_order_receives_a_unique_reference_and_access_token(): void
    {
        $event = Event::factory()->published()->create(['quota' => 5]);

        $this->post(route('orders.store', $event->slug), $this->validPayload());
        $this->post(route('orders.store', $event->slug), $this->validPayload([
            'participant_email' => 'lain@example.com',
        ]));

        $orders = Order::query()->get();

        $this->assertCount(2, $orders);
        $this->assertCount(2, $orders->pluck('order_reference')->unique());
        $this->assertCount(2, $orders->pluck('access_token')->unique());
    }

    public function test_one_order_reserves_exactly_one_quota_slot(): void
    {
        $event = Event::factory()->published()->create(['quota' => 3]);

        $this->post(route('orders.store', $event->slug), $this->validPayload());

        $this->assertSame(1, $event->fresh()->reservedQuota());
        $this->assertSame(2, $event->fresh()->remainingQuota());
    }

    public function test_order_is_rejected_when_quota_is_exhausted(): void
    {
        $event = Event::factory()->published()->create(['quota' => 1]);

        Order::factory()->forEvent($event)->create();

        $this->post(route('orders.store', $event->slug), $this->validPayload())
            ->assertRedirect()
            ->assertSessionHas('error');

        // Still only the pre-existing order: the event is not oversold.
        $this->assertSame(1, Order::query()->count());
    }

    public function test_order_cannot_be_created_for_a_draft_event(): void
    {
        $event = Event::factory()->draft()->create();

        $this->post(route('orders.store', $event->slug), $this->validPayload())
            ->assertNotFound();

        $this->assertSame(0, Order::query()->count());
    }

    public function test_order_cannot_be_created_for_a_closed_event(): void
    {
        $event = Event::factory()->closed()->create();

        $this->post(route('orders.store', $event->slug), $this->validPayload())
            ->assertNotFound();

        $this->assertSame(0, Order::query()->count());
    }

    public function test_participant_data_is_validated_on_the_server(): void
    {
        $event = Event::factory()->published()->create();

        $this->post(route('orders.store', $event->slug), [
            'participant_name' => 'A',
            'participant_whatsapp' => 'bukan-nomor',
            'participant_email' => 'bukan-email',
        ])->assertSessionHasErrors([
            'participant_name',
            'participant_whatsapp',
            'participant_email',
        ]);

        $this->assertSame(0, Order::query()->count());
    }

    public function test_missing_participant_fields_are_rejected(): void
    {
        $event = Event::factory()->published()->create();

        $this->post(route('orders.store', $event->slug), [])
            ->assertSessionHasErrors([
                'participant_name',
                'participant_whatsapp',
                'participant_email',
            ]);

        $this->assertSame(0, Order::query()->count());
    }

    public function test_whatsapp_number_is_normalised_before_storage(): void
    {
        $event = Event::factory()->published()->create();

        $this->post(route('orders.store', $event->slug), $this->validPayload([
            'participant_whatsapp' => '+62 812-3456-7890',
        ]))->assertSessionHasNoErrors();

        // Separators and the leading + are stripped so admins see one
        // consistent format.
        $this->assertSame('6281234567890', Order::query()->sole()->participant_whatsapp);
    }

    /**
     * Price, status, and deadline must come from the server, never from the
     * request, even if a crafted form posts them.
     */
    public function test_price_status_and_deadline_cannot_be_set_from_request_input(): void
    {
        $event = Event::factory()->published()->create(['ticket_price' => 200000]);

        $this->post(route('orders.store', $event->slug), $this->validPayload([
            'ticket_price_snapshot' => 1,
            'status' => OrderStatus::Paid->value,
            'expires_at' => now()->addYear()->toDateTimeString(),
            'order_reference' => 'ALM-PALSU',
        ]))->assertSessionHasNoErrors();

        $order = Order::query()->sole();

        $this->assertSame(200000, $order->ticket_price_snapshot);
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertNotSame('ALM-PALSU', $order->order_reference);
        $this->assertTrue($order->expires_at->lessThan(now()->addDay()));
    }
}

<?php

namespace Tests\Feature\Participant;

use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Services\OrderExpiryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 06 — the scheduled expiry of overdue orders (PRD FR-08).
 *
 * Quota arithmetic itself is covered by QuotaIntegrityTest; these tests are
 * about the sweep: what it transitions, what it must refuse to touch, and
 * that running it twice is harmless.
 */
class OrderExpiryTest extends TestCase
{
    use RefreshDatabase;

    private function sweep(): int
    {
        return app(OrderExpiryService::class)->sweep();
    }

    public function test_an_unpaid_order_past_its_deadline_is_expired(): void
    {
        $order = Order::factory()->overdue()->create();

        $this->assertSame(1, $this->sweep());
        $this->assertSame(OrderStatus::Expired, $order->fresh()->status);
    }

    public function test_an_unpaid_order_before_its_deadline_is_left_alone(): void
    {
        $order = Order::factory()->create(['expires_at' => now()->addMinutes(5)]);

        $this->assertSame(0, $this->sweep());
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    /**
     * The headline rule of FR-08: a proof submitted in time protects the
     * order even though its deadline is in the past.
     */
    public function test_an_order_awaiting_verification_is_never_expired(): void
    {
        $order = Order::factory()->pendingVerification()->create();

        $this->assertSame(0, $this->sweep());
        $this->assertSame(OrderStatus::PendingVerification, $order->fresh()->status);
    }

    public function test_a_paid_order_is_never_expired(): void
    {
        $order = Order::factory()->paid()->create();

        $this->assertSame(0, $this->sweep());
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_already_terminal_orders_are_not_touched_again(): void
    {
        $expired = Order::factory()->expired()->create();
        $rejected = Order::factory()->rejected()->create();

        $this->assertSame(0, $this->sweep());
        $this->assertSame(OrderStatus::Expired, $expired->fresh()->status);
        $this->assertSame(OrderStatus::Rejected, $rejected->fresh()->status);
    }

    /**
     * PRD FR-08 / Phase 06 acceptance: "proses dapat dijalankan ulang tanpa
     * merusak data" and quota must be released exactly once.
     */
    public function test_the_sweep_is_idempotent(): void
    {
        $event = Event::factory()->published()->create(['quota' => 5]);
        Order::factory()->forEvent($event)->overdue()->count(3)->create();

        $this->assertSame(3, $this->sweep());

        // Second and third runs find nothing left to do.
        $this->assertSame(0, $this->sweep());
        $this->assertSame(0, $this->sweep());

        $this->assertSame(3, Order::query()->where('status', OrderStatus::Expired)->count());
        $this->assertSame(5, $event->fresh()->remainingQuota());
    }

    /**
     * The exact boundary. An order expiring precisely now is overdue, which
     * must match the complement used by scopeHoldingQuota() so a slot is
     * never both held and expirable.
     */
    public function test_an_order_expiring_exactly_now_is_overdue(): void
    {
        $event = Event::factory()->published()->create(['quota' => 1]);
        $order = Order::factory()->forEvent($event)->create([
            'expires_at' => now(),
        ]);

        $this->assertSame(0, $event->fresh()->reservedQuota());
        $this->assertSame(1, $this->sweep());
        $this->assertSame(OrderStatus::Expired, $order->fresh()->status);
    }

    /**
     * Expiring an order must free the slot for someone else, which is the
     * whole business point of the deadline.
     */
    public function test_expiring_an_order_frees_the_slot_for_a_new_participant(): void
    {
        $event = Event::factory()->published()->create(['quota' => 1]);
        Order::factory()->forEvent($event)->overdue()->create();

        $this->sweep();

        $this->post(route('orders.store', $event->slug), [
            'participant_name' => 'Peserta Baru',
            'participant_whatsapp' => '081234567890',
            'participant_email' => 'baru@example.com',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $event->fresh()->reservedQuota());
        $this->assertSame(0, $event->fresh()->remainingQuota());
    }

    public function test_the_sweep_clears_every_overdue_order_in_one_run(): void
    {
        $event = Event::factory()->published()->create(['quota' => 1000]);
        Order::factory()->forEvent($event)->overdue()->count(12)->create();

        $this->assertSame(12, $this->sweep());
        $this->assertSame(0, Order::query()->overdue()->count());
    }

    public function test_expire_reports_whether_it_changed_the_order(): void
    {
        $service = app(OrderExpiryService::class);

        $overdue = Order::factory()->overdue()->create();
        $this->assertTrue($service->expire($overdue));
        $this->assertSame(OrderStatus::Expired, $overdue->status);

        // Second call changes nothing and says so.
        $this->assertFalse($service->expire($overdue));
    }

    public function test_expire_refuses_an_order_that_is_not_yet_overdue(): void
    {
        $order = Order::factory()->create(['expires_at' => now()->addMinutes(10)]);

        $this->assertFalse(app(OrderExpiryService::class)->expire($order));
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_the_overdue_scope_matches_only_unpaid_past_deadline_orders(): void
    {
        Order::factory()->overdue()->create();
        Order::factory()->pendingVerification()->create();
        Order::factory()->paid()->create();
        Order::factory()->expired()->create();
        Order::factory()->create(['expires_at' => now()->addMinutes(30)]);

        $this->assertSame(1, Order::query()->overdue()->count());
    }
}

<?php

namespace Tests\Feature\Participant;

use App\Exceptions\QuotaUnavailableException;
use App\Models\Event;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotaIntegrityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{participant_name: string, participant_whatsapp: string, participant_email: string}
     */
    private function participant(string $email = 'peserta@example.com'): array
    {
        return [
            'participant_name' => 'Peserta Uji',
            'participant_whatsapp' => '081234567890',
            'participant_email' => $email,
        ];
    }

    public function test_orders_awaiting_verification_keep_holding_quota_after_the_deadline(): void
    {
        $event = Event::factory()->published()->create(['quota' => 5]);

        // expires_at is in the past, but a proof was submitted in time, so
        // the slot stays reserved (PRD FR-08).
        Order::factory()->forEvent($event)->pendingVerification()->create();

        $this->assertSame(1, $event->fresh()->reservedQuota());
    }

    public function test_paid_orders_keep_holding_quota(): void
    {
        $event = Event::factory()->published()->create(['quota' => 5]);

        Order::factory()->forEvent($event)->paid()->create();

        $this->assertSame(1, $event->fresh()->reservedQuota());
    }

    public function test_expired_and_rejected_orders_release_their_quota(): void
    {
        $event = Event::factory()->published()->create(['quota' => 5]);

        Order::factory()->forEvent($event)->expired()->create();
        Order::factory()->forEvent($event)->rejected()->create();

        $this->assertSame(0, $event->fresh()->reservedQuota());
        $this->assertSame(5, $event->fresh()->remainingQuota());
    }

    /**
     * An unpaid order past its deadline must stop holding its slot even
     * before the Phase 06 sweep flips the status, otherwise a late
     * scheduler would keep tickets off sale.
     */
    public function test_overdue_unpaid_orders_do_not_hold_quota_before_the_sweep_runs(): void
    {
        $event = Event::factory()->published()->create(['quota' => 1]);

        Order::factory()->forEvent($event)->overdue()->create();

        $this->assertSame(0, $event->fresh()->reservedQuota());

        // The freed slot is genuinely usable again.
        $this->post(route('orders.store', $event->slug), $this->participant())
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $event->fresh()->reservedQuota());
    }

    public function test_quota_cannot_be_oversold_by_sequential_orders(): void
    {
        $event = Event::factory()->published()->create(['quota' => 3]);

        for ($i = 1; $i <= 5; $i++) {
            $this->post(route('orders.store', $event->slug), $this->participant("p{$i}@example.com"));
        }

        // Three succeed, the last two are refused.
        $this->assertSame(3, Order::query()->count());
        $this->assertSame(0, $event->fresh()->remainingQuota());
    }

    public function test_service_throws_when_the_last_slot_is_already_taken(): void
    {
        $event = Event::factory()->published()->create(['quota' => 1]);

        Order::factory()->forEvent($event)->create();

        $this->expectException(QuotaUnavailableException::class);

        app(OrderService::class)->create($event, $this->participant());
    }

    /**
     * The true concurrent-request test lives in ConcurrentOrderingTest,
     * which cannot use RefreshDatabase: that trait wraps each test in an
     * uncommitted transaction, so forked processes on their own connections
     * would not be able to see the event at all.
     */
}

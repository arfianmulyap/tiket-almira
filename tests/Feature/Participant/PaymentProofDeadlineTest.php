<?php

namespace Tests\Feature\Participant;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The payment deadline as it applies to proof submission (PRD FR-06, FR-08).
 *
 * These are the boundary cases that decide whether a quota slot is kept or
 * released, so they are tested against the clock rather than only against the
 * stored status.
 */
class PaymentProofDeadlineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function submit(Order $order)
    {
        return $this->post(route('orders.payment-proof.store', $order->access_token), [
            'payment_method' => 'dev_bank_transfer',
            'proof' => UploadedFile::fake()->image('bukti.jpg'),
        ]);
    }

    /**
     * An order whose deadline has passed but which the sweep has not yet
     * touched must still refuse the upload. The status alone would wrongly
     * accept it, so the service checks the clock too.
     */
    public function test_proof_is_refused_after_the_deadline_even_before_the_sweep_runs(): void
    {
        $order = Order::factory()->overdue()->create();

        $this->submit($order)->assertSessionHas('error');

        $this->assertDatabaseCount('payments', 0);
        $this->assertNotSame(OrderStatus::PendingVerification, $order->fresh()->status);
    }

    /**
     * The refused upload must not leave its file behind.
     */
    public function test_refused_submission_does_not_leave_an_orphaned_file(): void
    {
        $order = Order::factory()->overdue()->create();

        $this->submit($order);

        $this->assertEmpty(Storage::disk('local')->allFiles('payment-proofs'));
    }

    public function test_proof_is_refused_for_an_expired_order(): void
    {
        $order = Order::factory()->expired()->create();

        $this->submit($order)->assertSessionHas('error');

        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(OrderStatus::Expired, $order->fresh()->status);
    }

    /**
     * Submitting one second before the deadline is accepted: the rule is
     * "before expires_at", not "comfortably before".
     */
    public function test_proof_submitted_just_before_the_deadline_is_accepted(): void
    {
        $order = Order::factory()->create(['expires_at' => now()->addSecond()]);

        $this->submit($order)->assertSessionHasNoErrors();

        $this->assertSame(OrderStatus::PendingVerification, $order->fresh()->status);
    }

    /**
     * PRD FR-08: once a proof has arrived in time the order keeps its slot
     * past minute 60 and must not be expired by the sweep.
     */
    public function test_order_awaiting_verification_is_not_expired_after_the_deadline(): void
    {
        $order = Order::factory()->create(['expires_at' => now()->addMinute()]);

        $this->submit($order);

        $this->travel(2)->hours();

        $this->artisan('orders:expire-overdue')->assertSuccessful();

        $this->assertSame(OrderStatus::PendingVerification, $order->fresh()->status);
    }

    /**
     * The slot stays reserved while the proof waits for review, so the event
     * must not resell it.
     */
    public function test_submitted_proof_keeps_holding_its_quota_slot(): void
    {
        $order = Order::factory()->create(['expires_at' => now()->addMinute()]);
        $event = $order->event;

        $this->submit($order);

        $this->travel(2)->hours();

        $this->assertSame(1, $event->fresh()->reservedQuota());
    }

    /**
     * A second proof for the same order is refused. The unique index on
     * payments.order_id is the hard guarantee; this proves the application
     * layer refuses it cleanly rather than erroring.
     */
    public function test_a_second_proof_for_the_same_order_is_refused(): void
    {
        $order = Order::factory()->create();

        $this->submit($order)->assertSessionHasNoErrors();
        $this->submit($order)->assertSessionHas('error');

        $this->assertSame(1, Payment::query()->where('order_id', $order->id)->count());
    }

    public function test_proof_is_refused_for_an_already_paid_order(): void
    {
        $order = Order::factory()->paid()->create();

        $this->submit($order)->assertSessionHas('error');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_proof_is_refused_for_a_rejected_order(): void
    {
        // Re-upload after rejection is an open business decision (PRD section
        // 15 question 3). Until it is answered the transition does not exist,
        // and this test pins that so it cannot be enabled by accident.
        $order = Order::factory()->rejected()->create();

        $this->submit($order)->assertSessionHas('error');

        $this->assertDatabaseCount('payments', 0);
    }
}

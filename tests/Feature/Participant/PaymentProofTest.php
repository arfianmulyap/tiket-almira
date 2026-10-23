<?php

namespace Tests\Feature\Participant;

use App\Enums\AuditAction;
use App\Enums\OrderStatus;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Payment proof submission (PRD FR-06, FR-08).
 */
class PaymentProofTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The private disk. Faking only this one keeps the split from the
        // public poster disk visible.
        Storage::fake('local');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'payment_method' => 'dev_bank_transfer',
            'proof' => UploadedFile::fake()->image('bukti.jpg'),
        ], $overrides);
    }

    private function submit(Order $order, array $overrides = [])
    {
        return $this->post(
            route('orders.payment-proof.store', $order->access_token),
            $this->payload($overrides)
        );
    }

    public function test_proof_submitted_before_the_deadline_moves_the_order_to_pending_verification(): void
    {
        $order = Order::factory()->create();

        $this->submit($order)->assertSessionHasNoErrors();

        $this->assertSame(OrderStatus::PendingVerification, $order->fresh()->status);
    }

    public function test_submitting_a_proof_creates_a_payment_record(): void
    {
        $order = Order::factory()->create(['ticket_price_snapshot' => 175000]);

        $this->submit($order);

        $payment = Payment::first();

        $this->assertNotNull($payment);
        $this->assertSame($order->id, $payment->order_id);
        $this->assertSame('dev_bank_transfer', $payment->method);

        // Taken from the order's snapshot, never from request input.
        $this->assertSame(175000, $payment->amount);
    }

    public function test_submission_time_is_recorded(): void
    {
        $order = Order::factory()->create();

        $this->submit($order);

        $this->assertNotNull(Payment::first()->submitted_at);
    }

    /**
     * The amount is the order's snapshot, so a participant cannot declare
     * what they owe by posting it.
     */
    public function test_amount_cannot_be_overridden_by_request_input(): void
    {
        $order = Order::factory()->create(['ticket_price_snapshot' => 200000]);

        $this->submit($order, ['amount' => 1]);

        $this->assertSame(200000, Payment::first()->amount);
    }

    public function test_proof_is_stored_on_the_private_disk(): void
    {
        $order = Order::factory()->create();

        $this->submit($order);

        $path = Payment::first()->proof_path;

        $this->assertStringStartsWith('payment-proofs/', $path);
        Storage::disk('local')->assertExists($path);

        // Must not land on the public disk, which is served over HTTP.
        Storage::disk('public')->assertMissing($path);
    }

    /**
     * The participant's filename is attacker-controlled, so it must not
     * survive onto disk.
     */
    public function test_original_filename_is_not_used_on_disk(): void
    {
        $order = Order::factory()->create();

        $this->submit($order, [
            'proof' => UploadedFile::fake()->image('../../evil payload.jpg'),
        ]);

        $path = Payment::first()->proof_path;

        $this->assertStringNotContainsString('evil', $path);
        $this->assertStringNotContainsString('..', $path);
    }

    public function test_submission_is_recorded_in_the_audit_trail(): void
    {
        $order = Order::factory()->create();

        $this->submit($order);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => $order->getMorphClass(),
            'auditable_id' => $order->id,
            'action' => AuditAction::PaymentProofSubmitted->value,
        ]);

        // No actor: the participant is not an authenticated user.
        $this->assertNull(AuditLog::first()->user_id);
    }

    /**
     * PRD business rule 9: an uploaded proof is not an approved payment.
     */
    public function test_submitting_a_proof_does_not_mark_the_order_as_paid(): void
    {
        $order = Order::factory()->create();

        $this->submit($order);

        $this->assertNotSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertNull($order->fresh()->ticket);
        $this->assertDatabaseCount('tickets', 0);
    }
}

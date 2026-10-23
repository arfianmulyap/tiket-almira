<?php

namespace Tests\Feature\Admin;

use App\Enums\AuditAction;
use App\Enums\OrderStatus;
use App\Enums\TicketStatus;
use App\Exceptions\OrderNotDecidableException;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use App\Services\OrderExpiryService;
use App\Services\PaymentVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Phase 07 — the payment decision itself (PRD FR-07, FR-09).
 *
 * These exercise the service directly, where the transaction and the row lock
 * live. The HTTP layer is covered by PaymentVerificationRouteTest.
 */
class PaymentVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function service(): PaymentVerificationService
    {
        return app(PaymentVerificationService::class);
    }

    public function test_approving_marks_the_order_paid_and_records_the_reviewer(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->pendingVerification()->create();

        $this->service()->approve($order, $admin);

        $order->refresh();

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame($admin->id, $order->reviewed_by);
        $this->assertNotNull($order->reviewed_at);
        $this->assertNull($order->rejection_reason);
    }

    public function test_approving_issues_exactly_one_valid_ticket(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->pendingVerification()->create();

        $ticket = $this->service()->approve($order, $admin);

        $this->assertSame(1, Ticket::query()->count());
        $this->assertSame($order->id, $ticket->order_id);
        $this->assertSame(TicketStatus::Valid, $ticket->status);
        $this->assertNotNull($ticket->issued_at);
    }

    /**
     * PRD FR-09: the token must be unguessable, so it is long and random
     * rather than derived from the order.
     */
    public function test_the_qr_token_is_long_random_and_unique(): void
    {
        $admin = User::factory()->admin()->create();

        $first = $this->service()->approve(
            Order::factory()->pendingVerification()->create(), $admin
        );
        $second = $this->service()->approve(
            Order::factory()->pendingVerification()->create(), $admin
        );

        $this->assertSame(64, strlen($first->qr_token));
        $this->assertNotSame($first->qr_token, $second->qr_token);
    }

    /**
     * The idempotency requirement in PRD FR-09 and acceptance criterion
     * "Pemrosesan ulang tidak membuat tiket duplikat".
     */
    public function test_approving_twice_does_not_issue_a_second_ticket(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->pendingVerification()->create();

        $first = $this->service()->approve($order, $admin);

        // The replay must be refused outright: the order is no longer
        // awaiting verification.
        $this->expectException(OrderNotDecidableException::class);

        try {
            $this->service()->approve($order, $admin);
        } finally {
            $this->assertSame(1, Ticket::query()->count());
            $this->assertSame($first->qr_token, Ticket::query()->sole()->qr_token);
        }
    }

    public function test_rejecting_records_the_reason_and_issues_no_ticket(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->pendingVerification()->create();

        $this->service()->reject($order, $admin, 'Nominal transfer tidak sesuai tagihan.');

        $order->refresh();

        $this->assertSame(OrderStatus::Rejected, $order->status);
        $this->assertSame('Nominal transfer tidak sesuai tagihan.', $order->rejection_reason);
        $this->assertSame($admin->id, $order->reviewed_by);
        $this->assertNotNull($order->reviewed_at);

        // PRD FR-07: a rejection never issues a ticket.
        $this->assertSame(0, Ticket::query()->count());
    }

    public function test_a_rejected_order_cannot_then_be_approved(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->pendingVerification()->create();

        $this->service()->reject($order, $admin, 'Bukti tidak terbaca sama sekali.');

        $this->expectException(OrderNotDecidableException::class);

        try {
            $this->service()->approve($order, $admin);
        } finally {
            $this->assertSame(OrderStatus::Rejected, $order->fresh()->status);
            $this->assertSame(0, Ticket::query()->count());
        }
    }

    /**
     * Guards the status gate on both sides: an order that never submitted a
     * proof, and one whose deadline has passed, must not be decidable.
     * Otherwise an admin could approve a slot the participant no longer
     * holds.
     */
    #[DataProvider('undecidableStates')]
    public function test_only_an_order_awaiting_verification_can_be_decided(string $state): void
    {
        $admin = User::factory()->admin()->create();

        $order = $state === 'pendingPayment'
            ? Order::factory()->create()
            : Order::factory()->{$state}()->create();

        $this->expectException(OrderNotDecidableException::class);

        try {
            $this->service()->approve($order, $admin);
        } finally {
            $this->assertSame(0, Ticket::query()->count());
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function undecidableStates(): array
    {
        return [
            'awaiting payment' => ['pendingPayment'],
            'past its deadline' => ['overdue'],
            'already expired' => ['expired'],
            'already rejected' => ['rejected'],
        ];
    }

    public function test_an_approval_is_written_to_the_audit_trail(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->pendingVerification()->create();

        $this->service()->approve($order, $admin);

        // pluck() applies the model's cast, so these are AuditAction
        // instances rather than their backing strings.
        $actions = $order->auditLogs()->pluck('action');

        $this->assertTrue($actions->contains(AuditAction::PaymentApproved));
        $this->assertTrue($actions->contains(AuditAction::TicketIssued));

        $log = $order->auditLogs()
            ->where('action', AuditAction::PaymentApproved->value)
            ->sole();

        $this->assertSame($admin->id, $log->user_id);
        // Copied, so the trail survives the admin account being deleted.
        $this->assertSame($admin->name, $log->user_name);
        $this->assertNotNull($log->created_at);
    }

    public function test_a_rejection_is_written_to_the_audit_trail_with_its_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->pendingVerification()->create();

        $this->service()->reject($order, $admin, 'Rekening pengirim tidak cocok.');

        $log = $order->auditLogs()
            ->where('action', AuditAction::PaymentRejected->value)
            ->sole();

        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('Rekening pengirim tidak cocok.', $log->metadata['reason']);
    }

    /**
     * PRD FR-08: an approved order keeps its slot even though its original
     * 60 minute deadline is long past.
     */
    public function test_an_approved_order_still_holds_its_quota_slot(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->pendingVerification()->create();

        $this->service()->approve($order, $admin);

        $this->assertSame(1, $order->event->fresh()->reservedQuota());
    }

    /**
     * A rejection frees the slot. There is no counter to decrement: quota is
     * derived from order status, so this follows from the status change
     * alone.
     */
    public function test_a_rejected_order_releases_its_quota_slot(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->pendingVerification()->create();

        $this->assertSame(1, $order->event->reservedQuota());

        $this->service()->reject($order, $admin, 'Dana tidak pernah masuk ke rekening.');

        $this->assertSame(0, $order->event->fresh()->reservedQuota());
    }

    /**
     * The expiry sweep from Phase 06 must never touch an approved order, even
     * though its expires_at is in the past (PRD FR-08).
     */
    public function test_the_expiry_sweep_cannot_expire_an_approved_order(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->pendingVerification()->create();

        $this->service()->approve($order, $admin);

        $this->assertSame(0, app(OrderExpiryService::class)->sweep());
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(1, Ticket::query()->count());
    }
}

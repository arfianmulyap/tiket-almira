<?php

namespace Tests\Feature\Admin;

use App\Enums\AuditAction;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketIssuanceService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 07 — ticket issuance in isolation (PRD FR-09, business rule 10).
 *
 * PaymentVerificationTest proves a second *approval* is refused by the status
 * gate. That gate, however, means the approval path never reaches the
 * existing-ticket check inside the issuance service, so these tests drive the
 * service directly. Without them, the innermost idempotency guard and the
 * unique index are untested: verified by removing the guard, which leaves
 * PaymentVerificationTest green but fails the first test here.
 */
class TicketIssuanceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): TicketIssuanceService
    {
        return app(TicketIssuanceService::class);
    }

    /**
     * The guard that protects against a retried job or a replayed call that
     * has already bypassed the status check.
     */
    public function test_issuing_twice_returns_the_same_ticket(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->paid()->create();

        $first = $this->service()->issue($order, $admin);
        $second = $this->service()->issue($order, $admin);

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->qr_token, $second->qr_token);
        $this->assertSame(1, Ticket::query()->count());
    }

    /**
     * A duplicate must not be recorded in the audit trail either, otherwise
     * the trail would suggest two tickets exist.
     */
    public function test_a_repeated_issue_is_only_audited_once(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->paid()->create();

        $this->service()->issue($order, $admin);
        $this->service()->issue($order, $admin);

        $this->assertSame(
            1,
            $order->auditLogs()->where('action', AuditAction::TicketIssued->value)->count()
        );
    }

    /**
     * The database is the final backstop: even if every application check
     * were bypassed, the unique index on order_id refuses the second row.
     */
    public function test_the_database_refuses_a_second_ticket_for_one_order(): void
    {
        $order = Order::factory()->paid()->create();

        Ticket::factory()->forOrder($order)->create();

        $this->expectException(QueryException::class);

        // Written straight through the model to skip the service's guard.
        $duplicate = new Ticket;
        $duplicate->order_id = $order->id;
        $duplicate->qr_token = Str::random(64);
        $duplicate->issued_at = now();
        $duplicate->save();
    }

    /**
     * The QR token is the scanner's credential, so a collision would let one
     * ticket check in as another.
     */
    public function test_the_database_refuses_a_duplicate_qr_token(): void
    {
        $existing = Ticket::factory()->create();

        $this->expectException(QueryException::class);

        $duplicate = new Ticket;
        $duplicate->order_id = Order::factory()->paid()->create()->id;
        $duplicate->qr_token = $existing->qr_token;
        $duplicate->issued_at = now();
        $duplicate->save();
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 07 — authorisation and validation on the verification routes.
 *
 * PRD FR-07: "Hanya admin berwenang dapat memverifikasi pembayaran."
 */
class PaymentVerificationRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_see_the_verification_queue(): void
    {
        $this->get(route('admin.orders.index'))->assertRedirect(route('login'));
    }

    public function test_a_non_admin_cannot_see_the_verification_queue(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.orders.index'))
            ->assertForbidden();
    }

    public function test_an_admin_can_see_the_verification_queue(): void
    {
        $order = Order::factory()->pendingVerification()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee($order->order_reference);
    }

    /**
     * A non-admin must not be able to approve a payment by posting the route
     * directly, which is the attack the middleware and policy both guard.
     */
    public function test_a_non_admin_cannot_approve_a_payment(): void
    {
        $order = Order::factory()->pendingVerification()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('admin.orders.approve', $order))
            ->assertForbidden();

        $this->assertSame(OrderStatus::PendingVerification, $order->fresh()->status);
        $this->assertSame(0, Ticket::query()->count());
    }

    public function test_a_guest_cannot_approve_a_payment(): void
    {
        $order = Order::factory()->pendingVerification()->create();

        $this->post(route('admin.orders.approve', $order))
            ->assertRedirect(route('login'));

        $this->assertSame(OrderStatus::PendingVerification, $order->fresh()->status);
        $this->assertSame(0, Ticket::query()->count());
    }

    public function test_an_admin_can_approve_a_payment(): void
    {
        $order = Order::factory()->pendingVerification()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.orders.approve', $order))
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHas('success');

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(1, Ticket::query()->count());
    }

    /**
     * The policy refuses a decision on an order that is not awaiting one, so
     * a replayed or stale submit cannot overwrite an existing decision.
     */
    public function test_approving_an_already_decided_order_is_forbidden(): void
    {
        $order = Order::factory()->paid()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.orders.approve', $order))
            ->assertForbidden();

        $this->assertSame(0, Ticket::query()->count());
    }

    public function test_rejecting_requires_a_reason(): void
    {
        $order = Order::factory()->pendingVerification()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.orders.reject', $order), ['rejection_reason' => ''])
            ->assertSessionHasErrors('rejection_reason');

        $this->assertSame(OrderStatus::PendingVerification, $order->fresh()->status);
    }

    public function test_rejecting_refuses_a_too_short_reason(): void
    {
        $order = Order::factory()->pendingVerification()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.orders.reject', $order), ['rejection_reason' => 'salah'])
            ->assertSessionHasErrors('rejection_reason');

        $this->assertSame(OrderStatus::PendingVerification, $order->fresh()->status);
    }

    public function test_an_admin_can_reject_a_payment_with_a_reason(): void
    {
        $order = Order::factory()->pendingVerification()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.orders.reject', $order), [
                'rejection_reason' => 'Bukti transfer tidak dapat dibaca.',
            ])
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHas('success');

        $order->refresh();

        $this->assertSame(OrderStatus::Rejected, $order->status);
        $this->assertSame('Bukti transfer tidak dapat dibaca.', $order->rejection_reason);
        $this->assertSame(0, Ticket::query()->count());
    }

    /**
     * The admin order detail page must not become a way to read another
     * participant's data without being an admin.
     */
    public function test_a_non_admin_cannot_open_an_order_detail_page(): void
    {
        $order = Order::factory()->pendingVerification()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('admin.orders.show', $order))
            ->assertForbidden();
    }
}

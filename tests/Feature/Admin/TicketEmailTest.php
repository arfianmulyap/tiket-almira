<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Mail\TicketIssuedMail;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * Phase 07 — e-ticket email delivery (PRD FR-09).
 *
 * The headline requirement is the negative one: "kegagalan email tidak
 * membatalkan tiket yang sudah sah".
 */
class TicketEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_email_is_sent_while_delivery_is_disabled(): void
    {
        // Delivery is off by default until a mail service is confirmed
        // (PRD open question 7).
        config(['tickets.email_enabled' => false]);

        Mail::fake();

        $order = Order::factory()->pendingVerification()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.orders.approve', $order))
            ->assertRedirect(route('admin.orders.show', $order));

        Mail::assertNothingSent();

        // The ticket is still issued: delivery and issuance are independent.
        $this->assertSame(1, Ticket::query()->count());
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_the_eticket_is_emailed_when_delivery_is_enabled(): void
    {
        config(['tickets.email_enabled' => true]);

        Mail::fake();

        $order = Order::factory()->pendingVerification()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.orders.approve', $order))
            ->assertRedirect(route('admin.orders.show', $order));

        Mail::assertSent(TicketIssuedMail::class, function (TicketIssuedMail $mail) use ($order) {
            return $mail->hasTo($order->participant_email)
                && $mail->ticket->order_id === $order->id;
        });
    }

    /**
     * The important one. A broken mail transport must leave the approval and
     * the ticket intact, because the participant has genuinely paid.
     *
     * If TicketMailer let the exception escape, it would propagate out of the
     * controller and the approval transaction would already have committed
     * but the admin would see a 500 and assume the approval failed.
     */
    public function test_a_failing_mailer_does_not_void_the_issued_ticket(): void
    {
        config(['tickets.email_enabled' => true]);

        Mail::shouldReceive('send')
            ->once()
            ->andThrow(new RuntimeException('SMTP unreachable'));

        $order = Order::factory()->pendingVerification()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.orders.approve', $order))
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHas('success');

        $order->refresh();

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame(1, Ticket::query()->count());
        $this->assertNotNull($order->ticket);
    }
}

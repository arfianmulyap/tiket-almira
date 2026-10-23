<?php

namespace Tests\Feature\Participant;

use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 07 — participant access to the e-ticket (PRD FR-09, section 11).
 */
class TicketAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_ticket_page_is_reachable_with_the_order_access_token(): void
    {
        $order = Order::factory()->paid()->create();
        $ticket = Ticket::factory()->forOrder($order)->create();

        $this->get(route('tickets.show', $order->access_token))
            ->assertOk()
            ->assertSee($order->order_reference)
            ->assertSee($order->participant_name);

        $this->assertNotNull($ticket->fresh());
    }

    /**
     * The QR has to actually be rendered, not just the surrounding markup:
     * a ticket without a scannable code is useless at the gate.
     */
    public function test_the_ticket_page_renders_a_qr_code(): void
    {
        $order = Order::factory()->paid()->create();
        Ticket::factory()->forOrder($order)->create();

        $response = $this->get(route('tickets.show', $order->access_token));

        $response->assertOk();
        $response->assertSee('<svg', false);
    }

    /**
     * PRD section 11: the QR token must not be exposed in a URL. It is the
     * scanner's credential, so it stays out of browser history and logs.
     */
    public function test_the_qr_token_is_not_used_as_the_page_url(): void
    {
        $order = Order::factory()->paid()->create();
        $ticket = Ticket::factory()->forOrder($order)->create();

        // The ticket is addressed by the order's access token only.
        $this->get('/tickets/'.$ticket->qr_token)->assertNotFound();
    }

    public function test_an_unknown_token_is_not_found(): void
    {
        $this->get(route('tickets.show', Str::random(64)))->assertNotFound();
    }

    /**
     * PRD FR-09: no ticket exists before the payment is approved, so there is
     * nothing to show even to the legitimate token holder.
     */
    public function test_an_order_without_a_ticket_has_no_ticket_page(): void
    {
        $order = Order::factory()->create();

        $this->get(route('tickets.show', $order->access_token))->assertNotFound();
    }

    /**
     * One participant must never reach another participant's ticket
     * (PRD FR-04, FR-09).
     */
    public function test_another_participants_token_does_not_open_this_ticket(): void
    {
        $mine = Order::factory()->paid()->create();
        Ticket::factory()->forOrder($mine)->create();

        $theirs = Order::factory()->paid()->create();
        Ticket::factory()->forOrder($theirs)->create();

        $this->get(route('tickets.show', $theirs->access_token))
            ->assertOk()
            ->assertDontSee($mine->order_reference)
            ->assertDontSee($mine->participant_name);
    }

    /**
     * The status page should lead the participant to the ticket once it
     * exists, and must not advertise it before then.
     */
    public function test_the_order_status_page_links_to_the_ticket_once_issued(): void
    {
        $order = Order::factory()->paid()->create();
        Ticket::factory()->forOrder($order)->create();

        $this->get(route('orders.show', $order->access_token))
            ->assertOk()
            ->assertSee(route('tickets.show', $order->access_token));
    }

    public function test_the_order_status_page_does_not_link_to_a_ticket_before_approval(): void
    {
        $order = Order::factory()->pendingVerification()->create();

        $this->get(route('orders.show', $order->access_token))
            ->assertOk()
            ->assertDontSee(route('tickets.show', $order->access_token));
    }
}

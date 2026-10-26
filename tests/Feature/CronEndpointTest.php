<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HTTP cron endpoint that runs the overdue-order sweep (PRD FR-08).
 *
 * The endpoint mutates order state without an authenticated user, so the
 * access rules below are the only thing standing between a stranger and the
 * sweep. They are tested first and in more detail than the happy path.
 */
class CronEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-cron-secret';

    private function url(?string $token = null): string
    {
        $path = route('cron.expire-overdue-orders', absolute: false);

        return $token === null ? $path : $path.'?token='.$token;
    }

    /**
     * An overdue, unpaid order: still PENDING_PAYMENT with `expires_at` in the
     * past, which is exactly what Order::scopeOverdue matches.
     */
    private function overdueOrder(): Order
    {
        return Order::factory()->overdue()->create();
    }

    public function test_sweep_runs_with_the_correct_token(): void
    {
        config()->set('cron.secret', self::SECRET);

        $order = $this->overdueOrder();

        $this->getJson($this->url(self::SECRET))
            ->assertOk()
            ->assertJson(['expired' => 1]);

        $this->assertSame(OrderStatus::Expired, $order->fresh()->status);
    }

    /**
     * Vercel Cron sends the secret as a bearer token rather than in the query
     * string, so both have to be accepted.
     */
    public function test_sweep_runs_with_a_bearer_token(): void
    {
        config()->set('cron.secret', self::SECRET);

        $this->overdueOrder();

        $this->getJson($this->url(), ['Authorization' => 'Bearer '.self::SECRET])
            ->assertOk()
            ->assertJson(['expired' => 1]);
    }

    public function test_request_without_a_token_is_refused(): void
    {
        config()->set('cron.secret', self::SECRET);

        $order = $this->overdueOrder();

        $this->getJson($this->url())->assertNotFound();

        // The important half of the assertion: refused means nothing ran.
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_request_with_a_wrong_token_is_refused(): void
    {
        config()->set('cron.secret', self::SECRET);

        $order = $this->overdueOrder();

        $this->getJson($this->url('not-the-secret'))->assertNotFound();

        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    /**
     * The fail-closed rule. With no secret configured the endpoint must refuse
     * everyone, including a caller presenting an empty token, rather than
     * becoming an open state-changing URL on a deployment where someone forgot
     * to set the variable.
     */
    public function test_endpoint_is_disabled_when_no_secret_is_configured(): void
    {
        config()->set('cron.secret', '');

        $order = $this->overdueOrder();

        $this->getJson($this->url())->assertNotFound();
        $this->getJson($this->url(''))->assertNotFound();

        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    /**
     * The sweep is the same code the scheduled command runs, so repeat calls
     * must stay harmless (PRD FR-08).
     */
    public function test_sweep_is_safe_to_call_repeatedly(): void
    {
        config()->set('cron.secret', self::SECRET);

        $this->overdueOrder();

        $this->getJson($this->url(self::SECRET))->assertJson(['expired' => 1]);
        $this->getJson($this->url(self::SECRET))->assertJson(['expired' => 0]);
    }

    public function test_orders_within_the_deadline_are_left_alone(): void
    {
        config()->set('cron.secret', self::SECRET);

        $order = Order::factory()->create();

        $this->getJson($this->url(self::SECRET))
            ->assertOk()
            ->assertJson(['expired' => 0]);

        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }
}

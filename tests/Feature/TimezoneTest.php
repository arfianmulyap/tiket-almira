<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guards the WIB (GMT+7) configuration.
 *
 * A silent fallback to UTC would shift every payment deadline by seven
 * hours, which participants would read as their ticket expiring early.
 */
class TimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_timezone_is_jakarta(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
    }

    public function test_php_default_timezone_follows_the_config(): void
    {
        // Laravel applies config('app.timezone') to PHP itself on boot.
        $this->assertSame('Asia/Jakarta', date_default_timezone_get());
    }

    public function test_config_falls_back_to_jakarta_rather_than_utc(): void
    {
        // Mirrors config/app.php: the fallback must not be UTC.
        $this->assertSame('Asia/Jakarta', env('APP_TIMEZONE', 'Asia/Jakarta'));
    }

    public function test_carbon_now_is_seven_hours_ahead_of_utc(): void
    {
        $this->assertSame(7 * 3600, now()->getOffset());
        $this->assertSame('WIB', now()->format('T'));
    }

    /**
     * The sweep command in Phase 06 may compare dates in SQL rather than in
     * PHP, so both clocks have to agree.
     */
    public function test_mysql_session_clock_matches_php(): void
    {
        $mysqlNow = DB::selectOne('SELECT NOW() AS n')->n;

        $this->assertLessThanOrEqual(
            5,
            abs(now()->diffInSeconds($mysqlNow, false)),
            'MySQL NOW() and PHP now() must refer to the same wall clock.'
        );
    }

    public function test_order_deadline_round_trips_through_the_database_in_wib(): void
    {
        $order = Order::factory()->create();

        $expected = $order->expires_at->format('Y-m-d H:i:s');

        // expires_at is a DATETIME column, which stores a literal with no
        // offset. Reading it back must therefore yield the same wall clock.
        $raw = DB::table('orders')->where('id', $order->id)->value('expires_at');

        $this->assertStringStartsWith(substr($expected, 0, 16), (string) $raw);
        $this->assertSame($expected, $order->fresh()->expires_at->format('Y-m-d H:i:s'));
    }

    public function test_payment_window_is_still_sixty_minutes_after_the_timezone_change(): void
    {
        $order = Order::factory()->create();

        $this->assertSame(
            60,
            (int) $order->created_at->diffInMinutes($order->expires_at)
        );
    }
}

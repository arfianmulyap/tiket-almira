<?php

namespace Tests\Feature\Participant;

use App\Models\Event;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

/**
 * Proves PRD business rule 15: two concurrent requests cannot both take the
 * last quota slot.
 *
 * DatabaseTruncation is used instead of RefreshDatabase on purpose.
 * RefreshDatabase keeps every test inside one uncommitted transaction, so a
 * forked process on its own connection could not see the event, and the
 * race could not be reproduced at all. Truncation commits its data, at the
 * cost of a slower reset between tests.
 */
class ConcurrentOrderingTest extends TestCase
{
    use DatabaseTruncation;

    /**
     * Remove the committed rows this class leaves behind.
     *
     * DatabaseTruncation commits its data, and RefreshDatabase in the other
     * test classes only rolls back its own transaction: it never clears rows
     * that already existed. Without this cleanup the event and orders
     * created here would leak into every test that runs afterwards and
     * break unrelated assertions.
     *
     * Orders first, then events: the foreign key is restrictOnDelete.
     */
    protected function tearDown(): void
    {
        Order::query()->delete();
        Event::query()->delete();

        parent::tearDown();
    }

    /**
     * @return array{participant_name: string, participant_whatsapp: string, participant_email: string}
     */
    private function participant(string $email): array
    {
        return [
            'participant_name' => 'Peserta Uji',
            'participant_whatsapp' => '081234567890',
            'participant_email' => $email,
        ];
    }

    /**
     * Four processes race for a single slot; exactly one may win.
     *
     * Each child reconnects so it holds its own MySQL session. The row lock
     * taken by OrderService is therefore the only thing serialising them: if
     * the lock were removed, several children would read "0 reserved" at the
     * same time and this assertion would fail.
     */
    public function test_concurrent_requests_cannot_oversell_the_last_slot(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl is required to create real concurrent connections.');
        }

        $event = Event::factory()->published()->create(['quota' => 1]);

        // Make sure the event is committed and visible to the children
        // before any of them start.
        $this->assertSame(1, Event::query()->whereKey($event->id)->count());

        $children = [];

        for ($i = 0; $i < 4; $i++) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                $this->fail('Unable to fork a child process.');
            }

            if ($pid === 0) {
                $exitCode = 1;

                try {
                    // A forked child inherits the parent's socket; without
                    // reconnecting, both would write over one connection.
                    DB::reconnect();

                    app(OrderService::class)->create(
                        $event->fresh(),
                        $this->participant("race{$i}@example.com")
                    );

                    $exitCode = 0;
                } catch (Throwable) {
                    $exitCode = 1;
                }

                // exit() straight from the child so PHPUnit does not try to
                // report a second set of results from this process.
                exit($exitCode);
            }

            $children[] = $pid;
        }

        $succeeded = 0;

        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);

            if (pcntl_wifexited($status) && pcntl_wexitstatus($status) === 0) {
                $succeeded++;
            }
        }

        $this->assertSame(1, $succeeded, 'Exactly one concurrent request may win the last slot.');
        $this->assertSame(1, Order::query()->count());
        $this->assertSame(0, $event->fresh()->remainingQuota());
    }
}

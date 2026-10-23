<?php

namespace Tests\Feature\Participant;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Server-side validation of the uploaded proof (PRD FR-06, section 7).
 */
class PaymentProofValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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

    /**
     * PRD FR-06: the proof is mandatory. Without it there is nothing for an
     * admin to review, so the order must not enter the queue.
     */
    public function test_proof_file_is_required(): void
    {
        $order = Order::factory()->create();

        $this->submit($order, ['proof' => null])->assertSessionHasErrors('proof');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_payment_method_is_required(): void
    {
        $order = Order::factory()->create();

        $this->submit($order, ['payment_method' => null])
            ->assertSessionHasErrors('payment_method');

        $this->assertDatabaseCount('payments', 0);
    }

    /**
     * A method that was never offered cannot be selected by posting its key.
     */
    public function test_unknown_payment_method_is_rejected(): void
    {
        $order = Order::factory()->create();

        $this->submit($order, ['payment_method' => 'not_a_real_method'])
            ->assertSessionHasErrors('payment_method');

        $this->assertDatabaseCount('payments', 0);
    }

    /**
     * An inactive method must be unusable even though it exists in config.
     */
    public function test_inactive_payment_method_is_rejected(): void
    {
        config()->set('payments.methods.retired_method', [
            'label' => 'Metode Nonaktif',
            'is_active' => false,
            'is_dummy' => true,
        ]);

        $order = Order::factory()->create();

        $this->submit($order, ['payment_method' => 'retired_method'])
            ->assertSessionHasErrors('payment_method');
    }

    public function test_non_image_file_is_rejected(): void
    {
        $order = Order::factory()->create();

        $this->submit($order, [
            'proof' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('proof');

        $this->assertDatabaseCount('payments', 0);
    }

    /**
     * A PHP script renamed to .jpg must not pass.
     *
     * A real temporary file is used rather than UploadedFile::fake(): the fake
     * reports its MIME type from the filename, so it would sail through the
     * `image` rule and prove nothing. Writing actual bytes makes Laravel run
     * finfo over the content, which is the check that matters here.
     */
    public function test_disguised_non_image_is_rejected(): void
    {
        $order = Order::factory()->create();

        $path = tempnam(sys_get_temp_dir(), 'proof').'.jpg';
        file_put_contents($path, '<?php echo "hacked";');

        try {
            $this->submit($order, [
                // Null MIME type and $test = true, so the type is detected
                // from the bytes rather than supplied by the caller.
                'proof' => new UploadedFile($path, 'bukti.jpg', null, null, true),
            ])->assertSessionHasErrors('proof');
        } finally {
            @unlink($path);
        }

        $this->assertDatabaseCount('payments', 0);
        $this->assertEmpty(Storage::disk('local')->allFiles('payment-proofs'));
    }

    public function test_file_larger_than_the_configured_limit_is_rejected(): void
    {
        $order = Order::factory()->create();

        $maxKilobytes = (int) config('payments.proof.max_kilobytes');

        $this->submit($order, [
            // Size is in kilobytes.
            'proof' => UploadedFile::fake()->image('bukti.jpg')->size($maxKilobytes + 1),
        ])->assertSessionHasErrors('proof');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_png_and_webp_proofs_are_accepted(): void
    {
        foreach (['png', 'webp'] as $extension) {
            $order = Order::factory()->create();

            $this->submit($order, [
                'proof' => UploadedFile::fake()->image("bukti.{$extension}"),
            ])->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount('payments', 2);
    }

    /**
     * The upload route must not become a way to discover orders.
     */
    public function test_unknown_access_token_returns_not_found(): void
    {
        $this->post(route('orders.payment-proof.store', str_repeat('x', 64)), $this->payload())
            ->assertNotFound();
    }

    /**
     * One participant's token must not upload against another's order.
     */
    public function test_token_for_one_order_cannot_submit_a_proof_for_another(): void
    {
        $target = Order::factory()->create();
        $attacker = Order::factory()->create();

        $this->post(
            route('orders.payment-proof.store', $attacker->access_token),
            $this->payload()
        );

        // The attacker's own order advanced; the target was untouched.
        $this->assertDatabaseMissing('payments', ['order_id' => $target->id]);
    }

    /**
     * GET must not mutate state, so the upload route accepts POST only.
     */
    public function test_proof_route_rejects_get_requests(): void
    {
        $order = Order::factory()->create();

        $this->get(route('orders.payment-proof.store', $order->access_token))
            ->assertMethodNotAllowed();
    }
}

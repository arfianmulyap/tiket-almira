<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Access control over payment proofs (PRD FR-06, section 11).
 *
 * Proofs are personal data. These tests assert the only way to read one is
 * through the authorised admin route, and that the private disk is not exposed
 * by any other means.
 */
class PaymentProofAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    /**
     * Create an order with a real stored proof file.
     */
    private function orderWithProof(): Order
    {
        $order = Order::factory()->create();

        $this->post(route('orders.payment-proof.store', $order->access_token), [
            'payment_method' => 'dev_bank_transfer',
            'proof' => UploadedFile::fake()->image('bukti.jpg'),
        ])->assertSessionHasNoErrors();

        return $order->fresh();
    }

    public function test_admin_can_view_a_payment_proof(): void
    {
        $order = $this->orderWithProof();

        $this->actingAs($this->admin())
            ->get(route('admin.orders.payment-proof', $order))
            ->assertOk();
    }

    public function test_guest_cannot_view_a_payment_proof(): void
    {
        $order = $this->orderWithProof();

        $this->get(route('admin.orders.payment-proof', $order))
            ->assertRedirect(route('login'));
    }

    /**
     * A logged-in non-admin is still not authorised: proofs are not visible
     * to every authenticated user.
     */
    public function test_non_admin_user_cannot_view_a_payment_proof(): void
    {
        $order = $this->orderWithProof();

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('admin.orders.payment-proof', $order))
            ->assertForbidden();
    }

    public function test_proof_route_returns_not_found_when_no_proof_was_submitted(): void
    {
        $order = Order::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.orders.payment-proof', $order))
            ->assertNotFound();
    }

    /**
     * The row can outlive the file; the admin should get an honest 404 rather
     * than a broken stream.
     */
    public function test_proof_route_returns_not_found_when_the_file_is_missing(): void
    {
        $order = $this->orderWithProof();

        Storage::disk('local')->delete($order->payment->proof_path);

        $this->actingAs($this->admin())
            ->get(route('admin.orders.payment-proof', $order))
            ->assertNotFound();
    }

    /**
     * The proof must not be guessable or reachable as a static file. The
     * `local` disk has `serve` disabled precisely so this route does not
     * exist; if someone re-enables it, this test fails.
     */
    public function test_private_disk_is_not_served_over_http(): void
    {
        $order = $this->orderWithProof();

        $this->get('/storage/'.$order->payment->proof_path)
            ->assertNotFound();
    }

    /**
     * The response must not invite the browser to sniff a different type out
     * of a file uploaded by a stranger.
     */
    public function test_proof_response_sends_hardening_headers(): void
    {
        $order = $this->orderWithProof();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.orders.payment-proof', $order));

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('sandbox', $response->headers->get('Content-Security-Policy'));
    }

    /**
     * The download name is derived from the order reference, so the
     * participant's filename never reaches a response header.
     */
    public function test_download_filename_is_derived_from_the_order_reference(): void
    {
        $order = $this->orderWithProof();

        $this->assertStringContainsString(
            $order->order_reference,
            $order->payment->downloadFilename()
        );
    }

    /**
     * The admin review page must link to the proof once one exists.
     */
    public function test_order_detail_page_links_to_the_proof(): void
    {
        $order = $this->orderWithProof();

        $this->actingAs($this->admin())
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee(route('admin.orders.payment-proof', $order), false)
            ->assertSee('Bukti Pembayaran');
    }

    public function test_order_detail_page_shows_an_empty_state_without_a_proof(): void
    {
        $order = Order::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Bukti pembayaran belum dikirim');
    }

    /**
     * The method label shown to an admin is the snapshot, not the current
     * config value, so renaming a method cannot rewrite history.
     */
    public function test_method_label_snapshot_is_shown_not_the_current_config(): void
    {
        $order = $this->orderWithProof();

        config()->set('payments.methods.dev_bank_transfer.label', 'Nama Baru Setelah Diubah');

        $this->actingAs($this->admin())
            ->get(route('admin.orders.show', $order))
            ->assertSee(Payment::first()->method_label)
            ->assertDontSee('Nama Baru Setelah Diubah');
    }
}

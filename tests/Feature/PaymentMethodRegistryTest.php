<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Services\PaymentMethodRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Payment method configuration and the production guard (PRD FR-05).
 *
 * The central rule under test: placeholder payment data must never be
 * presented to a participant as a real instruction. The account, VA, and QRIS
 * details are unanswered business questions (PRD section 15 questions 1 and
 * 2), so until Phase 05C the only methods configured are flagged dummies.
 */
class PaymentMethodRegistryTest extends TestCase
{
    use RefreshDatabase;

    private function registry(): PaymentMethodRegistry
    {
        return app(PaymentMethodRegistry::class);
    }

    public function test_dummy_methods_are_available_in_testing(): void
    {
        $this->assertTrue($this->registry()->hasAvailableMethods());
    }

    /**
     * The guard that matters. In production the placeholder entries must
     * disappear rather than be shown as payment instructions.
     */
    public function test_dummy_methods_are_hidden_in_production(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $this->assertFalse($this->registry()->hasAvailableMethods());
        $this->assertSame([], $this->registry()->availableKeys());
    }

    /**
     * A real, confirmed method still works in production. This proves the
     * guard filters on `is_dummy` and not simply on the environment, so
     * Phase 05C only has to supply real data.
     */
    public function test_non_dummy_method_is_available_in_production(): void
    {
        app()->detectEnvironment(fn () => 'production');

        config()->set('payments.methods', [
            'confirmed_method' => [
                'label' => 'Metode Terkonfirmasi',
                'is_active' => true,
                'is_dummy' => false,
                'account' => ['Bank' => 'Contoh'],
                'steps' => ['Langkah pertama.'],
            ],
        ]);

        $this->assertSame(['confirmed_method'], $this->registry()->availableKeys());
        $this->assertFalse($this->registry()->showsDummyData());
    }

    public function test_inactive_methods_are_never_available(): void
    {
        config()->set('payments.methods', [
            'retired' => [
                'label' => 'Nonaktif',
                'is_active' => false,
                'is_dummy' => false,
            ],
        ]);

        $this->assertFalse($this->registry()->hasAvailableMethods());
        $this->assertNull($this->registry()->find('retired'));
    }

    /**
     * A config entry missing its flags must default to hidden, not published.
     */
    public function test_method_without_flags_defaults_to_unavailable(): void
    {
        config()->set('payments.methods', [
            'incomplete' => ['label' => 'Tanpa Flag'],
        ]);

        $this->assertFalse($this->registry()->hasAvailableMethods());

        // all() still exposes it, flagged as a dummy and inactive.
        $method = $this->registry()->all()->get('incomplete');

        $this->assertFalse($method->isActive);
        $this->assertTrue($method->isDummy);
    }

    public function test_find_returns_null_for_an_unknown_key(): void
    {
        $this->assertNull($this->registry()->find('does_not_exist'));
    }

    public function test_registry_reports_when_dummy_data_is_on_show(): void
    {
        $this->assertTrue($this->registry()->showsDummyData());
    }

    /**
     * Every method shipped in config today must be flagged as a dummy. This
     * fails the moment someone adds an unconfirmed method and marks it real,
     * which is exactly the mistake Phase 05C exists to prevent.
     */
    public function test_all_shipped_methods_are_still_flagged_as_development_data(): void
    {
        $real = $this->registry()->all()
            ->reject(fn ($method) => $method->isDummy)
            ->keys()
            ->all();

        $this->assertSame(
            [],
            $real,
            'A payment method is flagged as real. Phase 05C requires confirmed '
            .'account details from the business owner before this is allowed.'
        );
    }

    /**
     * The participant-facing page must say so plainly when it is showing
     * example data, and must not present it as a live instruction.
     */
    public function test_status_page_warns_that_payment_data_is_development_only(): void
    {
        $order = Order::factory()->create();

        $this->get(route('orders.show', $order->access_token))
            ->assertOk()
            ->assertSee('Mode pengembangan')
            ->assertSee('Jangan melakukan transfer');
    }

    /**
     * With no available methods the page shows an honest empty state instead
     * of a broken or invented form. This is the expected production state
     * while Phase 05C is blocked.
     */
    public function test_status_page_shows_empty_state_when_no_methods_are_available(): void
    {
        config()->set('payments.methods', []);

        $order = Order::factory()->create();

        $this->get(route('orders.show', $order->access_token))
            ->assertOk()
            ->assertSee('Instruksi pembayaran belum tersedia')
            ->assertDontSee('Kirim Bukti Pembayaran');
    }

    public function test_status_page_renders_the_upload_form_when_methods_exist(): void
    {
        $order = Order::factory()->create();

        $this->get(route('orders.show', $order->access_token))
            ->assertOk()
            ->assertSee('Kirim Bukti Pembayaran')
            ->assertSee('Metode Pembayaran');
    }
}

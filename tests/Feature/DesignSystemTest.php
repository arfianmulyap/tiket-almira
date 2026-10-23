<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Covers the Phase 02 design system: the shared stylesheet is reachable,
 * the layouts render, and each shared component produces the markup and
 * accessibility hooks the rest of the app will depend on.
 */
class DesignSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_design_system_stylesheet_exists_and_defines_tokens(): void
    {
        $path = public_path('css/app.css');

        $this->assertFileExists($path);

        $css = file_get_contents($path);

        // Token values come from styling.md and must not drift.
        $this->assertStringContainsString('--color-primary-900: #0D3978;', $css);
        $this->assertStringContainsString('--color-success-600: #19843E;', $css);
        $this->assertStringContainsString('--radius-lg: 14px;', $css);
        $this->assertStringContainsString('prefers-reduced-motion', $css);
    }

    public function test_public_layout_links_the_stylesheet(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('css/app.css', escape: false);
        $response->assertSee('skip-link', escape: false);
    }

    public function test_admin_layout_renders_navigation_and_marks_the_active_item(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('app-sidebar', escape: false);
        // Active state must be conveyed semantically, not by class alone.
        $response->assertSee('aria-current="page"', escape: false);
        $response->assertSee($admin->name);
    }

    public function test_button_component_renders_variants_and_states(): void
    {
        $primary = Blade::render('<x-button>Simpan</x-button>');

        $this->assertStringContainsString('class="button"', $primary);
        $this->assertStringContainsString('type="submit"', $primary);

        $secondary = Blade::render('<x-button variant="secondary" size="sm">Batal</x-button>');
        $this->assertStringContainsString('button-secondary', $secondary);
        $this->assertStringContainsString('button-sm', $secondary);

        $loading = Blade::render('<x-button :loading="true">Memproses</x-button>');
        $this->assertStringContainsString('disabled', $loading);
        $this->assertStringContainsString('data-loading="true"', $loading);
    }

    public function test_button_component_renders_as_a_link_when_given_an_href(): void
    {
        $link = Blade::render('<x-button href="/admin">Dashboard</x-button>');

        $this->assertStringContainsString('<a', $link);
        $this->assertStringContainsString('href="/admin"', $link);

        // A loading link drops its href so it cannot be followed.
        $loadingLink = Blade::render('<x-button href="/admin" :loading="true">Dashboard</x-button>');
        $this->assertStringContainsString('aria-disabled="true"', $loadingLink);
        $this->assertStringNotContainsString('href="/admin"', $loadingLink);
    }

    public function test_badge_and_alert_components_render_semantic_variants(): void
    {
        $badge = Blade::render('<x-badge variant="success">PAID</x-badge>');
        $this->assertStringContainsString('badge-success', $badge);
        // Status text accompanies the color.
        $this->assertStringContainsString('PAID', $badge);

        $error = Blade::render('<x-alert variant="error">Gagal</x-alert>');
        $this->assertStringContainsString('alert-error', $error);
        $this->assertStringContainsString('role="alert"', $error);

        $info = Blade::render('<x-alert>Informasi</x-alert>');
        $this->assertStringContainsString('alert-info', $info);
        $this->assertStringContainsString('role="status"', $info);
    }

    public function test_empty_state_and_card_components_render(): void
    {
        $card = Blade::render('<x-card title="Ringkasan" elevated>Isi</x-card>');
        $this->assertStringContainsString('card-elevated', $card);
        $this->assertStringContainsString('Ringkasan', $card);
        $this->assertStringContainsString('Isi', $card);

        $empty = Blade::render('<x-empty-state title="Belum ada data" description="Tidak ada hasil." />');
        $this->assertStringContainsString('Belum ada data', $empty);
        $this->assertStringContainsString('Tidak ada hasil.', $empty);
        // Decorative icon must be hidden from assistive technology.
        $this->assertStringContainsString('aria-hidden="true"', $empty);
    }
}

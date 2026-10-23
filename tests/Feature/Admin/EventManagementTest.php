<?php

namespace Tests\Feature\Admin;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function nonAdmin(): User
    {
        return User::factory()->create(['is_admin' => false]);
    }

    /**
     * Valid payload for creating an event, minus the poster.
     *
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Konser Almira',
            'description' => 'Konser tahunan.',
            'ticket_price' => 200000,
            'quota' => 100,
            'status' => EventStatus::Draft->value,
            'starts_at' => now()->addMonth()->format('Y-m-d\TH:i'),
            'location' => 'Jakarta',
        ], $overrides);
    }

    public function test_guest_is_redirected_from_event_index(): void
    {
        $this->get(route('admin.events.index'))
            ->assertRedirect(route('login'));
    }

    public function test_guest_cannot_create_event(): void
    {
        $this->post(route('admin.events.store'), $this->validPayload())
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('events', 0);
    }

    public function test_non_admin_cannot_view_event_index(): void
    {
        $this->actingAs($this->nonAdmin())
            ->get(route('admin.events.index'))
            ->assertForbidden();
    }

    public function test_non_admin_cannot_create_event(): void
    {
        $this->actingAs($this->nonAdmin())
            ->post(route('admin.events.store'), $this->validPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('events', 0);
    }

    public function test_admin_can_view_event_index(): void
    {
        $event = Event::factory()->create(['name' => 'Festival Almira']);

        $this->actingAs($this->admin())
            ->get(route('admin.events.index'))
            ->assertOk()
            ->assertSee('Festival Almira')
            ->assertSee($event->location);
    }

    public function test_event_index_shows_empty_state_when_no_events_exist(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.events.index'))
            ->assertOk()
            ->assertSee('Belum ada event');
    }

    public function test_admin_can_view_create_form(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.events.create'))
            ->assertOk()
            ->assertSee('Nama Event')
            ->assertSee('Kuota Tiket');
    }

    public function test_admin_can_create_event(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.events.store'), $this->validPayload())
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('events', [
            'name' => 'Konser Almira',
            'ticket_price' => 200000,
            'quota' => 100,
            'status' => EventStatus::Draft->value,
            'location' => 'Jakarta',
        ]);
    }

    public function test_created_event_receives_a_slug(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.events.store'), $this->validPayload());

        $this->assertSame('konser-almira', Event::first()->slug);
    }

    public function test_slug_stays_unique_for_duplicate_event_names(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.events.store'), $this->validPayload());
        $this->actingAs($admin)->post(route('admin.events.store'), $this->validPayload());

        $slugs = Event::pluck('slug');

        $this->assertCount(2, $slugs);
        $this->assertCount(2, $slugs->unique(), 'Slugs must be unique.');
    }

    public function test_required_fields_are_validated(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.events.store'), [])
            ->assertSessionHasErrors([
                'name',
                'ticket_price',
                'quota',
                'status',
                'starts_at',
                'location',
            ]);

        $this->assertDatabaseCount('events', 0);
    }

    public function test_description_is_optional(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.events.store'), $this->validPayload(['description' => null]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('events', 1);
    }

    public function test_status_must_be_a_valid_enum_value(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.events.store'), $this->validPayload(['status' => 'archived']))
            ->assertSessionHasErrors('status');
    }

    public function test_quota_must_be_at_least_one(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.events.store'), $this->validPayload(['quota' => 0]))
            ->assertSessionHasErrors('quota');
    }

    public function test_admin_can_view_event_detail(): void
    {
        $event = Event::factory()->create(['name' => 'Gala Almira']);

        $this->actingAs($this->admin())
            ->get(route('admin.events.show', $event))
            ->assertOk()
            ->assertSee('Gala Almira')
            ->assertSee($event->slug);
    }

    public function test_admin_can_view_edit_form_with_current_values(): void
    {
        $event = Event::factory()->create(['name' => 'Workshop Almira']);

        $this->actingAs($this->admin())
            ->get(route('admin.events.edit', $event))
            ->assertOk()
            ->assertSee('Workshop Almira');
    }

    public function test_admin_can_update_event(): void
    {
        $event = Event::factory()->create(['quota' => 50]);

        $this->actingAs($this->admin())
            ->put(route('admin.events.update', $event), $this->validPayload([
                'name' => 'Nama Baru',
                'quota' => 80,
                'ticket_price' => 150000,
            ]))
            ->assertRedirect(route('admin.events.show', $event))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'name' => 'Nama Baru',
            'quota' => 80,
            'ticket_price' => 150000,
        ]);
    }

    public function test_updating_an_event_does_not_change_its_slug(): void
    {
        $event = Event::factory()->create();
        $originalSlug = $event->slug;

        $this->actingAs($this->admin())
            ->put(route('admin.events.update', $event), $this->validPayload([
                'name' => 'Judul Yang Benar-Benar Berbeda',
            ]));

        $this->assertSame($originalSlug, $event->fresh()->slug);
    }

    public function test_non_admin_cannot_update_event(): void
    {
        $event = Event::factory()->create(['name' => 'Tetap Sama']);

        $this->actingAs($this->nonAdmin())
            ->put(route('admin.events.update', $event), $this->validPayload())
            ->assertForbidden();

        $this->assertSame('Tetap Sama', $event->fresh()->name);
    }

    /**
     * reservedQuota() returns 0 until orders exist in Phase 04, so no quota
     * above 0 can be rejected today. This test pins the contract so the rule
     * is exercised the moment Phase 04 implements reservedQuota().
     */
    public function test_quota_can_be_reduced_while_no_slots_are_reserved(): void
    {
        $event = Event::factory()->create(['quota' => 100]);

        $this->actingAs($this->admin())
            ->put(route('admin.events.update', $event), $this->validPayload(['quota' => 1]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $event->fresh()->quota);
    }

    public function test_event_navigation_link_is_marked_as_current_page(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.events.index'))
            ->assertOk()
            ->assertSee('aria-current="page"', false);
    }
}

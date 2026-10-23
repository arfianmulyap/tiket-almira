<?php

namespace Tests\Feature\Admin;

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EventStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_scope_returns_only_published_events(): void
    {
        $published = Event::factory()->published()->create();
        Event::factory()->draft()->create();
        Event::factory()->closed()->create();

        $results = Event::published()->get();

        $this->assertCount(1, $results);
        $this->assertSame($published->id, $results->first()->id);
    }

    public function test_only_published_events_are_orderable(): void
    {
        $this->assertTrue(Event::factory()->published()->create()->isOrderable());
        $this->assertFalse(Event::factory()->draft()->create()->isOrderable());
        $this->assertFalse(Event::factory()->closed()->create()->isOrderable());
    }

    public function test_status_is_cast_to_the_enum(): void
    {
        $event = Event::factory()->published()->create();

        $this->assertInstanceOf(EventStatus::class, $event->fresh()->status);
    }

    public function test_new_events_default_to_draft(): void
    {
        // Insert without a status so the database default is what applies.
        $id = Event::insertGetId([
            'name' => 'Tanpa Status',
            'slug' => 'tanpa-status',
            'ticket_price' => 100000,
            'quota' => 10,
            'starts_at' => now()->addMonth(),
            'location' => 'Bandung',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(EventStatus::Draft, Event::find($id)->status);
    }

    public function test_each_status_exposes_a_label_and_badge_variant(): void
    {
        foreach (EventStatus::cases() as $status) {
            $this->assertNotSame('', $status->label());
            $this->assertNotSame('', $status->badgeVariant());
        }
    }

    public function test_enum_values_helper_lists_every_status(): void
    {
        $this->assertSame(['draft', 'published', 'closed'], EventStatus::values());
    }

    public function test_starts_at_is_cast_to_a_date_instance(): void
    {
        $event = Event::factory()->create();

        $this->assertInstanceOf(Carbon::class, $event->fresh()->starts_at);
    }

    public function test_ticket_price_and_quota_are_cast_to_integers(): void
    {
        $event = Event::factory()->create([
            'ticket_price' => 200000,
            'quota' => 50,
        ])->fresh();

        $this->assertIsInt($event->ticket_price);
        $this->assertIsInt($event->quota);
    }

    public function test_remaining_quota_equals_quota_while_nothing_is_reserved(): void
    {
        // Orders arrive in Phase 04; until then nothing holds a slot.
        $event = Event::factory()->create(['quota' => 40]);

        $this->assertSame(0, $event->reservedQuota());
        $this->assertSame(40, $event->remainingQuota());
    }

    public function test_poster_url_is_null_without_a_poster(): void
    {
        $event = Event::factory()->create(['poster_path' => null]);

        $this->assertNull($event->posterUrl());
    }

    public function test_poster_url_is_built_from_the_public_disk(): void
    {
        $event = Event::factory()->create(['poster_path' => 'posters/example.jpg']);

        $this->assertStringContainsString('posters/example.jpg', $event->posterUrl());
    }
}

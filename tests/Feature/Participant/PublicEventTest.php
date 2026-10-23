<?php

namespace Tests\Feature\Participant;

use App\Models\Event;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_shows_only_published_events(): void
    {
        $published = Event::factory()->published()->create(['name' => 'Seminar Terbuka']);
        $draft = Event::factory()->draft()->create(['name' => 'Draft Rahasia']);
        $closed = Event::factory()->closed()->create(['name' => 'Event Ditutup']);

        $this->get(route('events.index'))
            ->assertOk()
            ->assertSee($published->name)
            ->assertDontSee($draft->name)
            ->assertDontSee($closed->name);
    }

    public function test_listing_is_reachable_without_logging_in(): void
    {
        Event::factory()->published()->create();

        $this->get(route('events.index'))->assertOk();
        $this->get(route('home'))->assertOk();
    }

    public function test_listing_shows_an_empty_state_when_nothing_is_published(): void
    {
        Event::factory()->draft()->create();

        $this->get(route('events.index'))
            ->assertOk()
            ->assertSee('Belum ada event yang dipublikasikan');
    }

    public function test_published_event_detail_page_shows_the_order_form(): void
    {
        $event = Event::factory()->published()->create(['quota' => 10]);

        $this->get(route('events.show', $event->slug))
            ->assertOk()
            ->assertSee($event->name)
            ->assertSee('participant_name', false)
            ->assertSee('participant_whatsapp', false)
            ->assertSee('participant_email', false);
    }

    public function test_poster_is_rendered_before_the_order_form(): void
    {
        $event = Event::factory()->published()->create();

        $content = $this->get(route('events.show', $event->slug))
            ->assertOk()
            ->getContent();

        $posterPosition = strpos($content, 'event-poster-hero');
        $formPosition = strpos($content, 'participant_name');

        $this->assertNotFalse($posterPosition, 'The poster should be rendered.');
        $this->assertNotFalse($formPosition, 'The order form should be rendered.');

        // PRD FR-03: the poster sits at the very top of the ordering page.
        $this->assertLessThan($formPosition, $posterPosition);
    }

    public function test_draft_event_detail_page_returns_not_found(): void
    {
        $event = Event::factory()->draft()->create();

        $this->get(route('events.show', $event->slug))->assertNotFound();
    }

    public function test_closed_event_detail_page_returns_not_found(): void
    {
        $event = Event::factory()->closed()->create();

        $this->get(route('events.show', $event->slug))->assertNotFound();
    }

    public function test_sold_out_event_hides_the_order_form(): void
    {
        $event = Event::factory()->published()->create(['quota' => 1]);

        Order::factory()->forEvent($event)->create();

        $this->get(route('events.show', $event->slug))
            ->assertOk()
            ->assertSee('Tiket sudah habis')
            ->assertDontSee('participant_whatsapp', false);
    }

    public function test_listing_shows_remaining_quota(): void
    {
        $event = Event::factory()->published()->create(['quota' => 10]);

        Order::factory()->forEvent($event)->count(3)->create();

        $this->get(route('events.index'))
            ->assertOk()
            ->assertSee('Sisa 7 tiket');
    }
}

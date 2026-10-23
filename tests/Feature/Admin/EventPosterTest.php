<?php

namespace Tests\Feature\Admin;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventPosterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Posters belong on the public disk; payment proofs in Phase 05 use
        // the private local disk. Faking only `public` keeps that split
        // visible in the tests.
        Storage::fake('public');
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    /**
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

    public function test_admin_can_upload_a_poster_when_creating_an_event(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.events.store'), $this->validPayload([
                'poster' => UploadedFile::fake()->image('poster.jpg', 800, 1200),
            ]))
            ->assertSessionHasNoErrors();

        $event = Event::first();

        $this->assertNotNull($event->poster_path);
        Storage::disk('public')->assertExists($event->poster_path);
    }

    public function test_poster_is_stored_in_the_posters_directory(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.events.store'), $this->validPayload([
                'poster' => UploadedFile::fake()->image('poster.png'),
            ]));

        $this->assertStringStartsWith('posters/', Event::first()->poster_path);
    }

    public function test_event_can_be_created_as_draft_without_a_poster(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.events.store'), $this->validPayload())
            ->assertSessionHasNoErrors();

        $this->assertNull(Event::first()->poster_path);
    }

    public function test_poster_is_required_to_publish_a_new_event(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.events.store'), $this->validPayload([
                'status' => EventStatus::Published->value,
            ]))
            ->assertSessionHasErrors('poster');

        $this->assertDatabaseCount('events', 0);
    }

    public function test_poster_is_required_to_publish_an_existing_draft(): void
    {
        $event = Event::factory()->draft()->create(['poster_path' => null]);

        $this->actingAs($this->admin())
            ->put(route('admin.events.update', $event), $this->validPayload([
                'status' => EventStatus::Published->value,
            ]))
            ->assertSessionHasErrors('poster');

        $this->assertSame(EventStatus::Draft, $event->fresh()->status);
    }

    public function test_draft_with_existing_poster_publishes_without_reuploading(): void
    {
        $event = Event::factory()->draft()->create([
            'poster_path' => 'posters/existing.jpg',
        ]);

        $this->actingAs($this->admin())
            ->put(route('admin.events.update', $event), $this->validPayload([
                'status' => EventStatus::Published->value,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(EventStatus::Published, $event->fresh()->status);
    }

    public function test_non_image_file_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.events.store'), $this->validPayload([
                'poster' => UploadedFile::fake()->create('poster.pdf', 100, 'application/pdf'),
            ]))
            ->assertSessionHasErrors('poster');

        $this->assertDatabaseCount('events', 0);
    }

    public function test_gif_image_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.events.store'), $this->validPayload([
                'poster' => UploadedFile::fake()->image('poster.gif'),
            ]))
            ->assertSessionHasErrors('poster');
    }

    public function test_poster_larger_than_four_megabytes_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.events.store'), $this->validPayload([
                // Size is in kilobytes; 5 MB exceeds the 4096 KB limit.
                'poster' => UploadedFile::fake()->image('poster.jpg')->size(5120),
            ]))
            ->assertSessionHasErrors('poster');

        $this->assertDatabaseCount('events', 0);
    }

    public function test_webp_poster_is_accepted(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.events.store'), $this->validPayload([
                'poster' => UploadedFile::fake()->image('poster.webp'),
            ]))
            ->assertSessionHasNoErrors();

        Storage::disk('public')->assertExists(Event::first()->poster_path);
    }

    public function test_replacing_a_poster_deletes_the_previous_file(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.events.store'), $this->validPayload([
            'poster' => UploadedFile::fake()->image('first.jpg'),
        ]));

        $event = Event::first();
        $originalPath = $event->poster_path;

        $this->actingAs($admin)->put(route('admin.events.update', $event), $this->validPayload([
            'poster' => UploadedFile::fake()->image('second.jpg'),
        ]))->assertSessionHasNoErrors();

        $newPath = $event->fresh()->poster_path;

        $this->assertNotSame($originalPath, $newPath);
        Storage::disk('public')->assertExists($newPath);
        Storage::disk('public')->assertMissing($originalPath);
    }

    public function test_updating_without_a_poster_keeps_the_existing_file(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.events.store'), $this->validPayload([
            'poster' => UploadedFile::fake()->image('keep.jpg'),
        ]));

        $event = Event::first();
        $originalPath = $event->poster_path;

        $this->actingAs($admin)->put(route('admin.events.update', $event), $this->validPayload([
            'name' => 'Judul Diperbarui',
        ]))->assertSessionHasNoErrors();

        $this->assertSame($originalPath, $event->fresh()->poster_path);
        Storage::disk('public')->assertExists($originalPath);
    }
}

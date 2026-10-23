<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEventRequest;
use App\Http\Requests\Admin\UpdateEventRequest;
use App\Models\Event;
use App\Services\EventPosterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class EventController extends Controller
{
    public function __construct(private readonly EventPosterService $posters) {}

    /**
     * List every event for the admin.
     */
    public function index(): View
    {
        $this->authorize('viewAny', Event::class);

        $events = Event::query()
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('admin.events.index', [
            'events' => $events,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Event::class);

        return view('admin.events.create', [
            'statuses' => EventStatus::cases(),
        ]);
    }

    /**
     * Persist a new event and its optional poster.
     *
     * The event row and the poster path are written inside one transaction so
     * a failed upload cannot leave a half-created event behind.
     */
    public function store(StoreEventRequest $request): RedirectResponse
    {
        $this->authorize('create', Event::class);

        $data = $request->validated();

        $event = DB::transaction(function () use ($request, $data): Event {
            $event = new Event;

            $event->fill([
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['name']),
                'description' => $data['description'] ?? null,
                'ticket_price' => $data['ticket_price'],
                'quota' => $data['quota'],
                'status' => $data['status'],
                'starts_at' => $data['starts_at'],
                'location' => $data['location'],
            ]);

            $event->save();

            if ($request->hasFile('poster')) {
                $this->posters->store($event, $request->file('poster'));
            }

            return $event;
        });

        return redirect()
            ->route('admin.events.show', $event)
            ->with('success', 'Event berhasil dibuat.');
    }

    public function show(Event $event): View
    {
        $this->authorize('view', $event);

        return view('admin.events.show', [
            'event' => $event,
        ]);
    }

    public function edit(Event $event): View
    {
        $this->authorize('update', $event);

        return view('admin.events.edit', [
            'event' => $event,
            'statuses' => EventStatus::cases(),
        ]);
    }

    /**
     * Update an event.
     *
     * The slug is intentionally left untouched: it is part of the public URL
     * and changing it would break links already shared with participants.
     */
    public function update(UpdateEventRequest $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $data = $request->validated();

        DB::transaction(function () use ($request, $event, $data): void {
            $event->fill([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'ticket_price' => $data['ticket_price'],
                'quota' => $data['quota'],
                'status' => $data['status'],
                'starts_at' => $data['starts_at'],
                'location' => $data['location'],
            ]);

            $event->save();

            if ($request->hasFile('poster')) {
                $this->posters->store($event, $request->file('poster'));
            }
        });

        return redirect()
            ->route('admin.events.show', $event)
            ->with('success', 'Event berhasil diperbarui.');
    }

    /**
     * Build a slug that is unique across events.
     *
     * A short random suffix is appended on collision instead of an
     * incrementing counter, which would need an extra query per attempt.
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'event';
        $slug = $base;

        while (Event::where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(6));
        }

        return $slug;
    }
}

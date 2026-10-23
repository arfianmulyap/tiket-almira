<?php

namespace App\Http\Controllers\Participant;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\OrderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

/**
 * Public, unauthenticated event browsing for participants.
 *
 * Namespaced `Participant` rather than `Public` because `public` is a
 * reserved word in PHP and cannot be used as a namespace segment.
 *
 * Every query goes through Event::published() so a draft or closed event
 * is never reachable here, no matter what slug is requested (PRD FR-02).
 */
class EventController extends Controller
{
    /**
     * List events that are open for ordering.
     */
    public function index(): View
    {
        $events = Event::query()
            ->published()
            // Soonest first: this is a "what can I attend" listing, unlike
            // the admin list which is newest-first.
            ->orderBy('starts_at')
            // Counted in one query per page instead of per-row lookups, so
            // the card list does not fire an N+1 for availability.
            ->withCount([
                'orders as reserved_quota_count' => fn (Builder $query) => $query->holdingQuota(),
            ])
            ->paginate(12);

        return view('participant.events.index', [
            'events' => $events,
        ]);
    }

    /**
     * Show one event with the order form.
     *
     * Resolved by slug and constrained to published events, so requesting
     * the slug of a draft event returns 404 rather than revealing that it
     * exists.
     */
    public function show(string $slug): View
    {
        $event = Event::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        return view('participant.events.show', [
            'event' => $event,
            // Passed in rather than referenced by FQCN inside Blade, so the
            // deadline shown to the participant and the one applied by
            // OrderService can never drift apart.
            'paymentWindowMinutes' => OrderService::PAYMENT_WINDOW_MINUTES,
        ]);
    }
}

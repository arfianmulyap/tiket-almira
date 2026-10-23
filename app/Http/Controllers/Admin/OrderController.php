<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    /**
     * List orders, defaulting to the verification queue.
     *
     * The queue is the admin's main job, so it is the default view. Search and
     * filter inputs are validated before use (see validatedFilters) so no
     * query-string value reaches the query builder unchecked.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Order::class);

        $filters = $this->validatedFilters($request);

        // Reused for both the page of rows and the per-status counts, so the
        // tab numbers always describe the same filtered set the table shows.
        $scoped = fn () => Order::query()
            ->search($filters['q'])
            ->when($filters['event_id'], fn ($query, $eventId) => $query->where('event_id', $eventId))
            ->when($filters['from'], fn ($query, $from) => $query->where('created_at', '>=', $from.' 00:00:00'))
            ->when($filters['to'], fn ($query, $to) => $query->where('created_at', '<=', $to.' 23:59:59'));

        $orders = $scoped()
            ->with('event')
            ->where('status', $filters['status'])
            // Oldest first: the participant who has waited longest for a
            // decision is served first.
            ->orderBy('created_at')
            ->paginate(20)
            ->withQueryString();

        $countsByStatus = $scoped()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return view('admin.orders.index', [
            'orders' => $orders,
            'activeStatus' => $filters['status'],
            'statuses' => OrderStatus::cases(),
            'countsByStatus' => $countsByStatus,
            'filters' => $filters,
            // Only used to populate the filter dropdown.
            'events' => Event::query()->orderByDesc('created_at')->get(['id', 'name']),
        ]);
    }

    /**
     * Validate and normalise the list filters (PRD FR-11).
     *
     * The status is checked against the enum and the event id against the
     * events table, so neither can reach the query builder as an arbitrary
     * string. Dates are validated as dates before being concatenated into a
     * day boundary. An unrecognised status falls back to the verification
     * queue instead of raising an error, because a stale bookmark should still
     * open a usable page.
     *
     * @return array{status: string, q: ?string, event_id: ?int, from: ?string, to: ?string}
     */
    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string'],
            'q' => ['nullable', 'string', 'max:255'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            // Guards against an inverted range producing a silently empty
            // table with no explanation.
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $status = $validated['status'] ?? null;

        if (! in_array($status, OrderStatus::values(), true)) {
            $status = OrderStatus::PendingVerification->value;
        }

        return [
            'status' => $status,
            'q' => $validated['q'] ?? null,
            'event_id' => isset($validated['event_id']) ? (int) $validated['event_id'] : null,
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
        ];
    }

    /**
     * Show one order with everything needed to decide its payment.
     */
    public function show(Order $order): View
    {
        $this->authorize('view', $order);

        $order->load(['event', 'ticket', 'reviewer', 'payment']);

        return view('admin.orders.show', [
            'order' => $order,
            // Newest first so the most recent decision is read first.
            'auditLogs' => $order->auditLogs()
                ->with('user')
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }
}

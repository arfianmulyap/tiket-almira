<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\TicketStatus;
use App\Models\CheckIn;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Support\Collection;

/**
 * Read-only operational metrics for the admin dashboard (PRD FR-11).
 *
 * Every figure is derived from the same scopes and enums the write paths use:
 * quota comes from Order::scopeHoldingQuota, the verification queue from
 * scopeAwaitingVerification, and revenue from the per-order price snapshot.
 * Nothing is recomputed with a second, parallel definition here, because the
 * acceptance criterion for this phase is that the dashboard agrees with the
 * system's own status rules rather than merely looking plausible.
 *
 * This class only reads. It is safe to call from a GET request and it never
 * mutates an order, a ticket, or a quota.
 */
class DashboardMetrics
{
    /**
     * Headline counters across all events.
     *
     * @return array<string, int>
     */
    public function summary(): array
    {
        return [
            'events_total' => Event::query()->count(),
            'events_published' => Event::query()->published()->count(),

            'orders_total' => Order::query()->count(),

            // The admin's work queue. Same scope the orders list defaults to,
            // so the number here always matches the tab counter there.
            'awaiting_verification' => Order::query()->awaitingVerification()->count(),

            // Orders past their deadline that the sweep has not flipped yet.
            // A non-zero value that keeps growing means the scheduler is not
            // running, which is worth seeing on the dashboard.
            'overdue_unswept' => Order::query()->overdue()->count(),

            'tickets_issued' => Ticket::query()->count(),

            // Counted from the check_ins table, not from the ticket status, so
            // the figure reflects the audit record that the gate actually
            // wrote (PRD FR-10/FR-12).
            'check_ins_total' => CheckIn::query()->count(),
        ];
    }

    /**
     * Order counts per status, keyed by the enum's stored value.
     *
     * Every status is present, including the ones with no rows, so the UI can
     * render a stable set of figures instead of hiding a status that happens
     * to be empty today.
     *
     * @return array<string, int>
     */
    public function ordersByStatus(): array
    {
        $counts = Order::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $result = [];

        foreach (OrderStatus::cases() as $status) {
            $result[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return $result;
    }

    /**
     * Total value of approved payments, in rupiah.
     *
     * Sums the order's price snapshot rather than the event's current price:
     * the snapshot is what the participant actually owed (PRD business rule
     * 12), so a later price change must not restate past revenue.
     *
     * Only PAID orders count. An uploaded proof is explicitly not a
     * confirmation that funds arrived (PRD business rule 8), so orders still
     * awaiting verification are excluded.
     */
    public function approvedRevenue(): int
    {
        return (int) Order::query()
            ->where('status', OrderStatus::Paid)
            ->sum('ticket_price_snapshot');
    }

    /**
     * Per-event operational breakdown, newest event first.
     *
     * Quota is read through the same scope as the public availability display,
     * so "sisa kuota" here cannot disagree with what a participant is shown.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function eventBreakdown(int $limit = 10): Collection
    {
        $events = Event::query()
            ->withCount([
                'orders',
                'orders as holding_quota_count' => fn ($query) => $query->holdingQuota(),
                'orders as awaiting_verification_count' => fn ($query) => $query->awaitingVerification(),
                'tickets as tickets_issued_count',
                'tickets as checked_in_count' => fn ($query) => $query->where('tickets.status', TicketStatus::CheckedIn),
            ])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return $events->map(fn (Event $event) => [
            'event' => $event,
            'orders_count' => (int) $event->orders_count,
            'awaiting_verification' => (int) $event->awaiting_verification_count,
            'tickets_issued' => (int) $event->tickets_issued_count,
            'checked_in' => (int) $event->checked_in_count,
            'quota' => $event->quota,
            'quota_used' => (int) $event->holding_quota_count,
            // max(0, ...) guards the display if a quota is lowered below the
            // number of slots already committed; PRD section 15 question 11
            // leaves that scenario undecided, so the dashboard reports zero
            // remaining rather than a negative figure.
            'quota_remaining' => max(0, $event->quota - (int) $event->holding_quota_count),
        ]);
    }

    /**
     * Whether any event exists at all.
     *
     * Used to tell "nothing has happened yet" apart from "all counters are
     * genuinely zero", so the dashboard can show an onboarding hint instead of
     * a wall of zeroes on a fresh install.
     */
    public function hasAnyData(): bool
    {
        return Event::query()->exists();
    }

    /**
     * Published events that have no quota left.
     *
     * Surfaced because a sold-out published event still appears on the public
     * listing and is a common source of "why can nobody order?" questions.
     *
     * @return Collection<int, Event>
     */
    public function soldOutPublishedEvents(): Collection
    {
        return Event::query()
            ->published()
            ->withCount(['orders as holding_quota_count' => fn ($query) => $query->holdingQuota()])
            ->get()
            ->filter(fn (Event $event) => $event->quota - (int) $event->holding_quota_count <= 0)
            ->values();
    }
}

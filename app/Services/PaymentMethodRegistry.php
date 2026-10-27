<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventPaymentMethod;
use App\Models\Order;
use App\Models\PaymentMethod as PaymentMethodRecord;
use App\Support\PaymentMethod;
use Illuminate\Support\Collection;

/**
 * Decides which payment methods exist and which ones a participant may
 * actually be offered (PRD FR-05, Phase 12).
 *
 * The `payment_methods` table is the source of truth. Three filters are applied
 * on top of it, and the second one is the important one:
 *
 *  1. `is_active` lets the business retire a method without deleting it, so
 *     historical payments and snapshots stay resolvable.
 *  2. `is_dummy` rows are served only in local and testing environments.
 *  3. Per-event overrides (Phase 12B) can switch a method off for one event
 *     without touching the global row, so two events can disagree.
 *
 * Filter 2 exists because the account, VA, and QRIS details are unanswered
 * business questions (PRD section 15 questions 1 and 2). Development needs
 * *some* method to exercise the upload flow, but a placeholder account number
 * presented as a real payment instruction would cause participants to send
 * money into the void. Rather than rely on someone remembering to empty the
 * config before deploying, the guard is in code: in production the dummy
 * entries vanish and the UI falls back to an honest "not yet available"
 * state.
 *
 * An internal, non-public deployment is the one case where that guard is too
 * strict: the whole point of such a deployment is to exercise the ordering and
 * upload flow end to end, which is impossible with no selectable method. The
 * PAYMENTS_ALLOW_DUMMY escape hatch below covers it. It is opt-in, defaults to
 * off, and does not suppress the "Mode pengembangan / Jangan melakukan
 * transfer" warning the UI renders for dummy data, so a participant who
 * somehow reaches the page is still told plainly not to send money. Never
 * enable it on a deployment real participants can reach.
 */
class PaymentMethodRegistry
{
    /**
     * Environments where placeholder payment data may be shown.
     */
    private const DUMMY_ENVIRONMENTS = ['local', 'testing'];

    /**
     * Memoised catalog for this request. A saved setting is visible on the
     * next request, which matches how permissions behave.
     *
     * @var Collection<string, PaymentMethod>|null
     */
    private ?Collection $cache = null;

    /**
     * Memoised per-event overrides, keyed by method key (Phase 12B).
     *
     * @var array<int, Collection<string, bool>>
     */
    private array $overrideCache = [];

    /**
     * Methods that may be offered to participants right now.
     *
     * @return Collection<string, PaymentMethod>
     */
    public function available(): Collection
    {
        $allowDummy = $this->allowsDummyData();

        return $this->all()
            ->filter(fn (PaymentMethod $method) => $method->isActive)
            ->filter(fn (PaymentMethod $method) => $allowDummy || ! $method->isDummy);
    }

    /**
     * Methods that may be offered to participants for one specific event
     * (Phase 12B).
     *
     * Starts from the global list and then applies two more conditions: the
     * method must be complete for its own type, and it must not have been
     * switched off for this event. A method with no override row follows the
     * global flag, which is why an installation that never touches the
     * per-event screen behaves exactly as it did in Phase 12.
     *
     * `available()` itself stays the raw catalog-minus-flags view: it is what
     * the admin screens and the availability report read, and adding the
     * completeness rule there would hide an unfinished method from the screen
     * that is supposed to show it.
     *
     * Passing null — an order whose event cannot be resolved — falls back to the
     * global list rather than to an empty one: losing the instructions for an
     * existing order would be worse than showing the catalog.
     *
     * @return Collection<string, PaymentMethod>
     */
    public function availableForEvent(?Event $event): Collection
    {
        if ($event === null) {
            return $this->available();
        }

        $overrides = $this->overrides($event);

        return $this->available()
            // Phase 12B business rule 8: a method that is missing the details
            // its own type requires is not offered for a new order, however its
            // flags are set. Offering it would hand a participant instructions
            // that point nowhere, which is worse than showing fewer methods.
            ->filter(fn (PaymentMethod $method) => $method->isConfigured())
            ->filter(fn (PaymentMethod $method) => $overrides->has($method->key)
                ? (bool) $overrides->get($method->key)
                : true);
    }

    /**
     * The explicit override for one method and event, or null when the event
     * follows the global flag.
     *
     * The settings screen uses the difference between "false" and "null" to
     * show whether a state was set on purpose for that event or is simply
     * inherited.
     */
    public function overrideFor(?Event $event, PaymentMethodRecord $method): ?bool
    {
        return $this->overrides($event)->get($method->key);
    }

    /**
     * Every configured method, active or not.
     *
     * Used by the settings screen and diagnostics. Never render this to a
     * participant: it intentionally includes inactive and dummy entries.
     *
     * @return Collection<string, PaymentMethod>
     */
    public function all(): Collection
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $records = PaymentMethodRecord::query()
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        $methods = $records->isEmpty()
            ? $this->fromConfig()
            : $records->map(fn (PaymentMethodRecord $record) => $record->toValue());

        return $this->cache = $methods->keyBy('key');
    }

    /**
     * Resolve one selectable method by key, or null if it may not be used.
     *
     * Goes through available() rather than the raw table so a method that is
     * inactive, or a dummy method in production, cannot be selected by posting
     * its key directly.
     */
    public function find(string $key): ?PaymentMethod
    {
        return $this->available()->get($key);
    }

    /**
     * Keys accepted by the order form's validation rules.
     *
     * @return array<int, string>
     */
    public function availableKeys(): array
    {
        return $this->available()->keys()->all();
    }

    /**
     * Whether any method can be offered at all.
     *
     * False in production until real methods are configured, which is the
     * expected state while the business details are unconfirmed.
     */
    public function hasAvailableMethods(): bool
    {
        return $this->available()->isNotEmpty();
    }

    /**
     * Whether any method currently on offer is placeholder data.
     *
     * Drives the visible development warning so nobody mistakes the example
     * instructions for real ones.
     */
    public function showsDummyData(): bool
    {
        return $this->available()->contains(fn (PaymentMethod $method) => $method->isDummy);
    }

    /**
     * Snapshot of everything a participant is offered right now.
     *
     * Stored on the order when it is created. Later edits to the table change
     * what new orders see, never what an existing order shows.
     *
     * Kept as the no-event form for callers with no event context; the ordering
     * flow uses snapshotForEvent() so per-event activation is baked into the
     * snapshot the participant receives.
     *
     * @return array<int, array<string, mixed>>
     */
    public function snapshot(): array
    {
        return $this->snapshotForEvent(null);
    }

    /**
     * Snapshot of the methods one event offers right now (Phase 12B).
     *
     * @return array<int, array<string, mixed>>
     */
    public function snapshotForEvent(?Event $event): array
    {
        return $this->availableForEvent($event)
            ->map(fn (PaymentMethod $method) => $method->toSnapshot())
            ->values()
            ->all();
    }

    /**
     * Payment instructions that apply to one specific order.
     *
     * The order's snapshot wins. Orders created before Phase 12 have no
     * snapshot; for those only, the live catalog for that order's event is used
     * so the page keeps working instead of showing an empty payment section.
     *
     * @return Collection<string, PaymentMethod>
     */
    public function forOrder(Order $order): Collection
    {
        $snapshot = $order->payment_instructions_snapshot;

        if (! is_array($snapshot)) {
            return $this->availableForEvent($order->event);
        }

        return collect($snapshot)
            ->filter(fn (mixed $row) => is_array($row) && isset($row['key']))
            ->map(fn (array $row) => PaymentMethod::fromSnapshot($row))
            ->keyBy('key');
    }

    /**
     * Overrides recorded for one event, keyed by method key.
     *
     * Memoised per request so a page that asks about every method does not
     * query once per row. A saved override is visible on the next request,
     * matching how permissions and settings already behave.
     *
     * @return Collection<string, bool>
     */
    private function overrides(?Event $event): Collection
    {
        if ($event === null || $event->getKey() === null) {
            return collect();
        }

        $eventId = (int) $event->getKey();

        if (array_key_exists($eventId, $this->overrideCache)) {
            return $this->overrideCache[$eventId];
        }

        // Joined to payment_methods so the result is keyed by the method key
        // the rest of the application uses, not by a database id.
        $rows = EventPaymentMethod::query()
            ->join('payment_methods', 'payment_methods.id', '=', 'event_payment_methods.payment_method_id')
            ->where('event_payment_methods.event_id', $eventId)
            ->pluck('event_payment_methods.is_active', 'payment_methods.key')
            ->map(fn (mixed $active) => (bool) $active);

        return $this->overrideCache[$eventId] = $rows;
    }

    /**
     * Legacy catalog, used only while the table has not been seeded.
     *
     * @return Collection<int, PaymentMethod>
     */
    private function fromConfig(): Collection
    {
        /** @var array<string, array<string, mixed>> $configured */
        $configured = config('payments.methods', []);

        return collect($configured)->map(
            fn (array $definition, string $key) => PaymentMethod::fromConfig($key, $definition)
        );
    }

    /**
     * Whether this environment may display placeholder payment data.
     *
     * Local and testing always may. Any other environment may only when
     * explicitly opted in via PAYMENTS_ALLOW_DUMMY, which exists for internal
     * deployments that need a selectable method to exercise the flow. The
     * default is false, so forgetting to set it fails closed.
     */
    private function allowsDummyData(): bool
    {
        if (in_array(app()->environment(), self::DUMMY_ENVIRONMENTS, true)) {
            return true;
        }

        return (bool) config('payments.allow_dummy', false);
    }
}

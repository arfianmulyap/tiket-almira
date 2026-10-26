<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentMethod as PaymentMethodRecord;
use App\Support\PaymentMethod;
use Illuminate\Support\Collection;

/**
 * Decides which payment methods exist and which ones a participant may
 * actually be offered (PRD FR-05, Phase 12).
 *
 * The `payment_methods` table is the source of truth. Two filters are applied
 * on top of it, and the second one is the important one:
 *
 *  1. `is_active` lets the business retire a method without deleting it, so
 *     historical payments and snapshots stay resolvable.
 *  2. `is_dummy` rows are served only in local and testing environments.
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
     * @return array<int, array<string, mixed>>
     */
    public function snapshot(): array
    {
        return $this->available()
            ->map(fn (PaymentMethod $method) => $method->toSnapshot())
            ->values()
            ->all();
    }

    /**
     * Payment instructions that apply to one specific order.
     *
     * The order's snapshot wins. Orders created before Phase 12 have no
     * snapshot; for those only, the live catalog is used so the page keeps
     * working instead of showing an empty payment section.
     *
     * @return Collection<string, PaymentMethod>
     */
    public function forOrder(Order $order): Collection
    {
        $snapshot = $order->payment_instructions_snapshot;

        if (! is_array($snapshot)) {
            return $this->available();
        }

        return collect($snapshot)
            ->filter(fn (mixed $row) => is_array($row) && isset($row['key']))
            ->map(fn (array $row) => PaymentMethod::fromSnapshot($row))
            ->keyBy('key');
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

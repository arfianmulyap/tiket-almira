<?php

namespace App\Services;

use App\Support\PaymentMethod;
use Illuminate\Support\Collection;

/**
 * Reads the payment methods from configuration and decides which ones a
 * participant may actually be offered (PRD FR-05).
 *
 * Two filters are applied, and the second one is the important one:
 *
 *  1. `is_active` lets the business retire a method without deleting it.
 *  2. `is_dummy` methods are served only in local and testing environments.
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
     * Used by diagnostics and tests. Never render this to a participant:
     * it intentionally includes inactive and dummy entries.
     *
     * @return Collection<string, PaymentMethod>
     */
    public function all(): Collection
    {
        /** @var array<string, array<string, mixed>> $configured */
        $configured = config('payments.methods', []);

        return collect($configured)->map(
            fn (array $definition, string $key) => PaymentMethod::fromConfig($key, $definition)
        );
    }

    /**
     * Resolve one selectable method by key, or null if it may not be used.
     *
     * Goes through available() rather than the raw config so a method that is
     * inactive, or a dummy method in production, cannot be selected by
     * posting its key directly. Validation and the service both resolve
     * through here, so there is one definition of "selectable".
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
     * expected state while Phase 05C is blocked.
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

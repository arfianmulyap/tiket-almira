<?php

namespace App\Support;

/**
 * One payment method as it is offered to a participant.
 *
 * A typed value object rather than an array so views and services read named
 * properties, and so a malformed configuration row fails here with a clear
 * shape instead of producing "undefined array key" inside a Blade template.
 *
 * Instances are built from three places, all of which must yield the same
 * shape:
 *
 *  1. The `payment_methods` table, via `fromRecord()` (the live definition).
 *  2. The legacy config file, via `fromConfig()`, only while the table has not
 *     been seeded yet.
 *  3. An order's `payment_instructions_snapshot`, via `fromSnapshot()`. A
 *     snapshot is authoritative for that order: it ignores the method's
 *     current active flag so a later retirement cannot erase instructions a
 *     participant already received.
 *
 * `account` is an ordered list of `['label' => ..., 'value' => ...]` rows, not
 * an associative array, because MySQL's JSON type does not preserve object key
 * order and the order is meaningful in the instructions.
 */
readonly class PaymentMethod
{
    /**
     * @param  array<int, array{label: string, value: string}>  $account
     * @param  array<int, string>  $steps
     */
    public function __construct(
        public string $key,
        public string $label,
        public bool $isActive,
        public bool $isDummy,
        public array $account,
        public array $steps,
    ) {}

    /**
     * Build from a `payment_methods` row.
     *
     * @param  array<string, mixed>  $row
     */
    public static function fromRecord(array $row): self
    {
        return new self(
            key: (string) ($row['key'] ?? ''),
            label: (string) ($row['label'] ?? ($row['key'] ?? '')),
            isActive: (bool) ($row['is_active'] ?? false),
            isDummy: (bool) ($row['is_dummy'] ?? true),
            account: self::normaliseAccount($row['account'] ?? []),
            steps: self::normaliseSteps($row['steps'] ?? []),
        );
    }

    /**
     * Build from a legacy configuration entry.
     *
     * `is_dummy` defaults to true and `is_active` to false. Both defaults are
     * deliberately the safe direction: a method missing its flags is treated
     * as untrusted placeholder data and stays hidden rather than being
     * published to participants by accident.
     *
     * @param  array<string, mixed>  $definition
     */
    public static function fromConfig(string $key, array $definition): self
    {
        $account = [];

        foreach ((array) ($definition['account'] ?? []) as $label => $value) {
            $account[] = ['label' => (string) $label, 'value' => (string) $value];
        }

        return new self(
            key: $key,
            label: (string) ($definition['label'] ?? $key),
            isActive: (bool) ($definition['is_active'] ?? false),
            isDummy: (bool) ($definition['is_dummy'] ?? true),
            account: self::normaliseAccount($account),
            steps: self::normaliseSteps($definition['steps'] ?? []),
        );
    }

    /**
     * Rebuild from one entry of an order snapshot.
     *
     * @param  array<string, mixed>  $row
     */
    public static function fromSnapshot(array $row): self
    {
        return new self(
            key: (string) ($row['key'] ?? ''),
            label: (string) ($row['label'] ?? ($row['key'] ?? '')),
            // Not the current flag: the participant already received these
            // instructions, so they remain valid for this order.
            isActive: true,
            isDummy: (bool) ($row['is_dummy'] ?? true),
            account: self::normaliseAccount($row['account'] ?? []),
            steps: self::normaliseSteps($row['steps'] ?? []),
        );
    }

    /**
     * Serialise for `orders.payment_instructions_snapshot`.
     *
     * Only what a participant is shown, never internal columns such as the
     * database id or the last editor.
     *
     * @return array{
     *     key: string,
     *     label: string,
     *     is_dummy: bool,
     *     account: array<int, array{label: string, value: string}>,
     *     steps: array<int, string>
     * }
     */
    public function toSnapshot(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'is_dummy' => $this->isDummy,
            'account' => $this->account,
            'steps' => $this->steps,
        ];
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private static function normaliseAccount(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $normalised = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));

            // A half-filled row is dropped rather than rendered as a blank
            // definition row. The settings form rejects it before saving.
            if ($label === '' || $value === '') {
                continue;
            }

            $normalised[] = ['label' => $label, 'value' => $value];
        }

        return $normalised;
    }

    /**
     * @return array<int, string>
     */
    private static function normaliseSteps(mixed $steps): array
    {
        if (! is_array($steps)) {
            return [];
        }

        $normalised = [];

        foreach ($steps as $step) {
            $step = trim((string) $step);

            if ($step !== '') {
                $normalised[] = $step;
            }
        }

        return $normalised;
    }
}

<?php

namespace App\Support;

use App\Enums\PaymentMethodType;

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
        public string $type,
        public bool $isActive,
        public bool $isDummy,
        public array $account,
        public array $steps,
        public ?string $qrImageUrl = null,
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
            type: self::normaliseType($row['type'] ?? null),
            isActive: (bool) ($row['is_active'] ?? false),
            isDummy: (bool) ($row['is_dummy'] ?? true),
            account: self::normaliseAccount($row['account'] ?? []),
            steps: self::normaliseSteps($row['steps'] ?? []),
            qrImageUrl: filled($row['qr_image_url'] ?? null) ? (string) $row['qr_image_url'] : null,
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
            type: self::normaliseType($definition['type'] ?? null),
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
            type: self::normaliseType($row['type'] ?? null),
            // Not the current flag: the participant already received these
            // instructions, so they remain valid for this order.
            isActive: true,
            isDummy: (bool) ($row['is_dummy'] ?? true),
            account: self::normaliseAccount($row['account'] ?? []),
            steps: self::normaliseSteps($row['steps'] ?? []),
            qrImageUrl: filled($row['qr_image_url'] ?? null) ? (string) $row['qr_image_url'] : null,
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
     *     type: string,
     *     is_dummy: bool,
     *     account: array<int, array{label: string, value: string}>,
     *     steps: array<int, string>,
     *     qr_image_url: string|null
     * }
     */
    public function toSnapshot(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type,
            'is_dummy' => $this->isDummy,
            'account' => $this->account,
            'steps' => $this->steps,
            // Snapshot of the image as it was when the order was created. If the
            // business later replaces the file, this order keeps pointing at the
            // version the participant was actually shown.
            'qr_image_url' => $this->qrImageUrl,
        ];
    }

    /**
     * Value a stored detail row holds for one field of this method's type.
     *
     * Matching runs through PaymentMethodType::fieldForLabel(), so a row written
     * with a hand-typed label ("No. Rekening") is still recognised as the
     * account number instead of looking empty.
     */
    public function detailValue(string $field): ?string
    {
        foreach ($this->account as $row) {
            if (PaymentMethodType::fieldForLabel($row['label']) !== $field) {
                continue;
            }

            return $row['value'];
        }

        return null;
    }

    /**
     * The bank, provider, or merchant a participant pays into.
     *
     * QRIS has no bank, so the merchant name stands in.
     */
    public function providerValue(): ?string
    {
        return $this->detailValue('provider') ?? $this->detailValue('merchant');
    }

    /**
     * Labels of the required details this method is still missing.
     *
     * Defined here rather than on the model because the participant-facing
     * availability rule depends on it: a method that does not state where to
     * send money must not be offered, whatever its active flags say (Phase 12B
     * business rule 8).
     *
     * @return array<int, string>
     */
    public function missingDetails(): array
    {
        $type = PaymentMethodType::from($this->type);
        $missing = [];

        foreach ($type->details() as $field => $spec) {
            if ($spec['required'] && blank($this->detailValue($field))) {
                $missing[] = $spec['label'];
            }
        }

        // A QRIS method may carry an NMID instead of an image, but not neither.
        $reference = $type->merchantReferenceField();

        if ($type->usesQrImage()
            && blank($this->qrImageUrl)
            && ($reference === null || blank($this->detailValue($reference)))) {
            $missing[] = 'Gambar QR atau ID Merchant';
        }

        return $missing;
    }

    /**
     * Whether every required detail for this method's type is present.
     */
    public function isConfigured(): bool
    {
        return $this->missingDetails() === [];
    }

    /**
     * Keep `type` to a value the rest of the application understands.
     *
     * A snapshot is free-form JSON and config/payments.php is hand-edited, so
     * this is the one place where an unknown or missing type can enter. Falling
     * back to the default keeps an old snapshot readable instead of making the
     * order page fail on an unrecognised type.
     */
    private static function normaliseType(mixed $type): string
    {
        $candidate = is_string($type) ? trim($type) : '';

        return PaymentMethodType::tryFrom($candidate)?->value
            ?? PaymentMethodType::BankTransfer->value;
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

<?php

namespace App\Support;

/**
 * One payment method as defined in config/payments.php.
 *
 * A typed object rather than a raw array so views and services access named
 * properties instead of guessing at string keys, and so a malformed config
 * entry fails here with a clear shape rather than producing an "undefined
 * array key" deep inside a Blade template.
 *
 * This is not an Eloquent model: PRD section 15 question 16 (global versus
 * per-event methods) is unanswered, so no `payment_methods` table is created
 * yet. Reading from configuration keeps the decision reversible — moving to a
 * table later means changing PaymentMethodRegistry only, since nothing else
 * touches the config directly.
 */
readonly class PaymentMethod
{
    /**
     * @param  array<string, string>  $account
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
     * Build from a configuration entry.
     *
     * `is_dummy` defaults to true, and `is_active` to false. Both defaults
     * are deliberately the safe direction: a method missing its flags is
     * treated as untrusted placeholder data and stays hidden, rather than
     * being published to participants by accident.
     *
     * @param  array<string, mixed>  $definition
     */
    public static function fromConfig(string $key, array $definition): self
    {
        return new self(
            key: $key,
            label: (string) ($definition['label'] ?? $key),
            isActive: (bool) ($definition['is_active'] ?? false),
            isDummy: (bool) ($definition['is_dummy'] ?? true),
            account: array_map('strval', $definition['account'] ?? []),
            steps: array_map('strval', $definition['steps'] ?? []),
        );
    }
}

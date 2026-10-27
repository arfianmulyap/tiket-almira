<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Give the seeded payment methods the type their config entry declares
 * (Phase 12B).
 *
 * Phase 12B added the `type` column with `bank_transfer` as its default, which
 * is right for a genuinely unknown row but wrong for a method that
 * config/payments.php already describes: the shipped e-wallet entry would be
 * labelled "Transfer Bank" on every installation that was seeded before this
 * phase, and an operator would reasonably read that as a bug.
 *
 * The backfill is deliberately narrow. It only touches a row when all three of
 * these hold:
 *
 *  1. the row is still on the column default (`bank_transfer`), so a type an
 *     admin already chose is never overwritten;
 *  2. the config entry declares a different type;
 *  3. the row is still flagged `is_dummy` — placeholder data, not confirmed
 *     business data. Any method whose details the business confirmed is left
 *     exactly as it is.
 *
 * Without the third condition a hand-typed method that happens to share a key
 * with a config entry could be retyped behind the operator's back.
 */
return new class extends Migration
{
    public function up(): void
    {
        /** @var array<string, array<string, mixed>> $configured */
        $configured = config('payments.methods', []);

        foreach ($configured as $key => $definition) {
            $declared = $definition['type'] ?? null;

            if (! is_string($declared) || $declared === '' || $declared === 'bank_transfer') {
                continue;
            }

            DB::table('payment_methods')
                ->where('key', (string) $key)
                ->where('type', 'bank_transfer')
                ->where('is_dummy', true)
                ->update(['type' => $declared, 'updated_at' => now()]);
        }
    }

    /**
     * Not reversible: the previous value was the column default for some rows
     * and a deliberate choice for others, and the two cannot be told apart after
     * the fact. Leaving the correct type in place is harmless — the column keeps
     * its default and the row stays readable either way.
     */
    public function down(): void {}
};

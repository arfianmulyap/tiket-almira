<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Freeze the payment instructions shown to each participant (Phase 12).
 *
 * PRD business rule 12 already snapshots the ticket price on the order. The
 * same reasoning applies to payment instructions: retiring a method or editing
 * an account number must not rewrite what a participant was told to pay into.
 * The snapshot is written when the order is created, so only the backfill here
 * has to look at the old configuration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // List of methods exactly as offered at order creation:
            // [{key, label, is_dummy, account: [{label, value}], steps: []}].
            $table->json('payment_instructions_snapshot')
                ->nullable()
                ->after('ticket_price_snapshot');
        });

        // Orders created before Phase 12 never got a snapshot. Freeze the
        // configuration that was live when they were created — which is the
        // configuration this migration runs against — rather than leaving them
        // to follow later edits. The environment filter mirrors
        // PaymentMethodRegistry: placeholder methods are never served in
        // production, so an order there saw no methods at all.
        $allowDummy = app()->environment(['local', 'testing']);

        $methods = [];

        /** @var array<string, array<string, mixed>> $configured */
        $configured = config('payments.methods', []);

        foreach ($configured as $key => $definition) {
            if (! (bool) ($definition['is_active'] ?? false)) {
                continue;
            }

            $isDummy = (bool) ($definition['is_dummy'] ?? true);

            if ($isDummy && ! $allowDummy) {
                continue;
            }

            $account = [];

            foreach ((array) ($definition['account'] ?? []) as $label => $value) {
                $account[] = ['label' => (string) $label, 'value' => (string) $value];
            }

            $steps = [];

            foreach ((array) ($definition['steps'] ?? []) as $step) {
                $step = trim((string) $step);

                if ($step !== '') {
                    $steps[] = $step;
                }
            }

            $methods[] = [
                'key' => (string) $key,
                'label' => (string) ($definition['label'] ?? $key),
                'is_dummy' => $isDummy,
                'account' => $account,
                'steps' => $steps,
            ];
        }

        DB::table('orders')
            ->whereNull('payment_instructions_snapshot')
            ->update(['payment_instructions_snapshot' => json_encode($methods)]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('payment_instructions_snapshot');
        });
    }
};

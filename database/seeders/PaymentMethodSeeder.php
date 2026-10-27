<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use App\Support\PaymentMethod as PaymentMethodValue;
use Illuminate\Database\Seeder;

/**
 * Copies the legacy config/payments.php catalog into the `payment_methods`
 * table (Phase 12).
 *
 * Safe to run repeatedly: `firstOrCreate` only inserts missing keys, so a
 * method an admin has since renamed, retyped, retired, or given real account
 * details is never overwritten by a later deployment. Since Phase 12B a new
 * method can also be created from the admin panel, which is now the intended
 * route; this seeder remains the way to introduce a method that must exist on
 * every installation, and the fallback for an installation whose
 * `payment_methods` table is still empty.
 */
class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array<string, array<string, mixed>> $configured */
        $configured = config('payments.methods', []);

        $created = 0;
        $sortOrder = 0;

        foreach ($configured as $key => $definition) {
            $method = PaymentMethodValue::fromConfig((string) $key, (array) $definition);

            $record = PaymentMethod::query()->firstOrCreate(
                ['key' => $method->key],
                [
                    'label' => $method->label,
                    'type' => $method->type,
                    'is_active' => $method->isActive,
                    'is_dummy' => $method->isDummy,
                    'account' => $method->account,
                    'steps' => $method->steps,
                    'sort_order' => $sortOrder,
                ],
            );

            if ($record->wasRecentlyCreated) {
                $created++;
            }

            $sortOrder++;
        }

        $total = PaymentMethod::query()->count();

        $this->command?->info(
            "Katalog metode pembayaran siap: {$created} metode baru, {$total} total."
        );
    }
}

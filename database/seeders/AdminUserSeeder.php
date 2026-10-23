<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Create or update the initial admin account.
     *
     * Credentials are read from the environment so they never live in
     * source control. Set ADMIN_EMAIL, ADMIN_NAME, and ADMIN_PASSWORD
     * before running this seeder.
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (blank($email) || blank($password)) {
            $this->command?->warn(
                'AdminUserSeeder skipped: set ADMIN_EMAIL and ADMIN_PASSWORD in your environment first.'
            );

            return;
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->name = env('ADMIN_NAME', 'Admin Almira');
        $user->password = Hash::make($password);
        $user->is_admin = true;
        $user->email_verified_at ??= now();
        $user->save();

        $this->command?->info("Admin account ready for {$email}.");
    }
}

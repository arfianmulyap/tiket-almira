<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'is_admin' => false,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user may access the admin panel.
     *
     * The account flag alone is not enough from Phase 10 onward: the panel
     * refuses a role-less admin and every policy reads the role's permissions,
     * so the state also attaches the permanent Super Admin role.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => true,
        ])->afterCreating(function (User $user): void {
            $user->assignRole($this->superAdminRole());
        });
    }

    /**
     * The protected Super Admin role, created on demand so the factory also
     * works on a database seeded without RolePermissionSeeder.
     */
    private function superAdminRole(): Role
    {
        $role = Role::query()->where('is_super_admin', true)->first();

        if ($role !== null) {
            return $role;
        }

        $role = new Role;
        $role->name = 'Super Admin';
        $role->is_super_admin = true;
        $role->save();

        return $role;
    }
}

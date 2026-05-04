<?php

namespace Database\Factories;

use App\Enums\UserStatus;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'country_code' => '+1',
            'mobile' => fake()->numerify('##########'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'preferred_theme' => 'light',
            'preferred_language' => 'en',
            'current_tenant_id' => null,
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function withTenant(?Tenant $tenant = null, bool $isAdmin = false, bool $isSupervisor = false): static
    {
        return $this->afterCreating(function (User $user) use ($tenant, $isAdmin, $isSupervisor) {
            $tenant ??= Tenant::factory()->create();
            $tenant->users()->attach($user->id, [
                'is_admin' => $isAdmin,
                'is_supervisor' => $isSupervisor,
                'status' => UserStatus::Active->value,
            ]);
            $user->update(['current_tenant_id' => $tenant->id]);
        });
    }

    public function admin(?Tenant $tenant = null): static
    {
        return $this->withTenant($tenant, isAdmin: true);
    }

    public function suspended(?Tenant $tenant = null): static
    {
        return $this->afterCreating(function (User $user) use ($tenant) {
            $tenant ??= Tenant::factory()->create();
            $tenant->users()->attach($user->id, [
                'is_admin' => true,
                'is_supervisor' => false,
                'status' => UserStatus::Suspended->value,
            ]);
            $user->update(['current_tenant_id' => $tenant->id]);
        });
    }
}

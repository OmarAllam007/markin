<?php

namespace Database\Factories;

use App\Models\Holiday;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Holiday>
 */
class HolidayFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->words(3, true),
            'date' => fake()->date(),
            'is_recurring' => false,
            'created_by' => null,
        ];
    }

    public function recurring(): static
    {
        return $this->state(['is_recurring' => true]);
    }
}

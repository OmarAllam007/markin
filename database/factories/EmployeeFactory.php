<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'created_by' => User::factory(),
            'arabic_name' => $this->faker->name(),
            'english_name' => $this->faker->name(),
            'mobile_country_code' => '+966',
            'mobile_number' => $this->faker->numerify('5########'),
            'email' => $this->faker->optional()->safeEmail(),
            'nationality' => $this->faker->optional()->country(),
            'marital_status' => $this->faker->optional()->randomElement(MaritalStatus::cases())?->value,
            'birth_date' => $this->faker->optional()->date(),
            'gender' => $this->faker->optional()->randomElement(Gender::cases())?->value,
            'religion' => $this->faker->optional()->randomElement(['muslim', 'christian', 'other']),
        ];
    }
}

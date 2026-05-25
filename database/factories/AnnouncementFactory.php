<?php

namespace Database\Factories;

use App\Enums\AnnouncementType;
use App\Models\Announcement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'created_by' => User::factory(),
            'type' => AnnouncementType::Notification,
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'target_type' => 'locations_departments',
            'target_location_id' => null,
            'target_department_id' => null,
            'target_employee_ids' => null,
            'sent_at' => now(),
        ];
    }

    public function forEmployees(array $ids): static
    {
        return $this->state([
            'target_type' => 'employees',
            'target_employee_ids' => array_map('strval', $ids),
            'target_location_id' => null,
            'target_department_id' => null,
        ]);
    }

    public function draft(): static
    {
        return $this->state(['sent_at' => null]);
    }
}

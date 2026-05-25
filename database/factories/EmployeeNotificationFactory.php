<?php

namespace Database\Factories;

use App\Enums\NotificationType;
use App\Models\Employee;
use App\Models\EmployeeNotification;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeNotification>
 */
class EmployeeNotificationFactory extends Factory
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
            'employee_id' => Employee::factory(),
            'type' => NotificationType::LateCheckIn,
            'title' => 'Late Check-in',
            'body' => 'You checked in 10 minutes late.',
            'data' => ['minutes_late' => 10, 'date' => now()->toDateString()],
            'read_at' => null,
        ];
    }

    public function read(): static
    {
        return $this->state(['read_at' => now()]);
    }

    public function earlyCheckOut(): static
    {
        return $this->state([
            'type' => NotificationType::EarlyCheckOut,
            'title' => 'Early Check-out',
            'body' => 'You left 1 hour early.',
            'data' => ['minutes_early' => 60, 'date' => now()->toDateString()],
        ]);
    }
}

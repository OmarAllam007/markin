<?php

namespace Database\Factories;

use App\Enums\ShiftType;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkShift>
 */
class WorkShiftFactory extends Factory
{
    public function definition(): array
    {
        $type = $this->faker->randomElement(ShiftType::cases());

        return [
            'tenant_id' => Tenant::factory(),
            'created_by' => User::factory(),
            'name' => $this->faker->words(nb: 2, asText: true),
            'type' => $type,
            'weekends' => ['friday', 'saturday'],
            'checkin_time' => $type === ShiftType::Fixed ? '09:00' : null,
            'checkout_time' => $type === ShiftType::Fixed ? '17:00' : null,
            'working_hours' => $type === ShiftType::Flexible ? 8 : null,
            'working_minutes' => $type === ShiftType::Flexible ? 0 : null,
            'overtime_enabled' => false,
            'calculate_overtime_early_checkin' => false,
        ];
    }

    public function fixed(): static
    {
        return $this->state([
            'type' => ShiftType::Fixed,
            'checkin_time' => '09:00',
            'checkout_time' => '17:00',
            'working_hours' => null,
            'working_minutes' => null,
        ]);
    }

    public function flexible(): static
    {
        return $this->state([
            'type' => ShiftType::Flexible,
            'working_hours' => 8,
            'working_minutes' => 0,
            'checkin_time' => null,
            'checkout_time' => null,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Enums\AttendancePunchLogStatus;
use App\Enums\AttendanceSource;
use App\Enums\PunchType;
use App\Models\AttendancePunchLog;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendancePunchLog>
 */
class AttendancePunchLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'tenant_id' => fn (array $attrs) => Employee::find($attrs['employee_id'])?->tenant_id ?? 1,
            'source' => AttendanceSource::Mobile,
            'punch_type' => PunchType::CheckIn,
            'status' => AttendancePunchLogStatus::Success,
            'failure_reason' => null,
            'attendance_punch_id' => null,
            'latitude' => $this->faker->optional()->latitude(),
            'longitude' => $this->faker->optional()->longitude(),
            'ip_address' => $this->faker->optional()->ipv4(),
            'device_name' => $this->faker->optional()->userAgent(),
            'user_agent' => $this->faker->optional()->userAgent(),
            'attempted_at' => now(),
        ];
    }

    public function failed(string $reason = 'Validation failed.'): static
    {
        return $this->state([
            'status' => AttendancePunchLogStatus::Failed,
            'failure_reason' => $reason,
            'attendance_punch_id' => null,
        ]);
    }

    public function biometric(): static
    {
        return $this->state([
            'source' => AttendanceSource::Biometric,
            'latitude' => null,
            'longitude' => null,
            'ip_address' => null,
            'user_agent' => null,
        ]);
    }
}

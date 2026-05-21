<?php

namespace Database\Factories;

use App\Enums\AttendanceSource;
use App\Enums\PunchType;
use App\Models\Attendance;
use App\Models\AttendancePunch;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendancePunch>
 */
class AttendancePunchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'attendance_id' => Attendance::factory(),
            'type' => PunchType::CheckIn,
            'punched_at' => now(),
            'source' => AttendanceSource::Mobile,
            'latitude' => $this->faker->optional()->latitude(),
            'longitude' => $this->faker->optional()->longitude(),
            'device_name' => $this->faker->optional()->userAgent(),
        ];
    }

    public function checkOut(): static
    {
        return $this->state(['type' => PunchType::CheckOut]);
    }

    public function forAttendance(Attendance $attendance): static
    {
        return $this->state(fn () => ['attendance_id' => $attendance->id]);
    }

    public function atTime(CarbonInterface $time): static
    {
        return $this->state(fn () => ['punched_at' => $time]);
    }
}

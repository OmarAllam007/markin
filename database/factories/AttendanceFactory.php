<?php

namespace Database\Factories;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'employee_id' => Employee::factory(),
            'attendance_date' => now()->toDateString(),
            'check_in_time' => '09:00:00',
            'check_out_time' => '17:00:00',
            'worked_minutes' => 480,
            'total_late_minutes' => 0,
            'total_early_leave_minutes' => 0,
            'overtime_minutes' => 0,
            'break_minutes' => 0,
            'status' => AttendanceStatus::Present,
            'shift_id' => null,
            'scheduled_check_in' => '09:00:00',
            'scheduled_check_out' => '17:00:00',
            'attendance_source' => AttendanceSource::Mobile,
            'comments' => null,
            'attachment_path' => null,
            'location_id' => null,
            'latitude' => null,
            'longitude' => null,
            'gps_accuracy' => null,
            'address' => null,
            'device_id' => null,
            'ip_address' => null,
            'user_agent' => null,
            'approved_by' => null,
            'approved_at' => null,
            'created_by' => User::factory(),
            'updated_by' => null,
            'is_manual_edit' => false,
            'edit_reason' => null,
            'is_weekend' => false,
            'is_holiday' => false,
            'is_locked' => false,
            'payroll_exported_at' => null,
        ];
    }

    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn () => [
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
        ]);
    }

    public function forDate(CarbonInterface $date): static
    {
        return $this->state(fn () => ['attendance_date' => $date->toDateString()]);
    }

    public function late(): static
    {
        $lateMinutes = $this->faker->numberBetween(10, 90);
        $checkInHour = 9 + (int) floor($lateMinutes / 60);
        $checkInMinute = $lateMinutes % 60;
        $checkInTime = sprintf('%02d:%02d:00', $checkInHour, $checkInMinute);
        $workedMinutes = 480 - $lateMinutes;

        return $this->state(fn () => [
            'status' => AttendanceStatus::Late,
            'check_in_time' => $checkInTime,
            'check_out_time' => '17:00:00',
            'total_late_minutes' => $lateMinutes,
            'worked_minutes' => max(0, $workedMinutes),
        ]);
    }

    public function absent(): static
    {
        return $this->state(fn () => [
            'status' => AttendanceStatus::Absent,
            'check_in_time' => null,
            'check_out_time' => null,
            'worked_minutes' => 0,
        ]);
    }

    public function halfDay(): static
    {
        return $this->state(fn () => [
            'status' => AttendanceStatus::HalfDay,
            'check_in_time' => '09:00:00',
            'check_out_time' => '13:00:00',
            'worked_minutes' => 240,
            'total_early_leave_minutes' => 240,
        ]);
    }

    public function weekend(): static
    {
        return $this->state(fn () => [
            'status' => AttendanceStatus::Weekend,
            'check_in_time' => null,
            'check_out_time' => null,
            'worked_minutes' => 0,
            'is_weekend' => true,
        ]);
    }

    public function holiday(): static
    {
        return $this->state(fn () => [
            'status' => AttendanceStatus::Holiday,
            'check_in_time' => null,
            'check_out_time' => null,
            'worked_minutes' => 0,
            'is_holiday' => true,
        ]);
    }

    public function missingCheckout(): static
    {
        return $this->state(fn () => [
            'status' => AttendanceStatus::MissingCheckout,
            'check_in_time' => '09:00:00',
            'check_out_time' => null,
            'worked_minutes' => 0,
        ]);
    }
}

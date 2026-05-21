<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceSeeder extends Seeder
{
    private int $shiftIn;

    private int $shiftOut;

    private string $shiftInTime;

    private string $shiftOutTime;

    public function run(): void
    {
        $tenantId = 1;
        $now = now();

        $adminId = DB::table('users')
            ->where('current_tenant_id', $tenantId)
            ->value('id');

        // Load shift 1 times so all calculations use the real schedule
        $shift = DB::table('work_shifts')->where('id', 1)->first();
        [$shiftInH, $shiftInM] = explode(':', $shift->checkin_time);
        [$shiftOutH, $shiftOutM] = explode(':', $shift->checkout_time);
        $this->shiftIn = (int) $shiftInH * 60 + (int) $shiftInM;
        $this->shiftOut = (int) $shiftOutH * 60 + (int) $shiftOutM;
        $this->shiftInTime = $shift->checkin_time.':00';
        $this->shiftOutTime = $shift->checkout_time.':00';

        // Locations to distribute employees across
        $locationIds = DB::table('locations')
            ->where('tenant_id', $tenantId)
            ->pluck('id')
            ->toArray();

        // Clean up previous data for this tenant
        $attendanceIds = DB::table('attendances')
            ->where('tenant_id', $tenantId)
            ->pluck('id');

        if ($attendanceIds->isNotEmpty()) {
            DB::table('attendance_punches')->whereIn('attendance_id', $attendanceIds)->delete();
        }

        DB::table('attendances')->where('tenant_id', $tenantId)->delete();
        DB::table('employees')->where('tenant_id', $tenantId)->delete();

        $this->command->info("Shift 1: {$shift->checkin_time} – {$shift->checkout_time}. Creating 20 employees...");

        for ($i = 1; $i <= 20; $i++) {
            $locationId = $locationIds[($i - 1) % count($locationIds)];

            $employeeId = DB::table('employees')->insertGetId([
                'tenant_id' => $tenantId,
                'created_by' => $adminId,
                'arabic_name' => "موظف {$i}",
                'english_name' => "Employee {$i}",
                'mobile_country_code' => '+966',
                'mobile_number' => '5'.str_pad($i, 8, '0', STR_PAD_LEFT),
                'employee_number' => 'EMP'.str_pad($i, 3, '0', STR_PAD_LEFT),
                'status' => 'active',
                'location_id' => $locationId,
                'work_shift_id' => 1,
                'check_biometrics' => 0,
                'send_reminders' => 0,
                'allow_remote_checkin' => 0,
                'allow_any_location_checkin' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            for ($day = 29; $day >= 0; $day--) {
                $date = now()->subDays($day)->toDateString();
                $dayOfWeek = Carbon::parse($date)->dayOfWeek;
                $isWeekend = in_array($dayOfWeek, [Carbon::FRIDAY, Carbon::SATURDAY]);

                if ($isWeekend) {
                    DB::table('attendances')->insert([
                        'tenant_id' => $tenantId,
                        'employee_id' => $employeeId,
                        'attendance_date' => $date,
                        'check_in_time' => null,
                        'check_out_time' => null,
                        'worked_minutes' => 0,
                        'total_late_minutes' => 0,
                        'total_early_leave_minutes' => 0,
                        'overtime_minutes' => 0,
                        'break_minutes' => 0,
                        'status' => 'weekend',
                        'shift_id' => 1,
                        'scheduled_check_in' => $this->shiftInTime,
                        'scheduled_check_out' => $this->shiftOutTime,
                        'attendance_source' => 'biometric',
                        'location_id' => $locationId,
                        'is_manual_edit' => 0,
                        'is_weekend' => 1,
                        'is_holiday' => 0,
                        'is_locked' => 0,
                        'created_by' => $adminId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    continue;
                }

                [$checkInTime, $checkOutTime, $status, $workedMinutes, $lateMinutes, $earlyLeaveMinutes, $overtimeMinutes] = $this->randomDayData();

                $attendanceId = DB::table('attendances')->insertGetId([
                    'tenant_id' => $tenantId,
                    'employee_id' => $employeeId,
                    'attendance_date' => $date,
                    'check_in_time' => $checkInTime,
                    'check_out_time' => $checkOutTime,
                    'worked_minutes' => $workedMinutes,
                    'total_late_minutes' => $lateMinutes,
                    'total_early_leave_minutes' => $earlyLeaveMinutes,
                    'overtime_minutes' => $overtimeMinutes,
                    'break_minutes' => 0,
                    'status' => $status,
                    'shift_id' => 1,
                    'scheduled_check_in' => $this->shiftInTime,
                    'scheduled_check_out' => $this->shiftOutTime,
                    'attendance_source' => 'biometric',
                    'location_id' => $locationId,
                    'is_manual_edit' => 0,
                    'is_weekend' => 0,
                    'is_holiday' => 0,
                    'is_locked' => 0,
                    'created_by' => $adminId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $punches = [];

                if ($checkInTime) {
                    $punches[] = [
                        'attendance_id' => $attendanceId,
                        'type' => 'check_in',
                        'punched_at' => $date.' '.$checkInTime,
                        'source' => 'biometric',
                        'location_id' => $locationId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($checkOutTime) {
                    $punches[] = [
                        'attendance_id' => $attendanceId,
                        'type' => 'check_out',
                        'punched_at' => $date.' '.$checkOutTime,
                        'source' => 'biometric',
                        'location_id' => $locationId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (! empty($punches)) {
                    DB::table('attendance_punches')->insert($punches);
                }
            }
        }

        $this->command->info('Done.');
    }

    /** @return array{0: string|null, 1: string|null, 2: string, 3: int, 4: int, 5: int, 6: int} */
    private function randomDayData(): array
    {
        $roll = rand(1, 100);

        // Absent (10%) — no punches
        if ($roll <= 10) {
            return [null, null, 'absent', 0, 0, 0, 0];
        }

        // Missing checkout (5%) — check-in only
        if ($roll <= 15) {
            $in = rand($this->shiftIn - 30, $this->shiftIn + 90);

            return [$this->toTime($in), null, 'missing_checkout', 0, max(0, $in - $this->shiftIn), 0, 0];
        }

        // Half day (5%): leaves around noon
        if ($roll <= 20) {
            $in = rand($this->shiftIn - 30, $this->shiftIn);
            $out = rand(12 * 60, 13 * 60);

            return $this->build($in, $out, 'half_day');
        }

        // Late arrival (20%): arrives after shift start
        if ($roll <= 40) {
            $in = rand($this->shiftIn + 1, $this->shiftIn + 90);
            $out = rand($this->shiftOut, $this->shiftOut + 30);

            return $this->build($in, $out, 'late');
        }

        // Early leave (15%): leaves before shift end
        if ($roll <= 55) {
            $in = rand($this->shiftIn - 30, $this->shiftIn);
            $out = rand($this->shiftOut - 120, $this->shiftOut - 1);

            return $this->build($in, $out, 'present');
        }

        // Present on time (45%): on time in, on time or slightly late out
        $in = rand($this->shiftIn - 30, $this->shiftIn);
        $out = rand($this->shiftOut, $this->shiftOut + 30);

        return $this->build($in, $out, 'present');
    }

    /** @return array{0: string, 1: string, 2: string, 3: int, 4: int, 5: int, 6: int} */
    private function build(int $in, int $out, string $status): array
    {
        return [
            $this->toTime($in),                        // check_in_time
            $this->toTime($out),                       // check_out_time
            $status,                                   // status
            $out - $in,                                // worked_minutes
            max(0, $in - $this->shiftIn),              // total_late_minutes
            max(0, $this->shiftOut - $out),            // total_early_leave_minutes
            max(0, $out - $this->shiftOut),            // overtime_minutes
        ];
    }

    private function toTime(int $totalMinutes): string
    {
        return \sprintf('%02d:%02d:00', intdiv($totalMinutes, 60), $totalMinutes % 60);
    }
}

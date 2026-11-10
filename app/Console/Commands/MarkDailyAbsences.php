<?php

namespace App\Console\Commands;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\EmployeeStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\WorkShift;
use App\Services\HolidayService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Closes out finished shifts: active employees with no attendance get an Absent
 * record, and check-ins with no check-out become MissingCheckout.
 *
 * Idempotent and scheduled hourly, so each tenant is handled in its own timezone
 * once a shift has ended plus a grace period (late ZK pushes, short overtime).
 * A punch arriving afterwards recomputes the record back to Present/Late.
 */
#[Signature('attendance:mark-absences {--date= : Process only this date (Y-m-d), e.g. to backfill}')]
#[Description('Mark absences and missing check-outs for shifts that have ended')]
class MarkDailyAbsences extends Command
{
    private const GRACE_HOURS = 2;

    public function handle(HolidayService $holidayService): int
    {
        $absent = 0;
        $missingCheckout = 0;

        Tenant::with('settings')->each(function (Tenant $tenant) use ($holidayService, &$absent, &$missingCheckout): void {
            $timezone = $tenant->settings?->timezone ?? 'UTC';
            $now = CarbonImmutable::now($timezone);

            $dates = $this->option('date')
                ? [CarbonImmutable::parse($this->option('date'), $timezone)]
                : [$now->subDay()->startOfDay(), $now->startOfDay()];

            foreach ($dates as $date) {
                if ($holidayService->isHoliday($tenant->id, $date)) {
                    continue;
                }

                $absent += $this->markAbsences($tenant, $date, $now);
                $missingCheckout += $this->markMissingCheckouts($tenant, $date, $now);
            }
        });

        $this->info("Marked {$absent} absences and {$missingCheckout} missing check-outs.");

        return self::SUCCESS;
    }

    private function markAbsences(Tenant $tenant, CarbonImmutable $date, CarbonImmutable $now): int
    {
        $count = 0;

        Employee::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', EmployeeStatus::Active)
            ->whereNotNull('work_shift_id')
            ->where(fn ($q) => $q->whereNull('working_start_date')->orWhereDate('working_start_date', '<=', $date->toDateString()))
            // withTrashed: a record an admin deleted on purpose must not be recreated.
            ->whereDoesntHave('attendances', fn ($q) => $q->withTrashed()->whereDate('attendance_date', $date->toDateString()))
            ->with('workShift')
            ->each(function (Employee $employee) use ($date, $now, &$count): void {
                $shift = $employee->workShift;

                if (! $shift || in_array(strtolower($date->englishDayOfWeek), $shift->weekends ?? [], true)) {
                    return;
                }

                if (! $this->shiftIsClosed($shift, $date, $now)) {
                    return;
                }

                Attendance::create([
                    'tenant_id' => $employee->tenant_id,
                    'employee_id' => $employee->id,
                    'attendance_date' => $date->toDateString(),
                    'shift_id' => $shift->id,
                    'scheduled_check_in' => $shift->checkin_time,
                    'scheduled_check_out' => $shift->checkout_time,
                    'attendance_source' => AttendanceSource::Manual,
                    'status' => AttendanceStatus::Absent,
                    'comments' => 'Auto-marked absent: no punches recorded.',
                    'created_by' => $employee->created_by,
                ]);

                $count++;
            });

        return $count;
    }

    // ponytail: keys off check_out_time, so a multi-session day whose last punch is a re-check-in isn't flagged
    private function markMissingCheckouts(Tenant $tenant, CarbonImmutable $date, CarbonImmutable $now): int
    {
        $count = 0;

        Attendance::query()
            ->where('tenant_id', $tenant->id)
            ->whereDate('attendance_date', $date->toDateString())
            ->whereIn('status', [AttendanceStatus::Present, AttendanceStatus::Late])
            ->whereNotNull('check_in_time')
            ->whereNull('check_out_time')
            ->with('shift')
            ->each(function (Attendance $attendance) use ($date, $now, &$count): void {
                if ($attendance->shift && ! $this->shiftIsClosed($attendance->shift, $date, $now)) {
                    return;
                }

                $attendance->update(['status' => AttendanceStatus::MissingCheckout]);
                $count++;
            });

        return $count;
    }

    private function shiftIsClosed(WorkShift $shift, CarbonImmutable $date, CarbonImmutable $now): bool
    {
        // Flexible shifts have no fixed end, so the day itself is the shift.
        $shiftEnd = $shift->checkout_time
            ? $date->setTimeFromTimeString($shift->checkout_time)->addDays($shift->isOvernight() ? 1 : 0)
            : $date->addDay();

        return $now->greaterThanOrEqualTo($shiftEnd->addHours(self::GRACE_HOURS));
    }
}

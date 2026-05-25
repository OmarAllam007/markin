<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Holiday;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class HolidayService
{
    /** Statuses that should be overwritten when a date is declared a holiday. */
    private const OVERRIDABLE_STATUSES = [
        AttendanceStatus::Absent->value,
        AttendanceStatus::Present->value,
        AttendanceStatus::Late->value,
        AttendanceStatus::HalfDay->value,
        AttendanceStatus::MissingCheckout->value,
        AttendanceStatus::Remote->value,
    ];

    public function isHoliday(int $tenantId, CarbonInterface $date): bool
    {
        return Holiday::where('tenant_id', $tenantId)
            ->where(function ($q) use ($date) {
                $q->whereDate('date', $date->toDateString())
                    ->orWhere(function ($q) use ($date) {
                        $q->where('is_recurring', true)
                            ->whereMonth('date', $date->month)
                            ->whereDay('date', $date->day);
                    });
            })
            ->exists();
    }

    /**
     * Retroactively mark existing attendance records as Holiday for the given holiday.
     * Skips records already set to Leave, BusinessTrip, Weekend, or Holiday.
     *
     * @return int number of records updated
     */
    public function applyToExistingAttendances(Holiday $holiday): int
    {
        $query = Attendance::where('tenant_id', $holiday->tenant_id)
            ->whereIn('status', self::OVERRIDABLE_STATUSES);

        if ($holiday->is_recurring) {
            $query->whereMonth('attendance_date', $holiday->date->month)
                ->whereDay('attendance_date', $holiday->date->day);
        } else {
            $query->whereDate('attendance_date', $holiday->date->toDateString());
        }

        return $query->update([
            'status' => AttendanceStatus::Holiday->value,
            'is_holiday' => true,
        ]);
    }

    /**
     * Revert Holiday-flagged attendances back to Absent for dates covered by the
     * given holiday, but only if no other active holiday still covers that date.
     *
     * @return int number of records reverted
     */
    public function revertFromAttendances(Holiday $holiday): int
    {
        $query = Attendance::where('tenant_id', $holiday->tenant_id)
            ->where('status', AttendanceStatus::Holiday->value)
            ->where('is_holiday', true);

        if ($holiday->is_recurring) {
            $query->whereMonth('attendance_date', $holiday->date->month)
                ->whereDay('attendance_date', $holiday->date->day);
        } else {
            $query->whereDate('attendance_date', $holiday->date->toDateString());
        }

        $updated = 0;

        $query->chunkById(200, function ($attendances) use (&$updated) {
            foreach ($attendances as $attendance) {
                // Skip if another holiday still covers this date.
                if ($this->isHoliday($attendance->tenant_id, Carbon::parse($attendance->attendance_date))) {
                    continue;
                }

                $attendance->update([
                    'status' => AttendanceStatus::Absent->value,
                    'is_holiday' => false,
                ]);

                $updated++;
            }
        });

        return $updated;
    }
}

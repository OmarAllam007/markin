<?php

namespace App\Services\Attendance;

use App\Enums\ShiftType;
use App\Models\Employee;
use App\Models\WorkShift;
use Carbon\CarbonInterface;

/**
 * Derives scheduled clock times and calendar flags from {@see WorkShift} configuration.
 *
 * Holiday calendars are expected to be injected as flags on the attendance row (or a future
 * `holidays` table); this resolver stays shift- and weekend-focused for scalability.
 */
final class AttendanceScheduleResolver
{
    /**
     * @return array{0: string|null, 1: string|null}
     */
    public function scheduledWallClockTimes(?WorkShift $shift): array
    {
        if ($shift === null) {
            return [null, null];
        }

        if ($shift->type === ShiftType::Fixed) {
            return [$shift->checkin_time, $shift->checkout_time];
        }

        return [$shift->limit_checkin_from, $shift->limit_checkin_to];
    }

    public function isConfiguredWeekend(CarbonInterface $date, ?WorkShift $shift): bool
    {
        if ($shift === null || empty($shift->weekends)) {
            return false;
        }

        $dayKey = strtolower($date->format('l'));

        return in_array($dayKey, $shift->weekends, true);
    }

    public function resolveShiftForAttendance(?int $shiftId, Employee $employee): ?WorkShift
    {
        if ($shiftId !== null) {
            return WorkShift::query()->find($shiftId);
        }

        if ($employee->work_shift_id === null) {
            return null;
        }

        return $employee->workShift;
    }
}

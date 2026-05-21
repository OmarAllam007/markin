<?php

namespace App\DataTransferObjects;

use App\Services\Attendance\AttendanceCalculatorService;

/**
 * Output metrics produced only by {@see AttendanceCalculatorService}.
 */
final readonly class AttendanceCalculationResult
{
    public function __construct(
        public int $workedMinutes,
        public int $totalLateMinutes,
        public int $totalEarlyLeaveMinutes,
        public int $overtimeMinutes,
    ) {}

    /**
     * @return array<string, int>
     */
    public function toAttendanceAttributes(): array
    {
        return [
            'worked_minutes' => $this->workedMinutes,
            'total_late_minutes' => $this->totalLateMinutes,
            'total_early_leave_minutes' => $this->totalEarlyLeaveMinutes,
            'overtime_minutes' => $this->overtimeMinutes,
        ];
    }
}

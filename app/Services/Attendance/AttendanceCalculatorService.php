<?php

namespace App\Services\Attendance;

use App\DataTransferObjects\AttendanceCalculationInput;
use App\DataTransferObjects\AttendanceCalculationResult;
use App\Enums\AttendanceStatus;
use App\Enums\ShiftType;
use App\Models\CompanySetting;
use Carbon\CarbonImmutable;

/**
 * Centralizes attendance mathematics so reporting, payroll exports, and APIs stay consistent.
 *
 * Grace rules use {@see CompanySetting}: checkout_after_minutes extends normal end before
 * overtime accrues; late tolerance can later be modeled explicitly—currently strict scheduled compare.
 */
final class AttendanceCalculatorService
{
    public function calculate(AttendanceCalculationInput $input): AttendanceCalculationResult
    {
        if ($this->shouldZeroMetrics($input)) {
            return new AttendanceCalculationResult(0, 0, 0, 0);
        }

        $checkIn = $input->checkIn;
        $checkOut = $input->checkOut;

        if ($checkIn === null || $checkOut === null) {
            return $this->handleIncompleteClocking($input);
        }

        if ($checkOut->lessThanOrEqualTo($checkIn)) {
            return new AttendanceCalculationResult(0, 0, 0, 0);
        }

        $rawWorked = $this->diffMinutes($checkIn, $checkOut);
        $deductBreak = max(0, $input->breakMinutesDeduction);
        $worked = max(0, $rawWorked - $deductBreak);

        $shift = $input->shift;
        $scheduledIn = $input->scheduledCheckIn;
        $scheduledOut = $input->scheduledCheckOut;

        if ($shift !== null && $shift->type === ShiftType::Flexible) {
            return $this->calculateFlexibleShift($input, $worked, $scheduledIn, $scheduledOut);
        }

        return $this->calculateFixedShift($input, $worked, $scheduledIn, $scheduledOut);
    }

    private function shouldZeroMetrics(AttendanceCalculationInput $input): bool
    {
        return in_array($input->status, [
            AttendanceStatus::Absent,
            AttendanceStatus::Leave,
            AttendanceStatus::Holiday,
            AttendanceStatus::Weekend,
        ], true);
    }

    private function handleIncompleteClocking(AttendanceCalculationInput $input): AttendanceCalculationResult
    {
        if ($input->status === AttendanceStatus::MissingCheckout && $input->checkIn !== null) {
            return new AttendanceCalculationResult(0, 0, 0, 0);
        }

        return new AttendanceCalculationResult(0, 0, 0, 0);
    }

    private function calculateFlexibleShift(
        AttendanceCalculationInput $input,
        int $worked,
        ?CarbonImmutable $scheduledIn,
        ?CarbonImmutable $scheduledOut,
    ): AttendanceCalculationResult {
        $shift = $input->shift;
        if ($shift === null) {
            return new AttendanceCalculationResult($worked, 0, 0, 0);
        }

        $expected = ($shift->working_hours ?? 0) * 60 + ($shift->working_minutes ?? 0);
        $overtimeEnabled = $shift->overtime_enabled;
        $checkoutGrace = (int) ($input->companySettings?->checkout_after_minutes ?? 0);

        $lateGrace = (int) ($shift->late_checkin_grace_minutes ?? 0);
        $earlyGrace = (int) ($shift->early_checkout_grace_minutes ?? 0);

        $late = 0;
        if ($scheduledIn !== null && $input->checkIn !== null && $input->checkIn->greaterThan($scheduledIn)) {
            $rawLate = $this->diffMinutes($scheduledIn, $input->checkIn);
            $late = $rawLate > $lateGrace ? $rawLate : 0;
        }

        $earlyLeave = 0;
        if ($scheduledOut !== null && $input->checkOut !== null && $input->checkOut->lessThan($scheduledOut)) {
            $rawEarly = $this->diffMinutes($input->checkOut, $scheduledOut);
            $earlyLeave = $rawEarly > $earlyGrace ? $rawEarly : 0;
        }

        $overtime = 0;
        if ($overtimeEnabled && $expected > 0 && $worked > $expected) {
            $overtime = $worked - $expected;
        } elseif ($overtimeEnabled && $scheduledOut !== null && $input->checkOut !== null) {
            $threshold = $scheduledOut->addMinutes($checkoutGrace);
            if ($input->checkOut->greaterThan($threshold)) {
                $overtime = $this->diffMinutes($threshold, $input->checkOut);
            }
        }

        return new AttendanceCalculationResult($worked, $late, $earlyLeave, max(0, $overtime));
    }

    private function calculateFixedShift(
        AttendanceCalculationInput $input,
        int $worked,
        ?CarbonImmutable $scheduledIn,
        ?CarbonImmutable $scheduledOut,
    ): AttendanceCalculationResult {
        $checkoutGrace = (int) ($input->companySettings?->checkout_after_minutes ?? 0);
        $shift = $input->shift;
        $lateGrace = (int) ($shift?->late_checkin_grace_minutes ?? 0);
        $earlyGrace = (int) ($shift?->early_checkout_grace_minutes ?? 0);

        $late = 0;
        if ($scheduledIn !== null && $input->checkIn !== null && $input->checkIn->greaterThan($scheduledIn)) {
            $rawLate = $this->diffMinutes($scheduledIn, $input->checkIn);
            $late = $rawLate > $lateGrace ? $rawLate : 0;
        }

        $earlyLeave = 0;
        if ($scheduledOut !== null && $input->checkOut !== null && $input->checkOut->lessThan($scheduledOut)) {
            $rawEarly = $this->diffMinutes($input->checkOut, $scheduledOut);
            $earlyLeave = $rawEarly > $earlyGrace ? $rawEarly : 0;
        }

        $overtime = 0;
        if (
            $shift !== null
            && $shift->overtime_enabled
            && $scheduledOut !== null
            && $input->checkOut !== null
        ) {
            $threshold = $scheduledOut->addMinutes($checkoutGrace);
            if ($input->checkOut->greaterThan($threshold)) {
                $overtime = $this->diffMinutes($threshold, $input->checkOut);
            }
        }

        return new AttendanceCalculationResult($worked, $late, $earlyLeave, max(0, $overtime));
    }

    private function diffMinutes(CarbonImmutable $start, CarbonImmutable $end): int
    {
        return max(0, (int) $start->diffInMinutes($end, false));
    }
}

<?php

namespace App\Services;

use App\Enums\AttendancePunchLogStatus;
use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\NotificationType;
use App\Enums\PunchType;
use App\Enums\ShiftType;
use App\Exceptions\EarlyCheckoutWarningException;
use App\Models\Attendance;
use App\Models\AttendancePunch;
use App\Models\AttendancePunchLog;
use App\Models\Employee;
use App\Models\EmployeeNotification;
use App\Models\Location;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;
use Throwable;

class AttendancePunchService
{
    public function __construct(private readonly HolidayService $holidayService) {}

    public function punch(
        Employee $employee,
        PunchType $type,
        ?float $latitude = null,
        ?float $longitude = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $deviceName = null,
        bool $confirmed = false,
        ?string $reason = null,
    ): AttendancePunch {
        $attemptedAt = now();

        try {
            $this->validateLocation($employee, $latitude, $longitude);

            $timezone = $this->tenantTimezone($employee);

            if ($type === PunchType::CheckIn) {
                $this->validateCheckInTiming($employee, $timezone);
            }

            $attendance = $this->findOrCreateAttendance($employee, $latitude, $longitude, $timezone);

            $this->validatePunchSequence($attendance, $type);

            if ($type === PunchType::CheckOut) {
                $this->enforceEarlyCheckOutPolicy($employee, $attendance, $confirmed, $timezone);
            }

            $punchLocation = ($latitude !== null && $longitude !== null)
                ? $this->resolveLocation($employee, $latitude, $longitude)
                : null;

            $resolvedDeviceName = $deviceName ?? $employee->device_name;

            $punch = AttendancePunch::create([
                'attendance_id' => $attendance->id,
                'type' => $type,
                'punched_at' => $attemptedAt,
                'source' => AttendanceSource::Mobile,
                'location_id' => $punchLocation?->id,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'device_name' => $resolvedDeviceName,
                'note' => $reason,
            ]);

            $this->recomputeTotals($attendance, $timezone, $employee);

            if ($type === PunchType::CheckOut && $confirmed && $reason) {
                $this->notifyEarlyCheckOut($employee, $attendance, $reason);
            }

            $this->writeLog(
                employee: $employee,
                source: AttendanceSource::Mobile,
                type: $type,
                status: AttendancePunchLogStatus::Success,
                punchId: $punch->id,
                latitude: $latitude,
                longitude: $longitude,
                ipAddress: $ipAddress,
                deviceName: $resolvedDeviceName,
                userAgent: $userAgent,
                attemptedAt: $attemptedAt,
            );

            return $punch;
        } catch (Throwable $e) {
            $this->writeLog(
                employee: $employee,
                source: AttendanceSource::Mobile,
                type: $type,
                status: AttendancePunchLogStatus::Failed,
                failureReason: $e->getMessage(),
                latitude: $latitude,
                longitude: $longitude,
                ipAddress: $ipAddress,
                deviceName: $deviceName ?? $employee->device_name,
                userAgent: $userAgent,
                attemptedAt: $attemptedAt,
            );

            throw $e;
        }
    }

    public function punchFromBiometric(
        Employee $employee,
        PunchType $type,
        Carbon $punchedAt,
        AttendanceSource $source,
        ?string $deviceName = null,
    ): AttendancePunch {
        try {
            $timezone = $this->tenantTimezone($employee);
            $attendanceDate = Carbon::instance($punchedAt)->setTimezone($timezone)->toDateString();

            $attendance = Attendance::where('employee_id', $employee->id)
                ->whereDate('attendance_date', $attendanceDate)
                ->first();

            if (! $attendance) {
                $shift = $employee->workShift;
                $isHoliday = $this->holidayService->isHoliday((int) $employee->tenant_id, Carbon::parse($attendanceDate));

                $attendance = Attendance::create([
                    'employee_id' => $employee->id,
                    'tenant_id' => $employee->tenant_id,
                    'attendance_date' => $attendanceDate,
                    'shift_id' => $shift?->id,
                    'scheduled_check_in' => $shift?->checkin_time,
                    'scheduled_check_out' => $shift?->checkout_time,
                    'attendance_source' => $source,
                    'status' => $isHoliday ? AttendanceStatus::Holiday : AttendanceStatus::Present,
                    'is_holiday' => $isHoliday,
                    'created_by' => $employee->created_by,
                ]);
            }

            $punch = AttendancePunch::create([
                'attendance_id' => $attendance->id,
                'type' => $type,
                'punched_at' => $punchedAt,
                'source' => $source,
                'device_name' => $deviceName,
            ]);

            $this->recomputeTotals($attendance, $timezone);

            $this->writeLog(
                employee: $employee,
                source: $source,
                type: $type,
                status: AttendancePunchLogStatus::Success,
                punchId: $punch->id,
                deviceName: $deviceName,
                attemptedAt: $punchedAt,
            );

            return $punch;
        } catch (Throwable $e) {
            $this->writeLog(
                employee: $employee,
                source: $source,
                type: $type,
                status: AttendancePunchLogStatus::Failed,
                failureReason: $e->getMessage(),
                deviceName: $deviceName,
                attemptedAt: $punchedAt,
            );

            throw $e;
        }
    }

    private function tenantTimezone(Employee $employee): string
    {
        $employee->loadMissing('tenant.settings');

        return $employee->tenant->settings?->timezone ?? 'UTC';
    }

    /**
     * @return array{is_within_location: bool, location_name: ?string, message: string}
     */
    public function detectLocation(Employee $employee, float $latitude, float $longitude): array
    {
        if ($employee->allow_remote_checkin) {
            return [
                'is_within_location' => true,
                'location_name' => $employee->location->name ?? 'N/A',
                'message' => 'Remote check-in is enabled for your account.',
            ];
        }

        if ($employee->allow_any_location_checkin) {
            $match = Location::where('tenant_id', $employee->tenant_id)
                ->get()
                ->first(fn (Location $loc) => $this->isWithinBounds($latitude, $longitude, $loc->coordinates));

            if ($match) {
                return [
                    'is_within_location' => true,
                    'location_name' => $match->name,
                    'message' => "You are within {$match->name}.",
                ];
            }

            return [
                'is_within_location' => false,
                'location_name' => null,
                'message' => 'You are not within any company location.',
            ];
        }

        $location = $employee->location;

        if (! $location) {
            return [
                'is_within_location' => false,
                'location_name' => null,
                'message' => 'No location is assigned to your account.',
            ];
        }

        if ($this->isWithinBounds($latitude, $longitude, $location->coordinates)) {
            return [
                'is_within_location' => true,
                'location_name' => $location->name,
                'message' => "You are within {$location->name}.",
            ];
        }

        return [
            'is_within_location' => false,
            'location_name' => $location->name,
            'message' => 'You are not within the registered location.',
        ];
    }

    private function validateLocation(
        Employee $employee,
        ?float $latitude,
        ?float $longitude,
    ): void {
        if ($employee->allow_remote_checkin) {
            return;
        }

        if ($latitude === null || $longitude === null) {
            throw ValidationException::withMessages([
                'location' => ['Location is required to punch in/out.'],
            ]);
        }

        if ($employee->allow_any_location_checkin) {
            $withinAny = Location::where('tenant_id', $employee->tenant_id)
                ->get()
                ->contains(fn (Location $loc) => $this->isWithinBounds($latitude, $longitude, $loc->coordinates));

            if (! $withinAny) {
                throw ValidationException::withMessages([
                    'location' => ['You are not within any company location.'],
                ]);
            }

            return;
        }

        $location = $employee->location;

        if (! $location) {
            throw ValidationException::withMessages([
                'location' => ['No location is assigned to your account.'],
            ]);
        }

        if (! $this->isWithinBounds($latitude, $longitude, $location->coordinates)) {
            throw ValidationException::withMessages([
                'location' => ['You are not within your assigned location.'],
            ]);
        }
    }

    private function isWithinBounds(float $lat, float $lng, array $coords): bool
    {
        return $lat >= $coords['south']
            && $lat <= $coords['north']
            && $lng >= $coords['west']
            && $lng <= $coords['east'];
    }

    private function findOrCreateAttendance(Employee $employee, ?float $latitude, ?float $longitude, string $timezone): Attendance
    {
        $attendanceDate = $this->resolveAttendanceDate($employee, $timezone);

        $attendance = Attendance::where('employee_id', $employee->id)
            ->whereDate('attendance_date', $attendanceDate)
            ->first();

        if ($attendance) {
            return $attendance;
        }

        $shift = $employee->workShift;

        $location = ($latitude !== null && $longitude !== null)
            ? $this->resolveLocation($employee, $latitude, $longitude)
            : null;

        $isHoliday = $this->holidayService->isHoliday((int) $employee->tenant_id, Carbon::parse($attendanceDate));

        return Attendance::create([
            'employee_id' => $employee->id,
            'tenant_id' => $employee->tenant_id,
            'attendance_date' => $attendanceDate,
            'shift_id' => $shift?->id,
            'scheduled_check_in' => $shift?->checkin_time,
            'scheduled_check_out' => $shift?->checkout_time,
            'attendance_source' => AttendanceSource::Mobile,
            'status' => $isHoliday ? AttendanceStatus::Holiday : AttendanceStatus::Present,
            'is_holiday' => $isHoliday,
            'created_by' => $employee->created_by,
            'location_id' => $location?->id,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
    }

    /**
     * For overnight shifts a punch arriving before the shift's checkout time belongs to the
     * previous calendar day's attendance (the shift start date), not today.
     */
    private function resolveAttendanceDate(Employee $employee, string $timezone): Carbon
    {
        $localNow = now($timezone);
        $shift = $employee->workShift;

        if ($shift && $shift->isOvernight() && $shift->checkout_time) {
            $shiftEnd = Carbon::createFromTimeString($shift->checkout_time);
            $currentTime = Carbon::createFromTimeString($localNow->format('H:i:s'));

            if ($currentTime->lt($shiftEnd)) {
                return Carbon::today($timezone)->subDay();
            }
        }

        return Carbon::today($timezone);
    }

    private function resolveLocation(Employee $employee, float $latitude, float $longitude): ?Location
    {
        if ($employee->allow_any_location_checkin) {
            return Location::where('tenant_id', $employee->tenant_id)
                ->get()
                ->first(fn (Location $loc) => $this->isWithinBounds($latitude, $longitude, $loc->coordinates));
        }

        $location = $employee->location;

        if ($location && $this->isWithinBounds($latitude, $longitude, $location->coordinates)) {
            return $location;
        }

        return null;
    }

    private function validatePunchSequence(Attendance $attendance, PunchType $type): void
    {
        $lastPunch = $attendance->punches()->latest('id')->first();
        $lastType = $lastPunch?->type;

        if ($type === PunchType::CheckIn) {
            // Cannot check in while already checked in
            if ($lastType === PunchType::CheckIn) {
                throw ValidationException::withMessages([
                    'type' => ['You are already checked in.'],
                ]);
            }

            // Single-session shifts: block a second check-in after a completed session
            $shift = $attendance->shift;
            $hasCompletedSession = $attendance->punches()
                ->where('type', PunchType::CheckOut->value)
                ->exists();

            if ($hasCompletedSession && $shift && ! $shift->allow_multiple_sessions) {
                throw ValidationException::withMessages([
                    'type' => ['Your attendance for today is already complete.'],
                ]);
            }
        }

        if ($type === PunchType::CheckOut) {
            if (! $lastType) {
                throw ValidationException::withMessages([
                    'type' => ['You must check in before checking out.'],
                ]);
            }

            if ($lastType === PunchType::CheckOut) {
                $shift = $attendance->shift;
                $allowsMultiple = $shift?->allow_multiple_sessions ?? false;

                if (! $allowsMultiple) {
                    throw ValidationException::withMessages([
                        'type' => ['Your attendance for today is already complete.'],
                    ]);
                }

                throw ValidationException::withMessages([
                    'type' => ['You must check in before checking out.'],
                ]);
            }
        }
    }

    private function validateCheckInTiming(Employee $employee, string $timezone): void
    {
        $shift = $employee->workShift;

        if (! $shift || $shift->isOvernight()) {
            // Overnight shifts cross midnight — early check-in logic doesn't cleanly apply.
            return;
        }

        $localNow = now($timezone);
        $earliestTime = $shift->limit_checkin_from;

        if (! $earliestTime && $shift->type === ShiftType::Fixed) {
            $earliestTime = $shift->checkin_time;
        }

        if (! $earliestTime) {
            return;
        }

        $earliest = Carbon::today($timezone)->setTimeFromTimeString($earliestTime);

        if ($localNow->lt($earliest)) {
            throw ValidationException::withMessages([
                'type' => [
                    'Check-in is not allowed before '.$earliest->format('h:i A').'. Your shift starts at '.$earliest->format('h:i A').'.',
                ],
            ]);
        }
    }

    private function enforceEarlyCheckOutPolicy(
        Employee $employee,
        Attendance $attendance,
        bool $confirmed,
        string $timezone,
    ): void {
        $shift = $employee->workShift;

        if (! $shift) {
            return;
        }

        // Feature is opt-in: null means disabled. 0 means strict (any early checkout triggers warning).
        if ($shift->early_checkout_grace_minutes === null) {
            return;
        }

        $graceMinutes = (int) $shift->early_checkout_grace_minutes;

        $scheduledCheckOut = $attendance->scheduled_check_out ?? $shift->checkout_time;

        if (! $scheduledCheckOut) {
            return;
        }

        $localNow = now($timezone);

        // Build the expected checkout datetime from the attendance date so overnight
        // shifts (where checkout falls on the next calendar day) are handled correctly.
        $checkOutCarbon = Carbon::parse($attendance->attendance_date, $timezone)
            ->setTimeFromTimeString($scheduledCheckOut);

        if ($shift->isOvernight()) {
            $checkOutCarbon->addDay();
        }

        $earliestAllowed = $checkOutCarbon->clone()->subMinutes($graceMinutes);

        if ($localNow->lt($earliestAllowed)) {
            $minutesEarly = (int) $localNow->diffInMinutes($checkOutCarbon, false);

            if (! $confirmed) {
                throw new EarlyCheckoutWarningException(
                    'You are leaving '.$this->formatDuration($minutesEarly).' early. Please confirm with a reason.',
                    $minutesEarly,
                );
            }
        }
    }

    private function writeLog(
        Employee $employee,
        AttendanceSource $source,
        PunchType $type,
        AttendancePunchLogStatus $status,
        ?int $punchId = null,
        ?string $failureReason = null,
        ?float $latitude = null,
        ?float $longitude = null,
        ?string $ipAddress = null,
        ?string $deviceName = null,
        ?string $userAgent = null,
        ?CarbonInterface $attemptedAt = null,
    ): void {
        AttendancePunchLog::create([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'source' => $source,
            'punch_type' => $type,
            'status' => $status,
            'failure_reason' => $failureReason,
            'attendance_punch_id' => $punchId,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'ip_address' => $ipAddress,
            'device_name' => $deviceName,
            'user_agent' => $userAgent,
            'attempted_at' => $attemptedAt ?? now(),
        ]);
    }

    private function formatDuration(int $minutes): string
    {
        if ($minutes < 60) {
            return "{$minutes} minutes";
        }

        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;
        $hourLabel = $hours === 1 ? '1 hour' : "{$hours} hours";

        return $remaining === 0 ? $hourLabel : "{$hourLabel} and {$remaining} minutes";
    }

    private function recomputeTotals(Attendance $attendance, string $timezone, ?Employee $employee = null): void
    {
        $punches = $attendance->punches()->orderBy('id')->get();

        $workedMinutes = 0;
        $firstCheckIn = null;
        $lastCheckOut = null;
        $pendingCheckIn = null;
        $wasAlreadyLate = $attendance->total_late_minutes > 0;

        foreach ($punches as $punch) {
            if ($punch->type === PunchType::CheckIn) {
                $pendingCheckIn = $punch->punched_at;
                $firstCheckIn ??= $punch->punched_at;
            } elseif ($punch->type === PunchType::CheckOut && $pendingCheckIn) {
                $workedMinutes += (int) $pendingCheckIn->diffInMinutes($punch->punched_at);
                $lastCheckOut = $punch->punched_at;
                $pendingCheckIn = null;
            }
        }

        $updates = ['worked_minutes' => $workedMinutes];
        $lateMinutes = 0;

        if ($firstCheckIn) {
            $firstCheckInLocal = $firstCheckIn->clone()->setTimezone($timezone);
            $updates['check_in_time'] = $firstCheckInLocal->format('H:i:s');

            if ($attendance->scheduled_check_in) {
                $scheduled = Carbon::parse($attendance->attendance_date, $timezone)
                    ->setTimeFromTimeString($attendance->scheduled_check_in);
                $lateMinutes = max(0, (int) $scheduled->diffInMinutes($firstCheckInLocal, false));
                $updates['total_late_minutes'] = $lateMinutes;
                $updates['status'] = $lateMinutes > 0
                    ? AttendanceStatus::Late
                    : AttendanceStatus::Present;
            }
        }

        if ($lastCheckOut) {
            $updates['check_out_time'] = $lastCheckOut->clone()->setTimezone($timezone)->format('H:i:s');
        }

        $attendance->update($updates);

        if ($employee && $lateMinutes > 0 && ! $wasAlreadyLate) {
            $this->notifyLateCheckIn($employee, $attendance, $lateMinutes);
        }
    }

    private function notifyLateCheckIn(Employee $employee, Attendance $attendance, int $lateMinutes): void
    {
        EmployeeNotification::create([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'type' => NotificationType::LateCheckIn,
            'title' => 'Late Check-in',
            'body' => 'You checked in '.$this->formatDuration($lateMinutes).' late on '.$attendance->attendance_date->format('M d, Y').'.',
            'data' => [
                'attendance_id' => $attendance->id,
                'minutes_late' => $lateMinutes,
                'date' => $attendance->attendance_date->toDateString(),
            ],
        ]);
    }

    private function notifyEarlyCheckOut(Employee $employee, Attendance $attendance, string $reason): void
    {
        $shift = $employee->workShift;
        $scheduledCheckOut = $attendance->scheduled_check_out ?? $shift?->checkout_time;
        $timezone = $this->tenantTimezone($employee);
        $localNow = now($timezone);

        $minutesEarly = 0;
        if ($scheduledCheckOut) {
            $checkOutCarbon = Carbon::parse($attendance->attendance_date, $timezone)
                ->setTimeFromTimeString($scheduledCheckOut);
            if ($shift?->isOvernight()) {
                $checkOutCarbon->addDay();
            }
            $minutesEarly = max(0, (int) $localNow->diffInMinutes($checkOutCarbon, false));
        }

        EmployeeNotification::create([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'type' => NotificationType::EarlyCheckOut,
            'title' => 'Early Check-out',
            'body' => 'You left '.$this->formatDuration($minutesEarly).' early on '.$attendance->attendance_date->format('M d, Y').'. Reason: '.$reason,
            'data' => [
                'attendance_id' => $attendance->id,
                'minutes_early' => $minutesEarly,
                'reason' => $reason,
                'date' => $attendance->attendance_date->toDateString(),
            ],
        ]);
    }
}

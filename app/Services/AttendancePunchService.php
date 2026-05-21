<?php

namespace App\Services;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\PunchType;
use App\Models\Attendance;
use App\Models\AttendancePunch;
use App\Models\Employee;
use App\Models\Location;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class AttendancePunchService
{
    public function punch(
        Employee $employee,
        PunchType $type,
        ?float $latitude = null,
        ?float $longitude = null,
    ): AttendancePunch {
        $this->validateLocation($employee, $latitude, $longitude);

        $timezone = $this->tenantTimezone($employee);
        $attendance = $this->findOrCreateAttendance($employee, $latitude, $longitude, $timezone);

        $this->validatePunchSequence($attendance, $type);

        $punchLocation = ($latitude !== null && $longitude !== null)
            ? $this->resolveLocation($employee, $latitude, $longitude)
            : null;

        $punch = AttendancePunch::create([
            'attendance_id' => $attendance->id,
            'type' => $type,
            'punched_at' => now(),
            'source' => AttendanceSource::Mobile,
            'location_id' => $punchLocation?->id,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'device_name' => $employee->device_name,
        ]);

        $this->recomputeTotals($attendance, $timezone);

        return $punch;
    }

    public function punchFromBiometric(
        Employee $employee,
        PunchType $type,
        Carbon $punchedAt,
        AttendanceSource $source,
        ?string $deviceName = null,
    ): AttendancePunch {
        $timezone = $this->tenantTimezone($employee);
        $attendanceDate = Carbon::instance($punchedAt)->setTimezone($timezone)->toDateString();

        $attendance = Attendance::where('employee_id', $employee->id)
            ->whereDate('attendance_date', $attendanceDate)
            ->first();

        if (! $attendance) {
            $shift = $employee->workShift;

            $attendance = Attendance::create([
                'employee_id' => $employee->id,
                'attendance_date' => $attendanceDate,
                'shift_id' => $shift?->id,
                'scheduled_check_in' => $shift?->checkin_time,
                'scheduled_check_out' => $shift?->checkout_time,
                'attendance_source' => $source,
                'status' => AttendanceStatus::Present,
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

        return $punch;
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

        return Attendance::create([
            'employee_id' => $employee->id,
            'attendance_date' => $attendanceDate,
            'shift_id' => $shift?->id,
            'scheduled_check_in' => $shift?->checkin_time,
            'scheduled_check_out' => $shift?->checkout_time,
            'attendance_source' => AttendanceSource::Mobile,
            'status' => AttendanceStatus::Present,
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
                    'type' => ['Your shift does not allow multiple sessions per day.'],
                ]);
            }
        }

        if ($type === PunchType::CheckOut) {
            // Cannot check out without checking in first
            if (! $lastType || $lastType === PunchType::CheckOut) {
                throw ValidationException::withMessages([
                    'type' => ['You must check in before checking out.'],
                ]);
            }
        }
    }

    private function recomputeTotals(Attendance $attendance, string $timezone): void
    {
        $punches = $attendance->punches()->orderBy('id')->get();

        $workedMinutes = 0;
        $firstCheckIn = null;
        $lastCheckOut = null;
        $pendingCheckIn = null;

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
    }
}

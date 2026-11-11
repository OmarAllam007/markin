<?php

namespace App\Services;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\LeaveRequestStatus;
use App\Enums\NotificationType;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeNotification;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class LeaveService
{
    public function __construct(private readonly HolidayService $holidayService) {}

    /**
     * The dates a leave actually costs: the employee's weekends and company
     * holidays don't burn leave days.
     *
     * @return list<string> Y-m-d
     */
    public function workingDays(Employee $employee, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $weekends = $employee->workShift?->weekends ?? [];
        $dates = [];

        for ($date = $start->startOfDay(); $date->lessThanOrEqualTo($end->startOfDay()); $date = $date->addDay()) {
            if (in_array(strtolower($date->englishDayOfWeek), $weekends, true)) {
                continue;
            }

            if ($this->holidayService->isHoliday($employee->tenant_id, $date)) {
                continue;
            }

            $dates[] = $date->toDateString();
        }

        return $dates;
    }

    /** @return array{entitled: int|null, used: int, remaining: int|null} */
    public function balance(Employee $employee, LeaveType $type, int $year): array
    {
        $used = (int) LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $type->id)
            ->where('status', LeaveRequestStatus::Approved)
            ->whereYear('start_date', $year)
            ->sum('days');

        return [
            'entitled' => $type->annual_days,
            'used' => $used,
            'remaining' => $type->annual_days === null ? null : max(0, $type->annual_days - $used),
        ];
    }

    /** A pending request blocks the dates too — two requests for one day is always a mistake. */
    public function overlaps(Employee $employee, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        return LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', [LeaveRequestStatus::Pending, LeaveRequestStatus::Approved])
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->exists();
    }

    public function approve(LeaveRequest $request, User $reviewer): int
    {
        return DB::transaction(function () use ($request, $reviewer) {
            $request->update([
                'status' => LeaveRequestStatus::Approved,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            $days = $this->applyToAttendances($request);

            $this->notify($request, NotificationType::LeaveApproved);

            return $days;
        });
    }

    public function reject(LeaveRequest $request, User $reviewer, ?string $note = null): int
    {
        return DB::transaction(function () use ($request, $reviewer, $note) {
            $reverted = $request->status === LeaveRequestStatus::Approved
                ? $this->revertFromAttendances($request)
                : 0;

            $request->update([
                'status' => LeaveRequestStatus::Rejected,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);

            $this->notify($request, NotificationType::LeaveRejected);

            return $reverted;
        });
    }

    /**
     * Writing the row is all it takes — the model pushes it to the phone.
     */
    private function notify(LeaveRequest $request, NotificationType $type): void
    {
        $request->loadMissing('leaveType:id,name');
        $approved = $type === NotificationType::LeaveApproved;

        EmployeeNotification::create([
            'tenant_id' => $request->tenant_id,
            'employee_id' => $request->employee_id,
            'type' => $type,
            'title' => $approved ? 'Leave approved' : 'Leave rejected',
            'body' => sprintf(
                '%s from %s to %s was %s.%s',
                $request->leaveType?->name ?? 'Leave',
                $request->start_date->toDateString(),
                $request->end_date->toDateString(),
                $approved ? 'approved' : 'rejected',
                $request->review_note ? ' '.$request->review_note : ''
            ),
            'data' => ['leave_request_id' => $request->id],
        ]);
    }

    /**
     * Writes a Leave attendance row per working day, which is also what keeps
     * attendance:mark-absences from marking the employee absent.
     */
    public function applyToAttendances(LeaveRequest $request): int
    {
        $employee = $request->employee;
        $dates = $this->workingDays(
            $employee,
            CarbonImmutable::parse($request->start_date),
            CarbonImmutable::parse($request->end_date),
        );

        $applied = 0;

        foreach ($dates as $date) {
            $existing = Attendance::query()
                ->where('employee_id', $employee->id)
                ->whereDate('attendance_date', $date)
                ->first();

            // They actually punched that day — their real attendance wins.
            if ($existing && $existing->check_in_time) {
                continue;
            }

            $payload = [
                'status' => AttendanceStatus::Leave,
                'comments' => "Leave: {$request->leaveType->name} (request #{$request->id})",
            ];

            if ($existing) {
                $existing->update($payload);
            } else {
                Attendance::create($payload + [
                    'tenant_id' => $employee->tenant_id,
                    'employee_id' => $employee->id,
                    'attendance_date' => $date,
                    'shift_id' => $employee->work_shift_id,
                    'scheduled_check_in' => $employee->workShift?->checkin_time,
                    'scheduled_check_out' => $employee->workShift?->checkout_time,
                    'attendance_source' => AttendanceSource::Manual,
                    'created_by' => $request->reviewed_by,
                ]);
            }

            $applied++;
        }

        return $applied;
    }

    /** Undo of applyToAttendances: only rows this request wrote, and only if still untouched. */
    public function revertFromAttendances(LeaveRequest $request): int
    {
        return Attendance::query()
            ->where('employee_id', $request->employee_id)
            ->where('status', AttendanceStatus::Leave)
            ->whereNull('check_in_time')
            ->where('comments', 'like', "%(request #{$request->id})")
            ->delete();
    }
}

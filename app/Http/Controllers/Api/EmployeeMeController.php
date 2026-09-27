<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeMeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();
        $employee->load(['department:id,name', 'location:id,name', 'workShift:id,name,checkin_time,checkout_time,weekends', 'tenant.modules']);

        $today = CarbonImmutable::today();
        $dayName = strtolower($today->englishDayOfWeek);
        $shift = $employee->workShift;

        $attendance = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', $today)
            ->first();

        $punchStatus = $this->resolvePunchStatus($attendance, $shift, $dayName);

        return ApiResponse::success('Employee profile retrieved.', [
            'enabled_modules' => $employee->tenant
                ->modules
                ->where('is_enabled', true)
                ->pluck('module')
                ->map(fn ($m) => $m->value)
                ->values(),
            'employee' => [
                'id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'english_name' => $employee->english_name,
                'arabic_name' => $employee->arabic_name,
                'job_title_en' => $employee->job_title_en,
                'job_title_ar' => $employee->job_title_ar,
                'department' => $employee->department?->only(['id', 'name']),
                'location' => $employee->location?->only(['id', 'name']),
            ],
            'shift' => $shift ? [
                'id' => $shift->id,
                'name' => $shift->name,
                'scheduled_check_in' => $shift->checkin_time,
                'scheduled_check_out' => $shift->checkout_time,
            ] : null,
            'today' => [
                'date' => $today->toDateString(),
                'punch_status' => $punchStatus,
                'check_in_time' => $attendance?->check_in_time,
                'check_out_time' => $attendance?->check_out_time,
                'status' => $attendance?->status?->value,
                'status_label' => $attendance?->status?->label(),
                'worked_minutes' => $attendance?->worked_minutes,
                'is_holiday' => (bool) $attendance?->is_holiday,
                'is_weekend' => (bool) $attendance?->is_weekend,
            ],
        ]);
    }

    private function resolvePunchStatus(?Attendance $attendance, mixed $shift, string $dayName): string
    {
        if (! $attendance) {
            $weekends = $shift?->weekends ?? [];
            if (in_array($dayName, $weekends, true)) {
                return 'day_off';
            }

            if (! $shift) {
                return 'no_shift';
            }

            return 'not_checked_in';
        }

        if ($attendance->check_in_time && $attendance->check_out_time) {
            return 'checked_out';
        }

        if ($attendance->check_in_time) {
            return 'checked_in';
        }

        return 'not_checked_in';
    }
}

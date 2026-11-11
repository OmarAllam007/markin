<?php

namespace App\Http\Controllers\Api;

use App\Enums\AttendanceStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AttendanceReportRequest;
use App\Models\Attendance;
use App\Models\Employee;
use App\Services\HolidayService;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;

class AttendanceReportController extends Controller
{
    public function __construct(private readonly HolidayService $holidayService) {}

    public function index(AttendanceReportRequest $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        [$from, $to] = $this->dateRange($request);

        $attendances = Attendance::query()
            ->where('employee_id', $employee->id)
            ->with(['shift:id,name,type', 'location:id,name'])
            ->whereDate('attendance_date', '>=', $from->toDateString())
            ->whereDate('attendance_date', '<=', $to->toDateString())
            ->orderByDesc('attendance_date')
            ->paginate(31)
            ->withQueryString();

        return ApiResponse::success('Attendance report retrieved.', [
            'summary' => $this->summary($employee, $from, $to),
            'records' => $attendances,
        ]);
    }

    public function missing(AttendanceReportRequest $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        [$from, $to] = $this->dateRange($request);

        $shift = $employee->workShift;
        $weekends = $shift?->weekends ?? [];

        $existingRecords = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', '>=', $from->toDateString())
            ->whereDate('attendance_date', '<=', $to->toDateString())
            ->get()
            ->keyBy(fn (Attendance $a) => $a->attendance_date->toDateString());

        $missingRecords = [];

        foreach (CarbonPeriod::create($from, $to) as $date) {
            $dateString = $date->toDateString();
            $dayName = strtolower($date->englishDayOfWeek);

            if (in_array($dayName, $weekends, true)) {
                continue;
            }

            if ($this->holidayService->isHoliday((int) $employee->tenant_id, $date)) {
                continue;
            }

            if (! isset($existingRecords[$dateString])) {
                $missingRecords[] = [
                    'date' => $dateString,
                    'type' => 'absent',
                    'message' => 'No attendance recorded.',
                ];

                continue;
            }

            $record = $existingRecords[$dateString];

            if ($record->check_in_time && ! $record->check_out_time) {
                $missingRecords[] = [
                    'date' => $dateString,
                    'type' => 'missing_checkout',
                    'message' => 'Checked in but never checked out.',
                    'check_in_time' => $record->check_in_time,
                ];
            }
        }

        return ApiResponse::success('Missing attendance report retrieved.', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'total_missing' => count($missingRecords),
            'records' => $missingRecords,
        ]);
    }

    /**
     * Totals for the whole range, not just the current page.
     *
     * @return array<string, mixed>
     */
    private function summary(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $row = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', '>=', $from->toDateString())
            ->whereDate('attendance_date', '<=', $to->toDateString())
            ->selectRaw('count(*) as days')
            ->selectRaw('coalesce(sum(worked_minutes), 0) as worked_minutes')
            ->selectRaw('coalesce(sum(total_late_minutes), 0) as late_minutes')
            ->selectRaw('coalesce(sum(overtime_minutes), 0) as overtime_minutes')
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as present_days', [AttendanceStatus::Present->value])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as late_days', [AttendanceStatus::Late->value])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as absent_days', [AttendanceStatus::Absent->value])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as leave_days', [AttendanceStatus::Leave->value])
            ->first();

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'days' => (int) $row->days,
            'present_days' => (int) $row->present_days,
            'late_days' => (int) $row->late_days,
            'absent_days' => (int) $row->absent_days,
            'leave_days' => (int) $row->leave_days,
            'worked_minutes' => (int) $row->worked_minutes,
            'late_minutes' => (int) $row->late_minutes,
            'overtime_minutes' => (int) $row->overtime_minutes,
        ];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function dateRange(AttendanceReportRequest $request): array
    {
        $from = $request->date_from
            ? CarbonImmutable::parse($request->date_from)
            : CarbonImmutable::now()->startOfMonth();

        $to = $request->date_to
            ? CarbonImmutable::parse($request->date_to)
            : CarbonImmutable::now()->endOfMonth();

        return [$from, $to];
    }
}

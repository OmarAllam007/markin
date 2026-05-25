<?php

namespace App\Http\Controllers\Api;

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

        return ApiResponse::success('Attendance report retrieved.', $attendances);
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

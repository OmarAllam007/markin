<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $tenantId = $request->user()->current_tenant_id;
        $today = CarbonImmutable::today();
        $range = (int) ($request->query('range', 7));
        $range = in_array($range, [7, 30]) ? $range : 7;

        if (! $tenantId) {
            return Inertia::render('Home', $this->emptyPayload($range));
        }

        $startDate = $today->subDays($range - 1);

        $kpis = $this->buildKpis($tenantId, $today);
        $trendRows = $this->fetchTrendRows($tenantId, $startDate, $today);

        return Inertia::render('Home', [
            'range' => $range,
            'kpis' => $kpis,
            'attendanceOverview' => $this->buildOverviewSeries($trendRows, $startDate, $today),
            'lateArrivalsTrend' => $this->buildLateTrend($trendRows, $startDate, $today),
            'departmentChart' => $this->buildDepartmentChart($tenantId, $today),
            'distributionChart' => $this->buildDistributionChart($tenantId, $startDate, $today),
            'todaysLateEmployees' => $this->todaysLateEmployees($tenantId, $today),
            'heatmap' => $this->buildHeatmap($tenantId, $startDate, $today),
        ]);
    }

    /** @return array<string, int> */
    private function buildKpis(int $tenantId, CarbonImmutable $today): array
    {
        $rows = Attendance::query()
            ->where('tenant_id', $tenantId)
            ->whereDate('attendance_date', $today)
            ->selectRaw("
                SUM(CASE WHEN status IN ('present','late','remote','business_trip','half_day') THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) as on_leave,
                SUM(CASE WHEN overtime_minutes > 0 THEN 1 ELSE 0 END) as overtime
            ")
            ->first();

        return [
            'present' => (int) ($rows->present ?? 0),
            'late' => (int) ($rows->late ?? 0),
            'absent' => (int) ($rows->absent ?? 0),
            'on_leave' => (int) ($rows->on_leave ?? 0),
            'overtime' => (int) ($rows->overtime ?? 0),
        ];
    }

    private function fetchTrendRows(int $tenantId, CarbonImmutable $startDate, CarbonImmutable $today): Collection
    {
        return Attendance::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('attendance_date', [$startDate->toDateString(), $today->toDateString()])
            ->selectRaw("
                attendance_date,
                SUM(CASE WHEN status IN ('present','late','remote','business_trip','half_day') THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late
            ")
            ->groupBy('attendance_date')
            ->orderBy('attendance_date')
            ->get()
            ->keyBy(fn ($row) => $row->getRawOriginal('attendance_date'));
    }

    /**
     * @return array{categories: string[], series: array<int, array{name: string, data: int[]}>}
     */
    private function buildOverviewSeries(Collection $rows, CarbonImmutable $startDate, CarbonImmutable $today): array
    {
        $categories = [];
        $present = [];
        $absent = [];
        $late = [];

        $cursor = $startDate;
        while (! $cursor->greaterThan($today)) {
            $key = $cursor->toDateString();
            $categories[] = $cursor->format('M d');
            $row = $rows->get($key);
            $present[] = (int) ($row->present ?? 0);
            $absent[] = (int) ($row->absent ?? 0);
            $late[] = (int) ($row->late ?? 0);
            $cursor = $cursor->addDay();
        }

        return [
            'categories' => $categories,
            'series' => [
                ['name' => 'Present', 'data' => $present],
                ['name' => 'Absent', 'data' => $absent],
                ['name' => 'Late', 'data' => $late],
            ],
        ];
    }

    /**
     * @return array{categories: string[], series: array<int, array{name: string, data: int[]}>}
     */
    private function buildLateTrend(Collection $rows, CarbonImmutable $startDate, CarbonImmutable $today): array
    {
        $categories = [];
        $data = [];

        $cursor = $startDate;
        while (! $cursor->greaterThan($today)) {
            $key = $cursor->toDateString();
            $categories[] = $cursor->format('M d');
            $data[] = (int) ($rows->get($key)?->late ?? 0);
            $cursor = $cursor->addDay();
        }

        return [
            'categories' => $categories,
            'series' => [['name' => 'Late Arrivals', 'data' => $data]],
        ];
    }

    /**
     * @return array{categories: string[], series: array<int, array{name: string, data: int[]}>}
     */
    private function buildDepartmentChart(int $tenantId, CarbonImmutable $today): array
    {
        $rows = Attendance::query()
            ->join('employees', 'employees.id', '=', 'attendances.employee_id')
            ->join('departments', 'departments.id', '=', 'employees.department_id')
            ->where('attendances.tenant_id', $tenantId)
            ->whereDate('attendances.attendance_date', $today)
            ->selectRaw("
                departments.name as dept_name,
                SUM(CASE WHEN attendances.status IN ('present','late','remote','business_trip','half_day') THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN attendances.status = 'absent' THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN attendances.status = 'late' THEN 1 ELSE 0 END) as late
            ")
            ->groupBy('departments.name')
            ->get();

        return [
            'categories' => $rows->pluck('dept_name')->values()->all(),
            'series' => [
                ['name' => 'Present', 'data' => $rows->pluck('present')->map(fn ($v) => (int) $v)->values()->all()],
                ['name' => 'Absent', 'data' => $rows->pluck('absent')->map(fn ($v) => (int) $v)->values()->all()],
                ['name' => 'Late', 'data' => $rows->pluck('late')->map(fn ($v) => (int) $v)->values()->all()],
            ],
        ];
    }

    /**
     * @return array{labels: string[], series: int[]}
     */
    private function buildDistributionChart(int $tenantId, CarbonImmutable $startDate, CarbonImmutable $today): array
    {
        $rows = Attendance::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('attendance_date', [$startDate->toDateString(), $today->toDateString()])
            ->whereNotIn('status', [AttendanceStatus::Weekend->value, AttendanceStatus::Holiday->value])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->get();

        $labels = $rows->pluck('status')->map(fn ($s) => $s instanceof AttendanceStatus ? $s->label() : AttendanceStatus::from($s)->label())->values()->all();
        $series = $rows->pluck('total')->map(fn ($v) => (int) $v)->values()->all();

        return ['labels' => $labels, 'series' => $series];
    }

    /**
     * @return array<int, array{employee_number: string|null, name: string, late_minutes: int}>
     */
    private function todaysLateEmployees(int $tenantId, CarbonImmutable $today): array
    {
        return Attendance::query()
            ->where('attendances.tenant_id', $tenantId)
            ->whereDate('attendance_date', $today)
            ->where('status', AttendanceStatus::Late->value)
            ->with('employee:id,english_name,employee_number')
            ->orderByDesc('total_late_minutes')
            ->get()
            ->map(fn ($a) => [
                'employee_number' => $a->employee->employee_number,
                'name' => $a->employee->english_name,
                'late_minutes' => (int) $a->total_late_minutes,
            ])
            ->all();
    }

    /**
     * Heatmap: one row per employee, columns = dates, value = status label.
     *
     * @return array{employees: string[], dates: string[], data: array<int, array{name: string, data: array<int, array{x: string, y: int}>}>}
     */
    private function buildHeatmap(int $tenantId, CarbonImmutable $startDate, CarbonImmutable $today): array
    {
        $rows = Attendance::query()
            ->where('attendances.tenant_id', $tenantId)
            ->whereBetween('attendance_date', [$startDate->toDateString(), $today->toDateString()])
            ->with('employee:id,english_name')
            ->select('attendance_date', 'employee_id', 'status')
            ->get();

        $dates = [];
        $cursor = $startDate;
        while (! $cursor->greaterThan($today)) {
            $dates[] = $cursor->format('M d');
            $cursor = $cursor->addDay();
        }

        $dateKeys = [];
        $cursor = $startDate;
        while (! $cursor->greaterThan($today)) {
            $dateKeys[] = $cursor->toDateString();
            $cursor = $cursor->addDay();
        }

        $byEmployee = $rows->groupBy('employee_id');

        $statusValue = function (AttendanceStatus|string $status): int {
            $value = $status instanceof AttendanceStatus ? $status->value : $status;

            return match ($value) {
                'absent' => 0,
                'late' => 1,
                default => 2,
            };
        };

        $series = $byEmployee->map(function (Collection $records) use ($dateKeys, $statusValue): array {
            $byDate = $records->keyBy(fn ($r) => $r->getRawOriginal('attendance_date'));
            $name = $records->first()->employee->english_name ?? 'Unknown';
            $data = array_map(function (string $date) use ($byDate, $statusValue): array {
                $record = $byDate->get($date);

                return [
                    'x' => CarbonImmutable::parse($date)->format('M d'),
                    'y' => $record ? $statusValue($record->status) : -1,
                ];
            }, $dateKeys);

            return ['name' => $name, 'data' => $data];
        })->values()->take(30)->all();

        return [
            'dates' => $dates,
            'series' => $series,
        ];
    }

    /** @return array<string, mixed> */
    private function emptyPayload(int $range): array
    {
        return [
            'range' => $range,
            'kpis' => ['present' => 0, 'late' => 0, 'absent' => 0, 'on_leave' => 0, 'overtime' => 0],
            'attendanceOverview' => ['categories' => [], 'series' => []],
            'lateArrivalsTrend' => ['categories' => [], 'series' => []],
            'departmentChart' => ['categories' => [], 'series' => []],
            'distributionChart' => ['labels' => [], 'series' => []],
            'todaysLateEmployees' => [],
            'heatmap' => ['dates' => [], 'series' => []],
        ];
    }
}

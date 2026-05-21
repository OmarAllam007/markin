<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\PunchType;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\WorkShift;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Attendance::class);

        return Inertia::render('reports/Index');
    }

    public function overtime(Request $request): Response|StreamedResponse
    {
        $this->authorize('viewAny', Attendance::class);

        $tenantId = $request->user()->current_tenant_id;

        if (! $tenantId) {
            return Inertia::render('reports/Overtime', $this->emptyOvertimePayload());
        }

        $this->assertTenantAccess($request, $tenantId);

        $from = $request->filled('date_from')
            ? CarbonImmutable::parse($request->input('date_from'))
            : CarbonImmutable::now()->startOfMonth();
        $to = $request->filled('date_to')
            ? CarbonImmutable::parse($request->input('date_to'))
            : CarbonImmutable::now()->endOfMonth();

        $locationId = $request->input('location_id');
        $departmentId = $request->input('department_id');

        $rows = Attendance::query()
            ->select([
                'attendances.id',
                'attendances.employee_id',
                'attendances.attendance_date',
                'attendances.check_in_time',
                'attendances.check_out_time',
                'attendances.overtime_minutes',
                'attendances.location_id',
            ])
            ->where('attendances.tenant_id', $tenantId)
            ->where('attendances.overtime_minutes', '>', 0)
            ->with([
                'employee:id,english_name,arabic_name,employee_number,job_title_en,department_id,location_id',
                'employee.department:id,name',
                'employee.location:id,name',
                'location:id,name',
            ])
            ->when($locationId, fn ($q) => $q->whereHas(
                'employee',
                fn ($q) => $q->where('location_id', $locationId),
            ))
            ->when($departmentId, fn ($q) => $q->whereHas(
                'employee',
                fn ($q) => $q->where('department_id', $departmentId),
            ))
            ->whereDate('attendance_date', '>=', $from->toDateString())
            ->whereDate('attendance_date', '<=', $to->toDateString())
            ->orderBy('attendances.employee_id')
            ->orderByDesc('attendances.attendance_date')
            ->get();

        $grouped = $rows->groupBy('employee_id')->map(function ($records) {
            $first = $records->first();
            $emp = $first->employee;

            return [
                'employee_id' => $emp->id,
                'employee_number' => $emp->employee_number,
                'name' => $emp->english_name,
                'job_title' => $emp->job_title_en,
                'location' => $emp->location?->name,
                'department' => $emp->department?->name,
                'total_overtime_minutes' => $records->sum('overtime_minutes'),
                'records' => $records->map(fn ($r) => [
                    'date' => $r->attendance_date->toDateString(),
                    'check_in_time' => $this->fmtTime($r->check_in_time),
                    'check_out_time' => $this->fmtTime($r->check_out_time),
                    'location' => $r->location?->name,
                    'overtime_minutes' => $r->overtime_minutes,
                ])->values(),
            ];
        })->values();

        if ($request->input('export') === 'xlsx') {
            return $this->streamXlsx($this->overtimeSpreadsheet($grouped), 'overtime-report.xlsx');
        }

        return Inertia::render('reports/Overtime', [
            'employees' => $grouped,
            'summary' => [
                'employee_count' => $grouped->count(),
                'total_overtime_minutes' => $grouped->sum('total_overtime_minutes'),
            ],
            'filters' => [
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
                'location_id' => $locationId ? (int) $locationId : null,
                'department_id' => $departmentId ? (int) $departmentId : null,
            ],
            'filterOptions' => [
                'locations' => Location::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
                'departments' => Department::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }

    public function dailySummary(Request $request): Response|StreamedResponse
    {
        $this->authorize('viewAny', Attendance::class);

        $tenantId = $request->user()->current_tenant_id;

        if (! $tenantId) {
            return Inertia::render('reports/DailySummary', $this->emptyDailySummaryPayload());
        }

        $this->assertTenantAccess($request, $tenantId);

        $date = $request->filled('date')
            ? CarbonImmutable::parse($request->input('date'))
            : CarbonImmutable::today();

        $groupBy = $request->input('group_by', 'departments');

        $attendances = Attendance::query()
            ->where('attendances.tenant_id', $tenantId)
            ->whereDate('attendances.attendance_date', $date->toDateString())
            ->with([
                'employee:id,department_id,location_id',
                'employee.department:id,name',
                'employee.location:id,name',
            ])
            ->get(['id', 'employee_id', 'status']);

        $groups = $attendances
            ->groupBy(fn ($a) => $groupBy === 'locations'
                ? ($a->employee?->location_id ?? 0)
                : ($a->employee?->department_id ?? 0)
            )
            ->map(function ($records, $groupId) use ($groupBy) {
                $first = $records->first();
                $name = $groupBy === 'locations'
                    ? ($first->employee?->location?->name ?? 'Unassigned')
                    : ($first->employee?->department?->name ?? 'Unassigned');

                return $this->buildGroupData($name, $records, (int) $groupId);
            })
            ->sortBy('name')
            ->values();

        if ($request->input('export') === 'xlsx') {
            return $this->streamXlsx($this->dailySummarySpreadsheet($groups, $date->toDateString(), $groupBy), 'daily-summary.xlsx');
        }

        return Inertia::render('reports/DailySummary', [
            'groups' => $groups,
            'filters' => [
                'date' => $date->toDateString(),
                'group_by' => $groupBy,
            ],
            'filterOptions' => [
                'locations' => Location::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
                'departments' => Department::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }

    public function monthlySummary(Request $request): Response|StreamedResponse
    {
        $this->authorize('viewAny', Attendance::class);

        $tenantId = $request->user()->current_tenant_id;

        if (! $tenantId) {
            return Inertia::render('reports/MonthlySummary', $this->emptyMonthlySummaryPayload());
        }

        $this->assertTenantAccess($request, $tenantId);

        $from = $request->filled('date_from')
            ? CarbonImmutable::parse($request->input('date_from'))
            : CarbonImmutable::now()->startOfMonth();

        $to = $request->filled('date_to')
            ? CarbonImmutable::parse($request->input('date_to'))
            : CarbonImmutable::now()->endOfMonth();

        if ($from->diffInDays($to) > 62) {
            $to = $from->addDays(61);
        }

        $locationId = $request->input('location_id');
        $departmentId = $request->input('department_id');

        $attendances = Attendance::query()
            ->where('tenant_id', $tenantId)
            ->whereDate('attendance_date', '>=', $from->toDateString())
            ->whereDate('attendance_date', '<=', $to->toDateString())
            ->with([
                'employee:id,english_name,arabic_name,employee_number,department_id,location_id',
                'employee.department:id,name',
                'employee.location:id,name',
            ])
            ->when($locationId, fn ($q) => $q->whereHas(
                'employee', fn ($q) => $q->where('location_id', $locationId)
            ))
            ->when($departmentId, fn ($q) => $q->whereHas(
                'employee', fn ($q) => $q->where('department_id', $departmentId)
            ))
            ->orderBy('employee_id')
            ->orderBy('attendance_date')
            ->get(['id', 'employee_id', 'attendance_date', 'status', 'total_late_minutes', 'total_early_leave_minutes', 'overtime_minutes']);

        $dates = [];
        $cursor = $from;
        while (! $cursor->greaterThan($to)) {
            $dates[] = $cursor->toDateString();
            $cursor = $cursor->addDay();
        }

        $employees = $attendances
            ->groupBy('employee_id')
            ->map(function ($records) {
                $first = $records->first();
                $emp = $first->employee;

                $dayMap = $records
                    ->keyBy(fn ($r) => $r->attendance_date->toDateString())
                    ->map(fn ($r) => [
                        'status' => $r->status->value,
                        'late' => $r->total_late_minutes,
                        'early' => $r->total_early_leave_minutes,
                        'ot' => $r->overtime_minutes,
                    ]);

                return [
                    'id' => $emp->id,
                    'employee_number' => $emp->employee_number,
                    'name' => $emp->english_name,
                    'department' => $emp->department?->name,
                    'location' => $emp->location?->name,
                    'days' => $dayMap,
                ];
            })
            ->sortBy('name')
            ->values();

        if ($request->input('export') === 'xlsx') {
            return $this->streamXlsx($this->monthlySummarySpreadsheet($employees, $dates), 'monthly-summary.xlsx');
        }

        return Inertia::render('reports/MonthlySummary', [
            'employees' => $employees,
            'dates' => $dates,
            'filters' => [
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
                'location_id' => $locationId ? (int) $locationId : null,
                'department_id' => $departmentId ? (int) $departmentId : null,
            ],
            'filterOptions' => [
                'locations' => Location::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
                'departments' => Department::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }

    public function detailedReport(Request $request): Response|StreamedResponse
    {
        $this->authorize('viewAny', Attendance::class);

        $tenantId = $request->user()->current_tenant_id;

        if (! $tenantId) {
            return Inertia::render('reports/DetailedReport', $this->emptyDetailedReportPayload());
        }

        $this->assertTenantAccess($request, $tenantId);

        $from = $request->filled('date_from')
            ? CarbonImmutable::parse($request->input('date_from'))
            : CarbonImmutable::now()->startOfMonth();
        $to = $request->filled('date_to')
            ? CarbonImmutable::parse($request->input('date_to'))
            : CarbonImmutable::now()->endOfMonth();

        $locationId = $request->input('location_id');
        $departmentId = $request->input('department_id');

        $mapEmployee = function ($emp) {
            return [
                'id' => $emp->id,
                'employee_number' => $emp->employee_number,
                'name' => $emp->english_name,
                'department' => $emp->department?->name,
                'location' => $emp->location?->name,
                'total_late_minutes' => $emp->attendances->sum('total_late_minutes'),
                'total_early_leave_minutes' => $emp->attendances->sum('total_early_leave_minutes'),
                'total_overtime_minutes' => $emp->attendances->sum('overtime_minutes'),
                'absent_days' => $emp->attendances->filter(fn ($a) => $a->status === AttendanceStatus::Absent)->count(),
                'records' => $emp->attendances->map(function ($a) {
                    $checkInPunch = $a->punches->first(fn ($p) => $p->type === PunchType::CheckIn);
                    $checkOutPunch = $a->punches->last(fn ($p) => $p->type === PunchType::CheckOut);

                    return [
                        'id' => $a->id,
                        'date' => $a->attendance_date->toDateString(),
                        'day' => $a->attendance_date->format('D'),
                        'shift' => $a->shift?->name,
                        'check_in_time' => $this->fmtTime($a->check_in_time),
                        'check_in_location' => $checkInPunch?->location?->name ?? $a->location?->name,
                        'check_out_time' => $this->fmtTime($a->check_out_time),
                        'check_out_location' => $checkOutPunch?->location?->name,
                        'late_minutes' => $a->total_late_minutes,
                        'early_leave_minutes' => $a->total_early_leave_minutes,
                        'overtime_minutes' => $a->overtime_minutes,
                        'status' => $a->status->value,
                    ];
                })->values(),
            ];
        };

        $baseQuery = Employee::query()
            ->where('tenant_id', $tenantId)
            ->whereHas('attendances', fn ($q) => $q
                ->whereDate('attendance_date', '>=', $from->toDateString())
                ->whereDate('attendance_date', '<=', $to->toDateString())
            )
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->with([
                'department:id,name',
                'location:id,name',
                'attendances' => fn ($q) => $q
                    ->whereDate('attendance_date', '>=', $from->toDateString())
                    ->whereDate('attendance_date', '<=', $to->toDateString())
                    ->with([
                        'shift:id,name',
                        'location:id,name',
                        'punches' => fn ($pq) => $pq
                            ->with('location:id,name')
                            ->orderBy('punched_at'),
                    ])
                    ->orderBy('attendance_date'),
            ])
            ->orderBy('english_name');

        if ($request->input('export') === 'xlsx') {
            $all = $baseQuery->get()->map($mapEmployee);

            return $this->streamXlsx($this->detailedReportSpreadsheet($all), 'detailed-report.xlsx');
        }

        $employees = $baseQuery->paginate(15)->withQueryString()->through($mapEmployee);

        return Inertia::render('reports/DetailedReport', [
            'employees' => $employees,
            'filters' => [
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
                'location_id' => $locationId ? (int) $locationId : null,
                'department_id' => $departmentId ? (int) $departmentId : null,
            ],
            'filterOptions' => [
                'locations' => Location::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
                'departments' => Department::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }

    public function storeManualAttendance(Request $request): RedirectResponse
    {
        $this->authorize('create', Attendance::class);

        $tenantId = $request->user()->current_tenant_id;

        if (! $tenantId) {
            return redirect()->back()->with('error', 'No active company selected.');
        }

        $this->assertTenantAccess($request, $tenantId);

        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'attendance_date' => ['required', 'date'],
            'check_in_time' => ['nullable', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i'],
            'late_minutes' => ['nullable', 'integer', 'min:0'],
            'early_leave_minutes' => ['nullable', 'integer', 'min:0'],
            'overtime_minutes' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $employee = Employee::where('tenant_id', $tenantId)->findOrFail($data['employee_id']);

        $lateMinutes = (int) ($data['late_minutes'] ?? 0);
        $earlyMinutes = (int) ($data['early_leave_minutes'] ?? 0);
        $otMinutes = (int) ($data['overtime_minutes'] ?? 0);
        $checkIn = $data['check_in_time'] ?? null;
        $checkOut = $data['check_out_time'] ?? null;

        $workedMinutes = 0;
        if ($checkIn && $checkOut) {
            $inC = Carbon::createFromFormat('H:i', $checkIn);
            $outC = Carbon::createFromFormat('H:i', $checkOut);
            if ($outC->lt($inC)) {
                $outC->addDay();
            }
            $workedMinutes = max(0, (int) $inC->diffInMinutes($outC));
        }

        $status = match (true) {
            $checkIn === null => AttendanceStatus::Absent,
            $lateMinutes > 0 => AttendanceStatus::Late,
            default => AttendanceStatus::Present,
        };

        Attendance::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'employee_id' => $employee->id,
                'attendance_date' => CarbonImmutable::parse($data['attendance_date'])->toDateString(),
            ],
            [
                'check_in_time' => $checkIn ? $checkIn.':00' : null,
                'check_out_time' => $checkOut ? $checkOut.':00' : null,
                'total_late_minutes' => $lateMinutes,
                'total_early_leave_minutes' => $earlyMinutes,
                'overtime_minutes' => $otMinutes,
                'worked_minutes' => $workedMinutes,
                'status' => $status,
                'shift_id' => $employee->work_shift_id,
                'attendance_source' => AttendanceSource::Manual,
                'comments' => $data['notes'] ?? null,
                'is_manual_edit' => true,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]
        );

        return redirect()->back()->with('success', 'Attendance record saved.');
    }

    public function dailySummaryGroupDetail(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Attendance::class);

        $tenantId = $request->user()->current_tenant_id;

        if (! $tenantId) {
            return response()->json(['employees' => []]);
        }

        $this->assertTenantAccess($request, $tenantId);

        $date = $request->filled('date')
            ? CarbonImmutable::parse($request->input('date'))
            : CarbonImmutable::today();

        $groupBy = $request->input('group_by', 'departments');
        $groupId = (int) $request->input('group_id', 0);

        $attendances = Attendance::query()
            ->where('tenant_id', $tenantId)
            ->whereDate('attendance_date', $date->toDateString())
            ->with([
                'employee:id,english_name,arabic_name,employee_number,department_id,location_id',
                'shift:id,name',
                'location:id,name',
            ])
            ->when($groupId === 0 && $groupBy === 'locations',
                fn ($q) => $q->whereHas('employee', fn ($q) => $q->whereNull('location_id'))
            )
            ->when($groupId === 0 && $groupBy === 'departments',
                fn ($q) => $q->whereHas('employee', fn ($q) => $q->whereNull('department_id'))
            )
            ->when($groupId > 0 && $groupBy === 'locations',
                fn ($q) => $q->whereHas('employee', fn ($q) => $q->where('location_id', $groupId))
            )
            ->when($groupId > 0 && $groupBy === 'departments',
                fn ($q) => $q->whereHas('employee', fn ($q) => $q->where('department_id', $groupId))
            )
            ->orderBy('employee_id')
            ->get();

        $employees = $attendances->map(fn ($a) => [
            'employee_number' => $a->employee?->employee_number,
            'name' => $a->employee?->english_name,
            'status' => $a->status->value,
            'status_label' => $a->status->label(),
            'shift_name' => $a->shift?->name,
            'check_in_time' => $this->fmtTime($a->check_in_time),
            'check_out_time' => $this->fmtTime($a->check_out_time),
            'location' => $a->location?->name,
            'total_late_minutes' => $a->total_late_minutes,
            'total_early_leave_minutes' => $a->total_early_leave_minutes,
            'overtime_minutes' => $a->overtime_minutes,
        ])->values();

        return response()->json(['employees' => $employees]);
    }

    /**
     * @param  Collection<int, mixed>  $records
     * @return array<string, mixed>
     */
    private function buildGroupData(string $name, Collection $records, int $groupId = 0): array
    {
        $onTime = 0;
        $late = 0;
        $absent = 0;
        $onLeave = 0;
        $offDays = 0;

        foreach ($records as $record) {
            $status = $record->status instanceof AttendanceStatus
                ? $record->status
                : AttendanceStatus::from($record->status);

            match ($status) {
                AttendanceStatus::Present,
                AttendanceStatus::Remote => $onTime++,
                AttendanceStatus::Late,
                AttendanceStatus::MissingCheckout => $late++,
                AttendanceStatus::Absent => $absent++,
                AttendanceStatus::Leave,
                AttendanceStatus::BusinessTrip,
                AttendanceStatus::HalfDay => $onLeave++,
                AttendanceStatus::Weekend,
                AttendanceStatus::Holiday => $offDays++,
            };
        }

        return [
            'group_id' => $groupId,
            'name' => $name,
            'total' => $records->count(),
            'on_time' => $onTime,
            'late' => $late,
            'absent' => $absent,
            'on_leave' => $onLeave,
            'off_days' => $offDays,
        ];
    }

    public function lateArrivals(Request $request): Response|StreamedResponse
    {
        $this->authorize('viewAny', Attendance::class);

        $tenantId = $request->user()->current_tenant_id;

        if (! $tenantId) {
            return Inertia::render('reports/LateArrivals', $this->emptyLateArrivalsPayload());
        }

        $this->assertTenantAccess($request, $tenantId);

        $from = $request->filled('date_from')
            ? CarbonImmutable::parse($request->input('date_from'))
            : CarbonImmutable::now()->startOfMonth();
        $to = $request->filled('date_to')
            ? CarbonImmutable::parse($request->input('date_to'))
            : CarbonImmutable::now()->endOfMonth();

        $locationId = $request->input('location_id');
        $departmentId = $request->input('department_id');
        $shiftId = $request->input('shift_id');
        $minLateMinutes = max(1, (int) $request->input('min_late_minutes', 1));

        $rows = Attendance::query()
            ->select([
                'attendances.id',
                'attendances.employee_id',
                'attendances.attendance_date',
                'attendances.check_in_time',
                'attendances.scheduled_check_in',
                'attendances.shift_id',
                'attendances.total_late_minutes',
                'attendances.status',
            ])
            ->where('attendances.tenant_id', $tenantId)
            ->where('attendances.total_late_minutes', '>=', $minLateMinutes)
            ->whereDate('attendances.attendance_date', '>=', $from->toDateString())
            ->whereDate('attendances.attendance_date', '<=', $to->toDateString())
            ->with([
                'employee:id,english_name,arabic_name,employee_number,department_id,location_id',
                'employee.department:id,name',
                'shift:id,name,checkin_time',
            ])
            ->when($locationId, fn ($q) => $q->whereHas(
                'employee', fn ($q) => $q->where('location_id', $locationId)
            ))
            ->when($departmentId, fn ($q) => $q->whereHas(
                'employee', fn ($q) => $q->where('department_id', $departmentId)
            ))
            ->when($shiftId, fn ($q) => $q->where('attendances.shift_id', $shiftId))
            ->orderBy('attendances.employee_id')
            ->orderByDesc('attendances.attendance_date')
            ->get();

        $grouped = $rows->groupBy('employee_id')->map(function ($records) {
            $first = $records->first();
            $emp = $first->employee;

            return [
                'employee_id' => $emp->id,
                'employee_number' => $emp->employee_number,
                'name' => $emp->english_name,
                'department' => $emp->department?->name,
                'shift_name' => $first->shift?->name,
                'total_late_minutes' => $records->sum('total_late_minutes'),
                'occurrences' => $records->count(),
                'avg_late_minutes' => (int) round($records->avg('total_late_minutes')),
                'records' => $records->map(fn ($r) => [
                    'date' => $r->attendance_date->toDateString(),
                    'day' => $r->attendance_date->format('D'),
                    'scheduled_check_in' => $this->fmtTime($r->scheduled_check_in ?? $r->shift?->checkin_time),
                    'check_in_time' => $this->fmtTime($r->check_in_time),
                    'late_minutes' => $r->total_late_minutes,
                    'status' => $r->status->value,
                ])->values(),
            ];
        })->sortByDesc('total_late_minutes')->values();

        if ($request->input('export') === 'xlsx') {
            return $this->streamXlsx($this->lateArrivalsSpreadsheet($grouped), 'late-arrivals.xlsx');
        }

        return Inertia::render('reports/LateArrivals', [
            'employees' => $grouped,
            'summary' => [
                'employee_count' => $grouped->count(),
                'total_occurrences' => $grouped->sum('occurrences'),
                'total_late_minutes' => $grouped->sum('total_late_minutes'),
            ],
            'filters' => [
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
                'location_id' => $locationId ? (int) $locationId : null,
                'department_id' => $departmentId ? (int) $departmentId : null,
                'shift_id' => $shiftId ? (int) $shiftId : null,
                'min_late_minutes' => $minLateMinutes,
            ],
            'filterOptions' => [
                'locations' => Location::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
                'departments' => Department::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
                'shifts' => WorkShift::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }

    public function missingPunches(Request $request): Response|StreamedResponse
    {
        $this->authorize('viewAny', Attendance::class);

        $tenantId = $request->user()->current_tenant_id;

        if (! $tenantId) {
            return Inertia::render('reports/MissingPunches', $this->emptyMissingPunchesPayload());
        }

        $this->assertTenantAccess($request, $tenantId);

        $from = $request->filled('date_from')
            ? CarbonImmutable::parse($request->input('date_from'))
            : CarbonImmutable::now()->startOfMonth();
        $to = $request->filled('date_to')
            ? CarbonImmutable::parse($request->input('date_to'))
            : CarbonImmutable::now()->endOfMonth();

        $locationId = $request->input('location_id');
        $departmentId = $request->input('department_id');
        $shiftId = $request->input('shift_id');
        $punchType = $request->input('punch_type', 'all');

        $nonWorkingStatuses = [
            AttendanceStatus::Absent->value,
            AttendanceStatus::Weekend->value,
            AttendanceStatus::Holiday->value,
            AttendanceStatus::Leave->value,
            AttendanceStatus::BusinessTrip->value,
            AttendanceStatus::HalfDay->value,
        ];

        $rows = Attendance::query()
            ->select([
                'attendances.id',
                'attendances.employee_id',
                'attendances.attendance_date',
                'attendances.check_in_time',
                'attendances.check_out_time',
                'attendances.scheduled_check_in',
                'attendances.scheduled_check_out',
                'attendances.shift_id',
                'attendances.status',
                'attendances.attendance_source',
                'attendances.location_id',
            ])
            ->where('attendances.tenant_id', $tenantId)
            ->whereDate('attendances.attendance_date', '>=', $from->toDateString())
            ->whereDate('attendances.attendance_date', '<=', $to->toDateString())
            ->where(function ($q) use ($punchType, $nonWorkingStatuses) {
                if ($punchType === 'no_checkout' || $punchType === 'all') {
                    $q->orWhere(function ($inner) use ($nonWorkingStatuses) {
                        $inner->whereNotNull('check_in_time')
                            ->whereNull('check_out_time')
                            ->whereNotIn('status', $nonWorkingStatuses);
                    });
                }
                if ($punchType === 'no_checkin' || $punchType === 'all') {
                    $q->orWhere(function ($inner) {
                        $inner->whereNull('check_in_time')
                            ->whereNotNull('check_out_time');
                    });
                }
            })
            ->with([
                'employee:id,english_name,arabic_name,employee_number,department_id,location_id',
                'employee.department:id,name',
                'shift:id,name,checkin_time,checkout_time',
                'location:id,name',
            ])
            ->when($locationId, fn ($q) => $q->whereHas(
                'employee', fn ($q) => $q->where('location_id', $locationId)
            ))
            ->when($departmentId, fn ($q) => $q->whereHas(
                'employee', fn ($q) => $q->where('department_id', $departmentId)
            ))
            ->when($shiftId, fn ($q) => $q->where('attendances.shift_id', $shiftId))
            ->orderBy('attendances.employee_id')
            ->orderByDesc('attendances.attendance_date')
            ->get();

        $grouped = $rows->groupBy('employee_id')->map(function ($records) {
            $first = $records->first();
            $emp = $first->employee;

            $noCheckoutCount = $records->filter(
                fn ($r) => $r->check_in_time !== null && $r->check_out_time === null
            )->count();
            $noCheckinCount = $records->filter(
                fn ($r) => $r->check_in_time === null && $r->check_out_time !== null
            )->count();

            return [
                'employee_id' => $emp->id,
                'employee_number' => $emp->employee_number,
                'name' => $emp->english_name,
                'department' => $emp->department?->name,
                'shift_name' => $first->shift?->name,
                'occurrences' => $records->count(),
                'no_checkout_count' => $noCheckoutCount,
                'no_checkin_count' => $noCheckinCount,
                'records' => $records->map(fn ($r) => [
                    'date' => $r->attendance_date->toDateString(),
                    'day' => $r->attendance_date->format('D'),
                    'punch_type' => ($r->check_in_time !== null && $r->check_out_time === null)
                        ? 'no_checkout'
                        : 'no_checkin',
                    'check_in_time' => $this->fmtTime($r->check_in_time),
                    'check_out_time' => $this->fmtTime($r->check_out_time),
                    'scheduled_check_in' => $this->fmtTime(
                        $r->scheduled_check_in ?? $r->shift?->checkin_time
                    ),
                    'scheduled_check_out' => $this->fmtTime(
                        $r->scheduled_check_out ?? $r->shift?->checkout_time
                    ),
                    'source' => $r->attendance_source?->label() ?? '—',
                    'location' => $r->location?->name,
                ])->values(),
            ];
        })->sortByDesc('occurrences')->values();

        $departmentChart = $grouped
            ->groupBy('department')
            ->map(fn ($emps, $dept) => [
                'department' => $dept ?? 'Unassigned',
                'occurrences' => $emps->sum('occurrences'),
                'employee_count' => $emps->count(),
            ])
            ->sortByDesc('occurrences')
            ->values();

        if ($request->input('export') === 'xlsx') {
            return $this->streamXlsx($this->missingPunchesSpreadsheet($grouped), 'missing-punches.xlsx');
        }

        return Inertia::render('reports/MissingPunches', [
            'employees' => $grouped,
            'summary' => [
                'employee_count' => $grouped->count(),
                'total_occurrences' => $grouped->sum('occurrences'),
                'no_checkout_count' => $grouped->sum('no_checkout_count'),
                'no_checkin_count' => $grouped->sum('no_checkin_count'),
            ],
            'departmentChart' => $departmentChart,
            'filters' => [
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
                'location_id' => $locationId ? (int) $locationId : null,
                'department_id' => $departmentId ? (int) $departmentId : null,
                'shift_id' => $shiftId ? (int) $shiftId : null,
                'punch_type' => $punchType,
            ],
            'filterOptions' => [
                'locations' => Location::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
                'departments' => Department::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
                'shifts' => WorkShift::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }

    public function departmentAttendance(Request $request): Response|StreamedResponse
    {
        $this->authorize('viewAny', Attendance::class);

        $tenantId = $request->user()->current_tenant_id;

        if (! $tenantId) {
            return Inertia::render('reports/DepartmentAttendance', $this->emptyDepartmentAttendancePayload());
        }

        $this->assertTenantAccess($request, $tenantId);

        $from = $request->filled('date_from')
            ? CarbonImmutable::parse($request->input('date_from'))
            : CarbonImmutable::now()->startOfMonth();
        $to = $request->filled('date_to')
            ? CarbonImmutable::parse($request->input('date_to'))
            : CarbonImmutable::now()->endOfMonth();

        $locationId = $request->input('location_id');

        $rows = Attendance::query()
            ->select([
                'attendances.id',
                'attendances.employee_id',
                'attendances.attendance_date',
                'attendances.status',
                'attendances.total_late_minutes',
                'attendances.overtime_minutes',
            ])
            ->where('attendances.tenant_id', $tenantId)
            ->whereDate('attendances.attendance_date', '>=', $from->toDateString())
            ->whereDate('attendances.attendance_date', '<=', $to->toDateString())
            ->with([
                'employee:id,english_name,arabic_name,employee_number,department_id,location_id',
                'employee.department:id,name',
            ])
            ->when($locationId, fn ($q) => $q->whereHas(
                'employee', fn ($q) => $q->where('location_id', $locationId)
            ))
            ->orderBy('attendances.employee_id')
            ->orderBy('attendances.attendance_date')
            ->get();

        $departments = $rows
            ->groupBy(fn ($r) => $r->employee?->department_id ?? 0)
            ->map(function ($deptRecords) {
                $first = $deptRecords->first();
                $deptName = $first->employee?->department?->name ?? 'Unassigned';

                $deptCounts = $this->statusCountsFor($deptRecords);
                $workingRecords = $deptRecords->count() - $deptCounts['off_days'];
                $attended = $deptCounts['present'] + $deptCounts['late'];
                $attendanceRate = $workingRecords > 0
                    ? (int) round($attended / $workingRecords * 100)
                    : 0;

                $employees = $deptRecords
                    ->groupBy('employee_id')
                    ->map(function ($empRecords) {
                        $first = $empRecords->first();
                        $emp = $first->employee;

                        $empCounts = $this->statusCountsFor($empRecords);
                        $workingDays = $empRecords->count() - $empCounts['off_days'];
                        $empAttended = $empCounts['present'] + $empCounts['late'];
                        $empRate = $workingDays > 0
                            ? (int) round($empAttended / $workingDays * 100)
                            : 0;

                        return [
                            'employee_id' => $emp->id,
                            'employee_number' => $emp->employee_number,
                            'name' => $emp->english_name,
                            'total_days' => $empRecords->count(),
                            'present' => $empCounts['present'],
                            'late' => $empCounts['late'],
                            'absent' => $empCounts['absent'],
                            'leave' => $empCounts['leave'],
                            'off_days' => $empCounts['off_days'],
                            'total_late_minutes' => $empRecords->sum('total_late_minutes'),
                            'overtime_minutes' => $empRecords->sum('overtime_minutes'),
                            'attendance_rate' => $empRate,
                        ];
                    })
                    ->sortBy('name')
                    ->values();

                return [
                    'department' => $deptName,
                    'employee_count' => $deptRecords->pluck('employee_id')->unique()->count(),
                    'total_records' => $deptRecords->count(),
                    'present' => $deptCounts['present'],
                    'late' => $deptCounts['late'],
                    'absent' => $deptCounts['absent'],
                    'leave' => $deptCounts['leave'],
                    'off_days' => $deptCounts['off_days'],
                    'attendance_rate' => $attendanceRate,
                    'employees' => $employees,
                ];
            })
            ->sortByDesc('attendance_rate')
            ->values();

        $totalWorkingRecords = $departments->sum('total_records') - $departments->sum('off_days');
        $totalAttended = $departments->sum('present') + $departments->sum('late');

        if ($request->input('export') === 'xlsx') {
            return $this->streamXlsx($this->departmentAttendanceSpreadsheet($departments), 'department-attendance.xlsx');
        }

        return Inertia::render('reports/DepartmentAttendance', [
            'departments' => $departments,
            'summary' => [
                'department_count' => $departments->count(),
                'overall_attendance_rate' => $totalWorkingRecords > 0
                    ? (int) round($totalAttended / $totalWorkingRecords * 100)
                    : 0,
            ],
            'filters' => [
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
                'location_id' => $locationId ? (int) $locationId : null,
            ],
            'filterOptions' => [
                'locations' => Location::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }

    public function absenceReport(Request $request): Response|StreamedResponse
    {
        $this->authorize('viewAny', Attendance::class);

        $tenantId = $request->user()->current_tenant_id;

        if (! $tenantId) {
            return Inertia::render('reports/AbsenceReport', $this->emptyAbsenceReportPayload());
        }

        $this->assertTenantAccess($request, $tenantId);

        $from = $request->filled('date_from')
            ? CarbonImmutable::parse($request->input('date_from'))
            : CarbonImmutable::now()->startOfMonth();
        $to = $request->filled('date_to')
            ? CarbonImmutable::parse($request->input('date_to'))
            : CarbonImmutable::now()->endOfMonth();

        $locationId = $request->input('location_id');
        $departmentId = $request->input('department_id');
        $shiftId = $request->input('shift_id');

        $rows = Attendance::query()
            ->select([
                'attendances.id',
                'attendances.employee_id',
                'attendances.attendance_date',
                'attendances.shift_id',
                'attendances.status',
            ])
            ->where('attendances.tenant_id', $tenantId)
            ->where('attendances.status', AttendanceStatus::Absent)
            ->whereDate('attendances.attendance_date', '>=', $from->toDateString())
            ->whereDate('attendances.attendance_date', '<=', $to->toDateString())
            ->with([
                'employee:id,english_name,arabic_name,employee_number,department_id,location_id',
                'employee.department:id,name',
                'shift:id,name',
            ])
            ->when($locationId, fn ($q) => $q->whereHas(
                'employee', fn ($q) => $q->where('location_id', $locationId)
            ))
            ->when($departmentId, fn ($q) => $q->whereHas(
                'employee', fn ($q) => $q->where('department_id', $departmentId)
            ))
            ->when($shiftId, fn ($q) => $q->where('attendances.shift_id', $shiftId))
            ->orderBy('attendances.employee_id')
            ->orderBy('attendances.attendance_date')
            ->get();

        $grouped = $rows->groupBy('employee_id')->map(function ($records) {
            $first = $records->first();
            $emp = $first->employee;

            $dates = $records
                ->pluck('attendance_date')
                ->map(fn ($d) => $d->toDateString())
                ->sort()
                ->values()
                ->all();

            return [
                'employee_id' => $emp->id,
                'employee_number' => $emp->employee_number,
                'name' => $emp->english_name,
                'department' => $emp->department?->name,
                'shift_name' => $first->shift?->name,
                'absent_days' => count($dates),
                'max_consecutive' => $this->maxConsecutiveDays($dates),
                'records' => $records->map(fn ($r) => [
                    'date' => $r->attendance_date->toDateString(),
                    'day' => $r->attendance_date->format('D'),
                ])->values(),
            ];
        })->sortByDesc('absent_days')->values();

        $departmentChart = $grouped
            ->groupBy('department')
            ->map(fn ($emps, $dept) => [
                'department' => $dept ?? 'Unassigned',
                'absent_days' => $emps->sum('absent_days'),
                'employee_count' => $emps->count(),
            ])
            ->sortByDesc('absent_days')
            ->values();

        if ($request->input('export') === 'xlsx') {
            return $this->streamXlsx($this->absenceReportSpreadsheet($grouped), 'absence-report.xlsx');
        }

        return Inertia::render('reports/AbsenceReport', [
            'employees' => $grouped,
            'summary' => [
                'employee_count' => $grouped->count(),
                'total_absent_days' => $grouped->sum('absent_days'),
                'max_consecutive' => $grouped->max('max_consecutive') ?? 0,
            ],
            'departmentChart' => $departmentChart,
            'filters' => [
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
                'location_id' => $locationId ? (int) $locationId : null,
                'department_id' => $departmentId ? (int) $departmentId : null,
                'shift_id' => $shiftId ? (int) $shiftId : null,
            ],
            'filterOptions' => [
                'locations' => Location::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
                'departments' => Department::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
                'shifts' => WorkShift::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }

    /**
     * @param  Collection<int, mixed>  $records
     * @return array<string, int>
     */
    private function statusCountsFor(Collection $records): array
    {
        $present = $late = $absent = $leave = $offDays = 0;

        foreach ($records as $record) {
            $status = $record->status instanceof AttendanceStatus
                ? $record->status
                : AttendanceStatus::from($record->status);

            match ($status) {
                AttendanceStatus::Present,
                AttendanceStatus::Remote => $present++,
                AttendanceStatus::Late,
                AttendanceStatus::MissingCheckout => $late++,
                AttendanceStatus::Absent => $absent++,
                AttendanceStatus::Leave,
                AttendanceStatus::BusinessTrip,
                AttendanceStatus::HalfDay => $leave++,
                AttendanceStatus::Weekend,
                AttendanceStatus::Holiday => $offDays++,
            };
        }

        return compact('present', 'late', 'absent', 'leave') + ['off_days' => $offDays];
    }

    /**
     * @param  string[]  $sortedDates
     */
    private function maxConsecutiveDays(array $sortedDates): int
    {
        if (empty($sortedDates)) {
            return 0;
        }

        $max = 1;
        $current = 1;

        for ($i = 1; $i < count($sortedDates); $i++) {
            $prev = CarbonImmutable::parse($sortedDates[$i - 1]);
            $curr = CarbonImmutable::parse($sortedDates[$i]);

            if ($prev->addDay()->toDateString() === $curr->toDateString()) {
                $current++;
                $max = max($max, $current);
            } else {
                $current = 1;
            }
        }

        return $max;
    }

    private function fmtTime(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $str = is_string($value) ? $value : (string) $value;

        return strlen($str) >= 5 ? substr($str, 0, 5) : $str;
    }

    private function assertTenantAccess(Request $request, int $tenantId): void
    {
        $tenant = Tenant::findOrFail($tenantId);
        if (! $request->user()->canAccessTenant($tenant)) {
            abort(403);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyMonthlySummaryPayload(): array
    {
        return [
            'employees' => [],
            'dates' => [],
            'filters' => [
                'date_from' => CarbonImmutable::now()->startOfMonth()->toDateString(),
                'date_to' => CarbonImmutable::now()->endOfMonth()->toDateString(),
                'location_id' => null,
                'department_id' => null,
            ],
            'filterOptions' => ['locations' => [], 'departments' => []],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyOvertimePayload(): array
    {
        return [
            'employees' => [],
            'summary' => ['employee_count' => 0, 'total_overtime_minutes' => 0],
            'filters' => [
                'date_from' => CarbonImmutable::now()->startOfMonth()->toDateString(),
                'date_to' => CarbonImmutable::now()->endOfMonth()->toDateString(),
                'location_id' => null,
                'department_id' => null,
            ],
            'filterOptions' => ['locations' => [], 'departments' => []],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDetailedReportPayload(): array
    {
        return [
            'employees' => [],
            'filters' => [
                'date_from' => CarbonImmutable::now()->startOfMonth()->toDateString(),
                'date_to' => CarbonImmutable::now()->endOfMonth()->toDateString(),
                'location_id' => null,
                'department_id' => null,
            ],
            'filterOptions' => ['locations' => [], 'departments' => []],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDailySummaryPayload(): array
    {
        return [
            'groups' => [],
            'filters' => [
                'date' => CarbonImmutable::today()->toDateString(),
                'group_by' => 'departments',
            ],
            'filterOptions' => ['locations' => [], 'departments' => []],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyLateArrivalsPayload(): array
    {
        return [
            'employees' => [],
            'summary' => ['employee_count' => 0, 'total_occurrences' => 0, 'total_late_minutes' => 0],
            'filters' => [
                'date_from' => CarbonImmutable::now()->startOfMonth()->toDateString(),
                'date_to' => CarbonImmutable::now()->endOfMonth()->toDateString(),
                'location_id' => null,
                'department_id' => null,
                'shift_id' => null,
                'min_late_minutes' => 1,
            ],
            'filterOptions' => ['locations' => [], 'departments' => [], 'shifts' => []],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyMissingPunchesPayload(): array
    {
        return [
            'employees' => [],
            'summary' => ['employee_count' => 0, 'total_occurrences' => 0, 'no_checkout_count' => 0, 'no_checkin_count' => 0],
            'departmentChart' => [],
            'filters' => [
                'date_from' => CarbonImmutable::now()->startOfMonth()->toDateString(),
                'date_to' => CarbonImmutable::now()->endOfMonth()->toDateString(),
                'location_id' => null,
                'department_id' => null,
                'shift_id' => null,
                'punch_type' => 'all',
            ],
            'filterOptions' => ['locations' => [], 'departments' => [], 'shifts' => []],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDepartmentAttendancePayload(): array
    {
        return [
            'departments' => [],
            'summary' => [
                'department_count' => 0,
                'overall_attendance_rate' => 0,
            ],
            'filters' => [
                'date_from' => CarbonImmutable::now()->startOfMonth()->toDateString(),
                'date_to' => CarbonImmutable::now()->endOfMonth()->toDateString(),
                'location_id' => null,
            ],
            'filterOptions' => ['locations' => []],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyAbsenceReportPayload(): array
    {
        return [
            'employees' => [],
            'summary' => ['employee_count' => 0, 'total_absent_days' => 0, 'max_consecutive' => 0],
            'departmentChart' => [],
            'filters' => [
                'date_from' => CarbonImmutable::now()->startOfMonth()->toDateString(),
                'date_to' => CarbonImmutable::now()->endOfMonth()->toDateString(),
                'location_id' => null,
                'department_id' => null,
                'shift_id' => null,
            ],
            'filterOptions' => ['locations' => [], 'departments' => [], 'shifts' => []],
        ];
    }

    // ── Excel export helpers ─────────────────────────────────────────────────

    private function streamXlsx(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new XlsxWriter($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /** @param array<string, mixed> $headers */
    private function makeSheet(Spreadsheet $spreadsheet, string $title, array $headers): Worksheet
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($title);
        $sheet->fromArray(array_values($headers), null, 'A1');

        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2B4570']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setSize(10);

        return $sheet;
    }

    private function autoSize(Worksheet $sheet, int $colCount): void
    {
        for ($i = 1; $i <= $colCount; $i++) {
            $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
        }
    }

    private function fmtMin(int $minutes): string
    {
        return \sprintf('%dh %02dm', intdiv($minutes, 60), $minutes % 60);
    }

    /** @param Collection<int, mixed> $grouped */
    private function overtimeSpreadsheet(Collection $grouped): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $headers = ['Employee #', 'Name', 'Job Title', 'Department', 'Location', 'Date', 'Check In', 'Check Out', 'OT (min)', 'OT (h:m)'];
        $sheet = $this->makeSheet($spreadsheet, 'Overtime', $headers);

        $row = 2;
        foreach ($grouped as $emp) {
            foreach ($emp['records'] as $rec) {
                $sheet->fromArray([
                    $emp['employee_number'] ?? '',
                    $emp['name'],
                    $emp['job_title'] ?? '',
                    $emp['department'] ?? '',
                    $emp['location'] ?? '',
                    $rec['date'],
                    $rec['check_in_time'] ?? '',
                    $rec['check_out_time'] ?? '',
                    $rec['overtime_minutes'],
                    $this->fmtMin($rec['overtime_minutes']),
                ], null, "A{$row}");
                $row++;
            }
        }
        $this->autoSize($sheet, count($headers));

        return $spreadsheet;
    }

    /** @param Collection<int, mixed> $groups */
    private function dailySummarySpreadsheet(Collection $groups, string $date, string $groupBy): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $groupLabel = $groupBy === 'locations' ? 'Location' : 'Department';
        $headers = [$groupLabel, 'Total', 'On Time', 'Late', 'Absent', 'On Leave', 'Off Days'];
        $sheet = $this->makeSheet($spreadsheet, 'Daily Summary', $headers);

        $row = 2;
        foreach ($groups as $group) {
            $sheet->fromArray([
                $group['name'],
                $group['total'],
                $group['on_time'],
                $group['late'],
                $group['absent'],
                $group['on_leave'],
                $group['off_days'],
            ], null, "A{$row}");
            $row++;
        }
        $this->autoSize($sheet, count($headers));
        $sheet->getCell('A1')->getWorksheet()->getParent()->getProperties()->setTitle("Daily Summary {$date}");

        return $spreadsheet;
    }

    /**
     * @param  Collection<int, mixed>  $employees
     * @param  string[]  $dates
     */
    private function monthlySummarySpreadsheet(Collection $employees, array $dates): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $shortDates = array_map(fn ($d) => Carbon::parse($d)->format('d/m'), $dates);
        $headers = array_merge(['Employee #', 'Name', 'Department', 'Location'], $shortDates, ['P', 'L', 'A', 'LV', 'WK']);
        $sheet = $this->makeSheet($spreadsheet, 'Monthly Summary', $headers);

        $statusAbbr = [
            'present' => 'P', 'late' => 'L', 'absent' => 'A',
            'weekend' => 'WK', 'holiday' => 'H', 'half_day' => 'HD',
            'leave' => 'LV', 'business_trip' => 'BT', 'remote' => 'R',
            'missing_checkout' => 'MC',
        ];

        $row = 2;
        foreach ($employees as $emp) {
            $dayCells = array_map(fn ($d) => $statusAbbr[$emp['days'][$d]['status'] ?? ''] ?? '', $dates);
            $counts = array_count_values(array_map(fn ($d) => $emp['days'][$d]['status'] ?? '', $dates));

            $sheet->fromArray(
                array_merge(
                    [$emp['employee_number'] ?? '', $emp['name'], $emp['department'] ?? '', $emp['location'] ?? ''],
                    $dayCells,
                    [
                        $counts['present'] ?? 0,
                        $counts['late'] ?? 0,
                        $counts['absent'] ?? 0,
                        $counts['leave'] ?? 0,
                        ($counts['weekend'] ?? 0) + ($counts['holiday'] ?? 0),
                    ]
                ),
                null,
                "A{$row}"
            );
            $row++;
        }

        // Fixed width for date columns, auto for info columns
        for ($i = 1; $i <= 4; $i++) {
            $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
        }
        $dateColCount = count($dates);
        for ($i = 5; $i <= 4 + $dateColCount; $i++) {
            $sheet->getColumnDimensionByColumn($i)->setWidth(5);
        }
        for ($i = 5 + $dateColCount; $i <= count($headers); $i++) {
            $sheet->getColumnDimensionByColumn($i)->setWidth(6);
        }

        return $spreadsheet;
    }

    /** @param Collection<int, mixed> $employees */
    private function detailedReportSpreadsheet(Collection $employees): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $headers = ['Employee #', 'Name', 'Department', 'Location', 'Date', 'Day', 'Shift', 'Check In', 'Check In Location', 'Check Out', 'Check Out Location', 'Late (min)', 'Early Leave (min)', 'OT (min)', 'Status'];
        $sheet = $this->makeSheet($spreadsheet, 'Detailed Report', $headers);

        $row = 2;
        foreach ($employees as $emp) {
            foreach ($emp['records'] as $rec) {
                $sheet->fromArray([
                    $emp['employee_number'] ?? '',
                    $emp['name'],
                    $emp['department'] ?? '',
                    $emp['location'] ?? '',
                    $rec['date'],
                    $rec['day'],
                    $rec['shift'] ?? '',
                    $rec['check_in_time'] ?? '',
                    $rec['check_in_location'] ?? '',
                    $rec['check_out_time'] ?? '',
                    $rec['check_out_location'] ?? '',
                    $rec['late_minutes'],
                    $rec['early_leave_minutes'],
                    $rec['overtime_minutes'],
                    $rec['status'],
                ], null, "A{$row}");
                $row++;
            }
        }
        $this->autoSize($sheet, count($headers));

        return $spreadsheet;
    }

    /** @param Collection<int, mixed> $grouped */
    private function lateArrivalsSpreadsheet(Collection $grouped): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $headers = ['Employee #', 'Name', 'Department', 'Shift', 'Date', 'Day', 'Scheduled In', 'Actual In', 'Late (min)'];
        $sheet = $this->makeSheet($spreadsheet, 'Late Arrivals', $headers);

        $row = 2;
        foreach ($grouped as $emp) {
            foreach ($emp['records'] as $rec) {
                $sheet->fromArray([
                    $emp['employee_number'] ?? '',
                    $emp['name'],
                    $emp['department'] ?? '',
                    $emp['shift_name'] ?? '',
                    $rec['date'],
                    $rec['day'],
                    $rec['scheduled_check_in'] ?? '',
                    $rec['check_in_time'] ?? '',
                    $rec['late_minutes'],
                ], null, "A{$row}");
                $row++;
            }
        }
        $this->autoSize($sheet, count($headers));

        return $spreadsheet;
    }

    /** @param Collection<int, mixed> $grouped */
    private function absenceReportSpreadsheet(Collection $grouped): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $headers = ['Employee #', 'Name', 'Department', 'Shift', 'Date', 'Day'];
        $sheet = $this->makeSheet($spreadsheet, 'Absences', $headers);

        $row = 2;
        foreach ($grouped as $emp) {
            foreach ($emp['records'] as $rec) {
                $sheet->fromArray([
                    $emp['employee_number'] ?? '',
                    $emp['name'],
                    $emp['department'] ?? '',
                    $emp['shift_name'] ?? '',
                    $rec['date'],
                    $rec['day'],
                ], null, "A{$row}");
                $row++;
            }
        }
        $this->autoSize($sheet, count($headers));

        return $spreadsheet;
    }

    /** @param Collection<int, mixed> $grouped */
    private function missingPunchesSpreadsheet(Collection $grouped): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $headers = ['Employee #', 'Name', 'Department', 'Shift', 'Date', 'Day', 'Missing', 'Check In', 'Check Out', 'Sched. In', 'Sched. Out', 'Source', 'Location'];
        $sheet = $this->makeSheet($spreadsheet, 'Missing Punches', $headers);

        $row = 2;
        foreach ($grouped as $emp) {
            foreach ($emp['records'] as $rec) {
                $sheet->fromArray([
                    $emp['employee_number'] ?? '',
                    $emp['name'],
                    $emp['department'] ?? '',
                    $emp['shift_name'] ?? '',
                    $rec['date'],
                    $rec['day'],
                    $rec['punch_type'] === 'no_checkout' ? 'No Check-Out' : 'No Check-In',
                    $rec['check_in_time'] ?? '',
                    $rec['check_out_time'] ?? '',
                    $rec['scheduled_check_in'] ?? '',
                    $rec['scheduled_check_out'] ?? '',
                    $rec['source'] ?? '',
                    $rec['location'] ?? '',
                ], null, "A{$row}");
                $row++;
            }
        }
        $this->autoSize($sheet, count($headers));

        return $spreadsheet;
    }

    /** @param Collection<int, mixed> $departments */
    private function departmentAttendanceSpreadsheet(Collection $departments): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $headers = ['Department', 'Employee #', 'Name', 'Total Days', 'Present', 'Late', 'Absent', 'Leave', 'Off Days', 'Late (min)', 'OT (min)', 'Rate %'];
        $sheet = $this->makeSheet($spreadsheet, 'Department Attendance', $headers);

        $row = 2;
        foreach ($departments as $dept) {
            foreach ($dept['employees'] as $emp) {
                $sheet->fromArray([
                    $dept['department'],
                    $emp['employee_number'] ?? '',
                    $emp['name'],
                    $emp['total_days'],
                    $emp['present'],
                    $emp['late'],
                    $emp['absent'],
                    $emp['leave'],
                    $emp['off_days'],
                    $emp['total_late_minutes'],
                    $emp['overtime_minutes'],
                    $emp['attendance_rate'],
                ], null, "A{$row}");
                $row++;
            }
        }
        $this->autoSize($sheet, count($headers));

        return $spreadsheet;
    }
}

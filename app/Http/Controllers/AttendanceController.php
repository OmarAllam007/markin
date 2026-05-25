<?php

namespace App\Http\Controllers;

use App\DataTransferObjects\AttendanceCalculationInput;
use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Http\Requests\IndexAttendanceRequest;
use App\Http\Requests\StoreAttendanceRequest;
use App\Http\Requests\UpdateAttendanceRequest;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\WorkShift;
use App\Services\Attendance\AttendanceCalculatorService;
use App\Services\Attendance\AttendanceScheduleResolver;
use App\Services\Attendance\AttendanceValidationService;
use App\Services\HolidayService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceCalculatorService $calculator,
        private readonly AttendanceScheduleResolver $scheduleResolver,
        private readonly AttendanceValidationService $validation,
        private readonly HolidayService $holidayService,
    ) {}

    public function index(IndexAttendanceRequest $request): Response
    {
        $this->authorize('viewAny', Attendance::class);

        $tenantId = $request->user()->current_tenant_id;

        if (! $tenantId) {
            return Inertia::render('attendance/Index', $this->emptyIndexPayload());
        }

        $this->authorizesTenantAccess($request, $tenantId);

        $filters = $request->validated();
        $from = isset($filters['date_from'])
            ? CarbonImmutable::parse($filters['date_from'])
            : CarbonImmutable::now()->startOfMonth();
        $to = isset($filters['date_to'])
            ? CarbonImmutable::parse($filters['date_to'])
            : CarbonImmutable::now()->endOfMonth();

        $query = Attendance::query()
            ->where('attendances.tenant_id', $tenantId)
            ->with([
                'employee:id,english_name,arabic_name,employee_number,department_id,location_id,tenant_id',
                'employee.department:id,name',
                'employee.location:id,name',
                'shift:id,name',
                'creator:id,name',
                'approver:id,name',
            ])
            ->when($filters['employee_id'] ?? null, fn ($q, $id) => $q->where('employee_id', $id))
            ->when($filters['department_id'] ?? null, fn ($q, $id) => $q->whereHas(
                'employee',
                fn ($q) => $q->where('department_id', $id),
            ))
            ->when($filters['business_unit_id'] ?? null, fn ($q, $id) => $q->whereHas(
                'employee',
                fn ($q) => $q->where('tenant_id', $id),
            ))
            ->when($filters['location_id'] ?? null, fn ($q, $id) => $q->whereHas(
                'employee',
                fn ($q) => $q->where('location_id', $id),
            ))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['attendance_source'] ?? null, fn ($q, $s) => $q->where('attendance_source', $s))
            ->whereDate('attendance_date', '>=', $from->toDateString())
            ->whereDate('attendance_date', '<=', $to->toDateString())
            ->orderByDesc('attendance_date')
            ->orderByDesc('id');

        $summaryQuery = clone $query;

        $attendances = (clone $query)->paginate(20)->withQueryString();

        $summary = [
            'present' => (clone $summaryQuery)->where('status', AttendanceStatus::Present)->count(),
            'absent' => (clone $summaryQuery)->where('status', AttendanceStatus::Absent)->count(),
            'late' => (clone $summaryQuery)->where('status', AttendanceStatus::Late)->count(),
            'overtime_records' => (clone $summaryQuery)->where('overtime_minutes', '>', 0)->count(),
            'missing_checkout' => (clone $summaryQuery)->where('status', AttendanceStatus::MissingCheckout)->count(),
        ];

        return Inertia::render('attendance/Index', [
            'attendances' => $attendances,
            'summary' => $summary,
            'abilities' => [
                'create' => $request->user()->can('create', Attendance::class),
            ],
            'filters' => [
                'employee_id' => $filters['employee_id'] ?? null,
                'department_id' => $filters['department_id'] ?? null,
                'business_unit_id' => $filters['business_unit_id'] ?? null,
                'location_id' => $filters['location_id'] ?? null,
                'status' => $filters['status'] ?? null,
                'attendance_source' => $filters['attendance_source'] ?? null,
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
            ],
            'filterOptions' => $this->filterOptions($tenantId),
            'enumLabels' => $this->enumLabels(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Attendance::class);

        $tenantId = $request->user()->current_tenant_id;

        if (! $tenantId) {
            return Inertia::render('attendance/Create', [
                'employees' => [],
                'filterOptions' => ['locations' => [], 'shifts' => []],
                'enumLabels' => $this->enumLabels(),
            ]);
        }

        $this->authorizesTenantAccess($request, $tenantId);

        $employees = Employee::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('english_name')
            ->get(['id', 'english_name', 'arabic_name', 'employee_number', 'work_shift_id']);

        return Inertia::render('attendance/Create', [
            'employees' => $employees,
            'filterOptions' => [
                'locations' => Location::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
                'shifts' => WorkShift::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
            ],
            'enumLabels' => $this->enumLabels(),
        ]);
    }

    public function store(StoreAttendanceRequest $request): RedirectResponse
    {
        $this->authorize('create', Attendance::class);

        $tenantId = $request->user()->current_tenant_id;

        if (! $tenantId) {
            return redirect()->back()->with('error', 'No active company selected.');
        }

        $this->authorizesTenantAccess($request, $tenantId);

        $data = $request->validated();

        $employee = Employee::query()
            ->with(['workShift', 'tenant.settings'])
            ->findOrFail($data['employee_id']);

        $this->validation->assertEmployeeBelongsToTenant($employee, $tenantId);

        $shift = $this->scheduleResolver->resolveShiftForAttendance(
            $data['shift_id'] ?? null,
            $employee,
        );
        $this->validation->assertShiftBelongsToTenant($shift, $tenantId);

        [$schedIn, $schedOut] = $this->scheduleResolver->scheduledWallClockTimes($shift);
        $scheduledCheckIn = $data['scheduled_check_in'] ?? $schedIn;
        $scheduledCheckOut = $data['scheduled_check_out'] ?? $schedOut;

        $attendanceDate = CarbonImmutable::parse($data['attendance_date'])->startOfDay();
        $isWeekend = $this->scheduleResolver->isConfiguredWeekend($attendanceDate, $shift);
        $isHoliday = isset($data['is_holiday'])
            ? (bool) $data['is_holiday']
            : $this->holidayService->isHoliday($tenantId, $attendanceDate);

        $checkIn = $this->wallClock($data['check_in_time'] ?? null);
        $checkOut = $this->wallClock($data['check_out_time'] ?? null);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('attendances', 'public');
        }

        $calcInput = AttendanceCalculationInput::fromPrimitives(
            attendanceDate: $attendanceDate,
            checkInTime: $checkIn,
            checkOutTime: $checkOut,
            shift: $shift,
            companySettings: $employee->tenant?->settings,
            isWeekend: $isWeekend,
            isHoliday: $isHoliday,
            status: AttendanceStatus::from($data['status']),
            breakMinutesDeduction: (int) ($data['break_minutes'] ?? 0),
            scheduledCheckInTime: $scheduledCheckIn ? $this->wallClock($scheduledCheckIn) : null,
            scheduledCheckOutTime: $scheduledCheckOut ? $this->wallClock($scheduledCheckOut) : null,
        );

        $metrics = $this->calculator->calculate($calcInput);

        Attendance::create([
            'tenant_id' => $tenantId,
            'employee_id' => $employee->id,
            'attendance_date' => $attendanceDate->toDateString(),
            'check_in_time' => $checkIn,
            'check_out_time' => $checkOut,
            ...$metrics->toAttendanceAttributes(),
            'break_minutes' => (int) ($data['break_minutes'] ?? 0),
            'status' => $data['status'],
            'shift_id' => $shift?->id,
            'scheduled_check_in' => $scheduledCheckIn ? $this->wallClock($scheduledCheckIn) : null,
            'scheduled_check_out' => $scheduledCheckOut ? $this->wallClock($scheduledCheckOut) : null,
            'attendance_source' => $data['attendance_source'],
            'comments' => $data['comments'] ?? null,
            'attachment_path' => $attachmentPath,
            'location_id' => $data['location_id'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'gps_accuracy' => $data['gps_accuracy'] ?? null,
            'address' => $data['address'] ?? null,
            'device_id' => $data['device_id'] ?? null,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr($request->userAgent() ?? '', 0, 1000),
            'created_by' => $request->user()->id,
            'is_manual_edit' => false,
            'edit_reason' => $data['edit_reason'] ?? null,
            'is_weekend' => $isWeekend,
            'is_holiday' => $isHoliday,
            'is_locked' => false,
        ]);

        return redirect()->route('attendances.index')->with('success', 'Attendance recorded.');
    }

    public function show(Request $request, Attendance $attendance): Response
    {
        $this->authorize('view', $attendance);

        $this->authorizesTenantAccess($request, $attendance->tenant_id);

        $attendance->load([
            'employee.department:id,name',
            'employee.location:id,name',
            'shift:id,name,type',
            'location:id,name',
            'creator:id,name',
            'updater:id,name',
            'approver:id,name',
        ]);

        return Inertia::render('attendance/Show', [
            'attendance' => $this->transformAttendance($attendance),
            'enumLabels' => $this->enumLabels(),
            'abilities' => [
                'update' => $request->user()->can('update', $attendance),
                'delete' => $request->user()->can('delete', $attendance),
                'approve' => $request->user()->can('approve', $attendance),
                'lock' => $request->user()->can('lock', $attendance),
            ],
        ]);
    }

    public function edit(Request $request, Attendance $attendance): Response
    {
        $this->authorize('update', $attendance);

        $this->authorizesTenantAccess($request, $attendance->tenant_id);

        $tenantId = $attendance->tenant_id;

        $attendance->load(['employee:id,english_name,arabic_name,employee_number']);

        return Inertia::render('attendance/Edit', [
            'attendance' => $this->transformAttendance($attendance),
            'filterOptions' => [
                'locations' => Location::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
                'shifts' => WorkShift::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
            ],
            'enumLabels' => $this->enumLabels(),
        ]);
    }

    public function update(UpdateAttendanceRequest $request, Attendance $attendance): RedirectResponse
    {
        $this->authorize('update', $attendance);

        $this->authorizesTenantAccess($request, $attendance->tenant_id);

        $this->validation->assertCanModify($request->user(), $attendance);

        $data = $request->validated();

        $employee = Employee::query()
            ->with(['workShift', 'tenant.settings'])
            ->findOrFail($attendance->employee_id);

        $shift = $this->scheduleResolver->resolveShiftForAttendance(
            $data['shift_id'] ?? $attendance->shift_id,
            $employee,
        );
        $this->validation->assertShiftBelongsToTenant($shift, (int) $attendance->tenant_id);

        [$schedIn, $schedOut] = $this->scheduleResolver->scheduledWallClockTimes($shift);

        $mergedSchedIn = $data['scheduled_check_in'] ?? $this->formatModelTime($attendance->scheduled_check_in) ?? $schedIn;
        $mergedSchedOut = $data['scheduled_check_out'] ?? $this->formatModelTime($attendance->scheduled_check_out) ?? $schedOut;

        $attendanceDate = CarbonImmutable::parse($attendance->attendance_date)->startOfDay();
        $isWeekend = $this->scheduleResolver->isConfiguredWeekend($attendanceDate, $shift);
        $isHoliday = array_key_exists('is_holiday', $data)
            ? (bool) $data['is_holiday']
            : $this->holidayService->isHoliday((int) $attendance->tenant_id, $attendanceDate);

        $checkIn = array_key_exists('check_in_time', $data)
            ? $this->wallClock($data['check_in_time'])
            : $this->wallClock($this->formatModelTime($attendance->check_in_time));
        $checkOut = array_key_exists('check_out_time', $data)
            ? $this->wallClock($data['check_out_time'])
            : $this->wallClock($this->formatModelTime($attendance->check_out_time));

        $calcInput = AttendanceCalculationInput::fromPrimitives(
            attendanceDate: $attendanceDate,
            checkInTime: $checkIn,
            checkOutTime: $checkOut,
            shift: $shift,
            companySettings: $employee->tenant?->settings,
            isWeekend: $isWeekend,
            isHoliday: $isHoliday,
            status: AttendanceStatus::from($data['status'] ?? $attendance->status->value),
            breakMinutesDeduction: (int) ($data['break_minutes'] ?? $attendance->break_minutes),
            scheduledCheckInTime: $mergedSchedIn ? $this->wallClock($mergedSchedIn) : null,
            scheduledCheckOutTime: $mergedSchedOut ? $this->wallClock($mergedSchedOut) : null,
        );

        $metrics = $this->calculator->calculate($calcInput);

        if ($request->hasFile('attachment')) {
            if ($attendance->attachment_path) {
                Storage::disk('public')->delete($attendance->attachment_path);
            }
            $data['attachment_path'] = $request->file('attachment')->store('attendances', 'public');
        }

        $attendance->fill([
            'check_in_time' => $checkIn,
            'check_out_time' => $checkOut,
            ...$metrics->toAttendanceAttributes(),
            'break_minutes' => (int) ($data['break_minutes'] ?? $attendance->break_minutes),
            'status' => $data['status'] ?? $attendance->status->value,
            'shift_id' => $data['shift_id'] ?? $attendance->shift_id,
            'scheduled_check_in' => $mergedSchedIn ? $this->wallClock($mergedSchedIn) : null,
            'scheduled_check_out' => $mergedSchedOut ? $this->wallClock($mergedSchedOut) : null,
            'attendance_source' => $data['attendance_source'] ?? $attendance->attendance_source->value,
            'comments' => $data['comments'] ?? $attendance->comments,
            'attachment_path' => $data['attachment_path'] ?? $attendance->attachment_path,
            'location_id' => array_key_exists('location_id', $data) ? $data['location_id'] : $attendance->location_id,
            'latitude' => $data['latitude'] ?? $attendance->latitude,
            'longitude' => $data['longitude'] ?? $attendance->longitude,
            'gps_accuracy' => $data['gps_accuracy'] ?? $attendance->gps_accuracy,
            'address' => $data['address'] ?? $attendance->address,
            'device_id' => $data['device_id'] ?? $attendance->device_id,
            'is_manual_edit' => (bool) ($data['is_manual_edit'] ?? $attendance->is_manual_edit),
            'edit_reason' => $data['edit_reason'] ?? $attendance->edit_reason,
            'is_weekend' => $isWeekend,
            'is_holiday' => $isHoliday,
            'updated_by' => $request->user()->id,
        ]);

        $attendance->save();

        return redirect()->route('attendances.show', $attendance)->with('success', 'Attendance updated.');
    }

    public function destroy(Request $request, Attendance $attendance): RedirectResponse
    {
        $this->authorize('delete', $attendance);

        $this->authorizesTenantAccess($request, $attendance->tenant_id);

        $this->validation->assertCanModify($request->user(), $attendance);

        $attendance->delete();

        return redirect()->route('attendances.index')->with('success', 'Attendance deleted.');
    }

    public function approve(Request $request, Attendance $attendance): RedirectResponse
    {
        $this->authorize('approve', $attendance);

        $this->authorizesTenantAccess($request, $attendance->tenant_id);

        $this->validation->assertCanModify($request->user(), $attendance);

        $attendance->forceFill([
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'updated_by' => $request->user()->id,
        ])->save();

        return redirect()->back()->with('success', 'Attendance approved.');
    }

    public function lock(Request $request, Attendance $attendance): RedirectResponse
    {
        $this->authorize('lock', $attendance);

        $this->authorizesTenantAccess($request, $attendance->tenant_id);

        $attendance->forceFill([
            'is_locked' => true,
            'updated_by' => $request->user()->id,
        ])->save();

        return redirect()->back()->with('success', 'Attendance locked.');
    }

    public function employeeHistory(IndexAttendanceRequest $request, Employee $employee): Response
    {
        $this->authorize('viewAny', Attendance::class);

        $this->authorizesTenantAccess($request, $employee->tenant_id);

        $filters = $request->validated();
        $from = isset($filters['date_from'])
            ? CarbonImmutable::parse($filters['date_from'])
            : CarbonImmutable::now()->subMonthsNoOverflow(3)->startOfMonth();
        $to = isset($filters['date_to'])
            ? CarbonImmutable::parse($filters['date_to'])
            : CarbonImmutable::now()->endOfMonth();

        $attendances = Attendance::query()
            ->where('employee_id', $employee->id)
            ->with(['shift:id,name'])
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->whereDate('attendance_date', '>=', $from->toDateString())
            ->whereDate('attendance_date', '<=', $to->toDateString())
            ->orderByDesc('attendance_date')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('attendance/EmployeeHistory', [
            'employee' => $employee->only(['id', 'english_name', 'arabic_name', 'employee_number']),
            'attendances' => $attendances,
            'filters' => [
                'status' => $filters['status'] ?? null,
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
            ],
            'enumLabels' => $this->enumLabels(),
        ]);
    }

    private function authorizesTenantAccess(Request $request, int $tenantId): void
    {
        $tenant = Tenant::findOrFail($tenantId);
        if (! $request->user()->canAccessTenant($tenant)) {
            abort(403, 'You do not have access to this tenant.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyIndexPayload(): array
    {
        return [
            'attendances' => [],
            'summary' => [
                'present' => 0,
                'absent' => 0,
                'late' => 0,
                'overtime_records' => 0,
                'missing_checkout' => 0,
            ],
            'filters' => [
                'employee_id' => null,
                'department_id' => null,
                'business_unit_id' => null,
                'location_id' => null,
                'status' => null,
                'attendance_source' => null,
                'date_from' => CarbonImmutable::now()->startOfMonth()->toDateString(),
                'date_to' => CarbonImmutable::now()->endOfMonth()->toDateString(),
            ],
            'filterOptions' => [
                'employees' => [],
                'departments' => [],
                'businessUnits' => [],
                'locations' => [],
            ],
            'enumLabels' => $this->enumLabels(),
            'abilities' => [
                'create' => false,
            ],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function enumLabels(): array
    {
        return [
            'status' => collect(AttendanceStatus::cases())->mapWithKeys(
                fn ($c) => [$c->value => $c->label()],
            )->all(),
            'attendance_source' => collect(AttendanceSource::cases())->mapWithKeys(
                fn ($c) => [$c->value => $c->label()],
            )->all(),
        ];
    }

    /**
     * @return array{employees: Collection, departments: Collection, businessUnits: Collection, locations: Collection}
     */
    private function filterOptions(int $tenantId): array
    {
        $tenant = Tenant::with('children')->find($tenantId);

        $businessUnits = collect();
        if ($tenant) {
            $businessUnits = Tenant::query()
                ->where('parent_id', $tenant->id)
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        return [
            'employees' => Employee::where('tenant_id', $tenantId)->orderBy('english_name')->get(['id', 'english_name', 'employee_number']),
            'departments' => Department::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
            'businessUnits' => $businessUnits,
            'locations' => Location::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformAttendance(Attendance $attendance): array
    {
        return [
            'id' => $attendance->id,
            'attendance_date' => $attendance->attendance_date->toDateString(),
            'check_in_time' => $this->formatModelTime($attendance->check_in_time),
            'check_out_time' => $this->formatModelTime($attendance->check_out_time),
            'worked_minutes' => $attendance->worked_minutes,
            'total_late_minutes' => $attendance->total_late_minutes,
            'total_early_leave_minutes' => $attendance->total_early_leave_minutes,
            'overtime_minutes' => $attendance->overtime_minutes,
            'break_minutes' => $attendance->break_minutes,
            'status' => $attendance->status->value,
            'shift_id' => $attendance->shift_id,
            'scheduled_check_in' => $this->formatModelTime($attendance->scheduled_check_in),
            'scheduled_check_out' => $this->formatModelTime($attendance->scheduled_check_out),
            'attendance_source' => $attendance->attendance_source->value,
            'comments' => $attendance->comments,
            'attachment_url' => $attendance->attachment_path
                ? Storage::disk('public')->url($attendance->attachment_path)
                : null,
            'location_id' => $attendance->location_id,
            'latitude' => $attendance->latitude,
            'longitude' => $attendance->longitude,
            'gps_accuracy' => $attendance->gps_accuracy,
            'address' => $attendance->address,
            'device_id' => $attendance->device_id,
            'ip_address' => $attendance->ip_address,
            'user_agent' => $attendance->user_agent,
            'approved_at' => $attendance->approved_at?->toIso8601String(),
            'is_manual_edit' => $attendance->is_manual_edit,
            'edit_reason' => $attendance->edit_reason,
            'is_weekend' => $attendance->is_weekend,
            'is_holiday' => $attendance->is_holiday,
            'is_locked' => $attendance->is_locked,
            'payroll_exported_at' => $attendance->payroll_exported_at?->toIso8601String(),
            'employee' => $attendance->employee,
            'shift' => $attendance->shift,
            'location' => $attendance->location,
            'creator' => $attendance->creator,
            'updater' => $attendance->updater,
            'approver' => $attendance->approver,
        ];
    }

    private function formatModelTime(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        return is_string($value) ? (strlen($value) > 5 ? substr($value, 0, 5) : $value) : null;
    }

    private function wallClock(?string $time): ?string
    {
        if ($time === null || $time === '') {
            return null;
        }
        if (preg_match('/^\d{2}:\d{2}$/', $time)) {
            return $time.':00';
        }

        return $time;
    }
}

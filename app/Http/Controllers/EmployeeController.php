<?php

namespace App\Http\Controllers;

use App\Enums\ContractType;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Enums\ShiftType;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Reader\Xls as XlsReader;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'department_id' => ['nullable', 'integer'],
            'location_id' => ['nullable', 'integer'],
            'work_shift_id' => ['nullable', 'integer'],
        ]);

        $tenantId = $request->user()->current_tenant_id;

        if (! $tenantId) {
            return Inertia::render('employees/Index', [
                'employees' => [],
                'filters' => ['search' => null, 'department_id' => null, 'location_id' => null, 'work_shift_id' => null],
                'departments' => [],
                'locations' => [],
                'workShifts' => [],
            ]);
        }

        $this->authorizesTenantAccess($request, $tenantId, PermissionModule::Employees, PermissionAction::View);

        $employees = Employee::query()
            ->where('tenant_id', $tenantId)
            ->with([
                'department:id,name',
                'location:id,name',
            ])
            ->when($request->search, fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('english_name', 'like', "%{$search}%")
                    ->orWhere('arabic_name', 'like', "%{$search}%")
                    ->orWhere('mobile_number', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%");
            }))
            ->when($request->integer('department_id'), fn ($q, $id) => $q->where('department_id', $id))
            ->when($request->integer('location_id'), fn ($q, $id) => $q->where('location_id', $id))
            ->when($request->integer('work_shift_id'), fn ($q, $id) => $q->where('work_shift_id', $id))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('employees/Index', [
            'employees' => $employees,
            'filters' => [
                'search' => $request->input('search'),
                'department_id' => $request->integer('department_id') ?: null,
                'location_id' => $request->integer('location_id') ?: null,
                'work_shift_id' => $request->integer('work_shift_id') ?: null,
            ],
            'departments' => Department::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
            'locations' => Location::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
            'workShifts' => WorkShift::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('employees/Create', [
            'options' => $this->formOptions(),
        ]);
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        if (! $tenantId) {
            return redirect()->back()->with('error', 'No active company selected.');
        }

        $this->authorizesTenantAccess($request, $tenantId, PermissionModule::Employees, PermissionAction::Create);

        Employee::create([
            'tenant_id' => $tenantId,
            'created_by' => $request->user()->id,
            ...$request->validated(),
        ]);

        return redirect()->route('employees.index')->with('success', 'Employee created successfully.');
    }

    public function edit(Employee $employee): Response
    {
        $this->authorizesTenantAccess(request(), $employee->tenant_id, PermissionModule::Employees, PermissionAction::View);

        return Inertia::render('employees/Edit', [
            'employee' => $employee->only([
                'id', 'arabic_name', 'english_name',
                'mobile_country_code', 'mobile_number',
                'email', 'nationality', 'marital_status', 'birth_date', 'gender', 'religion',
                'job_title_ar', 'job_title_en', 'employee_number',
                'social_security_number', 'id_number',
                'working_start_date', 'contract_end_date', 'contract_type',
                'department_id', 'location_id',
                'check_biometrics', 'send_reminders',
                'allow_remote_checkin', 'allow_any_location_checkin',
                'work_shift_id',
            ]),
            'options' => $this->formOptions(),
        ]);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $this->authorizesTenantAccess($request, $employee->tenant_id, PermissionModule::Employees, PermissionAction::Edit);

        $employee->update($request->validated());

        return redirect()->route('employees.index')->with('success', 'Employee updated successfully.');
    }

    public function destroy(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorizesTenantAccess($request, $employee->tenant_id, PermissionModule::Employees, PermissionAction::Delete);

        $employee->delete();

        return redirect()->route('employees.index')->with('success', 'Employee deleted successfully.');
    }

    public function importForm(): Response
    {
        return Inertia::render('employees/Import');
    }

    public function importTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'employee_number',
            'english_name',
            'arabic_name',
            'mobile',
            'email',
            'job_title',
            'join_date',
            'id_number',
        ];

        foreach ($headers as $col => $header) {
            $cell = chr(65 + $col).'1';
            $sheet->setCellValue($cell, $header);
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $sheet->getColumnDimensionByColumn($col + 1)->setAutoSize(true);
        }

        $examples = [
            ['EMP001', 'John Smith', 'جون سميث', '+966 501234567', 'john@example.com', 'Software Engineer', '2024-01-15', '1234567890'],
            ['EMP002', 'Jane Doe', 'جين دو', '+966 502345678', 'jane@example.com', 'HR Manager', '2024-02-01', '0987654321'],
            ['EMP003', 'Ahmed Ali', 'أحمد علي', '+966 503456789', 'ahmed@example.com', 'Accountant', '2024-03-10', '1122334455'],
        ];

        foreach ($examples as $rowIndex => $row) {
            foreach ($row as $col => $value) {
                $sheet->setCellValue(chr(65 + $col).($rowIndex + 2), $value);
            }
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 'employees_import_template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function importStore(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ]);

        $extension = strtolower($request->file('file')->getClientOriginalExtension());
        $reader = $extension === 'xls' ? new XlsReader : new XlsxReader;
        $spreadsheet = $reader->load($request->file('file')->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        if (empty($rows)) {
            return redirect()->back()->with('error', 'The uploaded file is empty.');
        }

        $headerRow = array_map(fn ($h) => strtolower(trim((string) $h)), $rows[0]);

        $requiredColumns = ['english_name', 'arabic_name', 'mobile'];
        $missing = array_diff($requiredColumns, $headerRow);

        if (! empty($missing)) {
            return redirect()->back()->with('error', 'Missing required columns: '.implode(', ', $missing).'. Please download and use the provided template.');
        }

        $columnMap = array_flip($headerRow);
        $tenantId = $request->user()->current_tenant_id;

        if (! $tenantId) {
            return redirect()->back()->with('error', 'No active company selected.');
        }

        $this->authorizesTenantAccess($request, $tenantId, PermissionModule::Employees, PermissionAction::Create);

        // Pre-load existing values for duplicate detection (lowercased for case-insensitive comparison)
        $existingNumbers = Employee::where('tenant_id', $tenantId)
            ->whereNotNull('employee_number')
            ->pluck('employee_number')
            ->mapWithKeys(fn ($v) => [strtolower(trim($v)) => true])
            ->all();

        $existingEmails = Employee::where('tenant_id', $tenantId)
            ->whereNotNull('email')
            ->pluck('email')
            ->mapWithKeys(fn ($v) => [strtolower(trim($v)) => true])
            ->all();

        $existingMobiles = Employee::where('tenant_id', $tenantId)
            ->selectRaw('CONCAT(LOWER(mobile_country_code), LOWER(mobile_number)) as mobile_key')
            ->pluck('mobile_key')
            ->mapWithKeys(fn ($v) => [$v => true])
            ->all();

        $dataRows = array_slice($rows, 1);
        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($dataRows as $i => $row) {
            $rowNum = $i + 2;
            $englishName = trim((string) ($row[$columnMap['english_name']] ?? ''));
            $arabicName = trim((string) ($row[$columnMap['arabic_name']] ?? ''));
            $rawMobile = trim((string) ($row[$columnMap['mobile']] ?? ''));

            // Skip completely empty rows silently
            if ($englishName === '' && $arabicName === '' && $rawMobile === '') {
                continue;
            }

            $rowErrors = [];

            if ($englishName === '') {
                $rowErrors[] = 'english_name is required';
            }

            if ($arabicName === '') {
                $rowErrors[] = 'arabic_name is required';
            }

            if ($rawMobile === '') {
                $rowErrors[] = 'mobile is required';
            }

            $countryCode = '+966';
            $mobileNumber = '';
            $mobileKey = '';

            if ($rawMobile !== '') {
                [$countryCode, $mobileNumber] = $this->parseMobile($rawMobile);

                if ($mobileNumber === '') {
                    $rowErrors[] = "mobile \"{$rawMobile}\" is not a valid number";
                } else {
                    $mobileKey = strtolower($countryCode.$mobileNumber);
                    if (isset($existingMobiles[$mobileKey])) {
                        $rowErrors[] = "mobile {$rawMobile} already exists";
                    }
                }
            }

            $employeeNumber = isset($columnMap['employee_number']) ? trim((string) ($row[$columnMap['employee_number']] ?? '')) : '';
            if ($employeeNumber !== '' && isset($existingNumbers[strtolower($employeeNumber)])) {
                $rowErrors[] = "employee_number \"{$employeeNumber}\" already exists";
            }

            $email = isset($columnMap['email']) ? trim((string) ($row[$columnMap['email']] ?? '')) : '';
            if ($email !== '' && isset($existingEmails[strtolower($email)])) {
                $rowErrors[] = "email \"{$email}\" already exists";
            }

            if (! empty($rowErrors)) {
                $errors[] = "Row {$rowNum}: ".implode(', ', $rowErrors).'.';
                $skipped++;

                continue;
            }

            $employee = [
                'tenant_id' => $tenantId,
                'created_by' => $request->user()->id,
                'english_name' => $englishName,
                'arabic_name' => $arabicName,
                'mobile_country_code' => $countryCode,
                'mobile_number' => $mobileNumber,
            ];

            if ($employeeNumber !== '') {
                $employee['employee_number'] = $employeeNumber;
            }

            if ($email !== '') {
                $employee['email'] = $email;
            }

            if (isset($columnMap['job_title'])) {
                $val = trim((string) ($row[$columnMap['job_title']] ?? ''));
                if ($val !== '') {
                    $employee['job_title_en'] = $val;
                }
            }

            if (isset($columnMap['join_date'])) {
                $val = trim((string) ($row[$columnMap['join_date']] ?? ''));
                if ($val !== '') {
                    try {
                        $employee['working_start_date'] = Carbon::parse($val)->toDateString();
                    } catch (\Throwable) {
                    }
                }
            }

            if (isset($columnMap['id_number'])) {
                $val = trim((string) ($row[$columnMap['id_number']] ?? ''));
                if ($val !== '') {
                    $employee['id_number'] = $val;
                }
            }

            Employee::create($employee);
            $imported++;

            // Track newly created records so within-file duplicates are caught on subsequent rows
            if ($mobileKey !== '') {
                $existingMobiles[$mobileKey] = true;
            }
            if ($employeeNumber !== '') {
                $existingNumbers[strtolower($employeeNumber)] = true;
            }
            if ($email !== '') {
                $existingEmails[strtolower($email)] = true;
            }
        }

        $successMessage = "{$imported} employee(s) imported successfully.";
        if ($skipped > 0) {
            $successMessage .= " {$skipped} row(s) skipped due to errors.";
        }

        if (! empty($errors)) {
            return redirect()->route('employees.import')
                ->with('success', $successMessage)
                ->with('import_errors', $errors);
        }

        return redirect()->route('employees.index')->with('success', $successMessage);
    }

    /** @return array{string, string} */
    private function parseMobile(string $mobile): array
    {
        $mobile = trim(preg_replace('/\s+/', ' ', $mobile));

        if (preg_match('/^(\+\d{1,3})\s*(\d+)$/', $mobile, $m)) {
            return [$m[1], ltrim($m[2], '0')];
        }

        return ['+966', ltrim($mobile, '0')];
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        $tenantId = request()->user()->current_tenant_id;

        $workShifts = WorkShift::query()
            ->where('tenant_id', $tenantId)
            ->withCount('employees')
            ->orderBy('name')
            ->get()
            ->map(fn (WorkShift $shift) => [
                'id' => $shift->id,
                'name' => $shift->name,
                'type' => $shift->type->value,
                'employees_count' => $shift->employees_count,
                'working_hours_label' => $this->workingHoursLabel($shift),
                'off_days_label' => $this->offDaysLabel($shift->weekends ?? []),
            ]);

        return [
            'genders' => collect(Gender::cases())->map(fn ($e) => ['value' => $e->value, 'label' => $e->label()]),
            'marital_statuses' => collect(MaritalStatus::cases())->map(fn ($e) => ['value' => $e->value, 'label' => $e->label()]),
            'contract_types' => collect(ContractType::cases())->map(fn ($e) => ['value' => $e->value, 'label' => $e->label()]),
            'religions' => [
                ['value' => 'muslim', 'label' => 'Muslim'],
                ['value' => 'christian', 'label' => 'Christian'],
                ['value' => 'jewish', 'label' => 'Jewish'],
                ['value' => 'hindu', 'label' => 'Hindu'],
                ['value' => 'buddhist', 'label' => 'Buddhist'],
                ['value' => 'other', 'label' => 'Other'],
            ],
            'departments' => Department::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
            'locations' => Location::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
            'work_shifts' => $workShifts,
        ];
    }

    private function workingHoursLabel(WorkShift $shift): string
    {
        if ($shift->type === ShiftType::Fixed && $shift->checkin_time && $shift->checkout_time) {
            $minutes = Carbon::createFromFormat('H:i', $shift->checkin_time)
                ->diffInMinutes(Carbon::createFromFormat('H:i', $shift->checkout_time));
            $h = intdiv($minutes, 60);
            $m = $minutes % 60;

            return $m > 0 ? "{$h}h {$m}m" : "{$h}h";
        }

        if ($shift->type === ShiftType::Flexible) {
            $h = $shift->working_hours ?? 0;
            $m = $shift->working_minutes ?? 0;

            return $m > 0 ? "{$h}h {$m}m" : "{$h}h";
        }

        return '—';
    }

    /** @param array<int, string> $weekends */
    private function offDaysLabel(array $weekends): string
    {
        $labels = [
            'monday' => 'Mon', 'tuesday' => 'Tue', 'wednesday' => 'Wed',
            'thursday' => 'Thu', 'friday' => 'Fri', 'saturday' => 'Sat', 'sunday' => 'Sun',
        ];

        $result = collect($weekends)->map(fn ($d) => $labels[$d] ?? $d)->implode(', ');

        return $result ?: '—';
    }

    private function authorizesTenantAccess(Request $request, int $tenantId, ?PermissionModule $module = null, ?PermissionAction $action = null): void
    {
        $tenant = Tenant::findOrFail($tenantId);

        if (! $request->user()->canAccessTenant($tenant)) {
            abort(403, 'You do not have access to this tenant.');
        }

        if ($module !== null && ! $request->user()->canPerform($tenant, $module, $action)) {
            abort(403, 'You do not have permission to perform this action.');
        }
    }
}

<?php

use App\Models\Attendance;
use App\Models\CompanySetting;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows overtime report page for authenticated admin', function () {
    $tenant = Tenant::factory()->create();
    CompanySetting::create(['tenant_id' => $tenant->id]);
    $user = User::factory()->admin($tenant)->create();

    $this->actingAs($user)
        ->get(route('reports.overtime'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/Overtime')
            ->has('employees')
            ->has('summary')
            ->has('filters')
            ->has('filterOptions'),
        );
});

it('redirects unauthenticated users from overtime report', function () {
    $this->get(route('reports.overtime'))
        ->assertRedirect(route('login'));
});

it('only includes employees with overtime_minutes > 0', function () {
    $tenant = Tenant::factory()->create();
    CompanySetting::create(['tenant_id' => $tenant->id]);
    $user = User::factory()->admin($tenant)->create();

    $employee = Employee::factory()->create([
        'tenant_id' => $tenant->id,
        'created_by' => $user->id,
    ]);
    $noOtEmployee = Employee::factory()->create([
        'tenant_id' => $tenant->id,
        'created_by' => $user->id,
    ]);

    Attendance::factory()->forEmployee($employee)->create([
        'overtime_minutes' => 45,
        'check_out_time' => '18:45:00',
        'created_by' => $user->id,
    ]);
    Attendance::factory()->forEmployee($noOtEmployee)->create([
        'overtime_minutes' => 0,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('reports.overtime'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/Overtime')
            ->has('employees', 1)
            ->where('employees.0.employee_id', $employee->id)
            ->where('employees.0.total_overtime_minutes', 45),
        );
});

it('sums overtime minutes across multiple records for the same employee', function () {
    $tenant = Tenant::factory()->create();
    CompanySetting::create(['tenant_id' => $tenant->id]);
    $user = User::factory()->admin($tenant)->create();
    $employee = Employee::factory()->create([
        'tenant_id' => $tenant->id,
        'created_by' => $user->id,
    ]);

    Attendance::factory()->forEmployee($employee)->create([
        'attendance_date' => now()->subDays(1)->toDateString(),
        'overtime_minutes' => 30,
        'created_by' => $user->id,
    ]);
    Attendance::factory()->forEmployee($employee)->create([
        'attendance_date' => now()->subDays(2)->toDateString(),
        'overtime_minutes' => 60,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('reports.overtime'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('employees.0.total_overtime_minutes', 90)
            ->where('summary.total_overtime_minutes', 90)
            ->where('summary.employee_count', 1),
        );
});

it('filters overtime report by department', function () {
    $tenant = Tenant::factory()->create();
    CompanySetting::create(['tenant_id' => $tenant->id]);
    $user = User::factory()->admin($tenant)->create();

    $dept = Department::factory()->create(['tenant_id' => $tenant->id]);
    $otherDept = Department::factory()->create(['tenant_id' => $tenant->id]);

    $empInDept = Employee::factory()->create([
        'tenant_id' => $tenant->id,
        'department_id' => $dept->id,
        'created_by' => $user->id,
    ]);
    $empOutDept = Employee::factory()->create([
        'tenant_id' => $tenant->id,
        'department_id' => $otherDept->id,
        'created_by' => $user->id,
    ]);

    Attendance::factory()->forEmployee($empInDept)->create([
        'overtime_minutes' => 30,
        'created_by' => $user->id,
    ]);
    Attendance::factory()->forEmployee($empOutDept)->create([
        'overtime_minutes' => 45,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('reports.overtime', ['department_id' => $dept->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('employees', 1)
            ->where('employees.0.employee_id', $empInDept->id),
        );
});

it('filters overtime report by location', function () {
    $tenant = Tenant::factory()->create();
    CompanySetting::create(['tenant_id' => $tenant->id]);
    $user = User::factory()->admin($tenant)->create();

    $loc = Location::factory()->create(['tenant_id' => $tenant->id]);
    $otherLoc = Location::factory()->create(['tenant_id' => $tenant->id]);

    $empAtLoc = Employee::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $loc->id,
        'created_by' => $user->id,
    ]);
    $empOther = Employee::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $otherLoc->id,
        'created_by' => $user->id,
    ]);

    Attendance::factory()->forEmployee($empAtLoc)->create([
        'overtime_minutes' => 20,
        'created_by' => $user->id,
    ]);
    Attendance::factory()->forEmployee($empOther)->create([
        'overtime_minutes' => 50,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('reports.overtime', ['location_id' => $loc->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('employees', 1)
            ->where('employees.0.employee_id', $empAtLoc->id),
        );
});

it('excludes overtime records outside the date range', function () {
    $tenant = Tenant::factory()->create();
    CompanySetting::create(['tenant_id' => $tenant->id]);
    $user = User::factory()->admin($tenant)->create();
    $employee = Employee::factory()->create([
        'tenant_id' => $tenant->id,
        'created_by' => $user->id,
    ]);

    Attendance::factory()->forEmployee($employee)->create([
        'attendance_date' => '2026-01-15',
        'overtime_minutes' => 60,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('reports.overtime', ['date_from' => '2026-02-01', 'date_to' => '2026-02-28']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('employees', 0));
});

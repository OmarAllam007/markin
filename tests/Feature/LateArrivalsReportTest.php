<?php

use App\Models\Attendance;
use App\Models\CompanySetting;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows late arrivals report page for authenticated admin', function () {
    $tenant = Tenant::factory()->create();
    CompanySetting::create(['tenant_id' => $tenant->id]);
    $user = User::factory()->admin($tenant)->create();

    $this->actingAs($user)
        ->get(route('reports.late-arrivals'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/LateArrivals')
            ->has('employees')
            ->has('summary')
            ->has('filters')
            ->has('filterOptions'),
        );
});

it('redirects unauthenticated users from late arrivals report', function () {
    $this->get(route('reports.late-arrivals'))
        ->assertRedirect(route('login'));
});

it('only includes employees with total_late_minutes >= min_late_minutes', function () {
    $tenant = Tenant::factory()->create();
    CompanySetting::create(['tenant_id' => $tenant->id]);
    $user = User::factory()->admin($tenant)->create();

    $lateEmployee = Employee::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);
    $onTimeEmployee = Employee::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);

    Attendance::factory()->forEmployee($lateEmployee)->create([
        'total_late_minutes' => 15,
        'created_by' => $user->id,
    ]);
    Attendance::factory()->forEmployee($onTimeEmployee)->create([
        'total_late_minutes' => 0,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('reports.late-arrivals'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/LateArrivals')
            ->has('employees', 1)
            ->where('employees.0.employee_id', $lateEmployee->id)
            ->where('employees.0.total_late_minutes', 15),
        );
});

it('filters late arrivals by department', function () {
    $tenant = Tenant::factory()->create();
    CompanySetting::create(['tenant_id' => $tenant->id]);
    $user = User::factory()->admin($tenant)->create();

    $department = Department::factory()->create(['tenant_id' => $tenant->id]);

    $empInDept = Employee::factory()->create([
        'tenant_id' => $tenant->id,
        'created_by' => $user->id,
        'department_id' => $department->id,
    ]);
    $empNoDept = Employee::factory()->create([
        'tenant_id' => $tenant->id,
        'created_by' => $user->id,
        'department_id' => null,
    ]);

    Attendance::factory()->forEmployee($empInDept)->create(['total_late_minutes' => 10, 'created_by' => $user->id]);
    Attendance::factory()->forEmployee($empNoDept)->create(['total_late_minutes' => 20, 'created_by' => $user->id]);

    $this->actingAs($user)
        ->get(route('reports.late-arrivals', ['department_id' => $department->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('employees', 1)
            ->where('employees.0.employee_id', $empInDept->id),
        );
});

it('respects min_late_minutes filter', function () {
    $tenant = Tenant::factory()->create();
    CompanySetting::create(['tenant_id' => $tenant->id]);
    $user = User::factory()->admin($tenant)->create();

    $slightlyLate = Employee::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);
    $veryLate = Employee::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);

    Attendance::factory()->forEmployee($slightlyLate)->create(['total_late_minutes' => 3, 'created_by' => $user->id]);
    Attendance::factory()->forEmployee($veryLate)->create(['total_late_minutes' => 20, 'created_by' => $user->id]);

    $this->actingAs($user)
        ->get(route('reports.late-arrivals', ['min_late_minutes' => 10]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('employees', 1)
            ->where('employees.0.employee_id', $veryLate->id),
        );
});

it('includes filter options for locations departments and shifts', function () {
    $tenant = Tenant::factory()->create();
    CompanySetting::create(['tenant_id' => $tenant->id]);
    $user = User::factory()->admin($tenant)->create();

    $this->actingAs($user)
        ->get(route('reports.late-arrivals'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('filterOptions.locations')
            ->has('filterOptions.departments')
            ->has('filterOptions.shifts'),
        );
});

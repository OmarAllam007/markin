<?php

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Models\Attendance;
use App\Models\CompanySetting;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function scopedReportsViewer(Tenant $tenant): User
{
    $user = User::factory()->withTenant($tenant)->create();
    UserPermission::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'module' => PermissionModule::Reports->value,
        'action' => PermissionAction::View->value,
    ]);

    return $user;
}

it('only includes overtime for employees in the scoped user\'s department and location', function () {
    $tenant = Tenant::factory()->create();
    CompanySetting::create(['tenant_id' => $tenant->id]);
    $department = Department::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $otherDepartment = Department::factory()->create(['tenant_id' => $tenant->id]);

    $inScope = Employee::factory()->create(['tenant_id' => $tenant->id, 'department_id' => $department->id, 'location_id' => $location->id]);
    $outOfScope = Employee::factory()->create(['tenant_id' => $tenant->id, 'department_id' => $otherDepartment->id, 'location_id' => $location->id]);

    Attendance::factory()->create(['tenant_id' => $tenant->id, 'employee_id' => $inScope->id, 'attendance_date' => now()->toDateString(), 'overtime_minutes' => 30]);
    Attendance::factory()->create(['tenant_id' => $tenant->id, 'employee_id' => $outOfScope->id, 'attendance_date' => now()->toDateString(), 'overtime_minutes' => 30]);

    $user = scopedReportsViewer($tenant);
    $user->departmentAccess()->attach($department->id, ['tenant_id' => $tenant->id]);
    $user->locationAccess()->attach($location->id, ['tenant_id' => $tenant->id]);

    $this->actingAs($user)->get(route('reports.overtime'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/Overtime')
            ->has('employees', 1)
            ->where('employees.0.employee_id', $inScope->id)
        );
});

it('shows a scoped user with zero department access no overtime records at all', function () {
    $tenant = Tenant::factory()->create();
    CompanySetting::create(['tenant_id' => $tenant->id]);
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);
    Attendance::factory()->create(['tenant_id' => $tenant->id, 'employee_id' => $employee->id, 'attendance_date' => now()->toDateString(), 'overtime_minutes' => 30]);

    $user = scopedReportsViewer($tenant);

    $this->actingAs($user)->get(route('reports.overtime'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('employees', 0));
});

it('shows an admin every overtime record regardless of department or location', function () {
    $tenant = Tenant::factory()->create();
    CompanySetting::create(['tenant_id' => $tenant->id]);
    $employeeA = Employee::factory()->create(['tenant_id' => $tenant->id]);
    $employeeB = Employee::factory()->create(['tenant_id' => $tenant->id]);
    Attendance::factory()->create(['tenant_id' => $tenant->id, 'employee_id' => $employeeA->id, 'attendance_date' => now()->toDateString(), 'overtime_minutes' => 30]);
    Attendance::factory()->create(['tenant_id' => $tenant->id, 'employee_id' => $employeeB->id, 'attendance_date' => now()->toDateString(), 'overtime_minutes' => 30]);

    $admin = User::factory()->admin($tenant)->create();

    $this->actingAs($admin)->get(route('reports.overtime'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('employees', 2));
});

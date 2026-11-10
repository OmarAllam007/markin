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

function scopedAttendanceViewer(Tenant $tenant): User
{
    $user = User::factory()->withTenant($tenant)->create();
    UserPermission::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'module' => PermissionModule::Attendance->value,
        'action' => PermissionAction::View->value,
    ]);

    return $user;
}

it('shows a scoped non-admin only attendance for employees in their assigned department and location', function () {
    $tenant = Tenant::factory()->create();
    CompanySetting::create(['tenant_id' => $tenant->id]);
    $department = Department::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $otherDepartment = Department::factory()->create(['tenant_id' => $tenant->id]);

    $inScope = Employee::factory()->create(['tenant_id' => $tenant->id, 'department_id' => $department->id, 'location_id' => $location->id]);
    $outOfScope = Employee::factory()->create(['tenant_id' => $tenant->id, 'department_id' => $otherDepartment->id, 'location_id' => $location->id]);

    Attendance::factory()->create(['tenant_id' => $tenant->id, 'employee_id' => $inScope->id]);
    Attendance::factory()->create(['tenant_id' => $tenant->id, 'employee_id' => $outOfScope->id]);

    $user = scopedAttendanceViewer($tenant);
    $user->departmentAccess()->attach($department->id, ['tenant_id' => $tenant->id]);
    $user->locationAccess()->attach($location->id, ['tenant_id' => $tenant->id]);

    $this->actingAs($user)->get(route('attendances.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('attendance/Index')
            ->has('attendances.data', 1)
            ->where('attendances.data.0.employee.id', $inScope->id)
        );
});

it('shows a scoped non-admin with zero assigned departments no attendance at all', function () {
    $tenant = Tenant::factory()->create();
    CompanySetting::create(['tenant_id' => $tenant->id]);
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);
    Attendance::factory()->create(['tenant_id' => $tenant->id, 'employee_id' => $employee->id]);

    $user = scopedAttendanceViewer($tenant);

    $this->actingAs($user)->get(route('attendances.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('attendances.data', 0));
});

it('shows an admin every attendance record regardless of department or location', function () {
    $tenant = Tenant::factory()->create();
    CompanySetting::create(['tenant_id' => $tenant->id]);
    $employeeA = Employee::factory()->create(['tenant_id' => $tenant->id]);
    $employeeB = Employee::factory()->create(['tenant_id' => $tenant->id]);
    Attendance::factory()->create(['tenant_id' => $tenant->id, 'employee_id' => $employeeA->id]);
    Attendance::factory()->create(['tenant_id' => $tenant->id, 'employee_id' => $employeeB->id]);

    $admin = User::factory()->admin($tenant)->create();

    $this->actingAs($admin)->get(route('attendances.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('attendances.data', 2));
});

it('blocks viewing the attendance history of an out-of-scope employee', function () {
    $tenant = Tenant::factory()->create();
    CompanySetting::create(['tenant_id' => $tenant->id]);
    $department = Department::factory()->create(['tenant_id' => $tenant->id]);
    $otherDepartment = Department::factory()->create(['tenant_id' => $tenant->id]);
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id, 'department_id' => $otherDepartment->id]);

    $user = scopedAttendanceViewer($tenant);
    $user->departmentAccess()->attach($department->id, ['tenant_id' => $tenant->id]);

    $this->actingAs($user)->get(route('employees.attendance.index', $employee))->assertForbidden();
});

it('allows viewing the attendance history of an in-scope employee', function () {
    $tenant = Tenant::factory()->create();
    CompanySetting::create(['tenant_id' => $tenant->id]);
    $department = Department::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id, 'department_id' => $department->id, 'location_id' => $location->id]);

    $user = scopedAttendanceViewer($tenant);
    $user->departmentAccess()->attach($department->id, ['tenant_id' => $tenant->id]);
    $user->locationAccess()->attach($location->id, ['tenant_id' => $tenant->id]);

    $this->actingAs($user)->get(route('employees.attendance.index', $employee))->assertOk();
});

<?php

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
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

function grantAttendancePermission(User $user, Tenant $tenant, PermissionAction $action): void
{
    UserPermission::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'module' => PermissionModule::Attendance->value,
        'action' => $action->value,
    ]);
}

function attendancePayload(Employee $employee): array
{
    return [
        'employee_id' => $employee->id,
        'attendance_date' => now()->toDateString(),
        'check_in_time' => '09:00',
        'check_out_time' => '17:00',
        'break_minutes' => 60,
        'status' => AttendanceStatus::Present->value,
        'attendance_source' => AttendanceSource::Manual->value,
    ];
}

// --- index / view ---

it('blocks a non-admin without the View permission from the attendance list', function () {
    $tenant = Tenant::factory()->create();
    CompanySetting::create(['tenant_id' => $tenant->id]);
    $user = User::factory()->withTenant($tenant)->create();

    $this->actingAs($user)->get(route('attendances.index'))->assertForbidden();
});

it('allows a non-admin with the View permission to see the attendance list', function () {
    $tenant = Tenant::factory()->create();
    CompanySetting::create(['tenant_id' => $tenant->id]);
    $user = User::factory()->withTenant($tenant)->create();
    grantAttendancePermission($user, $tenant, PermissionAction::View);

    $this->actingAs($user)->get(route('attendances.index'))->assertOk();
});

// --- create / store ---

it('blocks a non-admin without the Create permission from recording attendance', function () {
    $tenant = Tenant::factory()->create();
    CompanySetting::create(['tenant_id' => $tenant->id]);
    $user = User::factory()->withTenant($tenant)->create();
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)->post(route('attendances.store'), attendancePayload($employee))->assertForbidden();
    expect(Attendance::count())->toBe(0);
});

it('allows a non-admin with the Create permission to record attendance for an in-scope employee', function () {
    $tenant = Tenant::factory()->create();
    CompanySetting::create(['tenant_id' => $tenant->id]);
    $department = Department::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->withTenant($tenant)->create();
    grantAttendancePermission($user, $tenant, PermissionAction::Create);
    $user->departmentAccess()->attach($department->id, ['tenant_id' => $tenant->id]);
    $user->locationAccess()->attach($location->id, ['tenant_id' => $tenant->id]);
    $employee = Employee::factory()->create([
        'tenant_id' => $tenant->id,
        'department_id' => $department->id,
        'location_id' => $location->id,
    ]);

    $this->actingAs($user)->post(route('attendances.store'), attendancePayload($employee))
        ->assertRedirect(route('attendances.index'));
    expect(Attendance::count())->toBe(1);
});

it('blocks a non-admin with the Create permission from recording attendance for an out-of-scope employee', function () {
    $tenant = Tenant::factory()->create();
    CompanySetting::create(['tenant_id' => $tenant->id]);
    $user = User::factory()->withTenant($tenant)->create();
    grantAttendancePermission($user, $tenant, PermissionAction::Create);
    // no department/location access granted
    $employee = Employee::factory()->create([
        'tenant_id' => $tenant->id,
        'department_id' => Department::factory()->create(['tenant_id' => $tenant->id])->id,
    ]);

    $this->actingAs($user)->post(route('attendances.store'), attendancePayload($employee))->assertForbidden();
    expect(Attendance::count())->toBe(0);
});

// --- update ---

it('blocks a non-admin without the Edit permission from updating attendance', function () {
    $tenant = Tenant::factory()->create();
    CompanySetting::create(['tenant_id' => $tenant->id]);
    $admin = User::factory()->admin($tenant)->create();
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);
    $attendance = Attendance::factory()->create(['tenant_id' => $tenant->id, 'employee_id' => $employee->id]);

    $user = User::factory()->withTenant($tenant)->create();

    $this->actingAs($user)
        ->put(route('attendances.update', $attendance), ['status' => AttendanceStatus::Absent->value])
        ->assertForbidden();
});

it('allows a non-admin with the Edit permission to update attendance', function () {
    $tenant = Tenant::factory()->create();
    CompanySetting::create(['tenant_id' => $tenant->id]);
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);
    $attendance = Attendance::factory()->create(['tenant_id' => $tenant->id, 'employee_id' => $employee->id]);

    $user = User::factory()->withTenant($tenant)->create();
    grantAttendancePermission($user, $tenant, PermissionAction::Edit);

    $this->actingAs($user)
        ->put(route('attendances.update', $attendance), ['status' => AttendanceStatus::Absent->value])
        ->assertRedirect(route('attendances.show', $attendance));
});

// --- destroy / approve / lock stay admin-only no matter what's granted ---

it('blocks a non-admin from deleting attendance even with every Attendance permission granted', function () {
    $tenant = Tenant::factory()->create();
    CompanySetting::create(['tenant_id' => $tenant->id]);
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);
    $attendance = Attendance::factory()->create(['tenant_id' => $tenant->id, 'employee_id' => $employee->id]);

    $user = User::factory()->withTenant($tenant)->create();
    grantAttendancePermission($user, $tenant, PermissionAction::View);
    grantAttendancePermission($user, $tenant, PermissionAction::Create);
    grantAttendancePermission($user, $tenant, PermissionAction::Edit);

    $this->actingAs($user)->delete(route('attendances.destroy', $attendance))->assertForbidden();
    expect($attendance->fresh())->not->toBeNull();
});

it('blocks a non-admin from approving attendance even with every Attendance permission granted', function () {
    $tenant = Tenant::factory()->create();
    CompanySetting::create(['tenant_id' => $tenant->id]);
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);
    $attendance = Attendance::factory()->create(['tenant_id' => $tenant->id, 'employee_id' => $employee->id]);

    $user = User::factory()->withTenant($tenant)->create();
    grantAttendancePermission($user, $tenant, PermissionAction::View);
    grantAttendancePermission($user, $tenant, PermissionAction::Create);
    grantAttendancePermission($user, $tenant, PermissionAction::Edit);

    $this->actingAs($user)->post(route('attendances.approve', $attendance))->assertForbidden();
});

it('blocks a non-admin from locking attendance even with every Attendance permission granted', function () {
    $tenant = Tenant::factory()->create();
    CompanySetting::create(['tenant_id' => $tenant->id]);
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);
    $attendance = Attendance::factory()->create(['tenant_id' => $tenant->id, 'employee_id' => $employee->id]);

    $user = User::factory()->withTenant($tenant)->create();
    grantAttendancePermission($user, $tenant, PermissionAction::View);
    grantAttendancePermission($user, $tenant, PermissionAction::Create);
    grantAttendancePermission($user, $tenant, PermissionAction::Edit);

    $this->actingAs($user)->post(route('attendances.lock', $attendance))->assertForbidden();
});

// --- admin bypass ---

it('allows a tenant admin to do everything regardless of granted permissions', function () {
    $tenant = Tenant::factory()->create();
    CompanySetting::create(['tenant_id' => $tenant->id]);
    $admin = User::factory()->admin($tenant)->create();
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);
    $attendance = Attendance::factory()->create(['tenant_id' => $tenant->id, 'employee_id' => $employee->id]);

    $this->actingAs($admin)->get(route('attendances.index'))->assertOk();
    $this->actingAs($admin)->delete(route('attendances.destroy', $attendance))->assertRedirect(route('attendances.index'));
});

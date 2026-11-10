<?php

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function scopedEmployeeViewer(Tenant $tenant): User
{
    $user = User::factory()->withTenant($tenant)->create();
    UserPermission::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'module' => PermissionModule::Employees->value,
        'action' => PermissionAction::View->value,
    ]);

    return $user;
}

it('shows a scoped non-admin only the employees in their assigned department', function () {
    // Both dimensions gate independently for a non-admin (confirmed AND semantics),
    // so the shared location here is granted to isolate the department check.
    $tenant = Tenant::factory()->create();
    $deptA = Department::factory()->create(['tenant_id' => $tenant->id]);
    $deptB = Department::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Employee::factory()->create(['tenant_id' => $tenant->id, 'department_id' => $deptA->id, 'location_id' => $location->id, 'english_name' => 'In Scope']);
    Employee::factory()->create(['tenant_id' => $tenant->id, 'department_id' => $deptB->id, 'location_id' => $location->id, 'english_name' => 'Out Of Scope']);

    $user = scopedEmployeeViewer($tenant);
    $user->departmentAccess()->attach($deptA->id, ['tenant_id' => $tenant->id]);
    $user->locationAccess()->attach($location->id, ['tenant_id' => $tenant->id]);

    $this->actingAs($user)->get(route('employees.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/Index')
            ->has('employees.data', 1)
            ->where('employees.data.0.english_name', 'In Scope')
        );
});

it('shows a scoped non-admin with zero assigned departments no employees at all', function () {
    $tenant = Tenant::factory()->create();
    $department = Department::factory()->create(['tenant_id' => $tenant->id]);
    Employee::factory()->create(['tenant_id' => $tenant->id, 'department_id' => $department->id]);

    $user = scopedEmployeeViewer($tenant);

    $this->actingAs($user)->get(route('employees.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/Index')
            ->has('employees.data', 0)
        );
});

it('shows an admin every employee regardless of department or location', function () {
    $tenant = Tenant::factory()->create();
    $deptA = Department::factory()->create(['tenant_id' => $tenant->id]);
    $deptB = Department::factory()->create(['tenant_id' => $tenant->id]);
    Employee::factory()->create(['tenant_id' => $tenant->id, 'department_id' => $deptA->id]);
    Employee::factory()->create(['tenant_id' => $tenant->id, 'department_id' => $deptB->id]);

    $admin = User::factory()->admin($tenant)->create();

    $this->actingAs($admin)->get(route('employees.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/Index')
            ->has('employees.data', 2)
        );
});

it('requires matching both department and location scope when a user has both assigned', function () {
    $tenant = Tenant::factory()->create();
    $deptA = Department::factory()->create(['tenant_id' => $tenant->id]);
    $locationX = Location::factory()->create(['tenant_id' => $tenant->id]);
    $locationY = Location::factory()->create(['tenant_id' => $tenant->id]);

    Employee::factory()->create([
        'tenant_id' => $tenant->id,
        'department_id' => $deptA->id,
        'location_id' => $locationX->id,
        'english_name' => 'Matches Both',
    ]);
    Employee::factory()->create([
        'tenant_id' => $tenant->id,
        'department_id' => $deptA->id,
        'location_id' => $locationY->id,
        'english_name' => 'Wrong Location',
    ]);

    $user = scopedEmployeeViewer($tenant);
    $user->departmentAccess()->attach($deptA->id, ['tenant_id' => $tenant->id]);
    $user->locationAccess()->attach($locationX->id, ['tenant_id' => $tenant->id]);

    $this->actingAs($user)->get(route('employees.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/Index')
            ->has('employees.data', 1)
            ->where('employees.data.0.english_name', 'Matches Both')
        );
});

<?php

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── helpers ───────────────────────────────────────────────────────────────────

function announcementEmployee(Tenant $tenant, array $attrs = []): Employee
{
    return Employee::factory()->create(array_merge([
        'tenant_id' => $tenant->id,
        'allow_remote_checkin' => true,
    ], $attrs));
}

// ── auth ──────────────────────────────────────────────────────────────────────

it('requires authentication to list announcements', function () {
    $this->getJson(route('api.employee.announcements.index'))
        ->assertUnauthorized();
});

// ── targeting: whole tenant ───────────────────────────────────────────────────

it('returns announcements targeting all employees in the tenant', function () {
    $tenant = Tenant::factory()->create();
    $employee = announcementEmployee($tenant);

    Announcement::factory()->create([
        'tenant_id' => $tenant->id,
        'target_type' => 'locations_departments',
        'target_location_id' => null,
        'target_department_id' => null,
    ]);

    $response = $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.announcements.index'))
        ->assertOk();

    expect($response->json('data.total'))->toBe(1);
});

// ── targeting: specific employees ────────────────────────────────────────────

it('returns announcements targeted directly at the employee', function () {
    $tenant = Tenant::factory()->create();
    $employee = announcementEmployee($tenant);

    Announcement::factory()->forEmployees([$employee->id])->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.announcements.index'))
        ->assertOk();

    expect($response->json('data.total'))->toBe(1);
});

it('does not return announcements targeted at other employees', function () {
    $tenant = Tenant::factory()->create();
    $employee = announcementEmployee($tenant);
    $other = announcementEmployee($tenant);

    Announcement::factory()->forEmployees([$other->id])->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.announcements.index'))
        ->assertOk();

    expect($response->json('data.total'))->toBe(0);
});

// ── targeting: location ───────────────────────────────────────────────────────

it('returns announcements targeted at the employee location', function () {
    $tenant = Tenant::factory()->create();
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $employee = announcementEmployee($tenant, ['location_id' => $location->id]);

    Announcement::factory()->create([
        'tenant_id' => $tenant->id,
        'target_type' => 'locations_departments',
        'target_location_id' => $location->id,
        'target_department_id' => null,
    ]);

    $response = $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.announcements.index'))
        ->assertOk();

    expect($response->json('data.total'))->toBe(1);
});

it('does not return announcements targeted at a different location', function () {
    $tenant = Tenant::factory()->create();
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $otherLocation = Location::factory()->create(['tenant_id' => $tenant->id]);
    $employee = announcementEmployee($tenant, ['location_id' => $location->id]);

    Announcement::factory()->create([
        'tenant_id' => $tenant->id,
        'target_type' => 'locations_departments',
        'target_location_id' => $otherLocation->id,
        'target_department_id' => null,
    ]);

    $response = $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.announcements.index'))
        ->assertOk();

    expect($response->json('data.total'))->toBe(0);
});

// ── targeting: department ─────────────────────────────────────────────────────

it('returns announcements targeted at the employee department', function () {
    $tenant = Tenant::factory()->create();
    $department = Department::factory()->create(['tenant_id' => $tenant->id]);
    $employee = announcementEmployee($tenant, ['department_id' => $department->id]);

    Announcement::factory()->create([
        'tenant_id' => $tenant->id,
        'target_type' => 'locations_departments',
        'target_location_id' => null,
        'target_department_id' => $department->id,
    ]);

    $response = $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.announcements.index'))
        ->assertOk();

    expect($response->json('data.total'))->toBe(1);
});

// ── draft / unsent ────────────────────────────────────────────────────────────

it('does not return draft announcements', function () {
    $tenant = Tenant::factory()->create();
    $employee = announcementEmployee($tenant);

    Announcement::factory()->draft()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.announcements.index'))
        ->assertOk();

    expect($response->json('data.total'))->toBe(0);
});

// ── is_read flag ──────────────────────────────────────────────────────────────

it('returns is_read false for unread announcements', function () {
    $tenant = Tenant::factory()->create();
    $employee = announcementEmployee($tenant);

    Announcement::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.announcements.index'))
        ->assertOk();

    expect($response->json('data.data.0.is_read'))->toBeFalse();
});

it('returns is_read true after marking as read', function () {
    $tenant = Tenant::factory()->create();
    $employee = announcementEmployee($tenant);
    $announcement = Announcement::factory()->create(['tenant_id' => $tenant->id]);

    AnnouncementRead::create([
        'announcement_id' => $announcement->id,
        'employee_id' => $employee->id,
    ]);

    $response = $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.announcements.index'))
        ->assertOk();

    expect($response->json('data.data.0.is_read'))->toBeTrue();
});

// ── mark read ─────────────────────────────────────────────────────────────────

it('marks an announcement as read', function () {
    $tenant = Tenant::factory()->create();
    $employee = announcementEmployee($tenant);
    $announcement = Announcement::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($employee, 'sanctum')
        ->patchJson(route('api.employee.announcements.read', $announcement))
        ->assertOk();

    expect(AnnouncementRead::where('announcement_id', $announcement->id)
        ->where('employee_id', $employee->id)
        ->exists())->toBeTrue();
});

it('marking the same announcement twice does not create duplicate reads', function () {
    $tenant = Tenant::factory()->create();
    $employee = announcementEmployee($tenant);
    $announcement = Announcement::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($employee, 'sanctum')
        ->patchJson(route('api.employee.announcements.read', $announcement))
        ->assertOk();

    $this->actingAs($employee, 'sanctum')
        ->patchJson(route('api.employee.announcements.read', $announcement))
        ->assertOk();

    expect(AnnouncementRead::where('announcement_id', $announcement->id)
        ->where('employee_id', $employee->id)
        ->count())->toBe(1);
});

it('cannot mark an announcement from another tenant as read', function () {
    $employee = Employee::factory()->create(['allow_remote_checkin' => true]);
    $other = Announcement::factory()->create(); // different tenant

    $this->actingAs($employee, 'sanctum')
        ->patchJson(route('api.employee.announcements.read', $other))
        ->assertNotFound();
});

// ── mark all read ─────────────────────────────────────────────────────────────

it('marks all visible announcements as read', function () {
    $tenant = Tenant::factory()->create();
    $employee = announcementEmployee($tenant);

    Announcement::factory()->count(3)->create(['tenant_id' => $tenant->id]);

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.announcements.read-all'))
        ->assertOk();

    $unread = Announcement::visibleTo($employee)
        ->whereDoesntHave('reads', fn ($q) => $q->where('employee_id', $employee->id))
        ->count();

    expect($unread)->toBe(0);
});

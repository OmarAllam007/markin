<?php

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\CompanySetting;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows reports index page', function () {
    $tenant = Tenant::factory()->create();
    CompanySetting::create(['tenant_id' => $tenant->id]);
    $user = User::factory()->admin($tenant)->create();

    $this->actingAs($user)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('reports/Index'));
});

it('shows daily summary page for authenticated admin', function () {
    $tenant = Tenant::factory()->create();
    CompanySetting::create(['tenant_id' => $tenant->id]);
    $user = User::factory()->admin($tenant)->create();

    $this->actingAs($user)
        ->get(route('reports.daily-summary'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/DailySummary')
            ->has('groups')
            ->has('filters')
            ->has('filterOptions'),
        );
});

it('redirects unauthenticated users from daily summary', function () {
    $this->get(route('reports.daily-summary'))
        ->assertRedirect(route('login'));
});

it('groups attendance by department and counts statuses correctly', function () {
    $tenant = Tenant::factory()->create();
    CompanySetting::create(['tenant_id' => $tenant->id]);
    $user = User::factory()->admin($tenant)->create();

    $dept = Department::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Engineering']);

    $employees = Employee::factory()->count(4)->create([
        'tenant_id' => $tenant->id,
        'department_id' => $dept->id,
        'created_by' => $user->id,
    ]);

    $date = '2026-05-15';

    Attendance::factory()->forEmployee($employees[0])->create([
        'attendance_date' => $date,
        'status' => AttendanceStatus::Present,
        'created_by' => $user->id,
    ]);
    Attendance::factory()->forEmployee($employees[1])->create([
        'attendance_date' => $date,
        'status' => AttendanceStatus::Late,
        'created_by' => $user->id,
    ]);
    Attendance::factory()->forEmployee($employees[2])->create([
        'attendance_date' => $date,
        'status' => AttendanceStatus::Absent,
        'created_by' => $user->id,
    ]);
    Attendance::factory()->forEmployee($employees[3])->create([
        'attendance_date' => $date,
        'status' => AttendanceStatus::Leave,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('reports.daily-summary', ['date' => $date, 'group_by' => 'departments']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('groups', 1)
            ->where('groups.0.name', 'Engineering')
            ->where('groups.0.total', 4)
            ->where('groups.0.on_time', 1)
            ->where('groups.0.late', 1)
            ->where('groups.0.absent', 1)
            ->where('groups.0.on_leave', 1)
            ->where('groups.0.off_days', 0),
        );
});

it('returns empty groups when no attendance records exist for the date', function () {
    $tenant = Tenant::factory()->create();
    CompanySetting::create(['tenant_id' => $tenant->id]);
    $user = User::factory()->admin($tenant)->create();

    $this->actingAs($user)
        ->get(route('reports.daily-summary', ['date' => '2026-01-01', 'group_by' => 'departments']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('groups', 0));
});

it('maps weekend status to off_days', function () {
    $tenant = Tenant::factory()->create();
    CompanySetting::create(['tenant_id' => $tenant->id]);
    $user = User::factory()->admin($tenant)->create();

    $dept = Department::factory()->create(['tenant_id' => $tenant->id]);
    $employee = Employee::factory()->create([
        'tenant_id' => $tenant->id,
        'department_id' => $dept->id,
        'created_by' => $user->id,
    ]);

    $date = '2026-05-16';

    Attendance::factory()->forEmployee($employee)->create([
        'attendance_date' => $date,
        'status' => AttendanceStatus::Weekend,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('reports.daily-summary', ['date' => $date, 'group_by' => 'departments']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('groups.0.off_days', 1)
            ->where('groups.0.absent', 0),
        );
});

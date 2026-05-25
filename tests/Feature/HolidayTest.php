<?php

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists holidays including recurring ones regardless of year filter', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->admin($tenant)->create();

    Holiday::factory()->create(['tenant_id' => $tenant->id, 'name' => 'National Day', 'date' => '2026-11-18', 'is_recurring' => false]);
    Holiday::factory()->recurring()->create(['tenant_id' => $tenant->id, 'name' => 'Foundation Day', 'date' => '2024-02-22']);
    Holiday::factory()->create(['tenant_id' => Tenant::factory()->create()->id, 'name' => 'Other Tenant Holiday', 'date' => '2026-06-01']);

    $this->actingAs($user)
        ->get(route('holidays.index', ['year' => 2026]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('holidays/Index')
            ->has('holidays', 2) // National Day (2026) + Foundation Day (recurring, always shown)
        );
});

it('creates a holiday with country and backfills absent attendances', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->admin($tenant)->create();
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);

    // Pre-existing absent record on the holiday date
    $absent = Attendance::factory()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
        'attendance_date' => '2026-02-22',
        'status' => AttendanceStatus::Absent->value,
        'attendance_source' => AttendanceSource::Manual->value,
        'is_holiday' => false,
    ]);

    $this->actingAs($user)
        ->post(route('holidays.store'), [
            'name' => 'Foundation Day',
            'date' => '2026-02-22',
            'is_recurring' => false,
        ])
        ->assertRedirect(route('holidays.index'));

    // Absent record should now be Holiday
    expect($absent->fresh()->status->value)->toBe(AttendanceStatus::Holiday->value)
        ->and($absent->fresh()->is_holiday)->toBeTrue();
});

it('backfills late and missing_checkout records too', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->admin($tenant)->create();
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);

    $late = Attendance::factory()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
        'attendance_date' => '2026-03-10',
        'status' => AttendanceStatus::Late->value,
        'attendance_source' => AttendanceSource::Manual->value,
        'is_holiday' => false,
    ]);

    $this->actingAs($user)
        ->post(route('holidays.store'), [
            'name' => 'Test Holiday',
            'date' => '2026-03-10',
            'is_recurring' => false,
        ])
        ->assertRedirect(route('holidays.index'));

    expect($late->fresh()->status->value)->toBe(AttendanceStatus::Holiday->value);
});

it('does not override leave or business_trip records when backfilling', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->admin($tenant)->create();
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);

    $onLeave = Attendance::factory()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
        'attendance_date' => '2026-04-05',
        'status' => AttendanceStatus::Leave->value,
        'attendance_source' => AttendanceSource::Manual->value,
        'is_holiday' => false,
    ]);

    $this->actingAs($user)
        ->post(route('holidays.store'), [
            'name' => 'Some Holiday',
            'date' => '2026-04-05',
            'is_recurring' => false,
        ])
        ->assertRedirect(route('holidays.index'));

    // Leave should remain Leave
    expect($onLeave->fresh()->status->value)->toBe(AttendanceStatus::Leave->value);
});

it('backfills across all years for recurring holidays', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->admin($tenant)->create();
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);

    $record2024 = Attendance::factory()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
        'attendance_date' => '2024-11-18',
        'status' => AttendanceStatus::Absent->value,
        'attendance_source' => AttendanceSource::Manual->value,
        'is_holiday' => false,
    ]);

    $record2025 = Attendance::factory()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
        'attendance_date' => '2025-11-18',
        'status' => AttendanceStatus::Absent->value,
        'attendance_source' => AttendanceSource::Manual->value,
        'is_holiday' => false,
    ]);

    $this->actingAs($user)
        ->post(route('holidays.store'), [
            'name' => 'National Day',
            'date' => '2026-11-18',
            'is_recurring' => true,
        ])
        ->assertRedirect(route('holidays.index'));

    expect($record2024->fresh()->status->value)->toBe(AttendanceStatus::Holiday->value)
        ->and($record2025->fresh()->status->value)->toBe(AttendanceStatus::Holiday->value);
});

it('reverts attendances to absent when holiday is deleted', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->admin($tenant)->create();
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);

    $holiday = Holiday::factory()->create(['tenant_id' => $tenant->id, 'date' => '2026-05-01', 'is_recurring' => false]);

    $record = Attendance::factory()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
        'attendance_date' => '2026-05-01',
        'status' => AttendanceStatus::Holiday->value,
        'attendance_source' => AttendanceSource::Manual->value,
        'is_holiday' => true,
    ]);

    $this->actingAs($user)
        ->delete(route('holidays.destroy', $holiday))
        ->assertRedirect(route('holidays.index'));

    expect(Holiday::find($holiday->id))->toBeNull()
        ->and($record->fresh()->status->value)->toBe(AttendanceStatus::Absent->value)
        ->and($record->fresh()->is_holiday)->toBeFalse();
});

it('updates a holiday name without touching attendance records', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->admin($tenant)->create();
    $holiday = Holiday::factory()->create(['tenant_id' => $tenant->id, 'date' => '2026-06-15', 'is_recurring' => false]);

    $this->actingAs($user)
        ->put(route('holidays.update', $holiday), [
            'name' => 'Renamed Holiday',
            'date' => '2026-06-15',
            'is_recurring' => false,
        ])
        ->assertRedirect(route('holidays.index'));

    expect($holiday->fresh()->name)->toBe('Renamed Holiday');
});

it('prevents accessing another tenant holiday', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $user = User::factory()->admin($tenantA)->create();
    $holiday = Holiday::factory()->create(['tenant_id' => $tenantB->id]);

    $this->actingAs($user)
        ->delete(route('holidays.destroy', $holiday))
        ->assertForbidden();
});

<?php

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\CompanySetting;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows attendance index for tenant admin', function () {
    $tenant = Tenant::factory()->create();
    CompanySetting::create([
        'tenant_id' => $tenant->id,
    ]);
    $user = User::factory()->admin($tenant)->create();

    $this->actingAs($user)
        ->get(route('attendances.index'))
        ->assertOk();
});

it('stores attendance with metrics computed on server', function () {
    $tenant = Tenant::factory()->create();
    CompanySetting::create([
        'tenant_id' => $tenant->id,
    ]);
    $user = User::factory()->admin($tenant)->create();
    $employee = Employee::factory()->create([
        'tenant_id' => $tenant->id,
        'created_by' => $user->id,
    ]);

    $date = now()->toDateString();

    $this->actingAs($user)
        ->post(route('attendances.store'), [
            'employee_id' => $employee->id,
            'attendance_date' => $date,
            'check_in_time' => '09:00',
            'check_out_time' => '17:00',
            'break_minutes' => 60,
            'status' => AttendanceStatus::Present->value,
            'attendance_source' => AttendanceSource::Manual->value,
        ])
        ->assertRedirect(route('attendances.index'));

    $record = Attendance::query()->where('employee_id', $employee->id)->first();

    expect($record)->not->toBeNull()
        ->and((int) $record->tenant_id)->toBe((int) $tenant->id)
        ->and((int) $record->worked_minutes)->toBe(420)
        ->and((int) $record->break_minutes)->toBe(60);
});

it('allows viewing employee attendance history', function () {
    $tenant = Tenant::factory()->create();
    CompanySetting::create([
        'tenant_id' => $tenant->id,
    ]);
    $user = User::factory()->admin($tenant)->create();
    $employee = Employee::factory()->create([
        'tenant_id' => $tenant->id,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('employees.attendance.index', $employee))
        ->assertOk();
});

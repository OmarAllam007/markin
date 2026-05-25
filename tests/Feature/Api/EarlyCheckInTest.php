<?php

use App\Models\Employee;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('blocks check-in before the fixed shift start time', function () {
    Carbon::setTestNow('2026-05-24 08:30:00');

    $shift = WorkShift::factory()->create([
        'type' => 'fixed',
        'checkin_time' => '09:00',
        'checkout_time' => '17:00',
        'limit_checkin_from' => null,
    ]);
    $employee = Employee::factory()->create([
        'allow_remote_checkin' => true,
        'work_shift_id' => $shift->id,
    ]);

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), ['type' => 'check_in'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.type.0', fn ($msg) => str_contains($msg, 'not allowed before'));

    Carbon::setTestNow();
});

it('allows check-in at exactly the shift start time', function () {
    Carbon::setTestNow('2026-05-24 09:00:00');

    $shift = WorkShift::factory()->create([
        'type' => 'fixed',
        'checkin_time' => '09:00',
        'checkout_time' => '17:00',
    ]);
    $employee = Employee::factory()->create([
        'allow_remote_checkin' => true,
        'work_shift_id' => $shift->id,
    ]);

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), ['type' => 'check_in'])
        ->assertOk();

    Carbon::setTestNow();
});

it('respects limit_checkin_from when set, allowing earlier check-in', function () {
    Carbon::setTestNow('2026-05-24 08:30:00');

    $shift = WorkShift::factory()->create([
        'type' => 'fixed',
        'checkin_time' => '09:00',
        'checkout_time' => '17:00',
        'limit_checkin_from' => '08:00',
    ]);
    $employee = Employee::factory()->create([
        'allow_remote_checkin' => true,
        'work_shift_id' => $shift->id,
    ]);

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), ['type' => 'check_in'])
        ->assertOk();

    Carbon::setTestNow();
});

it('blocks check-in before limit_checkin_from', function () {
    Carbon::setTestNow('2026-05-24 07:45:00');

    $shift = WorkShift::factory()->create([
        'type' => 'fixed',
        'checkin_time' => '09:00',
        'checkout_time' => '17:00',
        'limit_checkin_from' => '08:00',
    ]);
    $employee = Employee::factory()->create([
        'allow_remote_checkin' => true,
        'work_shift_id' => $shift->id,
    ]);

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), ['type' => 'check_in'])
        ->assertUnprocessable();

    Carbon::setTestNow();
});

it('does not apply early check-in restriction to employees without a shift', function () {
    Carbon::setTestNow('2026-05-24 06:00:00');

    $employee = Employee::factory()->create([
        'allow_remote_checkin' => true,
        'work_shift_id' => null,
    ]);

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), ['type' => 'check_in'])
        ->assertOk();

    Carbon::setTestNow();
});

<?php

use App\Enums\PunchType;
use App\Models\AttendancePunch;
use App\Models\Employee;
use App\Models\WorkShift;
use App\Services\AttendancePunchService;
use App\Services\HolidayService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── helpers ───────────────────────────────────────────────────────────────────

function checkedInEmployee(array $shiftAttrs = []): Employee
{
    $shift = WorkShift::factory()->create(array_merge([
        'type' => 'fixed',
        'checkin_time' => '09:00',
        'checkout_time' => '17:00',
        'early_checkout_grace_minutes' => 5, // > 0 enables the feature; warn if > 5 min early
    ], $shiftAttrs));

    $employee = Employee::factory()->create([
        'allow_remote_checkin' => true,
        'work_shift_id' => $shift->id,
    ]);

    Carbon::setTestNow('2026-05-24 09:00:00');

    (new AttendancePunchService(app(HolidayService::class)))
        ->punch(employee: $employee, type: PunchType::CheckIn);

    return $employee;
}

// ── early checkout warning ────────────────────────────────────────────────────

it('returns a warning when checking out early without confirmation', function () {
    $employee = checkedInEmployee(); // grace = 5 min by default; 2 hrs early triggers warning

    Carbon::setTestNow('2026-05-24 15:00:00'); // 2 hours early

    $response = $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), ['type' => 'check_out'])
        ->assertOk()
        ->assertJsonPath('data.requires_confirmation', true)
        ->assertJsonPath('data.minutes_early', fn ($v) => $v > 0);

    expect($response->json('status'))->toBeTrue();

    Carbon::setTestNow();
});

it('records checkout when confirmed with a reason', function () {
    $employee = checkedInEmployee(); // grace = 5 min; 2 hrs early needs confirmation

    Carbon::setTestNow('2026-05-24 15:00:00');

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), [
            'type' => 'check_out',
            'confirmed' => true,
            'reason' => 'Doctor appointment',
        ])
        ->assertOk()
        ->assertJsonPath('data.type', 'check_out');

    $punch = AttendancePunch::where('type', 'check_out')->first();
    expect($punch)->not->toBeNull()
        ->and($punch->note)->toBe('Doctor appointment');

    Carbon::setTestNow();
});

it('allows checkout within the grace period without a warning', function () {
    $employee = checkedInEmployee(['early_checkout_grace_minutes' => 30]);

    Carbon::setTestNow('2026-05-24 16:45:00'); // 15 minutes early — within 30-min grace

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), ['type' => 'check_out'])
        ->assertOk()
        ->assertJsonPath('data.requires_confirmation', null);

    Carbon::setTestNow();
});

it('warns when checkout is outside the grace period', function () {
    $employee = checkedInEmployee(['early_checkout_grace_minutes' => 15]);

    Carbon::setTestNow('2026-05-24 16:30:00'); // 30 minutes early — outside 15-min grace

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), ['type' => 'check_out'])
        ->assertOk()
        ->assertJsonPath('data.requires_confirmation', true);

    Carbon::setTestNow();
});

it('does not warn for employees without a shift', function () {
    Carbon::setTestNow('2026-05-24 09:00:00');

    $employee = Employee::factory()->create([
        'allow_remote_checkin' => true,
        'work_shift_id' => null,
    ]);

    (new AttendancePunchService(app(HolidayService::class)))
        ->punch(employee: $employee, type: PunchType::CheckIn);

    Carbon::setTestNow('2026-05-24 14:00:00');

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), ['type' => 'check_out'])
        ->assertOk()
        ->assertJsonPath('data.requires_confirmation', null);

    Carbon::setTestNow();
});

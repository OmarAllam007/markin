<?php

use App\Enums\NotificationType;
use App\Enums\PunchType;
use App\Models\Employee;
use App\Models\EmployeeNotification;
use App\Models\WorkShift;
use App\Services\AttendancePunchService;
use App\Services\HolidayService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── index ─────────────────────────────────────────────────────────────────────

it('requires authentication to list notifications', function () {
    $this->getJson(route('api.employee.notifications.index'))
        ->assertUnauthorized();
});

it('returns paginated notifications for the authenticated employee', function () {
    $employee = Employee::factory()->create(['allow_remote_checkin' => true]);

    EmployeeNotification::factory()->count(3)->create([
        'employee_id' => $employee->id,
        'tenant_id' => $employee->tenant_id,
    ]);

    $response = $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.notifications.index'))
        ->assertOk();

    expect($response->json('data.notifications.total'))->toBe(3)
        ->and($response->json('data.unread_count'))->toBe(3);
});

it('does not return another employee notifications', function () {
    $employee = Employee::factory()->create(['allow_remote_checkin' => true]);
    $other = Employee::factory()->create();

    EmployeeNotification::factory()->count(2)->create([
        'employee_id' => $other->id,
        'tenant_id' => $other->tenant_id,
    ]);

    $response = $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.notifications.index'))
        ->assertOk();

    expect($response->json('data.notifications.total'))->toBe(0);
});

it('returns unread_count of zero when all notifications are read', function () {
    $employee = Employee::factory()->create(['allow_remote_checkin' => true]);

    EmployeeNotification::factory()->count(3)->read()->create([
        'employee_id' => $employee->id,
        'tenant_id' => $employee->tenant_id,
    ]);

    $response = $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.notifications.index'))
        ->assertOk();

    expect($response->json('data.unread_count'))->toBe(0);
});

it('returns notifications ordered by newest first', function () {
    $employee = Employee::factory()->create(['allow_remote_checkin' => true]);

    $old = EmployeeNotification::factory()->create([
        'employee_id' => $employee->id,
        'tenant_id' => $employee->tenant_id,
        'created_at' => now()->subDays(2),
    ]);
    $new = EmployeeNotification::factory()->create([
        'employee_id' => $employee->id,
        'tenant_id' => $employee->tenant_id,
        'created_at' => now(),
    ]);

    $response = $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.notifications.index'))
        ->assertOk();

    $ids = collect($response->json('data.notifications.data'))->pluck('id');
    expect($ids->first())->toBe($new->id)
        ->and($ids->last())->toBe($old->id);
});

// ── mark single read ──────────────────────────────────────────────────────────

it('marks a single notification as read', function () {
    $employee = Employee::factory()->create(['allow_remote_checkin' => true]);
    $notification = EmployeeNotification::factory()->create([
        'employee_id' => $employee->id,
        'tenant_id' => $employee->tenant_id,
    ]);

    $this->actingAs($employee, 'sanctum')
        ->patchJson(route('api.employee.notifications.read', $notification))
        ->assertOk();

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('cannot mark another employee notification as read', function () {
    $employee = Employee::factory()->create(['allow_remote_checkin' => true]);
    $other = Employee::factory()->create();
    $notification = EmployeeNotification::factory()->create([
        'employee_id' => $other->id,
        'tenant_id' => $other->tenant_id,
    ]);

    $this->actingAs($employee, 'sanctum')
        ->patchJson(route('api.employee.notifications.read', $notification))
        ->assertNotFound();
});

// ── mark all read ─────────────────────────────────────────────────────────────

it('marks all notifications as read', function () {
    $employee = Employee::factory()->create(['allow_remote_checkin' => true]);

    EmployeeNotification::factory()->count(3)->create([
        'employee_id' => $employee->id,
        'tenant_id' => $employee->tenant_id,
    ]);

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.notifications.read-all'))
        ->assertOk();

    $unread = EmployeeNotification::where('employee_id', $employee->id)->unread()->count();
    expect($unread)->toBe(0);
});

// ── auto-dispatch from punch service ─────────────────────────────────────────

it('creates a late check-in notification when employee arrives late', function () {
    Carbon::setTestNow('2026-05-24 09:15:00');

    $shift = WorkShift::factory()->create([
        'type' => 'fixed',
        'checkin_time' => '09:00',
        'checkout_time' => '17:00',
    ]);
    $employee = Employee::factory()->create([
        'allow_remote_checkin' => true,
        'work_shift_id' => $shift->id,
    ]);

    (new AttendancePunchService(app(HolidayService::class)))
        ->punch(employee: $employee, type: PunchType::CheckIn);

    $notification = EmployeeNotification::where('employee_id', $employee->id)->first();

    expect($notification)->not->toBeNull()
        ->and($notification->type)->toBe(NotificationType::LateCheckIn)
        ->and($notification->data['minutes_late'])->toBe(15);

    Carbon::setTestNow();
});

it('does not create a late check-in notification when employee arrives on time', function () {
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

    (new AttendancePunchService(app(HolidayService::class)))
        ->punch(employee: $employee, type: PunchType::CheckIn);

    expect(EmployeeNotification::where('employee_id', $employee->id)->count())->toBe(0);

    Carbon::setTestNow();
});

it('creates an early check-out notification when employee confirms leaving early', function () {
    Carbon::setTestNow('2026-05-24 09:00:00');

    $shift = WorkShift::factory()->create([
        'type' => 'fixed',
        'checkin_time' => '09:00',
        'checkout_time' => '17:00',
        'early_checkout_grace_minutes' => 5,
    ]);
    $employee = Employee::factory()->create([
        'allow_remote_checkin' => true,
        'work_shift_id' => $shift->id,
    ]);

    $service = new AttendancePunchService(app(HolidayService::class));
    $service->punch(employee: $employee, type: PunchType::CheckIn);

    Carbon::setTestNow('2026-05-24 15:00:00');

    $service->punch(
        employee: $employee,
        type: PunchType::CheckOut,
        confirmed: true,
        reason: 'Doctor appointment',
    );

    $notification = EmployeeNotification::where('employee_id', $employee->id)
        ->where('type', NotificationType::EarlyCheckOut->value)
        ->first();

    expect($notification)->not->toBeNull()
        ->and($notification->data['reason'])->toBe('Doctor appointment')
        ->and($notification->data['minutes_early'])->toBeGreaterThan(0);

    Carbon::setTestNow();
});

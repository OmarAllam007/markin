<?php

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\LeaveRequestStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkShift;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** @return array{0: Tenant, 1: User, 2: Employee, 3: LeaveType} */
function leaveSetup(?int $annualDays = 10): array
{
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin($tenant)->create(['current_tenant_id' => $tenant->id]);
    $shift = WorkShift::factory()->fixed()->create([
        'tenant_id' => $tenant->id,
        'created_by' => $admin->id,
        'weekends' => ['friday', 'saturday'],
    ]);
    $employee = Employee::factory()->create([
        'tenant_id' => $tenant->id,
        'created_by' => $admin->id,
        'work_shift_id' => $shift->id,
    ]);
    $type = LeaveType::create(['tenant_id' => $tenant->id, 'name' => 'Annual', 'annual_days' => $annualDays]);

    return [$tenant, $admin, $employee, $type];
}

/** Monday of next week, so a 5-day range always holds the same weekend. */
function nextMonday(): CarbonImmutable
{
    return CarbonImmutable::parse('next monday')->startOfDay();
}

// ── employee side (API) ──────────────────────────────────────────────────────

it('counts working days only, skipping the weekend', function () {
    [, , $employee, $type] = leaveSetup();
    $monday = nextMonday();

    // Mon → Sun = 7 days, minus Friday and Saturday = 5
    $response = $this->actingAs($employee, 'sanctum')->postJson('/api/employee/leaves', [
        'leave_type_id' => $type->id,
        'start_date' => $monday->toDateString(),
        'end_date' => $monday->addDays(6)->toDateString(),
        'reason' => 'Family trip',
    ])->assertCreated();

    expect($response->json('data.days'))->toBe(5);
    expect($response->json('data.status'))->toBe('pending');
    $this->assertDatabaseHas('leave_requests', ['employee_id' => $employee->id, 'days' => 5]);
});

it('refuses a second request over the same dates', function () {
    [, , $employee, $type] = leaveSetup();
    $monday = nextMonday();

    $payload = [
        'leave_type_id' => $type->id,
        'start_date' => $monday->toDateString(),
        'end_date' => $monday->addDay()->toDateString(),
    ];

    $this->actingAs($employee, 'sanctum')->postJson('/api/employee/leaves', $payload)->assertCreated();

    $this->actingAs($employee, 'sanctum')->postJson('/api/employee/leaves', [
        'leave_type_id' => $type->id,
        'start_date' => $monday->addDay()->toDateString(),
        'end_date' => $monday->addDays(2)->toDateString(),
    ])->assertStatus(422)->assertJsonPath('status', false);

    $this->assertDatabaseCount('leave_requests', 1);
});

it('refuses a request bigger than the remaining balance', function () {
    [, , $employee, $type] = leaveSetup(annualDays: 2);
    $monday = nextMonday();

    $this->actingAs($employee, 'sanctum')->postJson('/api/employee/leaves', [
        'leave_type_id' => $type->id,
        'start_date' => $monday->toDateString(),
        'end_date' => $monday->addDays(3)->toDateString(),
    ])->assertStatus(422);

    $this->assertDatabaseCount('leave_requests', 0);
});

it('refuses a range that is all weekend', function () {
    [, , $employee, $type] = leaveSetup();
    $friday = nextMonday()->addDays(4);

    $this->actingAs($employee, 'sanctum')->postJson('/api/employee/leaves', [
        'leave_type_id' => $type->id,
        'start_date' => $friday->toDateString(),
        'end_date' => $friday->addDay()->toDateString(),
    ])->assertStatus(422);
});

it('shows the balance with approved days already used', function () {
    [, $admin, $employee, $type] = leaveSetup();
    $monday = nextMonday();

    $request = LeaveRequest::create([
        'tenant_id' => $employee->tenant_id,
        'employee_id' => $employee->id,
        'leave_type_id' => $type->id,
        'start_date' => $monday->toDateString(),
        'end_date' => $monday->addDay()->toDateString(),
        'days' => 2,
        'status' => LeaveRequestStatus::Approved,
    ]);

    $response = $this->actingAs($employee, 'sanctum')
        ->getJson('/api/employee/leaves?year='.$monday->year)
        ->assertOk();

    expect($response->json('data.balances.0'))
        ->toMatchArray(['leave_type_id' => $type->id, 'entitled' => 10, 'used' => 2, 'remaining' => 8]);
    expect($response->json('data.requests.data.0.id'))->toBe($request->id);
});

// ── admin side (web) ─────────────────────────────────────────────────────────

function pendingRequest(Employee $employee, LeaveType $type, CarbonImmutable $start, int $days = 2): LeaveRequest
{
    return LeaveRequest::create([
        'tenant_id' => $employee->tenant_id,
        'employee_id' => $employee->id,
        'leave_type_id' => $type->id,
        'start_date' => $start->toDateString(),
        'end_date' => $start->addDays($days - 1)->toDateString(),
        'days' => $days,
        'status' => LeaveRequestStatus::Pending,
    ]);
}

it('marks the attendance days as leave on approval, and the absence command leaves them alone', function () {
    [, $admin, $employee, $type] = leaveSetup();
    $monday = nextMonday();
    $request = pendingRequest($employee, $type, $monday);

    $this->actingAs($admin)->post(route('leaves.approve', $request))->assertRedirect();

    expect($request->fresh()->status)->toBe(LeaveRequestStatus::Approved);
    $this->assertDatabaseHas('attendances', [
        'employee_id' => $employee->id,
        'attendance_date' => $monday->startOfDay(),
        'status' => AttendanceStatus::Leave->value,
    ]);

    // A day already covered by leave must not be turned into an absence.
    $this->artisan('attendance:mark-absences', ['--date' => $monday->toDateString()])->assertSuccessful();

    expect(Attendance::where('employee_id', $employee->id)->where('status', AttendanceStatus::Absent)->count())->toBe(0);
    expect(Attendance::where('employee_id', $employee->id)->where('status', AttendanceStatus::Leave)->count())->toBe(2);
});

it('keeps a day the employee actually worked', function () {
    [, $admin, $employee, $type] = leaveSetup();
    $monday = nextMonday();

    Attendance::create([
        'tenant_id' => $employee->tenant_id,
        'employee_id' => $employee->id,
        'attendance_date' => $monday->toDateString(),
        'check_in_time' => $monday->setTime(9, 0),
        'status' => AttendanceStatus::Present,
        'attendance_source' => AttendanceSource::Mobile,
        'created_by' => $admin->id,
    ]);

    $request = pendingRequest($employee, $type, $monday);
    $this->actingAs($admin)->post(route('leaves.approve', $request))->assertRedirect();

    $this->assertDatabaseHas('attendances', [
        'employee_id' => $employee->id,
        'attendance_date' => $monday->startOfDay(),
        'status' => AttendanceStatus::Present->value,
    ]);
});

it('reverts the leave days when an approved request is rejected', function () {
    [, $admin, $employee, $type] = leaveSetup();
    $monday = nextMonday();
    $request = pendingRequest($employee, $type, $monday);

    $this->actingAs($admin)->post(route('leaves.approve', $request))->assertRedirect();
    $this->actingAs($admin)->post(route('leaves.reject', $request), ['review_note' => 'Peak season'])->assertRedirect();

    expect($request->fresh()->status)->toBe(LeaveRequestStatus::Rejected);
    expect($request->fresh()->review_note)->toBe('Peak season');
    expect(Attendance::where('employee_id', $employee->id)->count())->toBe(0);
});

it('blocks a user without the leaves permission from approving', function () {
    [$tenant, , $employee, $type] = leaveSetup();
    $outsider = User::factory()->withTenant($tenant)->create(['current_tenant_id' => $tenant->id]);
    $request = pendingRequest($employee, $type, nextMonday());

    $this->actingAs($outsider)->post(route('leaves.approve', $request))->assertForbidden();

    expect($request->fresh()->status)->toBe(LeaveRequestStatus::Pending);
});

it('lists pending requests for an admin', function () {
    [, $admin, $employee, $type] = leaveSetup();
    pendingRequest($employee, $type, nextMonday());

    $this->actingAs($admin)->get(route('leaves.index'))->assertOk();
});

it('deactivates a used leave type instead of deleting it', function () {
    [, $admin, $employee, $type] = leaveSetup();
    pendingRequest($employee, $type, nextMonday());

    $this->actingAs($admin)->delete(route('leave-types.destroy', $type))->assertRedirect();

    expect($type->fresh()->is_active)->toBeFalse();
});

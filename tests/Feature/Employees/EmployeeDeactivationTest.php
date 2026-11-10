<?php

use App\Enums\EmployeeStatus;
use App\Mail\EmployeeOtpMail;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function employeeUpdatePayload(Employee $employee, array $overrides = []): array
{
    return [
        'arabic_name' => $employee->arabic_name,
        'english_name' => $employee->english_name,
        'mobile_country_code' => $employee->mobile_country_code,
        'mobile_number' => $employee->mobile_number,
        'status' => EmployeeStatus::Active->value,
        ...$overrides,
    ];
}

// ── deactivating from the admin panel ───────────────────────────────────────

it('signs the employee out of the app when an admin sets a non-active status', function (EmployeeStatus $status) {
    $tenant = Tenant::factory()->create();
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);
    $employee->createToken('phone');

    $this->actingAs(User::factory()->admin($tenant)->create())
        ->put(route('employees.update', $employee), employeeUpdatePayload($employee, ['status' => $status->value]))
        ->assertRedirect(route('employees.index'));

    expect($employee->refresh()->status)->toBe($status)
        ->and($employee->tokens()->count())->toBe(0);
})->with([EmployeeStatus::Inactive, EmployeeStatus::OnLeave, EmployeeStatus::Terminated]);

it('keeps the employee signed in when other fields change', function () {
    $tenant = Tenant::factory()->create();
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);
    $employee->createToken('phone');

    $this->actingAs(User::factory()->admin($tenant)->create())
        ->put(route('employees.update', $employee), employeeUpdatePayload($employee, ['english_name' => 'New Name']));

    expect($employee->tokens()->count())->toBe(1);
});

it('revokes the tokens when the employee is deleted', function () {
    $employee = Employee::factory()->create();
    $employee->createToken('phone');

    $employee->delete();

    expect(DB::table('personal_access_tokens')->count())->toBe(0);
});

// ── the mobile API ───────────────────────────────────────────────────────────

it('rejects and revokes a token whose employee was deactivated without model events', function () {
    $employee = Employee::factory()->create();
    $token = $employee->createToken('phone')->plainTextToken;

    // Bulk update: no model events fire, so only the middleware can catch it.
    Employee::whereKey($employee->id)->update(['status' => EmployeeStatus::Terminated->value]);

    $this->withToken($token)->getJson(route('api.employee.me'))
        ->assertForbidden()
        ->assertJsonPath('message', 'Your account is not active. Please contact your administrator.');

    expect($employee->tokens()->count())->toBe(0);
});

it('does not send an otp to a non-active employee', function () {
    Mail::fake();
    Employee::factory()->create([
        'mobile_country_code' => '+966',
        'mobile_number' => '512345678',
        'email' => 'ex@example.com',
        'status' => EmployeeStatus::Terminated,
    ]);

    $this->postJson(route('api.employee.auth.request-otp'), ['country_code' => '+966', 'mobile_number' => '512345678'])
        ->assertForbidden();

    Mail::assertNothingQueued();
});

it('rejects the otp if the employee was deactivated after the code was sent', function () {
    $employee = Employee::factory()->create(['device_fingerprint' => null]);
    $verificationToken = 'c0a8012e-0000-4000-8000-000000000001';
    Cache::put("otp:token:{$verificationToken}", ['employee_id' => $employee->id, 'code' => '123456'], 300);

    $employee->update(['status' => EmployeeStatus::Terminated]);

    $this->postJson(route('api.employee.auth.verify-otp'), [
        'verification_token' => $verificationToken,
        'otp' => '123456',
        'device' => ['platform' => 'android', 'device_id' => 'x', 'brand' => 'Samsung', 'model' => 'A55'],
    ])->assertForbidden();

    expect($employee->tokens()->count())->toBe(0);
});

it('sends the otp to the active record when an old terminated record shares the number', function () {
    Mail::fake();
    $old = Tenant::factory()->create();
    $current = Tenant::factory()->create();
    Employee::factory()->create(['tenant_id' => $old->id, 'mobile_country_code' => '+966', 'mobile_number' => '512345678', 'status' => EmployeeStatus::Terminated]);
    $active = Employee::factory()->create(['tenant_id' => $current->id, 'mobile_country_code' => '+966', 'mobile_number' => '512345678', 'email' => 'now@example.com']);

    $this->postJson(route('api.employee.auth.request-otp'), ['country_code' => '+966', 'mobile_number' => '512345678'])
        ->assertOk();

    Mail::assertQueued(EmployeeOtpMail::class, fn (EmployeeOtpMail $mail) => $mail->hasTo($active->email));
});

it('refuses to guess when the number is active in two companies', function () {
    Mail::fake();
    foreach (Tenant::factory()->count(2)->create() as $tenant) {
        Employee::factory()->create(['tenant_id' => $tenant->id, 'mobile_country_code' => '+966', 'mobile_number' => '512345678', 'email' => 'x@example.com']);
    }

    $this->postJson(route('api.employee.auth.request-otp'), ['country_code' => '+966', 'mobile_number' => '512345678'])
        ->assertStatus(409);

    Mail::assertNothingQueued();
});

// ── unique mobile per company ────────────────────────────────────────────────

it('rejects a mobile number already used by another employee in the same company', function () {
    $tenant = Tenant::factory()->create();
    Employee::factory()->create(['tenant_id' => $tenant->id, 'mobile_country_code' => '+966', 'mobile_number' => '512345678']);
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs(User::factory()->admin($tenant)->create())
        ->put(route('employees.update', $employee), employeeUpdatePayload($employee, ['mobile_country_code' => '+966', 'mobile_number' => '512345678']))
        ->assertSessionHasErrors('mobile_number');
});

it('allows saving an employee with their own unchanged mobile', function () {
    $tenant = Tenant::factory()->create();
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs(User::factory()->admin($tenant)->create())
        ->put(route('employees.update', $employee), employeeUpdatePayload($employee))
        ->assertSessionHasNoErrors();
});

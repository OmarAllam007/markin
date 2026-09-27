<?php

use App\Mail\EmployeeOtpMail;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// ── helpers ──────────────────────────────────────────────────────────────────

function devicePayload(array $overrides = []): array
{
    return array_merge([
        'platform' => 'android',
        'android_id' => 'a1b2c3d4',
        'brand' => 'Samsung',
        'model' => 'S24 Ultra',
        'sdk' => 34,
        'app_version' => '1.0.0',
    ], $overrides);
}

function deviceFingerprint(array $device): string
{
    return hash('sha256', $device['android_id'].$device['brand'].$device['model']);
}

// ── request-otp ─────────────────────────────────────────────────────────────

it('returns 422 when country_code or mobile_number are missing', function () {
    $this->postJson(route('api.employee.auth.request-otp'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['country_code', 'mobile_number']);
});

it('returns 404 when no employee matches the given mobile', function () {
    $this->postJson(route('api.employee.auth.request-otp'), [
        'country_code' => '+966',
        'mobile_number' => '500000000',
    ])->assertNotFound();
});

it('emails the otp and returns only the verification token when employee is found', function () {
    Mail::fake();

    $employee = Employee::factory()->create([
        'mobile_country_code' => '+966',
        'mobile_number' => '512345678',
        'email' => 'employee@example.com',
    ]);

    $response = $this->postJson(route('api.employee.auth.request-otp'), [
        'country_code' => '+966',
        'mobile_number' => '512345678',
    ])->assertOk()
        ->assertJsonStructure(['data' => ['verification_token']])
        ->assertJsonMissingPath('data.otp');

    $token = $response->json('data.verification_token');
    $otp = Cache::get("otp:employee:{$employee->id}");

    expect($otp)->toHaveLength(4)
        ->and(ctype_digit($otp))->toBeTrue()
        ->and(Cache::get("otp:token:{$token}"))->toBe($employee->id);

    Mail::assertQueued(EmployeeOtpMail::class, fn (EmployeeOtpMail $mail) => $mail->hasTo($employee->email) && $mail->code === $otp);
});

it('renders the employee otp email with its security guidance', function () {
    $employee = Employee::factory()->create([
        'english_name' => 'Nora Ahmed',
        'email' => 'nora@example.com',
    ]);

    $email = new EmployeeOtpMail($employee, '4821');

    expect($email->render())
        ->toContain('Hi Nora Ahmed')
        ->toContain('4821 is your')
        ->toContain('Keep this code private')
        ->toContain('Expires in');
});

it('returns 422 when the employee has no email on file', function () {
    Mail::fake();

    Employee::factory()->create([
        'mobile_country_code' => '+966',
        'mobile_number' => '512345678',
        'email' => null,
    ]);

    $this->postJson(route('api.employee.auth.request-otp'), [
        'country_code' => '+966',
        'mobile_number' => '512345678',
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'No email is registered for this account. Please contact your administrator.');

    Mail::assertNothingSent();
});

// ── verify-otp ───────────────────────────────────────────────────────────────

it('returns 422 when verify-otp required fields are missing', function () {
    $this->postJson(route('api.employee.auth.verify-otp'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['verification_token', 'otp', 'device']);
});

it('returns 422 when device object is missing required fields', function () {
    $this->postJson(route('api.employee.auth.verify-otp'), [
        'verification_token' => Str::uuid()->toString(),
        'otp' => '1234',
        'device' => [],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['device.platform', 'device.android_id', 'device.brand', 'device.model']);
});

it('returns 422 when verification token is not in cache', function () {
    $this->postJson(route('api.employee.auth.verify-otp'), [
        'verification_token' => Str::uuid()->toString(),
        'otp' => '1234',
        'device' => devicePayload(),
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'Invalid or expired OTP session.');
});

it('returns 422 when otp does not match', function () {
    $employee = Employee::factory()->create();
    $verificationToken = Str::uuid()->toString();

    Cache::put("otp:employee:{$employee->id}", '9999', 300);
    Cache::put("otp:token:{$verificationToken}", $employee->id, 300);

    $this->postJson(route('api.employee.auth.verify-otp'), [
        'verification_token' => $verificationToken,
        'otp' => '0000',
        'device' => devicePayload(),
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'Invalid OTP code.');
});

it('registers device fingerprint and returns sanctum token on first successful login', function () {
    $employee = Employee::factory()->create(['device_fingerprint' => null]);
    $verificationToken = Str::uuid()->toString();
    $device = devicePayload();

    Cache::put("otp:employee:{$employee->id}", '1234', 300);
    Cache::put("otp:token:{$verificationToken}", $employee->id, 300);

    $this->postJson(route('api.employee.auth.verify-otp'), [
        'verification_token' => $verificationToken,
        'otp' => '1234',
        'device' => $device,
    ])->assertOk()
        ->assertJsonStructure(['data' => ['token', 'token_type']]);

    $employee->refresh();

    expect($employee->device_fingerprint)->toBe(deviceFingerprint($device))
        ->and($employee->device_name)->toBe('Samsung S24 Ultra')
        ->and($employee->tokens()->count())->toBe(1)
        ->and(Cache::get("otp:employee:{$employee->id}"))->toBeNull()
        ->and(Cache::get("otp:token:{$verificationToken}"))->toBeNull();
});

it('issues a fresh token when the same device logs in again', function () {
    $device = devicePayload();
    $employee = Employee::factory()->create(['device_fingerprint' => deviceFingerprint($device)]);
    $verificationToken = Str::uuid()->toString();

    Cache::put("otp:employee:{$employee->id}", '5678', 300);
    Cache::put("otp:token:{$verificationToken}", $employee->id, 300);

    $employee->createToken('old-token');

    $this->postJson(route('api.employee.auth.verify-otp'), [
        'verification_token' => $verificationToken,
        'otp' => '5678',
        'device' => $device,
    ])->assertOk()
        ->assertJsonStructure(['data' => ['token', 'token_type']]);

    expect($employee->tokens()->count())->toBe(1);
});

it('returns 403 when a different device tries to log in', function () {
    $registeredDevice = devicePayload(['android_id' => 'original-device']);
    $employee = Employee::factory()->create(['device_fingerprint' => deviceFingerprint($registeredDevice)]);
    $verificationToken = Str::uuid()->toString();

    Cache::put("otp:employee:{$employee->id}", '4321', 300);
    Cache::put("otp:token:{$verificationToken}", $employee->id, 300);

    $this->postJson(route('api.employee.auth.verify-otp'), [
        'verification_token' => $verificationToken,
        'otp' => '4321',
        'device' => devicePayload(['android_id' => 'different-device']),
    ])->assertForbidden();
});

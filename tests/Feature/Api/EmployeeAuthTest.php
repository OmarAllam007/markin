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

function startOtpSession(Employee $employee, string $code): string
{
    $verificationToken = Str::uuid()->toString();

    Cache::put("otp:token:{$verificationToken}", ['employee_id' => $employee->id, 'code' => $code], 300);

    return $verificationToken;
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

    $session = Cache::get('otp:token:'.$response->json('data.verification_token'));
    $otp = $session['code'];

    expect($otp)->toHaveLength(6)
        ->and(ctype_digit($otp))->toBeTrue()
        ->and($session['employee_id'])->toBe($employee->id);

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
        'otp' => '123456',
        'device' => [],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['device.platform', 'device.device_id', 'device.brand', 'device.model']);
});

it('returns 422 when verification token is not in cache', function () {
    $this->postJson(route('api.employee.auth.verify-otp'), [
        'verification_token' => Str::uuid()->toString(),
        'otp' => '123456',
        'device' => devicePayload(),
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'Invalid or expired OTP session.');
});

it('returns 422 when otp does not match', function () {
    $employee = Employee::factory()->create();
    $verificationToken = startOtpSession($employee, '999999');

    $this->postJson(route('api.employee.auth.verify-otp'), [
        'verification_token' => $verificationToken,
        'otp' => '000000',
        'device' => devicePayload(),
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'Invalid OTP code.');
});

it('registers device fingerprint and returns sanctum token on first successful login', function () {
    $employee = Employee::factory()->create(['device_fingerprint' => null]);
    $device = devicePayload();

    $verificationToken = startOtpSession($employee, '123456');

    $this->postJson(route('api.employee.auth.verify-otp'), [
        'verification_token' => $verificationToken,
        'otp' => '123456',
        'device' => $device,
    ])->assertOk()
        ->assertJsonStructure(['data' => ['token', 'token_type']]);

    $employee->refresh();

    expect($employee->device_fingerprint)->toBe(deviceFingerprint($device))
        ->and($employee->device_name)->toBe('Samsung S24 Ultra')
        ->and($employee->tokens()->count())->toBe(1)
        ->and(Cache::get("otp:token:{$verificationToken}"))->toBeNull();
});

it('issues a fresh token when the same device logs in again', function () {
    $device = devicePayload();
    $employee = Employee::factory()->create(['device_fingerprint' => deviceFingerprint($device)]);

    $verificationToken = startOtpSession($employee, '567890');

    $employee->createToken('old-token');

    $this->postJson(route('api.employee.auth.verify-otp'), [
        'verification_token' => $verificationToken,
        'otp' => '567890',
        'device' => $device,
    ])->assertOk()
        ->assertJsonStructure(['data' => ['token', 'token_type']]);

    expect($employee->tokens()->count())->toBe(1);
});

it('returns 403 when a different device tries to log in', function () {
    $registeredDevice = devicePayload(['android_id' => 'original-device']);
    $employee = Employee::factory()->create(['device_fingerprint' => deviceFingerprint($registeredDevice)]);

    $verificationToken = startOtpSession($employee, '432100');

    $this->postJson(route('api.employee.auth.verify-otp'), [
        'verification_token' => $verificationToken,
        'otp' => '432100',
        'device' => devicePayload(['android_id' => 'different-device']),
    ])->assertForbidden();
});

// ── brute-force protection ───────────────────────────────────────────────────

it('rejects a 4-digit otp now that codes are 6 digits', function () {
    $this->postJson(route('api.employee.auth.verify-otp'), [
        'verification_token' => Str::uuid()->toString(),
        'otp' => '1234',
        'device' => devicePayload(),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['otp']);
});

it('burns the otp session after 5 wrong guesses so even the right code fails', function () {
    $employee = Employee::factory()->create(['device_fingerprint' => null]);
    $verificationToken = startOtpSession($employee, '123456');

    // A different IP per guess, so only the per-session counter can stop it.
    foreach (range(1, 5) as $attempt) {
        $this->withServerVariables(['REMOTE_ADDR' => "10.0.1.{$attempt}"])->postJson(route('api.employee.auth.verify-otp'), [
            'verification_token' => $verificationToken,
            'otp' => '000000',
            'device' => devicePayload(),
        ])->assertUnprocessable();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.99'])->postJson(route('api.employee.auth.verify-otp'), [
        'verification_token' => $verificationToken,
        'otp' => '123456',
        'device' => devicePayload(),
    ])->assertTooManyRequests()
        ->assertJsonPath('message', 'Too many invalid attempts. Please request a new code.');

    expect(Cache::get("otp:token:{$verificationToken}"))->toBeNull()
        ->and($employee->tokens()->count())->toBe(0);
});

it('limits otp requests per mobile number regardless of ip', function () {
    Mail::fake();

    Employee::factory()->create([
        'mobile_country_code' => '+966',
        'mobile_number' => '512345678',
        'email' => 'employee@example.com',
    ]);

    $payload = ['country_code' => '+966', 'mobile_number' => '512345678'];

    foreach (['10.0.0.1', '10.0.0.2', '10.0.0.3'] as $ip) {
        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson(route('api.employee.auth.request-otp'), $payload)
            ->assertOk();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.4'])
        ->postJson(route('api.employee.auth.request-otp'), $payload)
        ->assertTooManyRequests();

    Mail::assertQueuedCount(3);
});

it('returns 429, not 500, when the ip throttle on auth routes is hit', function () {
    foreach (range(1, 5) as $attempt) {
        $this->postJson(route('api.employee.auth.request-otp'), []);
    }

    $this->postJson(route('api.employee.auth.request-otp'), [])->assertTooManyRequests();
});

// ── iOS / generic device id ──────────────────────────────────────────────────

it('logs in an iOS device that sends device_id and no android_id', function () {
    $employee = Employee::factory()->create(['device_fingerprint' => null]);
    $verificationToken = startOtpSession($employee, '123456');

    $this->postJson(route('api.employee.auth.verify-otp'), [
        'verification_token' => $verificationToken,
        'otp' => '123456',
        'device' => ['platform' => 'ios', 'device_id' => 'F3A1-IOS-UUID', 'brand' => 'Apple', 'model' => 'iPhone 16'],
    ])->assertOk()
        ->assertJsonStructure(['data' => ['token', 'token_type']]);

    expect($employee->refresh()->device_fingerprint)->toBe(hash('sha256', 'F3A1-IOS-UUIDAppleiPhone 16'))
        ->and($employee->device_name)->toBe('Apple iPhone 16');
});

it('keeps an android device registered via android_id working when the app switches to device_id', function () {
    $legacyDevice = devicePayload(['android_id' => 'abc123']);
    $employee = Employee::factory()->create(['device_fingerprint' => deviceFingerprint($legacyDevice)]);
    $verificationToken = startOtpSession($employee, '123456');

    $newPayload = devicePayload(['device_id' => 'abc123']);
    unset($newPayload['android_id']);

    $this->postJson(route('api.employee.auth.verify-otp'), [
        'verification_token' => $verificationToken,
        'otp' => '123456',
        'device' => $newPayload,
    ])->assertOk();
});

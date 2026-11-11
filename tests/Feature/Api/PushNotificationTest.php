<?php

use App\Enums\NotificationType;
use App\Jobs\SendEmployeePush;
use App\Models\Employee;
use App\Models\EmployeeNotification;
use App\Services\FcmSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/** A throwaway service-account file — the sender never talks to Google in tests. */
function fakeFcmCredentials(): string
{
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $privateKey);

    $path = sys_get_temp_dir().'/fcm-test-'.uniqid().'.json';
    file_put_contents($path, json_encode([
        'client_email' => 'push@markin.iam.gserviceaccount.com',
        'private_key' => $privateKey,
        'project_id' => 'markin-test',
    ]));

    config(['services.fcm.enabled' => true, 'services.fcm.credentials' => $path]);

    return $path;
}

// ── token endpoint ───────────────────────────────────────────────────────────

it('stores the push token for the signed-in employee', function () {
    $employee = Employee::factory()->create();

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.push-token.store'), ['push_token' => 'tok-123'])
        ->assertOk();

    expect($employee->fresh()->push_token)->toBe('tok-123');
});

it('takes a reused token away from the previous owner', function () {
    $old = Employee::factory()->create(['push_token' => 'tok-123']);
    $new = Employee::factory()->create();

    $this->actingAs($new, 'sanctum')
        ->postJson(route('api.employee.push-token.store'), ['push_token' => 'tok-123'])
        ->assertOk();

    expect($old->fresh()->push_token)->toBeNull()
        ->and($new->fresh()->push_token)->toBe('tok-123');
});

// ── every notification row becomes a push ────────────────────────────────────

it('queues a push whenever a notification row is written', function () {
    Queue::fake();
    $employee = Employee::factory()->create(['push_token' => 'tok-123']);

    EmployeeNotification::create([
        'tenant_id' => $employee->tenant_id,
        'employee_id' => $employee->id,
        'type' => NotificationType::Absent,
        'title' => 'Marked absent',
        'body' => 'No punch recorded today.',
    ]);

    Queue::assertPushed(SendEmployeePush::class);
});

// ── the sender ───────────────────────────────────────────────────────────────

it('sends the notification to the FCM v1 endpoint', function () {
    fakeFcmCredentials();
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.fake']),
        'fcm.googleapis.com/*' => Http::response(['name' => 'projects/markin-test/messages/1']),
    ]);

    $employee = Employee::factory()->create(['push_token' => 'tok-123']);
    $notification = EmployeeNotification::create([
        'tenant_id' => $employee->tenant_id,
        'employee_id' => $employee->id,
        'type' => NotificationType::LateCheckIn,
        'title' => 'You are late',
        'body' => '20 minutes after your shift start.',
    ]);

    (new SendEmployeePush($notification->id))->handle(app(FcmSender::class));

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), 'fcm.googleapis.com')) {
            return false;
        }

        return $request['message']['token'] === 'tok-123'
            && $request['message']['notification']['title'] === 'You are late'
            && $request['message']['data']['type'] === 'late_check_in';
    });
});

it('stays silent while push is switched off', function () {
    config(['services.fcm.enabled' => false]);
    Http::fake();

    $employee = Employee::factory()->create(['push_token' => 'tok-123']);
    $notification = EmployeeNotification::create([
        'tenant_id' => $employee->tenant_id,
        'employee_id' => $employee->id,
        'type' => NotificationType::Absent,
        'title' => 'Marked absent',
        'body' => 'No punch recorded today.',
    ]);

    (new SendEmployeePush($notification->id))->handle(app(FcmSender::class));

    Http::assertNothingSent();
});

it('drops a token FCM says no longer exists', function () {
    fakeFcmCredentials();
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.fake']),
        'fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'UNREGISTERED']], 404),
    ]);

    $employee = Employee::factory()->create(['push_token' => 'dead-token']);
    $notification = EmployeeNotification::create([
        'tenant_id' => $employee->tenant_id,
        'employee_id' => $employee->id,
        'type' => NotificationType::Absent,
        'title' => 'Marked absent',
        'body' => 'No punch recorded today.',
    ]);

    (new SendEmployeePush($notification->id))->handle(app(FcmSender::class));

    expect($employee->fresh()->push_token)->toBeNull();
});

it('skips an employee who never registered a device', function () {
    fakeFcmCredentials();
    Http::fake();

    $employee = Employee::factory()->create(['push_token' => null]);
    $notification = EmployeeNotification::create([
        'tenant_id' => $employee->tenant_id,
        'employee_id' => $employee->id,
        'type' => NotificationType::Absent,
        'title' => 'Marked absent',
        'body' => 'No punch recorded today.',
    ]);

    (new SendEmployeePush($notification->id))->handle(app(FcmSender::class));

    Http::assertNothingSent();
});

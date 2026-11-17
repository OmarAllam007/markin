<?php

use App\Jobs\SendAnnouncementPush;
use App\Models\Announcement;
use App\Models\Employee;
use App\Models\Tenant;
use App\Services\FcmSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function pushAnnouncement(Tenant $tenant, array $attrs = []): Announcement
{
    return Announcement::factory()->create(array_merge([
        'tenant_id' => $tenant->id,
        'title' => 'Eid holiday',
        'description' => '<p>The office is <strong>closed</strong> on Sunday.</p>',
        'sent_at' => now(),
    ], $attrs));
}

it('pushes only to the targeted employees that have a device', function () {
    config(['services.fcm.enabled' => true]);

    $tenant = Tenant::factory()->create();
    $withDevice = Employee::factory()->create(['tenant_id' => $tenant->id, 'push_token' => 'token-a']);
    $withoutDevice = Employee::factory()->create(['tenant_id' => $tenant->id, 'push_token' => null]);
    $notTargeted = Employee::factory()->create(['tenant_id' => $tenant->id, 'push_token' => 'token-b']);

    $announcement = pushAnnouncement($tenant);

    $sender = Mockery::mock(FcmSender::class);
    $sender->shouldReceive('send')
        ->once()
        ->with('token-a', 'Eid holiday', 'The office is closed on Sunday.', Mockery::type('array'))
        ->andReturnTrue();

    (new SendAnnouncementPush($announcement->id, [$withDevice->id, $withoutDevice->id]))->handle($sender);

    expect($notTargeted->fresh()->push_token)->toBe('token-b');
});

it('clears a token FCM rejects', function () {
    config(['services.fcm.enabled' => true]);

    $tenant = Tenant::factory()->create();
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id, 'push_token' => 'dead-token']);
    $announcement = pushAnnouncement($tenant);

    $sender = Mockery::mock(FcmSender::class);
    $sender->shouldReceive('send')->once()->andReturnFalse();

    (new SendAnnouncementPush($announcement->id, [$employee->id]))->handle($sender);

    expect($employee->fresh()->push_token)->toBeNull();
});

it('sends nothing while push is disabled', function () {
    config(['services.fcm.enabled' => false]);

    $tenant = Tenant::factory()->create();
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id, 'push_token' => 'token-a']);
    $announcement = pushAnnouncement($tenant);

    $sender = Mockery::mock(FcmSender::class);
    $sender->shouldNotReceive('send');

    (new SendAnnouncementPush($announcement->id, [$employee->id]))->handle($sender);

    expect($employee->fresh()->push_token)->toBe('token-a');
});

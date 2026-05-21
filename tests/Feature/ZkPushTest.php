<?php

use App\Jobs\ProcessZkPunchJob;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\ZkMachine;
use App\Models\ZkRawLog;
use App\Services\AttendancePunchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    $this->tenant = Tenant::factory()->create();
    $this->machine = ZkMachine::factory()->create(['tenant_id' => $this->tenant->id]);
});

it('responds to handshake with plain text options', function () {
    $response = $this->get("/api/iclock/cdata?SN={$this->machine->serial_number}&options=all&pushver=2.4.1");

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    expect($response->getContent())->toContain("GET OPTION FROM: {$this->machine->serial_number}");
    expect($response->getContent())->toContain('ATTLOGStamp=0');
});

it('rejects handshake for unknown serial number', function () {
    $response = $this->get('/api/iclock/cdata?SN=UNKNOWN_SERIAL&options=all');

    $response->assertStatus(403);
});

it('rejects handshake when SN is missing', function () {
    $response = $this->get('/api/iclock/cdata');

    $response->assertStatus(400);
});

it('stores raw log and dispatches job on attendance push', function () {
    $body = "{$this->machine->serial_number}\t2026-05-20 08:30:00\t0\t1\t0\t1";

    $response = $this->call(
        'POST',
        "/api/iclock/cdata?SN={$this->machine->serial_number}&table=ATTLOG&Stamp=1716192600",
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'text/plain'],
        $body,
    );

    $response->assertOk();
    expect($response->getContent())->toBe('OK');

    expect(ZkRawLog::count())->toBe(1);
    $log = ZkRawLog::first();
    expect($log->employee_code)->toBe($this->machine->serial_number);
    expect($log->punch_status)->toBe(0);
    expect($log->processed_at)->toBeNull();

    Queue::assertPushed(ProcessZkPunchJob::class, fn ($job) => $job->zkRawLogId === $log->id);
});

it('updates last_attlog_stamp after push', function () {
    $this->call(
        'POST',
        "/api/iclock/cdata?SN={$this->machine->serial_number}&table=ATTLOG&Stamp=9999999",
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'text/plain'],
        "EMP01\t2026-05-20 08:30:00\t0\t1\t0\t1",
    );

    expect($this->machine->fresh()->last_attlog_stamp)->toBe(9999999);
});

it('responds OK to getrequest', function () {
    $this->get("/api/iclock/getrequest?SN={$this->machine->serial_number}")
        ->assertOk()
        ->assertSee('OK');
});

it('responds OK to devicecmd', function () {
    $this->post("/api/iclock/devicecmd?SN={$this->machine->serial_number}")
        ->assertOk()
        ->assertSee('OK');
});

it('processes punch job and links to employee by biometric_id', function () {

    $employee = Employee::factory()->create([
        'tenant_id' => $this->tenant->id,
        'biometric_id' => 'EMP001',
    ]);

    $log = ZkRawLog::create([
        'zk_machine_id' => $this->machine->id,
        'employee_code' => 'EMP001',
        'punched_at' => '2026-05-20 08:30:00',
        'punch_status' => 0,
        'verify_type' => 1,
        'raw_line' => "EMP001\t2026-05-20 08:30:00\t0\t1\t0\t1",
    ]);

    (new ProcessZkPunchJob($log->id))->handle(app(AttendancePunchService::class));

    expect($log->fresh()->processed_at)->not->toBeNull();
    expect($employee->attendances()->count())->toBe(1);
});

it('marks log with error when employee biometric_id not found', function () {

    $log = ZkRawLog::create([
        'zk_machine_id' => $this->machine->id,
        'employee_code' => 'UNKNOWN',
        'punched_at' => '2026-05-20 08:30:00',
        'punch_status' => 0,
        'verify_type' => 1,
        'raw_line' => "UNKNOWN\t2026-05-20 08:30:00\t0\t1\t0\t1",
    ]);

    (new ProcessZkPunchJob($log->id))->handle(app(AttendancePunchService::class));

    expect($log->fresh()->processed_at)->toBeNull();
    expect($log->fresh()->error_message)->toContain('UNKNOWN');
});

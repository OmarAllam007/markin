<?php

use App\Enums\AppModule;
use App\Mail\TicketMessageMail;
use App\Models\Employee;
use App\Models\RequestStatus;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\TicketLog;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    Storage::fake('local');
    $this->tenant = Tenant::factory()->create();
    TenantModule::create(['tenant_id' => $this->tenant->id, 'module' => AppModule::Ticketing->value, 'is_enabled' => true]);
    $this->admin = User::factory()->admin($this->tenant)->create();
    $this->service = configuredTicketService($this->tenant);
    $this->technician = $this->service['technician'];
    $this->requester = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'email' => 'req@example.com']);
    $this->colleague = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'email' => 'cc@example.com']);
    $this->ticket = app(TicketService::class)->create(
        ['subject' => 'VPN down', 'category_id' => $this->service['category']->id, 'subcategory_id' => $this->service['subcategory']->id],
        $this->requester,
        $this->tenant->id,
    );
    $this->status = fn (string $name) => RequestStatus::where('tenant_id', $this->tenant->id)->where('name', $name)->sole();
});

it('starts new requests on the default status and seeds the company statuses', function () {
    expect($this->ticket->requestStatus->name)->toBe('Open')
        ->and(RequestStatus::forTenant($this->tenant->id)->pluck('type')->map->value->unique()->values()->all())
        ->toBe(['open', 'on_hold', 'resolved', 'closed', 'cancelled']);
});

it('lets the technician reply, change the status, copy people and attach files', function () {
    $inProgress = ($this->status)('In progress');

    $this->actingAs($this->technician)->post(route('ticketing.tickets.messages.store', $this->ticket), [
        'body' => 'Looking into it',
        'request_status_id' => $inProgress->id,
        'cc' => [$this->colleague->id],
        'attachments' => [UploadedFile::fake()->create('log.txt', 3)],
    ])->assertSessionHasNoErrors();

    $message = TicketMessage::sole();
    expect($message->from_requester)->toBeFalse()
        ->and($message->attachments)->toHaveCount(1)
        ->and($this->ticket->fresh()->request_status_id)->toBe($inProgress->id);

    Mail::assertQueued(TicketMessageMail::class, fn ($m) => $m->hasTo('req@example.com') && $m->hasCc('cc@example.com') && count($m->attachments()) === 1);

    $logs = TicketLog::whereIn('type', ['updated', 'message_added'])->orderBy('id')->get();
    expect($logs->pluck('type')->map->value->all())->toBe(['updated', 'message_added'])
        ->and($logs[0]->changes['request_status_id'])->toBe(['old' => 'Open', 'new' => 'In progress']);
});

it('lets the requester reply from the app but not resolve', function () {
    $this->actingAs($this->requester, 'sanctum')
        ->getJson(route('api.employee.tickets.reply-options', $this->ticket))
        ->assertOk()
        ->assertJsonMissing(['name' => 'Resolved'])
        ->assertJsonFragment(['name' => 'Closed']);

    $this->postJson(route('api.employee.tickets.messages.store', $this->ticket), [
        'body' => 'Fixed?', 'request_status_id' => ($this->status)('Resolved')->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('request_status_id');

    $this->post(route('api.employee.tickets.messages.store', $this->ticket), [
        'body' => 'Still broken', 'attachments' => [UploadedFile::fake()->image('error.png')],
    ], ['Accept' => 'application/json'])->assertCreated();

    expect(TicketMessage::sole()->from_requester)->toBeTrue();
    Mail::assertQueued(TicketMessageMail::class, fn ($m) => $m->hasTo($this->technician->email));

    $this->getJson(route('api.employee.tickets.show', $this->ticket))
        ->assertJsonPath('data.messages.0.body', 'Still broken')
        ->assertJsonPath('data.messages.0.mine', true)
        ->assertJsonPath('data.request_status.name', 'Open');
});

it('keeps other people out of the conversation', function () {
    $stranger = User::factory()->withTenant($this->tenant)->create();
    $this->actingAs($stranger)->post(route('ticketing.tickets.messages.store', $this->ticket), ['body' => 'hi'])->assertForbidden();

    $otherEmployee = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs($otherEmployee, 'sanctum')->postJson(route('api.employee.tickets.messages.store', $this->ticket), ['body' => 'hi'])->assertNotFound();
});

it('manages statuses: one default, open types only, reorder', function () {
    $this->actingAs($this->admin);
    $this->post(route('ticketing.admin.request-statuses.store'), ['name' => 'Waiting for parts', 'type' => 'on_hold', 'color' => '#aabbcc', 'is_default' => true])
        ->assertSessionHasErrors('is_default');
    $this->post(route('ticketing.admin.request-statuses.store'), ['name' => 'Assigned', 'type' => 'open', 'color' => '#aabbcc', 'is_default' => true])
        ->assertSessionHasNoErrors();

    expect(RequestStatus::where('tenant_id', $this->tenant->id)->where('is_default', true)->pluck('name')->all())->toBe(['Assigned']);
    $this->delete(route('ticketing.admin.request-statuses.destroy', ($this->status)('Assigned')))->assertSessionHas('error');

    $ids = RequestStatus::forTenant($this->tenant->id)->pluck('id')->reverse()->values()->all();
    $this->post(route('ticketing.admin.request-statuses.reorder'), ['ids' => $ids]);
    expect(RequestStatus::forTenant($this->tenant->id)->pluck('id')->all())->toBe($ids);
});

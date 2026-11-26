<?php

use App\Enums\AppModule;
use App\Models\Attachment;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\Ticket;
use App\Services\TicketApprovalService;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    Storage::fake('local');
    $this->tenant = Tenant::factory()->create();
    TenantModule::create(['tenant_id' => $this->tenant->id, 'module' => AppModule::Ticketing->value, 'is_enabled' => true]);
    $service = configuredTicketService($this->tenant);
    $this->requester = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->ticket = app(TicketService::class)->create([
        'subject' => 'Laptop', 'category_id' => $service['category']->id, 'subcategory_id' => $service['subcategory']->id,
    ], $this->requester, $this->tenant->id);
});

function storedAttachment(string $type, int $id, string $name = 'invoice.pdf'): Attachment
{
    Storage::disk('local')->put("ticketing/attachments/{$name}", 'file-bytes');

    return Attachment::create([
        'attachable_type' => $type,
        'attachable_id' => $id,
        'file_path' => "ticketing/attachments/{$name}",
        'file_name' => $name,
        'file_size' => 10,
        'mime_type' => 'application/pdf',
    ]);
}

it('gives the requester a short-lived link that serves the file', function () {
    $attachment = storedAttachment(Ticket::class, $this->ticket->id);

    $path = $this->actingAs($this->requester, 'sanctum')
        ->getJson(route('api.employee.tickets.attachments.link', [$this->ticket, $attachment]))
        ->assertSuccessful()
        ->json('data.path');

    // The browser opening it has no token.
    $this->app['auth']->forgetGuards();
    $response = $this->get($path)->assertSuccessful();
    expect($response->streamedContent())->toBe('file-bytes');
});

it('serves files attached to the ticket messages', function () {
    $message = $this->ticket->messages()->create(['body' => 'Here', 'employee_id' => $this->requester->id, 'from_requester' => true]);
    $attachment = storedAttachment(get_class($message), $message->id, 'reply.png');

    $this->actingAs($this->requester, 'sanctum')
        ->getJson(route('api.employee.tickets.attachments.link', [$this->ticket, $attachment]))
        ->assertSuccessful();
});

it('refuses employees who are neither requester nor approver', function () {
    $attachment = storedAttachment(Ticket::class, $this->ticket->id);
    $other = Employee::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($other, 'sanctum')
        ->getJson(route('api.employee.tickets.attachments.link', [$this->ticket, $attachment]))
        ->assertNotFound();
});

it('refuses an attachment that belongs to another ticket', function () {
    $otherTicket = Ticket::create([...$this->ticket->only(['tenant_id', 'category_id', 'subcategory_id', 'employee_id']), 'subject' => 'Other']);
    $attachment = storedAttachment(Ticket::class, $otherTicket->id);

    $this->actingAs($this->requester, 'sanctum')
        ->getJson(route('api.employee.tickets.attachments.link', [$this->ticket, $attachment]))
        ->assertNotFound();
});

it('refuses the file without a valid signature', function () {
    $attachment = storedAttachment(Ticket::class, $this->ticket->id);

    $this->get(route('api.employee.attachments.file', $attachment))->assertForbidden();
});

it('lets exactly the employees who can open the ticket open its files', function () {
    $attachment = storedAttachment(Ticket::class, $this->ticket->id);
    $approver = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'email' => 'approver@example.com']);
    app(TicketApprovalService::class)->request($this->ticket, $approver, 1);
    $outsider = Employee::factory()->create(['tenant_id' => $this->tenant->id]);

    foreach ([[$this->requester, true], [$approver, true], [$outsider, false]] as [$employee, $allowed]) {
        $page = $this->actingAs($employee, 'sanctum')->getJson(route('api.employee.tickets.show', $this->ticket));
        $file = $this->actingAs($employee, 'sanctum')
            ->getJson(route('api.employee.tickets.attachments.link', [$this->ticket, $attachment]));

        expect($page->isSuccessful())->toBe($allowed)
            ->and($file->isSuccessful())->toBe($allowed);
    }
});

it('lists and serves files sent to the ticket approvers', function () {
    $approver = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'email' => 'approver@example.com']);
    $approval = app(TicketApprovalService::class)->request($this->ticket, $approver, 1);
    $attachment = storedAttachment(get_class($approval), $approval->id, 'quote.pdf');

    $this->actingAs($this->requester, 'sanctum')
        ->getJson(route('api.employee.tickets.show', $this->ticket))
        ->assertJsonPath('data.approvals.0.attachments.0.file_name', 'quote.pdf');

    $this->getJson(route('api.employee.tickets.attachments.link', [$this->ticket, $attachment]))
        ->assertSuccessful();
});

<?php

use App\Enums\AppModule;
use App\Models\Employee;
use App\Models\RequestStatus;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    $this->tenant = Tenant::factory()->create();
    TenantModule::create(['tenant_id' => $this->tenant->id, 'module' => AppModule::Ticketing->value, 'is_enabled' => true]);
    $this->admin = User::factory()->admin($this->tenant)->create();

    // IT desk: its technician has no Ticketing role permission, only group membership.
    $this->it = configuredTicketService($this->tenant);
    $this->tech = $this->it['technician'];
    // HR desk: someone else's queue.
    $this->hr = configuredTicketService($this->tenant, ['name' => 'HR']);
    $this->hr['group']->update(['name' => 'HR Desk']);

    $requester = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'english_name' => 'Sami Ali', 'employee_number' => 'E77']);
    $make = fn ($service, $subject) => app(TicketService::class)->create(
        ['subject' => $subject, 'category_id' => $service['category']->id, 'subcategory_id' => $service['subcategory']->id],
        $requester,
        $this->tenant->id,
    );
    $this->mine = $make($this->it, 'Printer jammed');
    $this->groupTicket = $make($this->it, 'Laptop slow');
    $this->groupTicket->update(['technician_id' => null]);
    $this->hrTicket = $make($this->hr, 'Salary letter');
});

function listed($response): array
{
    return collect($response->viewData('page')['props']['tickets']['data'])->pluck('subject')->sort()->values()->all();
}

it('lets a technician-group member work their tickets without a Ticketing role', function () {
    $this->actingAs($this->tech);

    expect(listed($this->get(route('ticketing.tickets.index'))->assertOk()))->toBe(['Laptop slow', 'Printer jammed']);

    $this->get(route('ticketing.tickets.show', $this->mine))->assertOk()->assertInertia(fn ($p) => $p->where('canEdit', true)->where('canReply', true));
    $this->get(route('ticketing.tickets.show', $this->groupTicket))->assertOk();
    $this->get(route('ticketing.tickets.show', $this->hrTicket))->assertForbidden();
    $this->get(route('ticketing.tickets.edit', $this->hrTicket))->assertForbidden();

    $this->post(route('ticketing.tickets.messages.store', $this->mine), [
        'body' => 'On it', 'request_status_id' => RequestStatus::where('tenant_id', $this->tenant->id)->where('name', 'Resolved')->value('id'),
    ])->assertSessionHasNoErrors();
    expect($this->mine->fresh()->requestStatus->name)->toBe('Resolved');
});

it('keeps people who are neither permitted nor technicians out', function () {
    $this->actingAs(User::factory()->withTenant($this->tenant)->create())
        ->get(route('ticketing.tickets.index'))->assertForbidden();

    // Leaving the group (or the group being switched off) removes access.
    $this->it['group']->update(['is_active' => false]);
    $this->actingAs($this->tech)->get(route('ticketing.tickets.index'))->assertForbidden();
});

it('filters by status, type, assignee, group, dates, overdue and search', function () {
    $this->actingAs($this->admin);
    $inProgress = RequestStatus::where('tenant_id', $this->tenant->id)->where('name', 'In progress')->sole();
    $this->hrTicket->update(['request_status_id' => $inProgress->id, 'overdue' => true]);
    Ticket::whereKey($this->mine->id)->update(['created_at' => now()->subDays(10)]);

    $get = fn (array $q) => listed($this->get(route('ticketing.tickets.index', $q)));

    expect($get([]))->toHaveCount(3)
        ->and($get(['request_status_id' => $inProgress->id]))->toBe(['Salary letter'])
        ->and($get(['request_status_id' => 'open']))->toHaveCount(3)
        ->and($get(['request_status_id' => 'on_hold']))->toBe([])
        ->and($get(['technician_id' => 'none']))->toBe(['Laptop slow'])
        ->and($get(['group_id' => $this->hr['group']->id]))->toBe(['Salary letter'])
        ->and($get(['overdue' => 1]))->toBe(['Salary letter'])
        ->and($get(['from' => now()->subDay()->toDateString()]))->toBe(['Laptop slow', 'Salary letter'])
        ->and($get(['search' => 'E77']))->toHaveCount(3)
        ->and($get(['search' => "#{$this->mine->id}"]))->toContain('Printer jammed');

    $this->actingAs($this->tech);
    expect($get(['technician_id' => 'me']))->toBe(['Printer jammed']);
});

it('exports the filtered list, limited to what the user can see', function () {
    $this->actingAs($this->tech);
    $response = $this->get(route('ticketing.tickets.export'))->assertOk()->assertDownload();

    $path = tempnam(sys_get_temp_dir(), 'tix').'.xlsx';
    file_put_contents($path, $response->streamedContent());
    $rows = IOFactory::load($path)->getActiveSheet()->toArray();

    expect($rows[0][1])->toBe('Subject')
        ->and(collect($rows)->skip(1)->pluck(1)->sort()->values()->all())->toBe(['Laptop slow', 'Printer jammed']);

    $this->actingAs($this->admin);
    $file = tempnam(sys_get_temp_dir(), 'tix').'.xlsx';
    file_put_contents($file, $this->get(route('ticketing.tickets.export', ['group_id' => $this->hr['group']->id]))->streamedContent());
    expect(IOFactory::load($file)->getActiveSheet()->toArray())->toHaveCount(2);
});

it('hides ticket setup from technicians', function () {
    $this->actingAs($this->tech)->get(route('ticketing.tickets.index'))->assertInertia(fn ($p) => $p->where('canManageTicketing', false));
    $this->actingAs($this->admin)->get(route('ticketing.tickets.index'))->assertInertia(fn ($p) => $p->where('canManageTicketing', true));
});

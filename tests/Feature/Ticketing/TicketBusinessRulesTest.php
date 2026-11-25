<?php

use App\Enums\AppModule;
use App\Enums\TicketType;
use App\Mail\TicketCreatedMail;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketGroup;
use App\Models\TicketSla;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    $this->tenant = Tenant::factory()->create();
    TenantModule::create(['tenant_id' => $this->tenant->id, 'module' => AppModule::Ticketing->value, 'is_enabled' => true]);
    $this->admin = User::factory()->admin($this->tenant)->create();
});

function submitTicket(array $service, array $extra = [])
{
    return test()->actingAs(test()->admin)->post(route('ticketing.tickets.store'), [
        'subject' => 'Printer jammed',
        'category_id' => $service['category']->id,
        'subcategory_id' => $service['subcategory']->id,
        ...$extra,
    ]);
}

it('assigns the group and technician from the service config, applies the SLA and emails both sides', function () {
    $service = configuredTicketService($this->tenant);
    $sla = TicketSla::create(['tenant_id' => $this->tenant->id, 'name' => 'Standard', 'first_response_hours' => 2, 'resolve_hours' => 24, 'is_active' => true]);
    $service['subcategory']->update(['sla_id' => $sla->id, 'technician_id' => $service['technician']->id]);

    $this->freezeTime();
    submitTicket($service)->assertRedirect();

    $ticket = Ticket::sole();
    expect($ticket->group_id)->toBe($service['group']->id)
        ->and($ticket->technician_id)->toBe($service['technician']->id)
        ->and($ticket->sla_id)->toBe($sla->id)
        ->and($ticket->due_date->toDateTimeString())->toBe(now()->addHours(24)->toDateTimeString());

    Mail::assertQueued(TicketCreatedMail::class, fn ($m) => ! $m->forTechnician && $m->hasTo($this->admin->email));
    Mail::assertQueued(TicketCreatedMail::class, fn ($m) => $m->forTechnician && $m->hasTo($service['technician']->email));
});

it('lets a subcategory override the category group', function () {
    $service = configuredTicketService($this->tenant);
    $hw = User::factory()->withTenant($this->tenant)->create();
    $hwGroup = TicketGroup::create(['tenant_id' => $this->tenant->id, 'name' => 'Hardware', 'type' => 'technician']);
    $hwGroup->members()->attach($hw);
    $service['subcategory']->update(['group_id' => $hwGroup->id]);

    submitTicket($service)->assertRedirect();

    expect(Ticket::sole()->only('group_id', 'technician_id'))->toBe(['group_id' => $hwGroup->id, 'technician_id' => $hw->id]);
});

it('picks the least busy group member when no technician is configured', function () {
    $service = configuredTicketService($this->tenant);
    $idle = User::factory()->withTenant($this->tenant)->create();
    $service['group']->members()->attach($idle);

    submitTicket($service);
    submitTicket($service);

    expect(Ticket::pluck('technician_id')->sort()->values()->all())
        ->toBe(collect([$service['technician']->id, $idle->id])->sort()->values()->all());
});

it('rejects the ticket when no technician group is configured', function () {
    $service = configuredTicketService($this->tenant);
    $service['category']->update(['group_id' => null]);

    submitTicket($service)->assertSessionHasErrors('category_id');
    expect(Ticket::count())->toBe(0);
});

it('ignores a non-technician group', function () {
    $service = configuredTicketService($this->tenant);
    $service['group']->update(['type' => 'approver']);

    submitTicket($service)->assertSessionHasErrors('category_id');
});

it('requires a subcategory but not an item', function () {
    $service = configuredTicketService($this->tenant);

    submitTicket($service, ['subcategory_id' => null])->assertSessionHasErrors('subcategory_id');
    submitTicket($service)->assertSessionDoesntHaveErrors();
});

it('validates against the designed form and only stores its fields', function () {
    $service = configuredTicketService($this->tenant);
    $service['subcategory']->update(['form_fields' => [
        ['key' => 'asset_tag', 'label' => 'Asset tag', 'type' => 'text', 'required' => true, 'width' => 'half'],
        ['key' => 'subject', 'label' => 'Subject', 'type' => 'text', 'required' => true, 'width' => 'full'],
    ]]);

    submitTicket($service)->assertSessionHasErrors('form_data.asset_tag');
    submitTicket($service, ['form_data' => ['asset_tag' => 'PC-42']])->assertSessionDoesntHaveErrors();

    expect(Ticket::sole()->form_data)->toBe(['asset_tag' => 'PC-42']);
});

it('computes the number of days for a leave request on the server', function () {
    $service = configuredTicketService($this->tenant, ['ticket_type' => TicketType::Leave->value]);

    submitTicket($service, ['form_data' => [
        'start_date' => '2026-12-01',
        'end_date' => '2026-12-03',
        'leave_type' => 'annual',
        'reason' => 'Family',
        'days_count' => 99, // client value is ignored
    ]])->assertSessionDoesntHaveErrors();

    expect(Ticket::sole()->form_data['days_count'])->toBe(3);
});

it('rejects a leave that ends before it starts', function () {
    $service = configuredTicketService($this->tenant, ['ticket_type' => TicketType::Leave->value]);

    submitTicket($service, ['form_data' => ['start_date' => '2026-12-05', 'end_date' => '2026-12-01', 'leave_type' => 'annual', 'reason' => 'x']])
        ->assertSessionHasErrors('form_data.end_date');
});

it('evaluates formulas', function (string $formula, string $from, string $to, int|float|null $expected) {
    expect(TicketService::evaluate($formula, $from, $to))->toBe($expected);
})->with([
    ['days_between', '2026-12-01', '2026-12-01', 1],
    ['days_between', '2026-12-30', '2027-01-02', 4],
    ['hours_between', '09:00', '17:30', 8.5],
    ['hours_between', '22:00', '02:00', 4.0],
]);

it('keeps ticket-type fields when the designer drops them', function () {
    $service = configuredTicketService($this->tenant, ['ticket_type' => TicketType::BusinessTrip->value]);

    $this->actingAs($this->admin)->put(route('ticketing.admin.service-catalog.update', ['level' => 'category', 'id' => $service['category']->id]), [
        'group_id' => $service['group']->id,
        'form_fields' => [['key' => 'subject', 'label' => 'Trip title', 'type' => 'text', 'required' => true, 'width' => 'full']],
    ])->assertSessionDoesntHaveErrors();

    $keys = array_column(app(TicketService::class)->formFields($service['category']->fresh()), 'key');
    expect($keys)->toContain('destination', 'start_date', 'end_date', 'days_count');
});

it('refuses a technician who is not in the chosen group', function () {
    $service = configuredTicketService($this->tenant);
    $outsider = User::factory()->withTenant($this->tenant)->create();

    $this->actingAs($this->admin)->put(route('ticketing.admin.service-catalog.update', ['level' => 'subcategory', 'id' => $service['subcategory']->id]), [
        'group_id' => $service['group']->id,
        'technician_id' => $outsider->id,
    ])->assertSessionHasErrors('technician_id');
});

it('renders the service catalog for a node', function () {
    $service = configuredTicketService($this->tenant);

    $this->actingAs($this->admin)
        ->get(route('ticketing.admin.service-catalog.index', ['node' => "subcategory:{$service['subcategory']->id}"]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ticketing/admin/service-catalog/Index')
            ->where('selected.inherited.group_id', $service['group']->id)
            ->has('selected.fields', 4));
});

it('stores label translations for any configured language and serves them to the app', function () {
    config(['app.content_locales' => ['en', 'ar', 'fr']]);
    $service = configuredTicketService($this->tenant);

    $this->actingAs($this->admin)->put(route('ticketing.admin.service-catalog.update', ['level' => 'category', 'id' => $service['category']->id]), [
        'group_id' => $service['group']->id,
        'form_fields' => [
            ['key' => 'subject', 'label' => 'Subject', 'translations' => ['ar' => 'الموضوع', 'fr' => 'Sujet'], 'type' => 'text', 'required' => true, 'width' => 'full'],
            ['key' => 'size', 'label' => 'Size', 'translations' => ['ar' => 'المقاس'], 'type' => 'select', 'required' => false, 'width' => 'half',
                'options' => [['value' => 's', 'label' => 'Small', 'translations' => ['ar' => 'صغير']]]],
        ],
    ])->assertSessionDoesntHaveErrors();

    $fields = app(TicketService::class)->formFields($service['category']->fresh());
    expect($fields[0]['translations'])->toBe(['ar' => 'الموضوع', 'fr' => 'Sujet']);

    $ar = TicketService::localize($fields, 'ar');
    expect($ar[1]['label'])->toBe('المقاس')
        ->and($ar[1]['options'][0]['label'])->toBe('صغير')
        ->and(TicketService::localize($fields, 'fr')[1]['label'])->toBe('Size'); // falls back to the default label
});

it('ships Arabic labels for the built-in request-type fields', function () {
    $fields = collect(TicketType::Leave->baseFields())->keyBy('key');

    expect($fields['days_count']['translations']['ar'])->toBe('عدد الأيام')
        ->and($fields['leave_type']['options'][0]['translations']['ar'])->toBe('إجازة سنوية');
});

it('saves category name translations from the admin form', function () {
    $this->actingAs($this->admin)->post(route('ticketing.admin.categories.store'), [
        'name' => 'Facilities',
        'name_translations' => ['ar' => 'المرافق', 'fr' => 'Installations'],
        'color' => '#000000',
        'is_active' => true,
    ])->assertSessionDoesntHaveErrors();

    expect(TicketCategory::where('name', 'Facilities')->sole()->name_translations)
        ->toBe(['ar' => 'المرافق', 'fr' => 'Installations']);
});

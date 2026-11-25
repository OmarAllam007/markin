<?php

use App\Enums\AppModule;
use App\Models\ApprovalRole;
use App\Models\CompanySetting;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\TicketSla;
use App\Models\User;
use App\Services\BusinessCalendar;
use App\Services\TicketApprovalService;
use App\Services\TicketService;
use App\Services\TicketSlaClock;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    $this->tenant = Tenant::factory()->create();
    TenantModule::create(['tenant_id' => $this->tenant->id, 'module' => AppModule::Ticketing->value, 'is_enabled' => true]);
    // Sunday–Thursday 08:00–17:00, Riyadh time.
    CompanySetting::updateOrCreate(['tenant_id' => $this->tenant->id], ['timezone' => 'Asia/Riyadh', 'business_hours' => BusinessCalendar::DEFAULT_HOURS]);
    $this->riyadh = fn (string $at) => CarbonImmutable::parse($at, 'Asia/Riyadh');
});

it('counts only working minutes, skipping nights, weekends and holidays', function () {
    $cal = BusinessCalendar::forTenant($this->tenant->id);
    $at = $this->riyadh;

    // Thursday 16:00 → Sunday 09:00 = 1h Thursday + 1h Sunday.
    expect($cal->minutesBetween($at('2026-11-26 16:00'), $at('2026-11-29 09:00')))->toBe(120)
        // 4 working hours from Thursday 16:00 lands on Sunday 11:00.
        ->and($cal->addMinutes($at('2026-11-26 16:00'), 240)->setTimezone('Asia/Riyadh')->format('D H:i'))->toBe('Sun 11:00')
        ->and($cal->isOpen($at('2026-11-27 10:00')))->toBeFalse();

    Holiday::create(['tenant_id' => $this->tenant->id, 'name' => 'National day', 'date' => '2026-11-29', 'is_recurring' => false]);
    $cal = BusinessCalendar::forTenant($this->tenant->id);
    expect($cal->addMinutes($at('2026-11-26 16:00'), 240)->setTimezone('Asia/Riyadh')->format('D H:i'))->toBe('Mon 11:00');
});

it('sets due dates in working hours for business-hours SLAs, and around the clock otherwise', function () {
    // Travel in UTC: a zoned test clock makes Carbon read stored dates in that zone.
    $this->travelTo(($this->riyadh)('2026-11-26 16:00')->utc());
    $make = function (bool $businessHours) {
        $sla = TicketSla::create(['tenant_id' => $this->tenant->id, 'name' => 'SLA'.rand(), 'first_response_hours' => 1, 'resolve_hours' => 4, 'business_hours_only' => $businessHours, 'is_active' => true]);
        $service = configuredTicketService($this->tenant, ['sla_id' => $sla->id, 'name' => 'Svc'.rand()]);

        return app(TicketService::class)->create(
            ['subject' => 'X', 'category_id' => $service['category']->id, 'subcategory_id' => $service['subcategory']->id],
            Employee::factory()->create(['tenant_id' => $this->tenant->id]),
            $this->tenant->id,
        );
    };

    $working = $make(true);
    expect($working->due_date->setTimezone('Asia/Riyadh')->format('D H:i'))->toBe('Sun 11:00')
        ->and($make(false)->due_date->setTimezone('Asia/Riyadh')->format('D H:i'))->toBe('Thu 20:00');

    // Friday noon: the business-hours clock is running, but only Thursday's last hour counted.
    $ticket = $working->fresh();
    $this->travelTo(($this->riyadh)('2026-11-27 12:00')->utc());
    expect(TicketSlaClock::elapsed($ticket))->toBe(60)->and(TicketSlaClock::ticking($ticket))->toBeFalse();
});

it('pauses the clock while approvals are pending and gives the time back on approval', function () {
    $this->freezeTime();
    $sla = TicketSla::create(['tenant_id' => $this->tenant->id, 'name' => '24/7', 'first_response_hours' => 1, 'resolve_hours' => 4, 'business_hours_only' => false, 'is_active' => true]);
    $manager = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'email' => 'boss@example.com']);
    $role = ApprovalRole::create(['tenant_id' => $this->tenant->id, 'name' => 'Boss', 'employee_id' => $manager->id]);
    $service = configuredTicketService($this->tenant, ['sla_id' => $sla->id, 'approval_steps' => ["role:{$role->id}"]]);

    $ticket = app(TicketService::class)->create(
        ['subject' => 'Laptop', 'category_id' => $service['category']->id, 'subcategory_id' => $service['subcategory']->id],
        Employee::factory()->create(['tenant_id' => $this->tenant->id]),
        $this->tenant->id,
    )->fresh();
    $due = $ticket->due_date;

    expect(TicketSlaClock::state($ticket))->toBe('approval');

    // A day of waiting for the manager doesn't count.
    $this->travel(1)->day();
    expect(TicketSlaClock::elapsed($ticket->fresh()))->toBe(0);
    app(TicketApprovalService::class)->respond($ticket->approvals()->sole(), true, null);

    $ticket->refresh();
    expect(TicketSlaClock::state($ticket))->toBe('running')
        ->and($ticket->due_date->toDateTimeString())->toBe($due->addDay()->toDateTimeString());
});

it('saves working hours from the admin screen', function () {
    $admin = User::factory()->admin($this->tenant)->create();
    $hours = [...BusinessCalendar::DEFAULT_HOURS, '1' => ['09:00', '13:00']];

    $this->actingAs($admin)->put(route('ticketing.admin.business-hours.update'), ['hours' => $hours])->assertSessionHasNoErrors();
    expect($this->tenant->settings()->first()->business_hours['1'])->toBe(['09:00', '13:00']);

    $this->put(route('ticketing.admin.business-hours.update'), ['hours' => [...$hours, '2' => ['17:00', '08:00']]])->assertSessionHasErrors('hours.2');
    $this->put(route('ticketing.admin.business-hours.update'), ['hours' => array_fill(0, 7, null)])->assertSessionHasErrors('hours');
});

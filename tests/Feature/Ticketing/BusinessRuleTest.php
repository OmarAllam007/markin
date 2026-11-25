<?php

use App\Enums\AppModule;
use App\Models\BusinessRule;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\Ticket;
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

    // The HR service; its catalog default is the HR desk's own technician.
    $this->hr = configuredTicketService($this->tenant, ['name' => 'HR']);
    $this->tech1 = User::factory()->withTenant($this->tenant)->create(['name' => 'Tech One']);
    $this->tech2 = User::factory()->withTenant($this->tenant)->create(['name' => 'Tech Two']);
    $this->hr['group']->members()->attach([$this->tech1->id, $this->tech2->id]);

    $this->infra = Department::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Infrastructure']);
    $this->software = Department::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Software']);
});

function rule(array $conditions, User $technician, array $extra = []): BusinessRule
{
    return BusinessRule::create([
        'tenant_id' => test()->tenant->id, 'name' => 'Rule '.BusinessRule::count(), 'match' => 'all',
        'conditions' => $conditions, 'group_id' => test()->hr['group']->id, 'technician_id' => $technician->id,
        'sort_order' => BusinessRule::count(), ...$extra,
    ]);
}

function requestFrom(Employee $employee, ?array $service = null): Ticket
{
    $service ??= test()->hr;

    return app(TicketService::class)->create(
        ['subject' => 'Salary letter', 'category_id' => $service['category']->id, 'subcategory_id' => $service['subcategory']->id],
        $employee,
        test()->tenant->id,
    );
}

it('routes the same service to different technicians by department', function () {
    $hrCategory = $this->hr['category']->id;
    rule([['field' => 'category', 'operator' => 'in', 'values' => [$hrCategory]], ['field' => 'department', 'operator' => 'in', 'values' => [$this->infra->id]]], $this->tech1);
    rule([['field' => 'category', 'operator' => 'in', 'values' => [$hrCategory]], ['field' => 'department', 'operator' => 'in', 'values' => [$this->software->id]]], $this->tech2);

    $fromInfra = requestFrom(Employee::factory()->create(['tenant_id' => $this->tenant->id, 'department_id' => $this->infra->id]));
    $fromSoftware = requestFrom(Employee::factory()->create(['tenant_id' => $this->tenant->id, 'department_id' => $this->software->id]));
    $fromElsewhere = requestFrom(Employee::factory()->create(['tenant_id' => $this->tenant->id]));

    expect($fromInfra->technician_id)->toBe($this->tech1->id)
        ->and($fromSoftware->technician_id)->toBe($this->tech2->id)
        // No rule matched: the service catalog decides.
        ->and($fromElsewhere->technician_id)->toBe($this->hr['technician']->id)
        ->and($fromInfra->logs()->where('type', 'rule_applied')->sole()->meta['rule'])->toBe('Rule 0')
        ->and($fromElsewhere->logs()->where('type', 'rule_applied')->exists())->toBeFalse();
});

it('routes a list of employees to one person, checking rules in order and skipping inactive ones', function () {
    $managers = Employee::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);
    rule([['field' => 'employee', 'operator' => 'in', 'values' => $managers->pluck('id')->all()]], $this->tech1, ['is_active' => false]);
    rule([['field' => 'employee', 'operator' => 'in', 'values' => $managers->pluck('id')->all()]], $this->tech2);
    rule([['field' => 'source', 'operator' => 'in', 'values' => ['mobile']]], $this->tech1);

    expect(requestFrom($managers[1])->technician_id)->toBe($this->tech2->id)
        ->and(requestFrom(Employee::factory()->create(['tenant_id' => $this->tenant->id]))->technician_id)->toBe($this->tech1->id);
});

it('supports "is none of" and matching any condition', function () {
    rule([
        ['field' => 'department', 'operator' => 'not_in', 'values' => [$this->infra->id, $this->software->id]],
        ['field' => 'department', 'operator' => 'in', 'values' => [$this->software->id]],
    ], $this->tech2, ['match' => 'any']);

    expect(requestFrom(Employee::factory()->create(['tenant_id' => $this->tenant->id, 'department_id' => $this->software->id]))->technician_id)->toBe($this->tech2->id)
        ->and(requestFrom(Employee::factory()->create(['tenant_id' => $this->tenant->id]))->technician_id)->toBe($this->tech2->id)
        ->and(requestFrom(Employee::factory()->create(['tenant_id' => $this->tenant->id, 'department_id' => $this->infra->id]))->technician_id)->toBe($this->hr['technician']->id);
});

it('saves rules from the admin screen, keeping values inside the company', function () {
    $other = Department::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);
    $payload = [
        'name' => 'Infra HR', 'match' => 'all', 'is_active' => true,
        'conditions' => [['field' => 'department', 'operator' => 'in', 'values' => [$this->infra->id, $other->id]]],
        'group_id' => $this->hr['group']->id, 'technician_id' => $this->tech1->id,
    ];

    $this->actingAs($this->admin)->post(route('ticketing.admin.business-rules.store'), $payload)->assertSessionHasNoErrors();
    expect(BusinessRule::sole()->conditions[0]['values'])->toBe([$this->infra->id]);

    $outsider = User::factory()->withTenant($this->tenant)->create();
    $this->post(route('ticketing.admin.business-rules.store'), [...$payload, 'technician_id' => $outsider->id])->assertSessionHasErrors('technician_id');
    $this->post(route('ticketing.admin.business-rules.store'), [...$payload, 'conditions' => [['field' => 'department', 'operator' => 'in', 'values' => [$other->id]]]])
        ->assertSessionHasErrors('conditions');

    $rule = BusinessRule::sole();
    $this->put(route('ticketing.admin.business-rules.update', $rule), ['is_active' => false]);
    expect($rule->fresh()->is_active)->toBeFalse();

    $this->get(route('ticketing.admin.business-rules.index'))->assertOk()->assertInertia(fn ($p) => $p->has('rules', 1)->has('options.department'));
    $this->actingAs($this->tech1)->get(route('ticketing.admin.business-rules.index'))->assertForbidden();
});

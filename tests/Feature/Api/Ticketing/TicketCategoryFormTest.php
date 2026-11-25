<?php

use App\Enums\AppModule;
use App\Enums\TicketType;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\TicketCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->employee = Employee::factory()->create();

    TenantModule::create([
        'tenant_id' => $this->employee->tenant_id,
        'module' => AppModule::Ticketing->value,
        'is_enabled' => true,
    ]);
});

it('always includes subject and description as first two fields', function () {
    $category = TicketCategory::create([
        'tenant_id' => $this->employee->tenant_id,
        'name' => 'General',
        'color' => '#000000',
        'is_active' => true,
        'ticket_type' => TicketType::General->value,
    ]);

    $response = $this->actingAs($this->employee, 'sanctum')
        ->getJson(route('api.employee.tickets.categories.form', $category))
        ->assertSuccessful()
        ->json('data.fields');

    expect($response[0]['key'])->toBe('subject');
    expect($response[1]['key'])->toBe('description');
});

it('returns base fields from the TicketType enum marked as is_base true', function () {
    $category = TicketCategory::create([
        'tenant_id' => $this->employee->tenant_id,
        'name' => 'Leave',
        'color' => '#000000',
        'is_active' => true,
        'ticket_type' => TicketType::Leave->value,
    ]);

    $fields = $this->actingAs($this->employee, 'sanctum')
        ->getJson(route('api.employee.tickets.categories.form', $category))
        ->assertSuccessful()
        ->json('data.fields');

    $baseFields = array_filter($fields, fn ($f) => $f['locked'] === true);
    $baseKeys = array_column(array_values($baseFields), 'key');

    expect($baseKeys)->toContain('start_date')
        ->toContain('end_date')
        ->toContain('leave_type')
        ->toContain('reason');
});

it('returns the form designed on the category, in designed order', function () {
    $category = TicketCategory::create([
        'tenant_id' => $this->employee->tenant_id,
        'name' => 'General',
        'color' => '#000000',
        'is_active' => true,
        'ticket_type' => TicketType::General->value,
        'form_fields' => [
            ['key' => 'level', 'label' => 'Level', 'type' => 'select', 'required' => true, 'width' => 'half', 'options' => [['value' => 'low', 'label' => 'Low'], ['value' => 'high', 'label' => 'High']]],
            ['key' => 'subject', 'label' => 'Subject', 'type' => 'text', 'required' => false, 'width' => 'full'],
        ],
    ]);

    $fields = $this->actingAs($this->employee, 'sanctum')
        ->getJson(route('api.employee.tickets.categories.form', $category))
        ->assertSuccessful()
        ->json('data.fields');

    expect(array_column($fields, 'key'))->toBe(['level', 'subject'])
        ->and($fields[0]['locked'])->toBeFalse()
        ->and($fields[0]['options'])->toHaveCount(2)
        ->and($fields[1]['required'])->toBeTrue(); // subject is always required
});

it('returns the default subject, description, priority and attachments form when nothing is designed', function () {
    $category = TicketCategory::create([
        'tenant_id' => $this->employee->tenant_id,
        'name' => 'General',
        'color' => '#000000',
        'is_active' => true,
        'ticket_type' => TicketType::General->value,
    ]);

    $fields = $this->actingAs($this->employee, 'sanctum')
        ->getJson(route('api.employee.tickets.categories.form', $category))
        ->assertSuccessful()
        ->json('data.fields');

    expect(array_column($fields, 'key'))->toBe(['subject', 'description', 'priority_id', 'attachments']);
});

it('returns 404 for form when category belongs to a different tenant', function () {
    $otherTenant = Tenant::factory()->create();
    $category = TicketCategory::create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Other',
        'color' => '#000000',
        'is_active' => true,
    ]);

    $this->actingAs($this->employee, 'sanctum')
        ->getJson(route('api.employee.tickets.categories.form', $category))
        ->assertNotFound();
});

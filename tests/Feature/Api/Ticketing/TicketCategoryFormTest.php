<?php

use App\Enums\AppModule;
use App\Enums\TicketType;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\TicketCategory;
use App\Models\TicketTypeCustomField;
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

    $baseFields = array_filter($fields, fn ($f) => $f['is_base'] === true);
    $baseKeys = array_column(array_values($baseFields), 'key');

    expect($baseKeys)->toContain('start_date')
        ->toContain('end_date')
        ->toContain('leave_type')
        ->toContain('reason');
});

it('returns custom fields from the database marked as is_base false', function () {
    $category = TicketCategory::create([
        'tenant_id' => $this->employee->tenant_id,
        'name' => 'General',
        'color' => '#000000',
        'is_active' => true,
        'ticket_type' => TicketType::General->value,
    ]);

    TicketTypeCustomField::create([
        'tenant_id' => $this->employee->tenant_id,
        'ticket_type' => TicketType::General->value,
        'field_key' => 'priority_level',
        'label' => 'Priority Level',
        'type' => 'select',
        'options' => [['value' => 'low', 'label' => 'Low'], ['value' => 'high', 'label' => 'High']],
        'is_required' => true,
        'sort_order' => 0,
    ]);

    $fields = $this->actingAs($this->employee, 'sanctum')
        ->getJson(route('api.employee.tickets.categories.form', $category))
        ->assertSuccessful()
        ->json('data.fields');

    $customField = collect($fields)->firstWhere('key', 'priority_level');

    expect($customField)->not->toBeNull()
        ->and($customField['is_base'])->toBeFalse()
        ->and($customField['type'])->toBe('select')
        ->and($customField['options'])->toHaveCount(2);
});

it('returns only subject and description for General type with no custom fields', function () {
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

    expect($fields)->toHaveCount(2)
        ->and($fields[0]['key'])->toBe('subject')
        ->and($fields[1]['key'])->toBe('description');
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

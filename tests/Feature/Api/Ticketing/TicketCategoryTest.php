<?php

use App\Enums\AppModule;
use App\Enums\TicketType;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\TicketCategory;
use App\Models\TicketSubcategory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function enableTicketing(int $tenantId): void
{
    TenantModule::create([
        'tenant_id' => $tenantId,
        'module' => AppModule::Ticketing->value,
        'is_enabled' => true,
    ]);
}

// --- index ---

it('returns 403 on categories list when ticketing module is disabled', function () {
    $employee = Employee::factory()->create();

    $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.tickets.categories.index'))
        ->assertForbidden();
});

it('returns categories list when ticketing module is enabled', function () {
    $employee = Employee::factory()->create();
    enableTicketing($employee->tenant_id);

    TicketCategory::create([
        'tenant_id' => $employee->tenant_id,
        'name' => 'IT Support',
        'color' => '#000000',
        'is_active' => true,
    ]);

    $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.tickets.categories.index'))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.categories')
        ->assertJsonPath('data.categories.0.name', 'IT Support');
});

it('returns has_subcategories true when category has active subcategories', function () {
    $employee = Employee::factory()->create();
    enableTicketing($employee->tenant_id);

    $category = TicketCategory::create([
        'tenant_id' => $employee->tenant_id,
        'name' => 'HR',
        'color' => '#000000',
        'is_active' => true,
    ]);

    TicketSubcategory::create([
        'tenant_id' => $employee->tenant_id,
        'category_id' => $category->id,
        'name' => 'Leave',
        'is_active' => true,
    ]);

    $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.tickets.categories.index'))
        ->assertJsonPath('data.categories.0.has_subcategories', true);
});

it('returns only active categories', function () {
    $employee = Employee::factory()->create();
    enableTicketing($employee->tenant_id);

    TicketCategory::create(['tenant_id' => $employee->tenant_id, 'name' => 'Active', 'color' => '#000000', 'is_active' => true]);
    TicketCategory::create(['tenant_id' => $employee->tenant_id, 'name' => 'Inactive', 'color' => '#000000', 'is_active' => false]);

    $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.tickets.categories.index'))
        ->assertJsonCount(1, 'data.categories')
        ->assertJsonPath('data.categories.0.name', 'Active');
});

it('scopes categories to the authenticated employee tenant', function () {
    $employee = Employee::factory()->create();
    enableTicketing($employee->tenant_id);

    $otherTenant = Tenant::factory()->create();
    TicketCategory::create(['tenant_id' => $otherTenant->id, 'name' => 'Other Tenant', 'color' => '#000000', 'is_active' => true]);

    $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.tickets.categories.index'))
        ->assertJsonCount(0, 'data.categories');
});

// --- show ---

it('returns subcategories when category has active subcategories', function () {
    $employee = Employee::factory()->create();
    enableTicketing($employee->tenant_id);

    $category = TicketCategory::create([
        'tenant_id' => $employee->tenant_id,
        'name' => 'IT',
        'color' => '#000000',
        'is_active' => true,
    ]);

    TicketSubcategory::create([
        'tenant_id' => $employee->tenant_id,
        'category_id' => $category->id,
        'name' => 'Hardware',
        'is_active' => true,
    ]);

    $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.tickets.categories.show', $category))
        ->assertSuccessful()
        ->assertJsonPath('data.type', 'subcategories')
        ->assertJsonCount(1, 'data.subcategories')
        ->assertJsonPath('data.subcategories.0.name', 'Hardware');
});

it('returns form fields when category has no active subcategories', function () {
    $employee = Employee::factory()->create();
    enableTicketing($employee->tenant_id);

    $category = TicketCategory::create([
        'tenant_id' => $employee->tenant_id,
        'name' => 'Leave',
        'color' => '#000000',
        'is_active' => true,
        'ticket_type' => TicketType::Leave->value,
    ]);

    $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.tickets.categories.show', $category))
        ->assertSuccessful()
        ->assertJsonPath('data.type', 'form')
        ->assertJsonPath('data.ticket_type', 'leave')
        ->assertJsonStructure(['data' => ['type', 'ticket_type', 'ticket_type_label', 'fields']]);
});

it('returns 404 for show when category belongs to a different tenant', function () {
    $employee = Employee::factory()->create();
    enableTicketing($employee->tenant_id);

    $otherTenant = Tenant::factory()->create();
    $category = TicketCategory::create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Other',
        'color' => '#000000',
        'is_active' => true,
    ]);

    $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.tickets.categories.show', $category))
        ->assertNotFound();
});

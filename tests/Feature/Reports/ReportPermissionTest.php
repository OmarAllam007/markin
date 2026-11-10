<?php

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Models\CompanySetting;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function grantReportsPermission(User $user, Tenant $tenant, PermissionAction $action): void
{
    UserPermission::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'module' => PermissionModule::Reports->value,
        'action' => $action->value,
    ]);
}

it('blocks a non-admin without the Reports View permission from every report route', function (string $route) {
    $tenant = Tenant::factory()->create();
    CompanySetting::create(['tenant_id' => $tenant->id]);
    $user = User::factory()->withTenant($tenant)->create();

    $this->actingAs($user)->get(route($route))->assertForbidden();
})->with([
    'reports.index',
    'reports.overtime',
    'reports.daily-summary',
    'reports.monthly-summary',
    'reports.detailed-report',
    'reports.late-arrivals',
    'reports.absence-report',
    'reports.missing-punches',
    'reports.department-attendance',
]);

it('allows a non-admin with the Reports View permission to see every report route', function (string $route) {
    $tenant = Tenant::factory()->create();
    CompanySetting::create(['tenant_id' => $tenant->id]);
    $user = User::factory()->withTenant($tenant)->create();
    grantReportsPermission($user, $tenant, PermissionAction::View);

    $this->actingAs($user)->get(route($route))->assertOk();
})->with([
    'reports.index',
    'reports.overtime',
    'reports.daily-summary',
    'reports.monthly-summary',
    'reports.detailed-report',
    'reports.late-arrivals',
    'reports.absence-report',
    'reports.missing-punches',
    'reports.department-attendance',
]);

it('does not let an Attendance permission alone unlock reports', function () {
    $tenant = Tenant::factory()->create();
    CompanySetting::create(['tenant_id' => $tenant->id]);
    $user = User::factory()->withTenant($tenant)->create();
    grantReportsPermission($user, $tenant, PermissionAction::Edit); // Reports/Edit, not View

    $this->actingAs($user)->get(route('reports.overtime'))->assertForbidden();
});

it('allows a tenant admin to view every report regardless of granted permissions', function () {
    $tenant = Tenant::factory()->create();
    CompanySetting::create(['tenant_id' => $tenant->id]);
    $admin = User::factory()->admin($tenant)->create();

    $this->actingAs($admin)->get(route('reports.overtime'))->assertOk();
});

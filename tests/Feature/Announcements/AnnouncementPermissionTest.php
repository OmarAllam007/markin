<?php

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Models\Announcement;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function grantNotificationsPermission(User $user, Tenant $tenant, PermissionAction $action): void
{
    UserPermission::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'module' => PermissionModule::Notifications->value,
        'action' => $action->value,
    ]);
}

function announcementPayload(): array
{
    return [
        'type' => 'notification',
        'title' => 'Office closed',
        'description' => 'The office will be closed tomorrow.',
        'target_type' => 'locations_departments',
    ];
}

it('blocks a non-admin without the View permission from the announcements list', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withTenant($tenant)->create();

    $this->actingAs($user)->get(route('announcements.index'))->assertForbidden();
});

it('blocks a non-admin without the Create permission from creating an announcement', function () {
    Mail::fake();
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withTenant($tenant)->create();

    $this->actingAs($user)->post(route('announcements.store'), announcementPayload())->assertForbidden();
    expect(Announcement::count())->toBe(0);
});

it('allows a non-admin with the Create permission to create an announcement', function () {
    Mail::fake();
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withTenant($tenant)->create();
    grantNotificationsPermission($user, $tenant, PermissionAction::Create);

    $this->actingAs($user)->post(route('announcements.store'), announcementPayload())
        ->assertRedirect(route('announcements.index'));
    expect(Announcement::count())->toBe(1);
});

it('blocks a non-admin without the Delete permission from deleting an announcement', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withTenant($tenant)->create();
    $announcement = Announcement::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)->delete(route('announcements.destroy', $announcement))->assertForbidden();
    expect($announcement->fresh())->not->toBeNull();
});

it('allows a tenant admin to manage announcements regardless of granted permissions', function () {
    Mail::fake();
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin($tenant)->create();

    $this->actingAs($admin)->get(route('announcements.index'))->assertOk();
    $this->actingAs($admin)->post(route('announcements.store'), announcementPayload())
        ->assertRedirect(route('announcements.index'));
});

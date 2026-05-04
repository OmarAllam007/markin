<?php

use App\Enums\UserStatus;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('displays the users index when authenticated', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->admin($tenant)->create();

    $this->actingAs($user)->get(route('users.index'))->assertOk();
});

it('can create a portal user for a tenant', function () {
    $tenant = Tenant::factory()->create();
    $auth = User::factory()->admin($tenant)->create();

    $this->actingAs($auth)
        ->post(route('users.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'country_code' => '+1',
            'mobile' => '5551234567',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'is_admin' => true,
            'is_supervisor' => false,
            'preferred_theme' => 'light',
            'preferred_language' => 'en',
            'status' => UserStatus::Active->value,
        ])->assertRedirect(route('users.index'));

    $newUser = User::where('email', 'test@example.com')->first();
    expect($newUser)->not->toBeNull();
    expect($newUser->tenants()->where('tenants.id', $tenant->id)->exists())->toBeTrue();
});

it('rejects duplicate email across the application', function () {
    $tenant = Tenant::factory()->create();
    $auth = User::factory()->admin($tenant)->create();
    User::factory()->withTenant($tenant)->create(['email' => 'dup@example.com']);

    $this->actingAs($auth)
        ->post(route('users.store'), [
            'name' => 'Other',
            'email' => 'dup@example.com',
            'country_code' => '+1',
            'mobile' => '5000000000',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'is_admin' => false,
            'is_supervisor' => false,
            'preferred_theme' => 'light',
            'preferred_language' => 'en',
            'status' => UserStatus::Active->value,
        ])->assertSessionHasErrors('email');
});

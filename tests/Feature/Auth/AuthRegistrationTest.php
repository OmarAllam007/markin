<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

it('shows the register page', function () {
    $this->get(route('register'))->assertOk();
});

it('creates a new tenant and user and logs in', function () {
    $this->post(route('register'), [
        'company_name' => 'Acme Corp',
        'number_of_employees' => 25,
        'name' => 'Jane Doe',
        'country_code' => '+1',
        'mobile' => '2025550100',
        'email' => 'jane@acme.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ])->assertRedirect(route('users.index'));

    $this->assertDatabaseHas('tenants', ['name' => 'Acme Corp', 'number_of_employees' => 25]);

    $user = User::query()->where('email', 'jane@acme.com')->first();
    expect($user)->not->toBeNull();
    expect($user->tenants()->where('tenants.name', 'Acme Corp')->exists())->toBeTrue();
    expect(Auth::check())->toBeTrue();
});

it('joins an existing tenant by company name and creates the user', function () {
    $tenant = Tenant::factory()->create(['name' => 'Existing Co', 'number_of_employees' => 5]);

    $this->post(route('register'), [
        'company_name' => 'Existing Co',
        'number_of_employees' => 999,
        'name' => 'New Hire',
        'country_code' => '+44',
        'mobile' => '7700900123',
        'email' => 'hire@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ])->assertRedirect(route('users.index'));

    $user = User::query()->where('email', 'hire@example.com')->first();
    expect($user->tenants()->where('tenants.id', $tenant->id)->exists())->toBeTrue();

    $tenant->refresh();
    expect($tenant->number_of_employees)->toBe(5);
});

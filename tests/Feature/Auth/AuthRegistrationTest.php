<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

it('shows the register page', function () {
    $this->get(route('register'))->assertOk();
});

it('creates a new tenant and user, logs in, and sends a verification email', function () {
    Notification::fake();

    $this->post(route('register'), [
        'company_name' => 'Acme Corp',
        'number_of_employees' => 25,
        'name' => 'Jane Doe',
        'country_code' => '+1',
        'mobile' => '2025550100',
        'email' => 'jane@acme.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ])->assertRedirect(route('verification.notice'));

    $this->assertDatabaseHas('tenants', ['name' => 'Acme Corp', 'number_of_employees' => 25]);

    $user = User::query()->where('email', 'jane@acme.com')->first();
    expect($user)->not->toBeNull();
    expect($user->tenants()->where('tenants.name', 'Acme Corp')->exists())->toBeTrue();
    expect(Auth::check())->toBeTrue();
    expect($user->hasVerifiedEmail())->toBeFalse();

    Notification::assertSentTo($user, VerifyEmail::class);
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
    ])->assertRedirect(route('verification.notice'));

    $user = User::query()->where('email', 'hire@example.com')->first();
    expect($user->tenants()->where('tenants.id', $tenant->id)->exists())->toBeTrue();

    $tenant->refresh();
    expect($tenant->number_of_employees)->toBe(5);
});

it('blocks an unverified user from the main app and sends them to the verification notice', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->admin($tenant)->unverified()->create();

    $this->actingAs($user)->get(route('users.index'))->assertRedirect(route('verification.notice'));
});

it('lets a verified user reach the main app', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->admin($tenant)->create();

    $this->actingAs($user)->get(route('users.index'))->assertOk();
});

it('verifies the email via the signed link and redirects into the app', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->admin($tenant)->unverified()->create();

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $this->actingAs($user)->get($url)->assertRedirect(route('users.index'));

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('resends the verification email on request', function () {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $user = User::factory()->admin($tenant)->unverified()->create();

    $this->actingAs($user)->post(route('verification.send'))->assertRedirect();

    Notification::assertSentTo($user, VerifyEmail::class);
});

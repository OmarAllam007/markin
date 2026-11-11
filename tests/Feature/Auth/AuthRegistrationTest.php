<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\CallQueuedClosure;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

it('no longer exposes a public register route', function () {
    expect(Route::has('register'))->toBeFalse();

    $this->get('/register')->assertNotFound();
});

it('shows the book-a-demo page', function () {
    $this->get(route('demo'))->assertOk();
});

it('stores a demo request and notifies sales', function () {
    Queue::fake();
    config(['services.demo.notify' => 'sales@markin.app']);

    $this->post(route('demo'), [
        'company_name' => 'Acme Corp',
        'number_of_employees' => 25,
        'name' => 'Jane Doe',
        'country_code' => '+966',
        'mobile' => '512345678',
        'email' => 'jane@acme.com',
        'message' => 'Three branches, two shifts.',
    ])->assertRedirect()->assertSessionHas('success');

    $this->assertDatabaseHas('demo_requests', ['company_name' => 'Acme Corp', 'email' => 'jane@acme.com']);
    Queue::assertPushed(CallQueuedClosure::class);
});

it('rejects a demo request without a contact', function () {
    $this->post(route('demo'), ['company_name' => 'Acme Corp'])
        ->assertSessionHasErrors(['name', 'mobile', 'email']);

    $this->assertDatabaseCount('demo_requests', 0);
});

it('creates a tenant and admin from the console after a demo', function () {
    $this->artisan('tenant:create', [
        '--company' => 'Acme Corp',
        '--employees' => 25,
        '--name' => 'Jane Doe',
        '--country-code' => '+966',
        '--mobile' => '512345678',
        '--email' => 'jane@acme.com',
        '--password' => 'Password1!',
    ])->assertSuccessful();

    $this->assertDatabaseHas('tenants', ['name' => 'Acme Corp', 'number_of_employees' => 25]);

    $user = User::query()->where('email', 'jane@acme.com')->first();
    expect($user)->not->toBeNull();
    expect($user->tenants()->where('tenants.name', 'Acme Corp')->exists())->toBeTrue();
    expect($user->hasVerifiedEmail())->toBeTrue();
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

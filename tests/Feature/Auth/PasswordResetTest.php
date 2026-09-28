<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

it('shows the forgot password page', function () {
    $this->get(route('password.request'))->assertOk();
});

it('sends a reset link for a known email', function () {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $user = User::factory()->admin($tenant)->create(['email' => 'owner@example.com']);

    $this->post(route('password.email'), ['email' => 'owner@example.com'])
        ->assertRedirect()
        ->assertSessionHas('success');

    Notification::assertSentTo($user, ResetPassword::class);
});

it('does not error for an unknown email, but does not send anything', function () {
    Notification::fake();

    $this->post(route('password.email'), ['email' => 'nobody@example.com'])
        ->assertRedirect()
        ->assertSessionHasErrors('email');

    Notification::assertNothingSent();
});

it('shows the reset password page with the token and email', function () {
    $this->get(route('password.reset', ['token' => 'abc123']).'?email=owner@example.com')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Public/ResetPassword')
            ->where('token', 'abc123')
            ->where('email', 'owner@example.com')
        );
});

it('resets the password with a valid token', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->admin($tenant)->create(['email' => 'owner@example.com']);

    $token = Password::createToken($user);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => 'owner@example.com',
        'password' => 'NewPassword1!',
        'password_confirmation' => 'NewPassword1!',
    ])->assertRedirect(route('login'));

    expect(Hash::check('NewPassword1!', $user->fresh()->password))->toBeTrue();
});

it('rejects an invalid token', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->admin($tenant)->create(['email' => 'owner@example.com']);

    $this->post(route('password.update'), [
        'token' => 'not-a-real-token',
        'email' => 'owner@example.com',
        'password' => 'NewPassword1!',
        'password_confirmation' => 'NewPassword1!',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('NewPassword1!', $user->fresh()->password))->toBeFalse();
});

afterEach(function () {
    DB::table('password_reset_tokens')->delete();
});

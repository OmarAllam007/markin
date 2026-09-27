<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the login page', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Public/Login'));
});

it('logs in an active user', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->admin($tenant)->create(['email' => 'login@example.com']);

    $this->post(route('login'), [
        'email' => 'login@example.com',
        'password' => 'password',
    ])->assertRedirect(route('users.index'));

    $this->assertAuthenticatedAs($user);
});

it('rejects a suspended user', function () {
    $tenant = Tenant::factory()->create();
    User::factory()->suspended($tenant)->create(['email' => 'bad@example.com']);

    $this->post(route('login'), [
        'email' => 'bad@example.com',
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

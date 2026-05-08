<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the announcement create page when authenticated with a tenant', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->admin($tenant)->create();

    $this->actingAs($user)
        ->get(route('announcements.create'))
        ->assertOk();
});

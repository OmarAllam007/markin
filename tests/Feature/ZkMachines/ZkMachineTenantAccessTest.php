<?php

use App\Models\Tenant;
use App\Models\User;
use App\Models\ZkMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('blocks a plain non-admin member with no permission grants from the zk machines list', function () {
    $tenant = Tenant::factory()->create();
    $member = User::factory()->withTenant($tenant)->create();

    $this->actingAs($member)->get(route('zk-machines.index'))->assertForbidden();
});

it('allows a tenant admin to see and create zk machines', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin($tenant)->create();

    $this->actingAs($admin)->get(route('zk-machines.index'))->assertOk();

    $this->actingAs($admin)->post(route('zk-machines.store'), [
        'serial_number' => 'SN-001',
    ])->assertRedirect(route('zk-machines.index'));

    expect(ZkMachine::where('serial_number', 'SN-001')->exists())->toBeTrue();
});

it('blocks editing a zk machine that belongs to another tenant', function () {
    $otherTenant = Tenant::factory()->create();
    $machine = ZkMachine::factory()->create(['tenant_id' => $otherTenant->id]);

    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin($tenant)->create();

    $this->actingAs($admin)->get(route('zk-machines.edit', $machine))->assertForbidden();
    $this->actingAs($admin)->delete(route('zk-machines.destroy', $machine))->assertForbidden();
    expect($machine->fresh())->not->toBeNull();
});

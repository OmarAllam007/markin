<?php

use App\Models\Holiday;
use App\Models\Tenant;
use App\Services\HolidayService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns true when the date matches a one-time holiday', function () {
    $tenant = Tenant::factory()->create();
    Holiday::factory()->create([
        'tenant_id' => $tenant->id,
        'date' => '2026-11-18',
        'is_recurring' => false,
    ]);

    $service = new HolidayService;

    expect($service->isHoliday($tenant->id, Carbon::parse('2026-11-18')))->toBeTrue()
        ->and($service->isHoliday($tenant->id, Carbon::parse('2026-11-19')))->toBeFalse()
        ->and($service->isHoliday($tenant->id, Carbon::parse('2027-11-18')))->toBeFalse();
});

it('returns true on matching month/day for recurring holidays regardless of year', function () {
    $tenant = Tenant::factory()->create();
    Holiday::factory()->recurring()->create([
        'tenant_id' => $tenant->id,
        'date' => '2026-02-22',
    ]);

    $service = new HolidayService;

    expect($service->isHoliday($tenant->id, Carbon::parse('2026-02-22')))->toBeTrue()
        ->and($service->isHoliday($tenant->id, Carbon::parse('2027-02-22')))->toBeTrue()
        ->and($service->isHoliday($tenant->id, Carbon::parse('2028-02-22')))->toBeTrue()
        ->and($service->isHoliday($tenant->id, Carbon::parse('2026-02-23')))->toBeFalse();
});

it('does not match holidays from other tenants', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    Holiday::factory()->create(['tenant_id' => $tenantB->id, 'date' => '2026-06-10']);

    $service = new HolidayService;

    expect($service->isHoliday($tenantA->id, Carbon::parse('2026-06-10')))->toBeFalse();
});

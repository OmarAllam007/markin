<?php

namespace Database\Seeders;

use App\Enums\AppModule;
use App\Models\Tenant;
use App\Models\TenantModule;
use Illuminate\Database\Seeder;

class TenantModuleSeeder extends Seeder
{
    public function run(): void
    {
        Tenant::each(function (Tenant $tenant): void {
            foreach (AppModule::cases() as $module) {
                TenantModule::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'module' => $module->value],
                    ['is_enabled' => false]
                );
            }
        });
    }
}

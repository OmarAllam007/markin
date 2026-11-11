<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $tenant = Tenant::query()->firstOrCreate(
            ['name' => 'Demo Company'],
            [
                'domain' => null,
                'number_of_employees' => 50,
            ],
        );

        // ponytail: fixed demo credentials so a sales demo (and the app-store reviewer)
        // can sign in without us minting an account each time.
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Demo Admin',
            'email' => env('DEMO_EMAIL', 'demo@markin.app'),
            'password' => env('DEMO_PASSWORD', 'DemoMarkin1!'),
        ]);

        foreach ([['Annual', 21, true], ['Sick', 30, true], ['Unpaid', null, false]] as [$name, $days, $paid]) {
            LeaveType::firstOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $name],
                ['annual_days' => $days, 'is_paid' => $paid],
            );
        }

        $this->call(AttendanceSeeder::class);

        TicketTypeSeeder::seedForTenant($tenant->id);
    }
}

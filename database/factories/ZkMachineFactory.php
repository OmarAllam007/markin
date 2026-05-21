<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\ZkMachine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ZkMachine>
 */
class ZkMachineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'serial_number' => fake()->unique()->numerify('##########'),
            'name' => fake()->optional()->words(2, true),
            'secret_token' => null,
            'firmware_version' => 'Ver 8.0.0(build 426-2885-02)',
            'last_attlog_stamp' => 0,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    public function definition(): array
    {
        $lat = $this->faker->latitude(-85, 85);
        $lng = $this->faker->longitude(-180, 180);
        $latDelta = $this->faker->randomFloat(4, 0.005, 0.05);
        $lngDelta = $this->faker->randomFloat(4, 0.005, 0.05);

        return [
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->city(),
            'coordinates' => [
                'north' => round($lat + $latDelta, 6),
                'south' => round($lat, 6),
                'east' => round($lng + $lngDelta, 6),
                'west' => round($lng, 6),
            ],
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Maintenance;
use App\Models\MaintenancePart;
use \App\Models\Part;
use Illuminate\Database\Eloquent\Factories\Factory;

class MaintenancePartFactory extends Factory
{
    protected $model = MaintenancePart::class;

    public function definition(): array
    {
        return [
            'maintenance_id' => Maintenance::factory(),
            'part_id'     => Part::factory(),
            'quantity'    => $this->faker->numberBetween(1, 10),
            'unit_cost_at_time'   => $this->faker->randomFloat(2, 1, 500),
        ];
    }
}

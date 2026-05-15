<?php

namespace Database\Factories;

use App\Models\MaintenancePlan;
use \App\Models\Part;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanPartFactory extends Factory
{
    public function definition(): array
    {
        return [
            'maintenance_plan_id' => MaintenancePlan::factory(),
            'part_id'             => Part::factory(),
            'quantity'            => fake()->numberBetween(1, 10),
        ];
    }
}

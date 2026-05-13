<?php

namespace Database\Factories;

use App\Models\MaintenancePlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanPartFactory extends Factory
{
    public function definition(): array
    {
        return [
            'maintenance_plan_id' => MaintenancePlan::factory(),
            'reference'           => fake()->optional()->bothify('PART-####-??'),
            'description'         => fake()->sentence(4),
            'quantity'            => fake()->numberBetween(1, 10),
            'unit_cost'           => fake()->randomFloat(2, 5, 500),
        ];
    }
}

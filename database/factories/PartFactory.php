<?php

namespace Database\Factories;

use App\Models\Part;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class PartFactory extends Factory
{
    protected $model = Part::class;

    public function definition(): array
    {
        return [
            'reference'         => fake()->unique()->bothify('PART-####-??'),
            'name'              => fake()->randomElement(['Filtro de óleo', 'Correia de transmissão', 'Rolamento SKF', 'Vedante de borracha', 'Bomba hidráulica']),
            'description'       => fake()->optional()->sentence(4),
            'stock_current'     => fake()->numberBetween(10, 100),
            'current_unit_cost' => fake()->randomFloat(2, 5, 500),
        ];
    }
}

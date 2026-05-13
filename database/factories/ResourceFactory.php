<?php

namespace Database\Factories;

use App\Models\Resource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Resource>
 */
class ResourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->words(2, true)) . ' ' . strtoupper(fake()->bothify('??##')),
            'description' => fake()->sentence(),
            'location' => 'Setor ' . $this->faker->bothify('#?'),
            'section' => ucfirst(fake()->word()),
            'status' => $this->faker->randomElement(['active', 'inactive'])
        ];
    }
}

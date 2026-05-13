<?php

namespace Database\Factories;

use App\Models\MaintenancePart;
use Illuminate\Database\Eloquent\Factories\Factory;

class MaintenancePartFactory extends Factory
{
    protected $model = MaintenancePart::class;

    public function definition(): array
    {
        return [
            'reference'   => $this->faker->optional(0.7)->bothify('REF-####-??'),
            'description' => $this->faker->randomElement([
                'Filtro de óleo', 'Correia de transmissão', 'Rolamento SKF',
                'Vedante de borracha', 'Parafuso M10', 'Sensor de temperatura',
                'Fusível 10A', 'Lubrificante Shell', 'Bomba hidráulica',
            ]),
            'quantity'    => $this->faker->numberBetween(1, 10),
            'unit_cost'   => $this->faker->randomFloat(2, 1, 500),
        ];
    }
}

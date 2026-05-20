<?php

namespace Database\Factories;

use App\Models\MaintenancePlan;
use App\Models\Resource;
use App\Models\Part;
use App\Models\PlanPart;
use Illuminate\Database\Eloquent\Factories\Factory;

class MaintenancePlanFactory extends Factory
{
    protected $model = MaintenancePlan::class;

    public function definition(): array
    {
        $intervals = $this->faker->randomElement([
            ['name' => 'Lubrificação Semanal', 'value' => 7, 'unit' => 'day'],
            ['name' => 'Limpeza Quinzenal', 'value' => 15, 'unit' => 'day'],
            ['name' => 'Manutenção Preventiva Mensal', 'value' => 1, 'unit' => 'month'],
            ['name' => 'Inspeção Trimestral', 'value' => 3, 'unit' => 'month'],
            ['name' => 'Revisão Semestral', 'value' => 6, 'unit' => 'month'],
            ['name' => 'Calibração Anual', 'value' => 1, 'unit' => 'year'],
            ['name' => 'Verificação de Segurança', 'value' => 1, 'unit' => 'month'],
        ]);

        return [
            'resource_id'   => Resource::inRandomOrder()->first()?->id ?? Resource::factory(),
            'name'          => $intervals['name'],
            'interval_value'=> $intervals['value'],
            'interval_unit' => $intervals['unit'],
            'description'   => $this->faker->optional(0.7)->paragraph(),
            'is_active'     => $this->faker->boolean(80),
            'started_at'    => $this->faker->optional(0.8)->dateTimeBetween('-1 year', 'now')?->format('Y-m-d'),
        ];
    }

    public function active(): static
    {
        return $this->state(['is_active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function withPlanParts(int $min = 1, int $max = 5, $parts = null): static
    {
        return $this->afterCreating(function (MaintenancePlan $plan) use ($min, $max, $parts) {
            $count = rand($min, $max);

            for ($i = 0; $i < $count; $i++) {
                $part = ($parts && method_exists($parts, 'isNotEmpty') && $parts->isNotEmpty())
                    ? $parts->random()
                    : Part::factory()->create();

                PlanPart::updateOrCreate(
                    [
                        'maintenance_plan_id' => $plan->id,
                        'part_id'             => $part->id,
                    ],
                    [
                        'quantity' => rand(1, 5),
                    ]
                );
            }
        });
    }
}

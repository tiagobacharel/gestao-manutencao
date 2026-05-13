<?php

namespace Database\Factories;

use App\Models\MaintenancePlan;
use App\Models\Resource;
use Illuminate\Database\Eloquent\Factories\Factory;

class MaintenancePlanFactory extends Factory
{
    protected $model = MaintenancePlan::class;

    public function definition(): array
    {
        return [
            'resource_id'   => Resource::inRandomOrder()->first()->id,
            'name'          => $this->faker->randomElement([
                'Manutenção Preventiva Mensal',
                'Inspeção Trimestral',
                'Revisão Semestral',
                'Lubrificação Semanal',
                'Calibração Anual',
                'Limpeza Quinzenal',
                'Verificação de Segurança',
            ]),
            'interval_days' => $this->faker->randomElement([7, 14, 30, 60, 90, 180, 365]),
            'description'   => $this->faker->optional(0.7)->paragraph(),
            'is_active'     => $this->faker->boolean(80),
            'started_at'    => $this->faker->optional(0.8)->dateTimeBetween('-1 year', 'now'),
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

    // rand() é chamado dentro do afterCreating, logo cada plano recebe um número diferente
    public function withPlanParts(int $min = 1, int $max = 5): static
    {
        return $this->afterCreating(function (MaintenancePlan $plan) use ($min, $max) {
            \App\Models\PlanPart::factory(rand($min, $max))->create([
                'maintenance_plan_id' => $plan->id,
            ]);
        });
    }
}

<?php

namespace Database\Factories;

use App\Models\Maintenance;
use App\Models\MaintenancePlan;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MaintenanceFactory extends Factory
{
    protected $model = Maintenance::class;

    public function definition(): array
    {
        $status = $this->faker->randomElement([
            'pending', 'pending', 'pending',
            'done', 'done', 'done',
            'cancelled',
            'in_progress',
        ]);

        // scheduled_at sempre no passado para evitar conflito com done_at
        $scheduledAt = $this->faker->dateTimeBetween('-6 months', '-1 day');

        $doneAt = $status === 'done'
            ? $this->faker->dateTimeBetween($scheduledAt, 'now')
            : null;

        return [
            'maintenance_plan_id' => $this->faker->boolean(60)
                ? MaintenancePlan::inRandomOrder()->first()?->id
                : null,
            'resource_id'  => Resource::inRandomOrder()->first()->id,
            'created_by'   => User::inRandomOrder()->first()->id,
            'scheduled_at' => $scheduledAt,
            'done_at'      => $doneAt,
            'status'       => $status,
            'notes'        => $this->faker->optional(0.7)->paragraph()
                ? $this->faker->randomFloat(2, 20, 2000)
                : null,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => 'pending', 'done_at' => null]);
    }

    public function done(): static
    {
        return $this->state(function () {
            $scheduledAt = $this->faker->dateTimeBetween('-6 months', '-1 day');
            return [
                'status'       => 'done',
                'scheduled_at' => $scheduledAt,
                'done_at'      => $this->faker->dateTimeBetween($scheduledAt, 'now'),
            ];
        });
    }

    public function cancelled(): static
    {
        return $this->state(['status' => 'cancelled', 'done_at' => null]);
    }

    public function withParts(int $count = 3): static
    {
        return $this->afterCreating(function (Maintenance $maintenance) use ($count) {
            \App\Models\MaintenancePart::factory($count)->create([
                'maintenance_id' => $maintenance->id,
            ]);
        });
    }

    // Peças baseadas no plano associado, com variações realistas.
    // Manutenções sem plano recebem peças aleatórias.
    public function withPlanBasedParts(): static
    {
        return $this->afterCreating(function (Maintenance $maintenance) {
            $planParts = $maintenance->maintenance_plan_id
                ? \App\Models\PlanPart::where('maintenance_plan_id', $maintenance->maintenance_plan_id)->get()
                : collect();

            if ($planParts->isEmpty()) {
                if (fake()->boolean(70)) {
                    \App\Models\MaintenancePart::factory(rand(1, 4))->create([
                        'maintenance_id' => $maintenance->id,
                    ]);
                }
                return;
            }

            foreach ($planParts as $planPart) {
                $scenario = fake()->randomElement(['exact', 'exact', 'more_qty', 'different']);

                \App\Models\MaintenancePart::factory()->create([
                    'maintenance_id' => $maintenance->id,
                    'reference'      => $scenario === 'different'
                        ? fake()->optional(0.6)->bothify('REF-####-??')
                        : $planPart->reference,
                    'description'    => $scenario === 'different'
                        ? fake()->randomElement([
                            'Filtro substituto', 'Correia reforçada', 'Rolamento alternativo',
                            'Vedante importado', 'Parafuso reforçado M12', 'Sensor substituto',
                        ])
                        : $planPart->description,
                    'quantity'       => $scenario === 'more_qty'
                        ? $planPart->quantity + rand(1, 3)
                        : $planPart->quantity,
                    'unit_cost'      => $planPart->unit_cost,
                ]);
            }

            // Peças extras além do plano (40% de chance)
            if (fake()->boolean(40)) {
                \App\Models\MaintenancePart::factory(rand(1, 2))->create([
                    'maintenance_id' => $maintenance->id,
                ]);
            }
        });
    }
}

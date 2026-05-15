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
            #'created_by'   => User::inRandomOrder()->first()->id,
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

    public function withPlanBasedParts(): static
    {
        return $this->afterCreating(function (Maintenance $maintenance) {
            if ($maintenance->maintenance_plan_id) {
                $planParts = \App\Models\PlanPart::where('maintenance_plan_id', $maintenance->maintenance_plan_id)->get();

                foreach ($planParts as $planPart) {
                    $part = $planPart->part;

                    // SIMULAÇÃO DE DESVIO INDUSTRIAL:
                    // 30% de probabilidade de o técnico gastar uma quantidade diferente da prevista no plano
                    $quantidadeReal = fake()->boolean(30)
                        ? $planPart->quantity + fake()->randomElement([-1, 1, 2])
                        : $planPart->quantity;

                    // Garante que a quantidade nunca é zero ou negativa
                    $quantidadeReal = max(1, $quantidadeReal);

                    // 20% de probabilidade de o preço ter sofrido uma ligeira flutuação no dia da compra
                    $custoCongelado = fake()->boolean(20)
                        ? ($part ? $part->current_unit_cost * fake()->randomFloat(2, 0.9, 1.1) : 0)
                        : ($part ? $part->current_unit_cost : 0);

                    \App\Models\MaintenancePart::create([
                        'maintenance_id'    => $maintenance->id,
                        'part_id'           => $planPart->part_id,
                        'quantity'          => $quantidadeReal, // Quantidade com desvio simulado
                        'unit_cost_at_time' => $custoCongelado, // Preço com flutuação simulada
                    ]);
                }
            } else {
                // Código para manutenção sem plano mantém-se igual...
                $parts = \App\Models\Part::inRandomOrder()->take(rand(1, 3))->get();
                foreach ($parts as $part) {
                    \App\Models\MaintenancePart::create([
                        'maintenance_id'    => $maintenance->id,
                        'part_id'           => $part->id,
                        'quantity'          => rand(1, 5),
                        'unit_cost_at_time' => $part->current_unit_cost,
                    ]);
                }
            }
        });
    }
}

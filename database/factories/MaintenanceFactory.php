<?php

namespace Database\Factories;

use App\Models\Maintenance;
use App\Models\MaintenancePart;
use App\Models\MaintenancePlan;
use App\Models\Part;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MaintenanceFactory extends Factory
{
    protected $model = Maintenance::class;

    public function definition(): array
    {
        $status = fake()->randomElement([
            'pending', 'pending', 'pending',
            'done', 'done', 'done',
            'cancelled',
            'in_progress',
        ]);

        // scheduled_at sempre no passado para evitar conflito com done_at
        $scheduledAt = fake()->dateTimeBetween('-6 months', '-1 day');

        $doneAt = $status === 'done'
            ? fake()->dateTimeBetween($scheduledAt, 'now')
            : null;

        return [
            'maintenance_plan_id' => fake()->boolean(60)
                ? MaintenancePlan::inRandomOrder()->first()?->id
                : null,
            'resource_id'  => Resource::inRandomOrder()->first()->id,
            #'created_by'   => User::inRandomOrder()->first()->id,
            'scheduled_at' => $scheduledAt,
            'done_at'      => $doneAt,
            'status'       => $status,
            'notes'        => fake()->optional(0.7)->paragraph()
                ? fake()->randomFloat(2, 20, 2000)
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
            $scheduledAt = fake()->dateTimeBetween('-6 months', '-1 day');
            return [
                'status'       => 'done',
                'scheduled_at' => $scheduledAt,
                'done_at'      => fake()->dateTimeBetween($scheduledAt, 'now'),
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
            MaintenancePart::factory($count)->create([
                'maintenance_id' => $maintenance->id,
            ]);
        });
    }

    public function withPlanBasedParts(): static
    {
        return $this->afterCreating(function (Maintenance $maintenance) {

            // CENÁRIO A: Manutenção baseada num Plano
            if ($maintenance->maintenance_plan_id) {
                $planParts = \App\Models\PlanPart::where('maintenance_plan_id', $maintenance->maintenance_plan_id)->get();

                foreach ($planParts as $planPart) {
                    $part = $planPart->part;

                    $quantidadeReal = fake()->boolean(30)
                        ? $planPart->quantity + fake()->randomElement([-1, 1, 2])
                        : $planPart->quantity;

                    $quantidadeReal = max(1, $quantidadeReal);

                    $custoCongelado = fake()->boolean(20)
                        ? ($part ? $part->current_unit_cost * fake()->randomFloat(2, 0.9, 1.1) : 0)
                        : ($part ? $part->current_unit_cost : 0);

                    // Encontra a tarefa correspondente na execução real
                    $maintenanceTaskId = null;

                    // Se o plano original tiver uma tarefa associada, tenta associar na manutenção
                    if ($planPart->plan_task_id) {
                        $taskId = \DB::table('plan_tasks')->where('id', $planPart->plan_task_id)->value('task_id');

                        if ($taskId) {
                            $maintenanceTaskId = \DB::table('maintenance_tasks')
                                ->where('maintenance_id', $maintenance->id)
                                ->where('task_id', $taskId)
                                ->value('id');
                        }
                    }

                    MaintenancePart::create([
                        'maintenance_id'         => $maintenance->id,
                        'part_id'                => $planPart->part_id,
                        'maintenance_task_id'    => $maintenanceTaskId, // Será null se o plano não tiver tarefa
                        'quantity'               => $quantidadeReal,
                        'unit_cost_at_time'      => $custoCongelado,
                    ]);
                }

                // CENÁRIO B: Manutenção Avulsa (Pode ter tarefas manuais e peças)
            } else {
                $parts = Part::inRandomOrder()->take(rand(1, 3))->get();

                foreach ($parts as $part) {
                    // Modificado: Dá 60% de probabilidade de vincular a uma tarefa real, caso exista
                    $randomTask = fake()->boolean(60)
                        ? \DB::table('maintenance_tasks')
                            ->where('maintenance_id', $maintenance->id)
                            ->inRandomOrder()
                            ->first()
                        : null;

                    MaintenancePart::create([
                        'maintenance_id'         => $maintenance->id,
                        'part_id'                => $part->id,
                        'maintenance_task_id'    => $randomTask ? $randomTask->id : null,
                        'quantity'               => rand(1, 5),
                        'unit_cost_at_time'      => $part->current_unit_cost,
                    ]);
                }
            }
        });
    }

    public function withPlanBasedTasks(): self
    {
        return $this->afterCreating(function (\App\Models\Maintenance $maintenance) {
            // Se tem plano, herda as tarefas do molde do plano
            if ($maintenance->plan && $maintenance->plan->tasks->isNotEmpty()) {
                foreach ($maintenance->plan->tasks as $task) {
                    $maintenance->tasks()->attach($task->id, [
                        'status' => fake()->randomElement(['pending', 'in_progress', 'completed']),
                    ]);
                }
                // Se NÃO tem plano, injeta entre 1 a 3 tarefas aleatórias do catálogo geral
            } else {
                $randomCatalogTasks = \App\Models\Task::inRandomOrder()->take(rand(1, 3))->get();
                foreach ($randomCatalogTasks as $task) {
                    $maintenance->tasks()->attach($task->id, [
                        'status' => fake()->randomElement(['pending', 'in_progress', 'completed']),
                    ]);
                }
            }
        });
    }

}

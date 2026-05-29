<?php

namespace Database\Factories;

use App\Models\MaintenancePlan;
use App\Models\Resource;
use App\Models\Part;
use App\Models\PlanPart;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

class MaintenancePlanFactory extends Factory
{
    protected $model = MaintenancePlan::class;

    public function definition(): array
    {
        $intervals = fake()->randomElement([
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
            'description'   => fake()->optional(0.7)->paragraph(),
            'is_active'     => fake()->boolean(80),
            'started_at'    => fake()->optional(0.8)->dateTimeBetween('-1 year', 'now')?->format('Y-m-d'),
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

                $randomPlanTask = fake()->boolean(70)
                    ? \DB::table('plan_tasks')->where('maintenance_plan_id', $plan->id)->inRandomOrder()->first()
                    : null;

                PlanPart::updateOrCreate(
                    [
                        'maintenance_plan_id' => $plan->id,
                        'part_id'             => $part->id,
                    ],
                    [
                        'quantity'     => rand(1, 5),
                        'plan_task_id' => $randomPlanTask ? $randomPlanTask->id : null,
                    ]
                );
            }
        });
    }


    public function withPlanTasks(int $min = 1, int $max = 3, $tasks = null): self
    {
        return $this->afterCreating(function (\App\Models\MaintenancePlan $plan) use ($min, $max, $tasks) {
            // Se não passarmos tarefas, vai buscar ou criar algumas
            $tasksCollection = $tasks ?? Task::all();

            if ($tasksCollection->isEmpty()) {
                $tasksCollection = Task::factory(5)->create();
            }

            // Seleciona uma quantidade aleatória de tarefas e associa ao plano
            $randomTasks = $tasksCollection->random(fake()->numberBetween($min, min($max, $tasksCollection->count())));

            $plan->tasks()->attach($randomTasks);
        });
    }
}

<?php

use App\Models\MaintenancePlan;
use App\Models\PlanPart;
use App\Models\PlanTask;
use App\Models\Resource;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\Rules\In;


uses(RefreshDatabase::class);


test('regras de validação do PlanTask com cobertura total', function () {

    $dbTask = (object) ['id' => 1, 'name' => 'Manutenção Preventiva'];
    $dbPart = (object) ['id' => 10, 'name' => 'Filtro de Ar', 'reference' => 'REF-99'];

    DB::shouldReceive('table')->with('tasks')->andReturnSelf();
    DB::shouldReceive('table')->with('parts')->andReturnSelf();
    DB::shouldReceive('whereIn')->andReturnSelf();

    DB::shouldReceive('get')->andReturnValues([
        collect([$dbTask]), // Para as tarefas
        collect([$dbPart])  // Para as peças
    ]);

    $mockComponent = (object) [
        'plan_tasks' => [
            [
                'task_id' => 1,
                'search'  => 'Manutenção Preventiva',
                'parts'   => [
                    [
                        'part_id'  => 10,
                        'search'   => 'Filtro de Ar / REF-99',
                        'quantity' => 2
                    ]
                ]
            ]
        ]
    ];

    $rules = PlanTask::rules($mockComponent);

    expect($rules)->toHaveKeys([
        'plan_tasks',
        'plan_tasks.*.task_id',
        'plan_tasks.*.parts',
        'plan_tasks.*.parts.*.part_id',
        'plan_tasks.*.parts.*.quantity',
    ])
        ->and($rules['plan_tasks.*.task_id'])->toContain('required')
        ->and($rules['plan_tasks.*.task_id'][1])->toBeInstanceOf(In::class)
        ->and($rules['plan_tasks.*.parts.*.part_id'])->toContain('required')
        ->and($rules['plan_tasks.*.parts.*.part_id'][1])->toBeInstanceOf(In::class)
        ->and($rules['plan_tasks.*.parts.*.quantity'])
        ->toContain('required')
        ->toContain('integer')
        ->toContain('min:1');
});



test('relações', function () {
    $plano = MaintenancePlan::factory()->create(['resource_id' => Resource::factory()->create()->id]);
    $task = Task::factory()->create();

    $tarefa = PlanTask::create([
        'maintenance_plan_id' => $plano->id,
        'task_id'             => $task->id,
    ]);

    $peca = PlanPart::factory()->create(['plan_task_id' => $tarefa->id]);

    expect($tarefa->maintenance_plan)->toBeInstanceOf(MaintenancePlan::class)
        ->and($tarefa->maintenance_plan->id)->toBe($plano->id)

        ->and($tarefa->task)->toBeInstanceOf(Task::class)
        ->and($tarefa->task->id)->toBe($task->id)

        ->and($tarefa->parts)->toHaveCount(1)
        ->and($tarefa->parts->first()->id)->toBe($peca->id);
});

<?php

use App\Models\MaintenancePlan;
use App\Models\PlanPart;
use App\Models\PlanTask;
use App\Models\Resource;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;


uses(RefreshDatabase::class);


test('regras de validação do PlanTask', function () {
    $rules = PlanTask::rules();

    expect($rules)->toHaveKeys([
        'plan_tasks.*.task_id',
        'plan_tasks.*.parts.*.part_id',
        'plan_tasks.*.parts.*.quantity',
    ])
        ->and($rules['plan_tasks.*.task_id'])->toContain('required')
        ->and($rules['plan_tasks.*.task_id'])->toContain('exists:tasks,id')

        ->and($rules['plan_tasks.*.parts.*.part_id'])->toContain('required')
        ->and($rules['plan_tasks.*.parts.*.part_id'])->toContain('exists:parts,id')

        ->and($rules['plan_tasks.*.parts.*.quantity'])->toContain('required')
        ->and($rules['plan_tasks.*.parts.*.quantity'])->toContain('integer')
        ->and($rules['plan_tasks.*.parts.*.quantity'])->toContain('min:1');
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

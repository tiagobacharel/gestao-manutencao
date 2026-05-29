<?php

use App\Models\Maintenance;
use App\Models\MaintenancePart;
use App\Models\MaintenanceTask;
use App\Models\Resource;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;


uses(RefreshDatabase::class);


test('regras de validação do MaintenanceTask', function () {
    $rules = MaintenanceTask::rules();

    expect($rules)->toHaveKeys([
        'maintenance_tasks',
        'maintenance_tasks.*.task_id',
        'maintenance_tasks.*.status',
        'maintenance_tasks.*.parts',
        'maintenance_tasks.*.parts.*.part_id',
        'maintenance_tasks.*.parts.*.quantity',
    ])
        ->and($rules['maintenance_tasks'])->toContain('array')
        ->and($rules['maintenance_tasks.*.status'])
        ->toContain('required')
        ->and($rules['maintenance_tasks.*.status'])->toContain('in:pending,in_progress,completed')
        ->and($rules['maintenance_tasks.*.parts.*.part_id'])
        ->toContain('required_with:maintenance_tasks.*.parts.*.quantity')
        ->and($rules['maintenance_tasks.*.parts.*.part_id'])->toContain('exists:parts,id')
        ->and($rules['maintenance_tasks.*.parts.*.quantity'])
        ->toContain('required')
        ->and($rules['maintenance_tasks.*.parts.*.quantity'])->toContain('integer')
        ->and($rules['maintenance_tasks.*.parts.*.quantity'])->toContain('min:1');
});


test('relações', function () {
    $maintenance = Maintenance::factory()->create(['resource_id' => Resource::factory()->create()->id]);
    $task = Task::factory()->create();

    $tarefa = MaintenanceTask::create([
        'maintenance_id' => $maintenance->id,
        'task_id'        => $task->id,
        'status'         => 'pending'
    ]);

    $peca = MaintenancePart::factory()->create(['maintenance_task_id' => $tarefa->id]);

    expect($tarefa->maintenance)->toBeInstanceOf(Maintenance::class)
        ->and($tarefa->maintenance->id)->toBe($maintenance->id)

        ->and($tarefa->task)->toBeInstanceOf(Task::class)
        ->and($tarefa->task->id)->toBe($task->id)

        ->and($tarefa->parts)->toHaveCount(1)
        ->and($tarefa->parts->first()->id)->toBe($peca->id);
});


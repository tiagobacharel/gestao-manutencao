<?php

use App\Models\Maintenance;
use App\Models\MaintenancePart;
use App\Models\MaintenanceTask;
use App\Models\Resource;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;


uses(RefreshDatabase::class);


use Illuminate\Support\Facades\DB;

test('regras de validacao do MaintenanceTask', function () {
    $dbTask = (object) ['id' => 1, 'name' => 'Troca de Oleo'];
    $dbPart = (object) ['id' => 2, 'name' => 'Filtro', 'reference' => 'FL123'];

    DB::shouldReceive('table')->with('tasks')->andReturnSelf();
    DB::shouldReceive('table')->with('parts')->andReturnSelf();
    DB::shouldReceive('whereIn')->andReturnSelf();

    DB::shouldReceive('get')->andReturnValues([
        collect([$dbTask]),
        collect([$dbPart])
    ]);

    $component = (object) [
        'maintenance_tasks' => [
            [
                'task_id' => 1,
                'search'  => 'Troca de Oleo',
                'parts'   => [
                    [
                        'part_id' => 2,
                        'search'  => 'Filtro / FL123',
                    ]
                ]
            ]
        ]
    ];

    $rules = MaintenanceTask::rules($component);

    expect($rules)->toBeArray()
        ->toHaveKey('maintenance_tasks.*.parts.*.part_id');
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


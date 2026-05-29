<?php

use App\Models\Maintenance;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceTask;
use App\Models\Resource;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;


uses(RefreshDatabase::class);


test('regras de validação do Task', function () {
    $rules = Task::rules();

    expect($rules)->toHaveKeys([
        'name',
        'description',
    ])
        ->and($rules['name'])->toContain('required')
        ->and($rules['name'])->toContain('string')
        ->and($rules['name'])->toContain('max:255')

        ->and($rules['description'])->toContain('nullable')
        ->and($rules['description'])->toContain('string');

});

test('relações', function () {
    $tarefa = Task::factory()->create();
    $resource = Resource::factory()->create();

    $plano = MaintenancePlan::factory()->create(['resource_id' => $resource->id]);
    $maintenance = Maintenance::factory()->create(['resource_id' => $resource->id]);

    $tarefa->plans()->attach($plano->id);

    $maintenanceTask = MaintenanceTask::create([
        'maintenance_id' => $maintenance->id,
        'task_id'        => $tarefa->id,
        'status'         => 'pending'
    ]);

    expect($tarefa->plans)->toHaveCount(1)
        ->and($tarefa->plans->first()->id)->toBe($plano->id)

        ->and($tarefa->maintenances)->toHaveCount(1)
        ->and($tarefa->maintenances->first()->id)->toBe($maintenance->id)
        ->and($tarefa->maintenances->first()->pivot->status)->toBe('pending')

        ->and($tarefa->maintenanceTasks)->toHaveCount(1)
        ->and($tarefa->maintenanceTasks->first()->id)->toBe($maintenanceTask->id);

});

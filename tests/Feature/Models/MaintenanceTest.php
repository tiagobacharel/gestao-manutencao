<?php

use App\Models\Maintenance;
use App\Models\MaintenancePart;
use App\Models\MaintenancePlan;
use App\Models\Part;
use App\Models\PlanPart;
use App\Models\Resource;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;


uses(RefreshDatabase::class);


test('relação com Task', function () {
    $plano = Maintenance::factory()->create(['resource_id' => Resource::factory()->create()->id]);
    $plano->tasks()->attach($task = Task::factory()->create());

    expect($plano->tasks)->toHaveCount(1)
        ->and($plano->tasks->first()->id)->toBe($task->id);
});

test('CostDeviation', function () {
    $peca = Part::factory()->create(['current_unit_cost' => 11.00]);
    $plano = MaintenancePlan::factory()->create();
    $manotecao = Maintenance::factory()->create(['maintenance_plan_id' => $plano->id ]);


    $maintenancePart = MaintenancePart::create([
        'maintenance_id' => $manotecao->id,
        'part_id'             => $peca->id,
        'quantity'            => 3,
        'unit_cost_at_time' => 10.00,
    ]);

    $planoPart = PlanPart::create([
        'maintenance_plan_id' => $plano->id,
        'part_id'             => $peca->id,
        'quantity'            => 3,
    ]);


    expect($manotecao->costDeviation)->toBe(-3.00);

});


test('StatusBadge', function () {
    $cenarios = [
        'status done' => [
            'done',
            ['color' => 'green', 'icon' => 'check-circle', 'label' => 'Concluída']
        ],
        'status in_progress' => [
            'in_progress',
            ['color' => 'blue', 'icon' => 'wrench', 'label' => 'Em Progresso']
        ],
        'status cancelled' => [
            'cancelled',
            ['color' => 'red', 'icon' => 'x-circle', 'label' => 'Cancelada']
        ],
        'status default/desconhecido' => [
            'unknown_status',
            ['color' => 'yellow', 'icon' => 'clock', 'label' => 'Pendente']
        ],
    ];

    foreach ($cenarios as $nomeCenario => [$status, $expectedBadge]) {
        $model = new Maintenance(['status' => $status]);

        expect($model->status_badge)
            ->toBe($expectedBadge, "Falhou no cenário: {$nomeCenario}");
    }
});


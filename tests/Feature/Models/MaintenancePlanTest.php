<?php

use App\Models\MaintenancePlan;
use App\Models\Resource;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;


uses(RefreshDatabase::class);

test('proxima data de manuteção', function () {
    $cenarios = [
        ['2026-01-01', 5, 'day', '2026-01-06'],
        ['2026-01-01', 3, 'month', '2026-04-01'],
        ['2026-01-01', 2, 'year', '2028-01-01'],
        ['2026-01-01', 1, 'desconhecido', '2026-01-01'],
        [null, 1, 'day', null],
    ];

    foreach ($cenarios as [$startedAt, $value, $unit, $expected]) {
        $plano = MaintenancePlan::factory()->make([
            'started_at' => $startedAt,
            'interval_value' => $value,
            'interval_unit' => $unit,
        ]);

        $dataEsperada = $startedAt ? $expected : Carbon::now()->addDays($value)->toDateString();

        expect($plano->getNextMaintenanceDate()->toDateString())->toBe($dataEsperada);
    }
});


test('relação com Task', function () {
    $plano = MaintenancePlan::factory()->create(['resource_id' => Resource::factory()->create()->id]);
    $plano->tasks()->attach($task = Task::factory()->create());

    expect($plano->tasks)->toHaveCount(1)
        ->and($plano->tasks->first()->id)->toBe($task->id);
});

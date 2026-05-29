<?php

use App\Models\Maintenance;
use App\Models\MaintenancePart;
use App\Models\Part;
use App\Models\Resource;
use Illuminate\Foundation\Testing\RefreshDatabase;


uses(RefreshDatabase::class);

test('relações do MaintenancePart', function () {
    $resource = Resource::factory()->create();
    $plano = Maintenance::factory()->create(['resource_id' => $resource->id]);
    $peca = Part::factory()->create();

    $maintenancePart = MaintenancePart::create([
        'maintenance_id' => $plano->id,
        'part_id'             => $peca->id,
        'quantity'            => 3,
        'unit_cost_at_time' => 10.00,
    ]);

    expect($maintenancePart->part)->toBeInstanceOf(Part::class);
    expect($maintenancePart->part->id)->toBe($peca->id);

    expect($maintenancePart->maintenance)->toBeInstanceOf(Maintenance::class);
    expect($maintenancePart->maintenance->id)->toBe($plano->id);
});


test('TotalCost', function () {
    $resource = Resource::factory()->create();
    $manotecao = Maintenance::factory()->create(['resource_id' => $resource->id]);
    $peca = Part::factory()->create();

    $maintenancePart = MaintenancePart::create([
        'maintenance_id' => $manotecao->id,
        'part_id'             => $peca->id,
        'quantity'            => 3,
        'unit_cost_at_time' => 10.00,
    ]);

    expect($maintenancePart->totalCost)->toBe(30.00);

});

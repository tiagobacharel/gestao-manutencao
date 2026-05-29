<?php

use App\Models\MaintenancePlan;
use App\Models\Part;
use App\Models\PlanPart;
use App\Models\Resource;
use Illuminate\Foundation\Testing\RefreshDatabase;


uses(RefreshDatabase::class);

test('regras de validação do PlanPart', function () {
    $rules = PlanPart::rules();

    expect($rules)->toHaveKeys(['plan_parts', 'plan_parts.*.part_id', 'plan_parts.*.quantity']);
    expect($rules['plan_parts'])->toContain('nullable');
    expect($rules['plan_parts.*.part_id'])->toContain('required');
    expect($rules['plan_parts.*.quantity'])->toContain('required');
});

test('relações do PlanPart', function () {
    $resource = Resource::factory()->create();
    $plano = MaintenancePlan::factory()->create(['resource_id' => $resource->id]);
    $peca = Part::factory()->create();

    $planPart = PlanPart::create([
        'maintenance_plan_id' => $plano->id,
        'part_id'             => $peca->id,
        'quantity'            => 3,
    ]);

    expect($planPart->part)->toBeInstanceOf(Part::class);
    expect($planPart->part->id)->toBe($peca->id);

    expect($planPart->maintenancePlan)->toBeInstanceOf(MaintenancePlan::class);
    expect($planPart->maintenancePlan->id)->toBe($plano->id);
});

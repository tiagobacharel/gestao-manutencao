<?php

use App\Models\Maintenance;
use App\Models\MaintenancePart;
use App\Models\MaintenancePlan;
use App\Models\Part;
use App\Models\PlanPart;
use Livewire\Livewire;
use App\Models\Resource;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('criar manutenção', function () {

    Livewire::test('manutencoes.⚡index')
        ->call('openModal')
        ->assertSet('showModal', true)
        ->assertSeeLivewire('manutencoes.modal');

    $resource = Resource::factory()->create();
    $plano = MaintenancePlan::factory()->create();

    Livewire::test('manutencoes.modal')
        ->set('resource_id', $resource->id)
        ->set('maintenance_plan_id', $plano->id)
        ->set('scheduled_at', '2026-05-20')
        ->set('status', 'in_progress')
        ->set('notes', 'TesteNotas')
        ->call('save')
        ->assertHasNoErrors();


    $this->assertDatabaseHas('maintenances', [
        'resource_id' => $resource->id,
        'maintenance_plan_id' => $plano->id,
        'scheduled_at' => '2026-05-20 00:00:00',
        'status' => 'in_progress',
        'notes' => 'TesteNotas',
    ]);

    $this->assertDatabaseCount('maintenances', 1);
    $this->assertDatabaseCount('resources', 1);
    $this->assertDatabaseCount('maintenance_plans', 1);
});


test('editar a manutenção', function () {


    $resourceOriginal = Resource::factory()->create();
    $planoOriginal = MaintenancePlan::factory()->create();

    $manutencao = Maintenance::factory()->create([
        'resource_id' => $resourceOriginal->id,
        'maintenance_plan_id' => $planoOriginal->id,
        'scheduled_at' => '2023-11-03 00:00:00',
        'status' => 'cancelled',
        'notes' => 'OriginalNotas',
    ]);


    $resource = Resource::factory()->create();
    $plano = MaintenancePlan::factory()->create();


    Livewire::test('manutencoes.modal', ['manutencao' => $manutencao])
        ->assertSet('scheduled_at', '2023-11-03')
        ->assertSet('status', 'cancelled')
        ->assertSet('notes', 'OriginalNotas')
        ->set('resource_id', $resource->id)
        ->set('maintenance_plan_id', $plano->id)
        ->set('scheduled_at', '2026-05-20')
        ->set('status', 'in_progress')
        ->set('notes', 'TesteNotas')
        ->call('save')
        ->assertHasNoErrors();


    $this->assertDatabaseHas('maintenances', [
        'resource_id' => $resource->id,
        'maintenance_plan_id' => $plano->id,
        'scheduled_at' => '2026-05-20 00:00:00',
        'status' => 'in_progress',
        'notes' => 'TesteNotas',
    ]);

    $this->assertDatabaseCount('maintenances', 1);
    $this->assertDatabaseCount('resources', 2);
    $this->assertDatabaseCount('maintenance_plans', 2);
});


test('apagar manutenção', function () {

    $resource = Resource::factory()->create();
    $plano = MaintenancePlan::factory()->create();

    $manutencao = Maintenance::factory()->create([
        'resource_id' => $resource->id,
        'maintenance_plan_id' => $plano->id,
    ]);


    Livewire::test('manutencoes.⚡show', ['manutencao' => $manutencao])
        ->call('delete');

    $this->assertDatabaseCount('maintenances', 0);
    $this->assertDatabaseCount('resources', 1);
    $this->assertDatabaseCount('maintenance_plans', 1);
});


test('trocar estado da manutenção', function () {

    $resource = Resource::factory()->create();
    $plano = MaintenancePlan::factory()->create();

    $manutencao = Maintenance::factory()->create([
        'resource_id' => $resource->id,
        'maintenance_plan_id' => $plano->id,
        'scheduled_at' => '2026-05-20 00:00:00',
        'status' => 'in_progress',
        'notes' => 'TesteNotas',
    ]);



    Livewire::test('manutencoes.⚡show', ['manutencao' => $manutencao])
        ->call('updateStatus', 'cancelled');

    $this->assertDatabaseHas('maintenances', [
        'resource_id' => $resource->id,
        'maintenance_plan_id' => $plano->id,
        'scheduled_at' => '2026-05-20 00:00:00',
        'status' => 'cancelled',
        'notes' => 'TesteNotas',
    ]);

    $this->assertDatabaseCount('maintenances', 1);
    $this->assertDatabaseCount('resources', 1);
    $this->assertDatabaseCount('maintenance_plans', 1);
});



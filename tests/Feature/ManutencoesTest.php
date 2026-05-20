<?php

use App\Models\Maintenance;
use App\Models\MaintenancePlan;
use App\Models\Part;
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



    $resource = Resource::factory()->create();
    $plano = MaintenancePlan::factory()->create();

    $manutencao = Maintenance::factory()->create([
        'resource_id' => $resource->id,
        'maintenance_plan_id' => null,
        'scheduled_at' => '2023-11-03 00:00:00',
        'status' => 'cancelled',
        'notes' => 'OriginalNotas',
    ]);

    Livewire::test('manutencoes.⚡show', ['manutencao' => $manutencao])
        ->call('openModal')
        ->assertSet('showModal', true)
        ->assertSeeLivewire('manutencoes.modal');


    Livewire::test('manutencoes.modal', ['manutencao' => $manutencao])
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


test('apagar manutenção', function () {

    $resource = Resource::factory()->create();
    $plano = MaintenancePlan::factory()->create();

    $manutencao = Maintenance::factory()->create([
        'resource_id' => $resource->id,
        'maintenance_plan_id' => null,
        'scheduled_at' => '2023-11-03 00:00:00',
        'status' => 'cancelled',
        'notes' => 'OriginalNotas',
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


test('editar peças da manutenção', function () {

    $resource = Resource::factory()->create();
    $plano = MaintenancePlan::factory()->create();

    $manutencao = Maintenance::factory()->create([
        'resource_id' => $resource->id,
        'maintenance_plan_id' => $plano->id,
        'scheduled_at' => '2026-05-20 00:00:00',
        'status' => 'in_progress',
        'notes' => 'TesteNotas',
    ]);

    $peca = Part::factory()->create([
        'name'      => 'Pastilhas de Travão',
        'reference' => 'PT-9988',
    ]);

    $manutencao->parts()->attach($peca->id, ['quantity' => 2]);

    $peca2 = Part::factory()->create([
        'name'      => 'Peça Nova',
        'reference' => 'PN-2222',
    ]);

    Livewire::test('manutencoes.⚡show', ['manutencao' => $manutencao])
        ->call('openModal2')
        ->assertSet('showModal2', true)
        ->assertSeeLivewire('manutencoes.pecas_modal');


    Livewire::test('manutencoes.pecas_modal', ['manutencao' => $manutencao])
        ->assertSet('maintenance_parts.0.part_id', (string) $peca->id)
        ->assertSet('maintenance_parts.0.quantity', 2)
        ->call('removePart', 0)
        ->assertSet('maintenance_parts', [])
        ->call('addPart')
        ->call('selectPeca', 0, $peca2->id, 'Peça Nova / PN-2222')
        ->set('maintenance_parts.0.quantity', 5)
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('maintenances', [
        'id' => $manutencao->id,
        'resource_id' => $resource->id,
        'maintenance_plan_id' => $plano->id,
        'scheduled_at' => '2026-05-20 00:00:00',
        'status' => 'in_progress',
        'notes' => 'TesteNotas',
    ]);

    $this->assertDatabaseMissing('maintenance_parts', [
        'maintenance_id' => $manutencao->id,
        'part_id'            => $peca->id,
    ]);

    $this->assertDatabaseHas('maintenance_parts', [
        'maintenance_id' => $manutencao->id,
        'part_id'            => $peca2->id,
        'quantity'           => 5,
    ]);

    $this->assertDatabaseCount('maintenances', 1);
    $this->assertDatabaseCount('maintenance_plans', 1);
    $this->assertDatabaseCount('resources', 1);
    $this->assertDatabaseCount('parts', 2);
});

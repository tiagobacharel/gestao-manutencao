<?php

use App\Models\Maintenance;
use App\Models\MaintenancePlan;
use App\Models\Resource;
use App\Models\Part;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('criar peça', function () {

    Livewire::test('pecas.⚡index')
        ->call('openModal')
        ->assertSet('showModal', true);

    Livewire::test('pecas_modal')
        ->set('reference', '1234567890')
        ->set('name', 'TestePeça')
        ->set('description', 'TesteDescrição')
        ->set('stock_current', '10')
        ->set('current_unit_cost', 1.10)
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('parts', [
        'reference'         => '1234567890',
        'name'              => 'TestePeça',
        'description'       => 'TesteDescrição',
        'stock_current'     => 10,
        'current_unit_cost' => 1.10,
    ]);
});


test('editar o peça', function () {

    $peca = Part::factory()->create([
        'reference'         => '0987654321',
        'name'              => 'OriginalPeça',
        'description'       => 'OriginalDescrição',
        'stock_current'     => 10,
        'current_unit_cost' => 1.10,
    ]);

    Livewire::test('pecas_modal', ['part' => $peca])
        ->assertSet('reference', '0987654321')
        ->assertSet('name', 'OriginalPeça')
        ->assertSet('description', 'OriginalDescrição')
        ->assertSet('stock_current', '10')
        ->assertSet('current_unit_cost', '1.10')
        ->set('reference', '1234567890')
        ->set('name', 'TestePeça')
        ->set('description', 'TesteDescrição')
        ->set('stock_current', 5)
        ->set('current_unit_cost', 2.15)
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('parts', [
        'id'                => $peca->id,
        'reference'         => '1234567890',
        'name'              => 'TestePeça',
        'description'       => 'TesteDescrição',
        'stock_current'     => 5,
        'current_unit_cost' => 2.15,
    ]);

    $this->assertDatabaseCount('parts', 1);
});


test('apagar peça', function () {

    $peca = Part::factory()->create();

    Livewire::test('pecas.⚡show', ['part' => $peca])
        ->call('delete');

    $this->assertDatabaseCount('parts', 0);
});


test('editar peças do plano de manutenção', function () {

    $resource = Resource::factory()->create();

    $plano = MaintenancePlan::factory()->create([
        'resource_id' => $resource->id,
        'name'        => 'OriginalPlano',
        'description' => 'OriginalDescrição',
        'interval_value' => 1,
        'interval_unit' => 'month',
        'is_active' => 0,
        'started_at' => '2025-01-22 00:00:00',
        'email_responsible' => 'original@email.com',
        'notification_days_before' => 5,
    ]);

    $peca = Part::factory()->create([
        'name'      => 'Pastilhas de Travão',
        'reference' => 'PT-9988',
    ]);

    $plano->parts()->attach($peca->id, ['quantity' => 2]);

    $peca2 = Part::factory()->create([
        'name'      => 'Peça Nova',
        'reference' => 'PN-2222',
    ]);



    Livewire::test('manutencoes.planos.pecas_modal', ['plano' => $plano])
        ->assertSet('plan_parts.0.part_id', (string) $peca->id)
        ->assertSet('plan_parts.0.quantity', 2)
        ->call('removePart', 0)
        ->assertSet('plan_parts', [])
        ->call('addPart')
        ->call('selectPeca', 0, $peca2->id, 'Peça Nova / PN-2222')
        ->set('plan_parts.0.quantity', 5)
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('maintenance_plans', [
        'id'                       => $plano->id,
        'resource_id'              => $resource->id,
        'name'                     => 'OriginalPlano',
        'is_active'                => 0,
        'started_at'               => '2025-01-22 00:00:00',
    ]);

    $this->assertDatabaseMissing('plan_parts', [
        'maintenance_plan_id' => $plano->id,
        'part_id'            => $peca->id,
    ]);

    $this->assertDatabaseHas('plan_parts', [
        'maintenance_plan_id' => $plano->id,
        'part_id'            => $peca2->id,
        'quantity'           => 5,
    ]);

    $this->assertDatabaseCount('maintenance_plans', 1);
    $this->assertDatabaseCount('resources', 1);
    $this->assertDatabaseCount('parts', 2);
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







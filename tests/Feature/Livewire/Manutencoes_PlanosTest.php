<?php

use App\Models\MaintenancePlan;
use Livewire\Livewire;
use App\Models\Resource;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('criar plano de manutenção', function () {

    Livewire::test('planos_manutencoes.⚡index')
        ->call('openModal')
        ->assertSet('showModal', true)
        ->assertSeeLivewire('manutencoes.planos.modal');

    $resource = Resource::factory()->create();

    Livewire::test('manutencoes.planos.modal')
        ->set('resource_id', $resource->id)
        ->set('name', 'TestePlano')
        ->set('description', 'TesteDescrição')
        ->set('interval_value', 5)
        ->set('interval_unit', 'month')
        ->set('is_active', true)
        ->set('started_at', '2026-05-20')
        ->set('email_responsible', 'teste@email.com')
        ->set('notification_days_before', 3)
        ->call('save')
        ->assertHasNoErrors();


    $this->assertDatabaseHas('maintenance_plans', [
        'resource_id' => $resource->id,
        'name'        => 'TestePlano',
        'description' => 'TesteDescrição',
        'interval_value' => 5,
        'interval_unit' => 'month',
        'is_active' => 1,
        'started_at' => '2026-05-20 00:00:00',
        'email_responsible' => 'teste@email.com',
        'notification_days_before' => 3,
        'last_notified_at' => null,
    ]);
});


test('editar o plano de manutenção', function () {


    $resourceOriginal = Resource::factory()->create();

    $plano = MaintenancePlan::factory()->create([
        'resource_id' => $resourceOriginal->id,
        'name'        => 'OriginalPlano',
        'description' => 'OriginalDescrição',
        'interval_value' => 1,
        'interval_unit' => 'month',
        'is_active' => 0,
        'started_at' => '2025-01-22 00:00:00',
        'email_responsible' => 'original@email.com',
        'notification_days_before' => 5,
    ]);

    $resource = Resource::factory()->create();

    Livewire::test('manutencoes.planos.modal', ['plano' => $plano])
        ->assertSet('resource_id', $resourceOriginal->id)
        ->assertSet('name', 'OriginalPlano')
        ->assertSet('description', 'OriginalDescrição')
        ->assertSet('interval_value', '1')
        ->assertSet('interval_unit', 'month')
        ->assertSet('is_active', '0')
        ->assertSet('started_at', '2025-01-22')
        ->assertSet('email_responsible', 'original@email.com')
        ->assertSet('notification_days_before', '5')
        ->set('resource_id', $resource->id)
        ->set('name', 'TestePlano')
        ->set('description', 'TesteDescrição')
        ->set('interval_value', 5)
        ->set('interval_unit', 'month')
        ->set('is_active', true)
        ->set('started_at', '2026-05-20')
        ->set('email_responsible', 'teste@email.com')
        ->set('notification_days_before', 3)
        ->call('save')
        ->assertHasNoErrors();


    $this->assertDatabaseHas('maintenance_plans', [
        'resource_id' => $resource->id,
        'name'        => 'TestePlano',
        'description' => 'TesteDescrição',
        'interval_value' => 5,
        'interval_unit' => 'month',
        'is_active' => 1,
        'started_at' => '2026-05-20 00:00:00',
        'email_responsible' => 'teste@email.com',
        'notification_days_before' => 3,
        'last_notified_at' => null,
    ]);

    $this->assertDatabaseCount('maintenance_plans', 1);
    $this->assertDatabaseCount('resources', 2);
});


test('apagar  plano de manutenção', function () {

    $resource = Resource::factory()->create();

    $plano = MaintenancePlan::factory()->create([
        'resource_id' => $resource->id
    ]);


    Livewire::test('planos_manutencoes.⚡show', ['plano_manutencao' => $plano])
        ->call('delete');

    $this->assertDatabaseCount('maintenance_plans', 0);
    $this->assertDatabaseCount('resources', 1);
});


test('trocar estado do plano de manutenção', function () {

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


    Livewire::test('planos_manutencoes.⚡show', ['plano_manutencao' => $plano])
        ->call('toggleAtivo');

    $this->assertDatabaseHas('maintenance_plans', [
        'resource_id' => $resource->id,
        'name'        => 'OriginalPlano',
        'description' => 'OriginalDescrição',
        'interval_value' => 1,
        'interval_unit' => 'month',
        'is_active' => 1,
        'started_at' => '2025-01-22 00:00:00',
        'email_responsible' => 'original@email.com',
        'notification_days_before' => 5,
    ]);

    $this->assertDatabaseCount('maintenance_plans', 1);
    $this->assertDatabaseCount('resources', 1);
});


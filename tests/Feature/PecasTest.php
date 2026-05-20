<?php

use Livewire\Volt\Volt;
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
        'reference'         => '1234567890',
        'name'              => 'OriginalPeça',
        'description'       => 'OriginalDescrição',
        'stock_current'     => 10,
        'current_unit_cost' => 1.10,
    ]);

    Livewire::test('pecas.⚡show', ['part' => $peca])
        ->call('openModal')
        ->assertSet('showModal', true);

    Livewire::test('pecas_modal', ['part' => $peca])
        #->set('reference', '1234567890')
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

    $peca = Part::factory()->create([
        'reference'         => '1234567890',
        'name'              => 'OriginalPeça',
        'description'       => 'OriginalDescrição',
        'stock_current'     => 10,
        'current_unit_cost' => 1.10,
    ]);

    Livewire::test('pecas.⚡show', ['part' => $peca])
        ->call('delete');

    $this->assertDatabaseCount('parts', 0);
});

<?php

use Livewire\Livewire;
use App\Models\Resource;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('criar recurso', function () {

    Livewire::test('recursos.⚡index')
        ->call('openModal')
        ->assertSet('showModal', true)
        ->assertSeeLivewire('recursos.modal');

    Livewire::test('recursos.modal')
        ->set('name', 'TesteRecurso')
        ->set('description', 'TesteDescrição')
        ->set('location', 'TesteLocalização')
        ->set('section', 'TesteSecção')
        ->set('status', 'active')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('resources', [
        'name'        => 'TesteRecurso',
        'description' => 'TesteDescrição',
        'location'    => 'TesteLocalização',
        'section'     => 'TesteSecção',
        'status'      => 'active',
    ]);
});


test('editar o recurso', function () {

    $resource = Resource::factory()->create([
        'name' => 'OriginalRecurso',
        'description' => 'OriginalDescrição',
        'location'    => 'OriginalLocalização',
        'section'     => 'OriginalSecção',
        'status'      => 'active',
    ]);


    Livewire::test('recursos.modal', ['recurso' => $resource])
        ->assertSet('name', 'OriginalRecurso')
        ->assertSet('description', 'OriginalDescrição')
        ->assertSet('location', 'OriginalLocalização')
        ->assertSet('section', 'OriginalSecção')
        ->assertSet('status', 'active')
        ->set('name', 'TesteRecurso')
        ->set('description', 'TesteDescrição')
        ->set('location', 'TesteLocalização')
        ->set('section', 'TesteSecção')
        ->set('status', 'inactive')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('resources', [
        'id'          => $resource->id,
        'name'        => 'TesteRecurso',
        'description' => 'TesteDescrição',
        'location'    => 'TesteLocalização',
        'section'     => 'TesteSecção',
        'status'      => 'inactive',
    ]);

    $this->assertDatabaseCount('resources', 1);
});


test('apagar recurso', function () {

    $resource = Resource::factory()->create();


    Livewire::test('recursos.⚡show', ['recurso' => $resource])
        ->call('delete');

    $this->assertDatabaseCount('resources', 0);
});


test('trocar estado do recurso', function () {

    $resource = Resource::factory()->create([
        'name' => 'TesteRecurso',
        'description' => 'TesteDescrição',
        'location'    => 'TesteLocalização',
        'section'     => 'TesteSecção',
        'status'      => 'active',
    ]);


    Livewire::test('recursos.⚡show', ['recurso' => $resource])
        ->call('toggleStatus');

    $this->assertDatabaseHas('resources', [
        'id'          => $resource->id,
        'name'        => 'TesteRecurso',
        'description' => 'TesteDescrição',
        'location'    => 'TesteLocalização',
        'section'     => 'TesteSecção',
        'status'      => 'inactive',
    ]);

    $this->assertDatabaseCount('resources', 1);
});


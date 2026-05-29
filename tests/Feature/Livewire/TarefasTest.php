<?php

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('criar tarefa', function () {

    Livewire::test('tarefas.⚡index')
        ->call('openModal')
        ->assertSet('showModal', true)
        ->assertSeeLivewire('tarefas_modal');

    Livewire::test('tarefas_modal')
        ->set('name', 'TesteTarefa')
        ->set('description', 'TesteDescrição')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('tasks', [
        'name'        => 'TesteTarefa',
        'description' => 'TesteDescrição',
    ]);
});

test('editar o tarefa', function () {

    $task = Task::factory()->create([
        'name' => 'OriginalTarefa',
        'description' => 'OriginalDescrição',
    ]);


    Livewire::test('tarefas_modal', ['task' => $task])
        ->assertSet('name', 'OriginalTarefa')
        ->assertSet('description', 'OriginalDescrição')
        ->set('name', 'TesteTarefa')
        ->set('description', 'TesteDescrição')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('tasks', [
        'id'          => $task->id,
        'name'        => 'TesteTarefa',
        'description' => 'TesteDescrição',
    ]);

    $this->assertDatabaseCount('tasks', 1);
});


test('apagar tarefa', function () {

    $task = Task::factory()->create();


    Livewire::test('tarefas.⚡show', ['task' => $task])
        ->call('delete');

    $this->assertDatabaseCount('tasks', 0);
});

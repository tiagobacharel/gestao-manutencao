<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Volt::route('/', '⚡index')->name('home');

Volt::route('/recursos', 'recursos.⚡index')->name('recursos.index');;
Volt::route('/recurso/{recurso}', 'recursos.⚡show')->name('resource.show');


Volt::route('/manutencoes', 'manutencoes.⚡index')->name('manutencoes.index');
Volt::route('/manutencoes/{manutencao}', 'manutencoes.⚡show')->name('manutencoes.show');

Volt::route('/pecas', 'pecas.⚡index')->name('pecas.index');
Volt::route('/pecas/{part}', 'pecas.⚡show')->name('pecas.show');


Volt::route('/calendar', '⚡calendar')->name('recursos.calendar');;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';

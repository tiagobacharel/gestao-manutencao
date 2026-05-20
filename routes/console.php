<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('maintenances:notify-upcoming')
    ->dailyAt('08:00')                 // corre todos os dias às 08:00
    ->withoutOverlapping()             // evita execuções simultâneas
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/maintenances-notify.log'));

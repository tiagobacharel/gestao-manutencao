<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('maintenances:notify-upcoming')
    ->dailyAt('08:00')
    ->timezone('Europe/Lisbon')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/maintenances-notify.log'));

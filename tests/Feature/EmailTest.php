<?php

use App\Models\MaintenancePlan;
use App\Models\Resource;
use App\Notifications\MaintenancePlansPublished;
use App\Notifications\UpcomingMaintenanceAlert; // Substitua pelo nome real da sua Notification
use Illuminate\Support\Facades\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('enviar email de planos de manutenções', function () {
    Notification::fake();

    $resource = Resource::factory()->create();

    $dataManutencaoPrevista = now()->addDays(1)->startOfDay();
    $dataInicialDinamica    = $dataManutencaoPrevista->copy()->subDays(3);

    $plano = MaintenancePlan::factory()->create([
        'resource_id'              => $resource->id,
        'name'                     => 'TestePlano',
        'description'              => 'TesteDescrição',
        'interval_value'           => 3,
        'interval_unit'            => 'day',
        'is_active'                => 1,
        'started_at'               => $dataInicialDinamica->format('Y-m-d H:i:s'),
        'email_responsible'        => 'teste@email.com',
        'notification_days_before' => 1,
        'last_notified_at'         => null,
    ]);

    $this->artisan('maintenances:notify-upcoming')
        ->assertExitCode(0);


    Notification::assertSentOnDemand(
        MaintenancePlansPublished::class,
        function ($notification, $channels, $notifiable) use ($plano) {
            return $notifiable->routes['mail'] === $plano->email_responsible;
        }
    );
});

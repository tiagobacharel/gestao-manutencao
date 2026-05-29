<?php

use App\Models\MaintenancePlan;
use App\Models\Resource;
use App\Notifications\MaintenancePlansPublished;
use Illuminate\Support\Facades\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Notifications\AnonymousNotifiable;

uses(RefreshDatabase::class);

test('enviar email', function () {
    Notification::fake();

    $cenarios = [
        ['day', 'subDays', 3],
        ['month', 'subMonths', 2],
        ['year', 'subYears', 1],
        ['day', 'addDays', 1],
    ];

    foreach ($cenarios as [$unit, $subMethod, $value]) {
        $resource = Resource::factory()->create();

        $dataManutencaoPrevista = now()->addDays(1)->startOfDay();
        $dataInicial = $subMethod === 'addDays'
            ? now()->$subMethod($value)->startOfDay() // Caso do plano iniciar amanha
            : $dataManutencaoPrevista->copy()->$subMethod($value); // Casos do plano já ter iniciado

        $plano = MaintenancePlan::factory()->create([
            'resource_id'              => $resource->id,
            'interval_value'           => $value,
            'interval_unit'            => $unit,
            'is_active'                => 1,
            'started_at'               => $dataInicial->format('Y-m-d H:i:s'),
            'email_responsible'        => 'teste@email.com',
            'notification_days_before' => 1,
            'last_notified_at'         => null,
        ]);

        $this->artisan('maintenances:notify-upcoming')->assertExitCode(0);

        Notification::assertSentOnDemand(
            MaintenancePlansPublished::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === $plano->email_responsible
        );
    }
});


test('executa comando sem enviar email', function () {
    Notification::fake();

    $resource = Resource::factory()->create();
    $dataManutencaoPrevista = now()->addDays(1)->startOfDay();
    $dataInicialDinamica = $dataManutencaoPrevista->copy()->subDays(3);

    $plano = MaintenancePlan::factory()->create([
        'resource_id'              => $resource->id,
        'name'                     => 'Plano XPTO',
        'interval_value'           => 3,
        'interval_unit'            => 'day',
        'is_active'                => 1,
        'started_at'               => $dataInicialDinamica->format('Y-m-d H:i:s'),
        'email_responsible'        => 'dryrun@email.com',
        'notification_days_before' => 1,
        'last_notified_at'         => null,
    ]);

    $this->artisan('maintenances:notify-upcoming', ['--dry-run' => true])
        ->expectsTable(
            ['Email responsável', 'Plano', 'Próxima manutenção', 'Aviso'],
            [
                [
                    'dryrun@email.com',
                    'Plano XPTO',
                    $dataManutencaoPrevista->format('d/m/Y'),
                    '1 dia(s) antes'
                ]
            ]
        )
        ->assertExitCode(0);

    Notification::assertNothingSent();
    expect($plano->fresh()->last_notified_at)->toBeNull();
});

test('não enviar email', function () {
    Notification::fake();

    $this->artisan('maintenances:notify-upcoming')
        ->expectsOutput('No upcoming maintenance notifications to send today.')
        ->assertExitCode(0);

    Notification::assertNothingSent();
});

test('conteudo do email', function () {
    Carbon::setTestNow(now()->startOfDay());
    $res = Resource::factory()->create(['name' => 'Torno']);

    $cenarios = [
        [now(), 3, 'hoje', ''],
        [now()->addDay(), 3, 'amanhã', now()->addDay()->format('d/m/Y')],
        [now()->addDays(5), 3, 'em 5 dias', now()->addDays(5)->format('d/m/Y')],
        [now()->subDays(2), 3, 'amanhã', now()->addDay()->format('d/m/Y')],
    ];

    foreach ($cenarios as [$start, $interval, $txt, $date]) {
        $plan = MaintenancePlan::factory()->create([
            'resource_id' => $res->id, 'started_at' => $start->format('Y-m-d H:i:s'), 'interval_value' => $interval, 'interval_unit' => 'day'
        ]);
        $plan->setRelation('planParts', collect([(object)['quantity' => 1, 'part' => (object)['name' => 'X', 'reference' => 'Y']]])); // Linha 61

        $linhas = (new MaintenancePlansPublished(collect([$plan])))->toMail(new AnonymousNotifiable())->toArray()['introLines'];

        $msg = $date ? "{$txt} ({$date})" : $txt;
        expect($linhas)->toContain("**Data prevista:** {$msg}");
    }

    Carbon::setTestNow();
});

<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class MaintenancePlansPublished extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param Collection $maintenancePlans  Collection of MaintenancePlan models due soon
     */
    public function __construct(
        protected Collection $maintenancePlans,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = $this->maintenancePlans->count();

        $mail = (new MailMessage)
            ->subject("Lembrete: {$count} manutenções agendadas brevemente")
            ->greeting('Olá!')
            ->line("Tem {$count} manutenções agendadas que requerem a sua atenção:"
            );

        foreach ($this->maintenancePlans as $plan) {
            $dueDate   = $plan->started_at->format('d/m/Y');
            $daysUntil = now()->startOfDay()->diffInDays($plan->started_at->startOfDay());
            $when      = $daysUntil === 0 ? 'hoje' : "em {$daysUntil} dia(s) ({$dueDate})";

            // Transforma em array de strings (sem implodir)
            $parts = $plan->planParts->map(function ($planPart) {
                return "• {$planPart->part->name} (Ref: {$planPart->part->reference}) x{$planPart->quantity}";
            })->toArray();

            $mail->line("───────────────────────────────")
                ->line("**{$plan->name}**")
                ->line("**Recurso:** {$plan->resource->name}")
                ->line("**Data prevista:** {$when}");

            if ($plan->description) {
                $mail->line("**Descrição:** {$plan->description}");
            }

            // O Laravel Mail aceita um array e cria uma linha nova para cada item
            $mail->line("**Itens:**")
                ->lines($parts);
        }


        return $mail
            ->line("───────────────────────────────")
            ->line('Por favor, certifique-se de que os recursos necessários estão disponíveis.')
            ->salutation('Com os melhores cumprimentos');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'maintenance_plan_ids' => $this->maintenancePlans->pluck('id'),
        ];
    }

    private function translateUnit(string $unit): string
    {
        return match ($unit) {
            'day'   => 'dia(s)',
            'month' => 'mês(es)',
            'year'  => 'ano(s)',
            default => $unit,
        };
    }
}

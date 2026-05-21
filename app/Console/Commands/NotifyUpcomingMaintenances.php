<?php

namespace App\Console\Commands;

use App\Models\MaintenancePlan;
use App\Notifications\MaintenancePlansPublished;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class NotifyUpcomingMaintenances extends Command
{
    protected $signature = 'maintenances:notify-upcoming
                            {--dry-run : List due plans without sending notifications}';

    protected $description = 'Send email reminders to responsible parties for upcoming maintenance plans';

    public function handle(): int
    {
        $today = Carbon::today();

        $plans = MaintenancePlan::query()
            ->with('resource')
            ->where('is_active', true)
            ->whereNotNull('email_responsible')
            ->whereNotNull('started_at')
            ->where(function ($query) use ($today) {
                $query->whereNull('last_notified_at')
                    ->orWhereDate('last_notified_at', '<', $today);
            })
            ->get();

        $due = $plans->filter(function (MaintenancePlan $plan) use ($today): bool {
            $nextOccurrence = $this->getNextOccurrence($plan, $today);

            if (! $nextOccurrence) {
                return false;
            }

            $notifyOn = $nextOccurrence->copy()->subDays($plan->notification_days_before);

            return $notifyOn->isSameDay($today);
        });

        $due = $due->map(function (MaintenancePlan $plan) use ($today) {
            $plan->next_occurrence = $this->getNextOccurrence($plan, $today);
            return $plan;
        });

        if ($due->isEmpty()) {
            $this->info('No upcoming maintenance notifications to send today.');
            return self::SUCCESS;
        }

        $grouped = $due->groupBy('email_responsible');

        $this->info("Found {$due->count()} plan(s) across {$grouped->count()} recipient(s).");

        if ($this->option('dry-run')) {
            $this->renderDryRunTable($grouped);
            return self::SUCCESS;
        }

        $notifiedPlanIds = [];

        foreach ($grouped as $email => $userPlans) {
            Notification::route('mail', $email)
                ->notify(new MaintenancePlansPublished($userPlans));

            $this->line("  ✉  Sent to {$email} ({$userPlans->count()} plan(s))");

            foreach ($userPlans as $p) {
                $notifiedPlanIds[] = $p->id;
            }
        }

        MaintenancePlan::whereIn('id', $notifiedPlanIds)
            ->update(['last_notified_at' => now()]);

        $this->info('All notifications sent successfully.');
        return self::SUCCESS;
    }

    /**
     * Calculate the next occurrence date from today based on the plan interval.
     */
    private function getNextOccurrence(MaintenancePlan $plan, Carbon $today): ?Carbon
    {
        $start = Carbon::parse($plan->started_at)->startOfDay();

        if ($start->greaterThan($today)) {
            return $start;
        }

        $intervalValue = $plan->interval_value;
        $intervalUnit  = $plan->interval_unit;

        $diffMethod = match ($intervalUnit) {
            'day'   => 'diffInDays',
            'month' => 'diffInMonths',
            'year'  => 'diffInYears',
        };

        $elapsed   = $start->$diffMethod($today);
        $periods   = (int) floor($elapsed / $intervalValue);
        $addMethod = match ($intervalUnit) {
            'day'   => 'addDays',
            'month' => 'addMonths',
            'year'  => 'addYears',
        };

        $lastOccurrence = $start->copy()->$addMethod($periods * $intervalValue);
        $nextOccurrence = $lastOccurrence->copy()->$addMethod($intervalValue);

        return $nextOccurrence;
    }

    private function renderDryRunTable(Collection $grouped): void
    {
        $rows = [];
        foreach ($grouped as $email => $plans) {
            foreach ($plans as $plan) {
                $rows[] = [
                    $email,
                    $plan->name,
                    $plan->next_occurrence->format('d/m/Y'),
                    "{$plan->notification_days_before} dia(s) antes",
                ];
            }
        }

        $this->table(
            ['Email responsável', 'Plano', 'Próxima manutenção', 'Aviso'],
            $rows
        );
    }
}

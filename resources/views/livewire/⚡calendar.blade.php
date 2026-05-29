<?php

use App\Models\Maintenance;
use App\Models\MaintenancePlan;
use Carbon\Carbon;
use Livewire\Volt\Component;

new class extends Component {

    public int $year;
    public int $month;

    public function mount(): void
    {
        $this->year = now()->year;
        $this->month = now()->month;
    }

    public function rendering($view): void
    {
        $view->layoutData(['title' => 'Calendário']);
    }

    public function alterarMes(string $acao): void
    {
        $date = Carbon::create($this->year, $this->month);

        $date = match ($acao) {
            'anterior' => $date->subMonth(),
            'seguinte' => $date->addMonth(),
            default    => now(),
        };

        $this->year = $date->year;
        $this->month = $date->month;
    }

    public function getIsCurrentMonthProperty(): bool
    {
        return $this->year === now()->year && $this->month === now()->month;
    }

    public function with(): array
    {
        return once(function () {
            $start = Carbon::create($this->year, $this->month, 1)->startOfMonth();
            $end = $start->copy()->endOfMonth();

            $manutencoes = Maintenance::with(['resource', 'plan'])
                ->whereBetween('scheduled_at', [$start, $end])
                ->orderBy('scheduled_at')
                ->get();

            $planosVirtuais = MaintenancePlan::with('resource')
                ->where('is_active', true)
                ->whereNotNull('started_at')
                ->get()
                ->flatMap(function (MaintenancePlan $plan) use ($start, $end, $manutencoes) {
                    $ocorrencias = collect();
                    $cursor = Carbon::parse($plan->started_at);

                    while ($cursor->lt($start)) {
                        $cursor->add($plan->interval_value, $plan->interval_unit);
                    }

                    while ($cursor->lte($end)) {
                        $jaExiste = $manutencoes->contains(function ($m) use ($plan, $cursor) {
                            return $m->maintenance_plan_id === $plan->id
                                && Carbon::parse($m->scheduled_at)->isSameDay($cursor);
                        });

                        if (!$jaExiste) {
                            $ocorrencias->push((object)[
                                'id' => null,
                                'maintenance_plan_id' => $plan->id,
                                'resource_id' => $plan->resource_id,
                                'resource' => $plan->resource,
                                'plan' => $plan,
                                'scheduled_at' => $cursor->copy(),
                                'status' => 'planned',
                                'notes' => $plan->description,
                                'done_at' => null,
                            ]);
                        }

                        $cursor->add($plan->interval_value, $plan->interval_unit);
                    }

                    return $ocorrencias;
                });

            $tudo = $manutencoes
                ->concat($planosVirtuais)
                ->sortBy(fn($m) => Carbon::parse($m->scheduled_at)->format('Y-m-d H:i:s'))
                ->groupBy(fn($m) => Carbon::parse($m->scheduled_at)->format('Y-m-d'));

            $gridStart = $start->copy()->startOfWeek(Carbon::MONDAY);
            $gridEnd = $end->copy()->endOfWeek(Carbon::SUNDAY);

            $weeks = [];
            $cursor = $gridStart->copy();

            while ($cursor->lte($gridEnd)) {
                $week = [];
                for ($i = 0; $i < 7; $i++) {
                    $key = $cursor->format('Y-m-d');
                    $week[] = [
                        'date' => $cursor->copy(),
                        'isCurrentMonth' => $cursor->month === $this->month,
                        'isToday' => $cursor->isToday(),
                        'manutencoes' => $tudo->get($key, collect()),
                    ];
                    $cursor->addDay();
                }
                $weeks[] = $week;
            }

            return [
                'weeks' => $weeks,
                'monthLabel' => Carbon::create($this->year, $this->month)->locale('pt_PT')->isoFormat('MMMM [de] YYYY'),
                'isCurrentMonth' => $this->year === now()->year && $this->month === now()->month,
            ];
        });
    }
};
?>


<div>
    <flux:main container class="space-y-6">

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <flux:heading size="xl" level="1" class="capitalize">{{ $monthLabel }}</flux:heading>

            <div class="flex items-center gap-2">
                @if(!$this->isCurrentMonth)
                    <flux:button wire:click="alterarMes('hoje')" wire:loading.attr="disabled" variant="subtle" size="sm">Hoje</flux:button>
                @endif

                <flux:button wire:click="alterarMes('anterior')" wire:loading.attr="disabled" icon="chevron-left" variant="subtle" size="sm" label="Mês anterior"/>
                <flux:button wire:click="alterarMes('seguinte')" wire:loading.attr="disabled" icon="chevron-right" variant="subtle" size="sm" label="Próximo mês"/>
            </div>

        </div>

        <flux:separator variant="subtle"/>

        <div class="flex flex-wrap gap-4 text-xs">
            <div class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-purple-400"></span>
                <span class="text-zinc-500">Planeada</span>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-green-500"></span>
                <span class="text-zinc-500">Concluída</span>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                <span class="text-zinc-500">Em Progresso</span>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-yellow-400"></span>
                <span class="text-zinc-500">Pendente</span>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-red-500"></span>
                <span class="text-zinc-500">Cancelada</span>
            </div>
        </div>

        {{-- COMPUTADOR --}}
        <div class="min-w-0 hidden md:block">

            <flux:card class="p-0 overflow-hidden">

                <div class="grid grid-cols-7 border-b border-zinc-200 dark:border-zinc-700">
                    @foreach(['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'] as $diaSemana)
                        <div class="py-2.5 text-center text-xs font-semibold text-zinc-400 uppercase tracking-wide">
                            {{ $diaSemana }}
                        </div>
                    @endforeach
                </div>

                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach($weeks as $week)
                        <div class="grid grid-cols-7 divide-x divide-zinc-100 dark:divide-zinc-800">
                            @foreach($week as $day)
                                <div class="min-h-[120px] p-1.5 flex flex-col gap-1
                            {{ !$day['isCurrentMonth'] ? 'bg-zinc-50/60 dark:bg-zinc-900/50' : '' }}
                            {{ $day['isToday'] ? 'bg-zinc-100/70 dark:bg-zinc-800/40 font-semibold' : '' }}">

                                    <div class="flex justify-end mb-0.5">
                                <span class="text-xs font-semibold flex items-center justify-center w-6 h-6 rounded-full
                                    {{ $day['isToday']
                                        ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900 shadow-sm scale-105'
                                        : ($day['isCurrentMonth'] ? 'text-zinc-700 dark:text-zinc-300' : 'text-zinc-300 dark:text-zinc-600')
                                    }}">
                                    {{ $day['date']->day }}
                                </span>
                                    </div>

                                    @foreach($day['manutencoes'] as $m)
                                        @php
                                            $bg = match($m->status) {
                                                'done'        => 'bg-green-100 hover:bg-green-200 text-green-800 dark:bg-green-900/40 dark:text-green-300',
                                                'in_progress' => 'bg-blue-100 hover:bg-blue-200 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
                                                'cancelled'   => 'bg-red-100 hover:bg-red-200 text-red-700 dark:bg-red-900/40 dark:text-red-300',
                                                'planned'     => 'bg-purple-100 hover:bg-purple-200 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300',
                                                default       => 'bg-yellow-100 hover:bg-yellow-200 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
                                            };
                                            $dot = match($m->status) {
                                                'done'        => 'bg-green-500',
                                                'in_progress' => 'bg-blue-500',
                                                'cancelled'   => 'bg-red-500',
                                                'planned'     => 'bg-purple-400',
                                                default       => 'bg-yellow-400',
                                            };
                                            $url = $m->id
                                                ? route('manutencoes.show', $m->id)
                                                : route('planos_manutencoes.show', $m->plan->id);
                                        @endphp
                                        <a href="{{ $url }}"
                                           wire:navigate
                                           wire:key="cal-{{ $m->id ?? 'plan-' . $m->plan->id . '-' . Carbon::parse($m->scheduled_at)->format('Ymd') }}"
                                           title="{{ $m->resource->name }}{{ $m->plan ? ' · ' . $m->plan->name : '' }}"
                                           class="flex items-center gap-1 rounded px-1.5 py-0.5 text-xs truncate transition-colors {{ $bg }}
                                           {{ $m->status === 'cancelled' ? 'line-through opacity-70' : '' }}
                                           {{ $m->status === 'planned' ? 'border border-dashed border-purple-300 dark:border-purple-700' : '' }}"
                                        >
                                            <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $dot }}"></span>
                                            <span class="truncate leading-4">{{ $m->resource->name }}</span>
                                        </a>
                                    @endforeach

                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>

            </flux:card>

        </div>


        {{-- TELEMÓVEL --}}
        <div class="min-w-0 md:hidden">
            <div class="space-y-6">
                @foreach($weeks as $weekIndex => $week)
                    @php
                        $diasComManutencoes = collect($week)->filter(
                            fn($day) => $day['isCurrentMonth'] && count($day['manutencoes']) > 0
                        );
                    @endphp

                    @if($diasComManutencoes->isNotEmpty())

                            <div class="space-y-3">
                                @foreach($diasComManutencoes as $day)
                                    <div class="rounded-2xl overflow-hidden border border-zinc-100 dark:border-zinc-800 shadow-sm">

                                        <div class="flex items-center gap-3 px-4 py-3
                                    {{ $day['isToday']
                                        ? 'bg-zinc-900 dark:bg-zinc-100'
                                        : 'bg-zinc-50 dark:bg-zinc-800/80' }}">
                                            <div class="flex flex-col leading-tight">
                                        <span class="text-xl font-bold tabular-nums
                                            {{ $day['isToday']
                                                ? 'text-white dark:text-zinc-900'
                                                : 'text-zinc-800 dark:text-zinc-200' }}">
                                            {{ $day['date']->day }}
                                        </span>
                                            </div>
                                            <div class="flex flex-col leading-tight">
                                        <span class="text-xs font-semibold uppercase tracking-wide
                                            {{ $day['isToday']
                                                ? 'text-zinc-300 dark:text-zinc-600'
                                                : 'text-zinc-500 dark:text-zinc-400' }}">
                                            {{ $day['date']->translatedFormat('D') }}
                                        </span>
                                                <span class="text-xs
                                            {{ $day['isToday']
                                                ? 'text-zinc-400 dark:text-zinc-500'
                                                : 'text-zinc-400 dark:text-zinc-500' }}">
                                            {{ $day['date']->translatedFormat('F') }}
                                        </span>
                                            </div>
                                            @if($day['isToday'])
                                                <span class="ml-auto text-[10px] font-semibold uppercase tracking-widest text-zinc-400 dark:text-zinc-500 bg-zinc-800 dark:bg-zinc-200 px-2 py-0.5 rounded-full">
                                            Hoje
                                        </span>
                                            @endif
                                            <span class="{{ $day['isToday'] ? '' : 'ml-auto' }} text-[10px] font-medium text-zinc-400 dark:text-zinc-500 bg-zinc-200 dark:bg-zinc-700 px-2 py-0.5 rounded-full">
                                        {{ count($day['manutencoes']) }} {{ count($day['manutencoes']) === 1 ? 'tarefa' : 'tarefas' }}
                                    </span>
                                        </div>

                                        <div class="divide-y divide-zinc-100 dark:divide-zinc-800 bg-white dark:bg-zinc-900">
                                            @foreach($day['manutencoes'] as $m)
                                                @php
                                                    $leftBorder = match($m->status) {
                                                        'done'        => 'border-l-4 border-green-500',
                                                        'in_progress' => 'border-l-4 border-blue-500',
                                                        'cancelled'   => 'border-l-4 border-red-400',
                                                        'planned'     => 'border-l-4 border-purple-400',
                                                        default       => 'border-l-4 border-yellow-400',
                                                    };
                                                    $dot = match($m->status) {
                                                        'done'        => 'bg-green-500',
                                                        'in_progress' => 'bg-blue-500',
                                                        'cancelled'   => 'bg-red-400',
                                                        'planned'     => 'bg-purple-400',
                                                        default       => 'bg-yellow-400',
                                                    };
                                                    $statusLabel = match($m->status) {
                                                        'done'        => 'Concluída',
                                                        'in_progress' => 'Em Progresso',
                                                        'cancelled'   => 'Cancelada',
                                                        'planned'     => 'Planeada',
                                                        default       => 'Pendente',
                                                    };
                                                    $url = $m->id
                                                        ? route('manutencoes.show', $m->id)
                                                        : route('planos_manutencoes.show', $m->plan->id);
                                                @endphp
                                                <a href="{{ $url }}"
                                                   wire:navigate
                                                   wire:key="mob-{{ $m->id ?? 'plan-' . $m->plan->id . '-' . Carbon::parse($m->scheduled_at)->format('Ymd') }}"
                                                   class="flex items-center gap-3 px-4 py-3.5 active:bg-zinc-50 dark:active:bg-zinc-800/60 transition-colors {{ $leftBorder }}
                                               {{ $m->status === 'cancelled' ? 'opacity-50' : '' }}">

                                                    <div class="flex flex-col min-w-0 flex-1 gap-0.5">
                                                <span class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 truncate
                                                    {{ $m->status === 'cancelled' ? 'line-through' : '' }}">
                                                    {{ $m->resource->name }}
                                                </span>
                                                        @if($m->plan)
                                                            <span class="text-xs text-zinc-400 dark:text-zinc-500 truncate">
                                                        {{ $m->plan->name }}
                                                    </span>
                                                        @endif
                                                        <div class="flex items-center gap-1 mt-0.5">
                                                            <span class="w-1.5 h-1.5 rounded-full {{ $dot }}"></span>
                                                            <span class="text-[10px] font-medium text-zinc-400 dark:text-zinc-500 uppercase tracking-wide">
                                                        {{ $statusLabel }}
                                                    </span>
                                                        </div>
                                                    </div>

                                                    <flux:icon name="chevron-right" class="shrink-0 w-4 h-4 text-zinc-300 dark:text-zinc-600"/>
                                                </a>
                                            @endforeach
                                        </div>

                                    </div>
                                @endforeach
                            </div>
                    @endif
                @endforeach

                @if(collect($weeks)->flatten(1)->filter(fn($d) => $d['isCurrentMonth'] && count($d['manutencoes']) > 0)->isEmpty())
                    <div class="flex flex-col items-center justify-center py-16 text-center gap-2">
                        <flux:icon name="calendar" class="w-10 h-10 text-zinc-300 dark:text-zinc-600"/>
                        <span class="text-sm text-zinc-400 dark:text-zinc-500">Sem manutenções este mês</span>
                    </div>
                @endif
            </div>

        </div>

    </flux:main>
</div>

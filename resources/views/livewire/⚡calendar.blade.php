<?php

use App\Models\Maintenance;
use Carbon\Carbon;
use Livewire\Volt\Component;

new class extends Component {

    public int $year;
    public int $month;

    public function mount(): void
    {
        $this->year  = now()->year;
        $this->month = now()->month;
    }

    public function rendering($view): void
    {
        $view->layoutData(['title' => 'Calendário']);
    }

    public function previousMonth(): void
    {
        $date = Carbon::create($this->year, $this->month)->subMonth();
        $this->year  = $date->year;
        $this->month = $date->month;
    }

    public function nextMonth(): void
    {
        $date = Carbon::create($this->year, $this->month)->addMonth();
        $this->year  = $date->year;
        $this->month = $date->month;
    }

    public function goToday(): void
    {
        $this->year  = now()->year;
        $this->month = now()->month;
    }

    public function with(): array
    {
        $start = Carbon::create($this->year, $this->month, 1)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        $manutencoes = Maintenance::with(['resource', 'plan'])
            ->whereBetween('scheduled_at', [$start, $end])
            ->orderBy('scheduled_at')
            ->get()
            ->groupBy(fn($m) => Carbon::parse($m->scheduled_at)->format('Y-m-d'));

        // Grelha começa na segunda-feira da semana do 1º do mês
        $gridStart = $start->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd   = $end->copy()->endOfWeek(Carbon::SUNDAY);

        $weeks  = [];
        $cursor = $gridStart->copy();

        while ($cursor->lte($gridEnd)) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $key    = $cursor->format('Y-m-d');
                $week[] = [
                    'date'           => $cursor->copy(),
                    'isCurrentMonth' => $cursor->month === $this->month,
                    'isToday'        => $cursor->isToday(),
                    'manutencoes'    => $manutencoes->get($key, collect()),
                ];
                $cursor->addDay();
            }
            $weeks[] = $week;
        }

        return [
            'weeks'          => $weeks,
            'monthLabel'     => Carbon::create($this->year, $this->month)->locale('pt_PT')->isoFormat('MMMM [de] YYYY'),
            'isCurrentMonth' => $this->year === now()->year && $this->month === now()->month,
        ];
    }
};
?>

<div>
    <flux:main container class="space-y-6">

        {{-- Cabeçalho --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <flux:heading size="xl" level="1" class="capitalize">{{ $monthLabel }}</flux:heading>

            <div class="flex items-center gap-2">
                @if(!$isCurrentMonth)
                    <flux:button wire:click="goToday" variant="subtle" size="sm">Hoje</flux:button>
                @endif
                <flux:button wire:click="previousMonth" icon="chevron-left" variant="subtle" size="sm" label="Mês anterior" />
                <flux:button wire:click="nextMonth"     icon="chevron-right" variant="subtle" size="sm" label="Próximo mês" />
            </div>
        </div>

        <flux:separator variant="subtle" />

        {{-- Legenda --}}
        <div class="flex flex-wrap gap-4 text-xs">
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

        {{-- Grelha --}}
        <flux:card class="p-0 overflow-hidden">

            {{-- Dias da semana --}}
            <div class="grid grid-cols-7 border-b border-zinc-200 dark:border-zinc-700">
                @foreach(['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'] as $diaSemana)
                    <div class="py-2.5 text-center text-xs font-semibold text-zinc-400 uppercase tracking-wide">
                        {{ $diaSemana }}
                    </div>
                @endforeach
            </div>

            {{-- Semanas --}}
            <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach($weeks as $week)
                    <div class="grid grid-cols-7 divide-x divide-zinc-100 dark:divide-zinc-800">
                        @foreach($week as $day)
                            <div class="min-h-[120px] p-1.5 flex flex-col gap-1
                                {{ !$day['isCurrentMonth'] ? 'bg-zinc-50/60 dark:bg-zinc-900/50' : '' }}
                                {{ $day['isToday'] ? 'bg-zinc-100/70 dark:bg-zinc-800/40 font-semibold' : '' }}">

                                {{-- Número do dia --}}
                                <div class="flex justify-end mb-0.5">
                                    <span class="text-xs font-semibold flex items-center justify-center w-6 h-6 rounded-full
                                        {{ $day['isToday']
                                            ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900 shadow-sm scale-105'
                                            : ($day['isCurrentMonth'] ? 'text-zinc-700 dark:text-zinc-300' : 'text-zinc-300 dark:text-zinc-600')
                                        }}">
                                        {{ $day['date']->day }}
                                    </span>
                                </div>


                                {{-- Manutenções --}}
                                @foreach($day['manutencoes'] as $m)
                                    @php
                                        $bg  = match($m->status) {
                                            'done'        => 'bg-green-100 hover:bg-green-200 text-green-800 dark:bg-green-900/40 dark:text-green-300',
                                            'in_progress' => 'bg-blue-100 hover:bg-blue-200 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
                                            'cancelled'   => 'bg-red-100 hover:bg-red-200 text-red-700 dark:bg-red-900/40 dark:text-red-300',
                                            default       => 'bg-yellow-100 hover:bg-yellow-200 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
                                        };
                                        $dot = match($m->status) {
                                            'done'        => 'bg-green-500',
                                            'in_progress' => 'bg-blue-500',
                                            'cancelled'   => 'bg-red-500',
                                            default       => 'bg-yellow-400',
                                        };
                                    @endphp
                                    <a href="{{ route('manutencoes.show', $m) }}"
                                       wire:navigate
                                       wire:key="cal-{{ $m->id }}"
                                       title="{{ $m->resource->name }}{{ $m->plan ? ' · ' . $m->plan->name : '' }}"
                                       class="flex items-center gap-1 rounded px-1.5 py-0.5 text-xs truncate transition-colors {{ $bg }}
                                           {{ $m->status === 'cancelled' ? 'line-through opacity-70' : '' }}">
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

    </flux:main>
</div>

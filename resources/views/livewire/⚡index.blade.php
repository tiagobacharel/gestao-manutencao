<?php

use Carbon\Carbon;
use Livewire\Component;
use App\Models\Maintenance;
use App\Models\MaintenancePlan;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public int $ano;

    public function mount(): void
    {
        $this->ano = now()->year;
    }

    public function alterarAno(string $direcao): void
    {
        if ($direcao === 'anterior') {
            $this->ano--;
            return;
        }

        $this->ano = min($this->ano + 1, now()->year);
    }

    public function rendering($view): void
    {
        $view->layoutData(['title' => 'Dashboard']);
    }

    public function with(): array
    {
        $ano = $this->ano;

        $totalFeitas = Maintenance::where('status', 'done')->count();

        $totalFeitasAno = Maintenance::where('status', 'done')
            ->whereYear('done_at', $ano)
            ->count();

        $custoAnual = Maintenance::where('status', 'done')
            ->whereYear('done_at', $ano)
            ->with('parts')
            ->get()
            ->sum(fn($m) => $m->parts->sum(
                fn($p) => $p->pivot->quantity * $p->pivot->unit_cost_at_time
            ));

        $custoTotal = Maintenance::where('status', 'done')
            ->with('parts')
            ->get()
            ->sum(fn($m) => $m->parts->sum(
                fn($p) => $p->pivot->quantity * $p->pivot->unit_cost_at_time
            ));

        $emProgresso = Maintenance::where('status', 'in_progress')->count();

        $manutencoes = Maintenance::with(['resource', 'plan'])
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereNotNull('scheduled_at')
            ->orderBy('scheduled_at')
            ->limit(4)
            ->get();

        $planosSeemManutencaoPendente = MaintenancePlan::with('resource')
            ->where('is_active', true)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('maintenances')
                    ->whereColumn('maintenances.maintenance_plan_id', 'maintenance_plans.id')
                    ->whereIn('maintenances.status', ['pending', 'in_progress']);
            })
            ->get()
            ->map(function (MaintenancePlan $plan) {
                $proxima = $plan->started_at
                    ? Carbon::parse($plan->started_at)
                    : Carbon::now();

                while ($proxima->isPast()) {
                    $proxima->add($plan->interval_value, $plan->interval_unit);
                }

                return (object)[
                    'id' => null,
                    'resource' => $plan->resource,
                    'plan' => $plan,
                    'status' => 'planned',
                    'scheduled_at' => $proxima->toDateString(),
                    'notes' => $plan->description,
                ];
            });

        $proximasManutencoes = $manutencoes
            ->concat($planosSeemManutencaoPendente)
            ->sortBy('scheduled_at')
            ->take(4)
            ->values();

        $custoMensal = collect(range(1, 12))->mapWithKeys(function ($mes) use ($ano) {
            $manutencoes = Maintenance::where('status', 'done')
                ->whereYear('done_at', $ano)
                ->whereMonth('done_at', $mes)
                ->with('parts')
                ->get();

            $custo = $manutencoes->sum(fn($m) => $m->parts->sum(
                fn($p) => $p->pivot->quantity * $p->pivot->unit_cost_at_time
            ));

            return [$mes => round($custo, 2)];
        });

        $recentes = Maintenance::with(['resource', 'plan'])
            ->where('status', 'done')
            ->whereYear('done_at', $ano)
            ->orderByDesc('done_at')
            ->limit(5)
            ->get();

        $custoPorAno = Maintenance::where('status', 'done')
            ->whereNotNull('done_at')
            ->with('parts')
            ->get()
            ->groupBy(fn($m) => $m->done_at->year)
            ->map(fn($manutencoes) => $manutencoes->sum(
                fn($m) => $m->parts->sum(fn($p) => $p->pivot->quantity * $p->pivot->unit_cost_at_time)
            ))
            ->sortKeys();

        $mesesParaMedia = $ano === now()->year ? now()->month : 12;

        return [
            'ano' => $ano,
            'totalFeitas' => $totalFeitas,
            'totalFeitasAno' => $totalFeitasAno,
            'custoAnual' => $custoAnual,
            'custoTotal' => $custoTotal,
            'emProgresso' => $emProgresso,
            'proximasManutencoes' => $proximasManutencoes,
            'custoMensal' => $custoMensal,
            'recentes' => $recentes,
            'custoPorAno' => $custoPorAno,
            'mesesParaMedia' => $mesesParaMedia,
        ];
    }
};
?>

<div>
    <flux:main container class="space-y-6">

        {{-- Cabeçalho --}}
        <div class="flex items-center justify-between">
            <flux:text size="sm" class="text-zinc-400 mt-0.5">Visão geral das manutenções</flux:text>

            {{-- Navegação de ano --}}
            <div class="flex items-center gap-1">
                <flux:button wire:click="alterarAno('anterior')" wire:loading.attr="disabled" variant="ghost" size="sm" icon="chevron-left" />
                <span class="text-sm font-semibold tabular-nums w-12 text-center">{{ $ano }}</span>
                <flux:button wire:click="alterarAno('seguinte')" wire:loading.attr="disabled" variant="ghost" size="sm" icon="chevron-right" :disabled="$ano >= now()->year" />
            </div>
        </div>

        <flux:separator variant="subtle"/>

        {{-- KPI Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

            <flux:card class="space-y-1">
                <flux:text size="sm" class="text-zinc-400">Concluídas (total)</flux:text>
                <div class="flex items-end gap-2">
                    <span class="text-3xl font-bold">{{ $totalFeitas }}</span>
                    <flux:badge color="green" size="sm" class="mb-1">Concluídas</flux:badge>
                </div>
                <flux:text size="xs" class="text-zinc-400">{{ $totalFeitasAno }} concluídas em {{ $ano }}</flux:text>
            </flux:card>

            <flux:card class="space-y-1">
                <flux:text size="sm" class="text-zinc-400">Custo total {{ $ano }}</flux:text>
                <div class="flex items-end gap-2">
                    <span class="text-3xl font-bold">{{ number_format($custoAnual, 0, ',', '.') }}</span>
                    <span class="text-zinc-400 mb-1 text-sm">€</span>
                </div>
                <flux:text size="xs" class="text-zinc-400">{{ number_format($custoTotal, 0, ',', '.') }} € acumulado
                </flux:text>
            </flux:card>

            <flux:card class="space-y-1">
                <flux:text size="sm" class="text-zinc-400">Em Progresso</flux:text>
                <div class="flex items-end gap-2">
                    <span class="text-3xl font-bold">{{ $emProgresso }}</span>
                    <flux:badge color="blue" size="sm" class="mb-1">ativas</flux:badge>
                </div>
                <flux:text size="xs" class="text-zinc-400">manutenções a decorrer</flux:text>
            </flux:card>

            <flux:card class="space-y-1">
                <flux:text size="sm" class="text-zinc-400">Custo médio/mês</flux:text>
                <div class="flex items-end gap-2">
                    <span class="text-3xl font-bold">
                        {{ $totalFeitasAno > 0 ? number_format($custoAnual / $mesesParaMedia, 0, ',', '.') : '0' }}
                    </span>
                    <span class="text-zinc-400 mb-1 text-sm">€</span>
                </div>
                <flux:text size="xs" class="text-zinc-400">baseado em {{ $mesesParaMedia }} meses</flux:text>
            </flux:card>

        </div>

        {{-- Gráfico de barras + Próximas --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Gráfico custo mensal --}}
            <flux:card class="lg:col-span-2">
                <div class="flex items-center justify-between mb-6">
                    <flux:heading size="md">Custo mensal {{ $ano }}</flux:heading>
                    @if($ano === now()->year)
                        <flux:badge color="blue" size="sm" icon="calendar">
                            {{ now()->translatedFormat('F') }}
                        </flux:badge>
                    @endif
                </div>

                @php
                    $maxCusto = $custoMensal->max() ?: 1;
                    $meses = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
                    $mesAtual = $ano === now()->year ? now()->month : -1;

                    $steps = 4;
                    $stepValue = $maxCusto / $steps;
                    $yLabels = collect(range(0, $steps))->map(fn($i) => $stepValue * $i)->reverse();
                @endphp

                <div class="flex gap-3">
                    {{-- Eixo Y --}}
                    <div class="flex flex-col justify-between items-end pb-6" style="height: 176px;">
                        @foreach($yLabels as $label)
                            <span class="text-[10px] text-zinc-400 dark:text-zinc-500 tabular-nums leading-none">
                                {{ $label >= 1000 ? number_format($label/1000, 1, ',', '.') . 'k' : number_format($label, 0) }}
                            </span>
                        @endforeach
                    </div>

                    {{-- Área do gráfico --}}
                    <div class="flex-1 relative">
                        {{-- Linhas de referência --}}
                        <div class="absolute inset-0 pb-6 flex flex-col justify-between pointer-events-none">
                            @foreach($yLabels as $label)
                                <div class="w-full border-t border-dashed border-zinc-100 dark:border-zinc-800"></div>
                            @endforeach
                        </div>

                        {{-- Barras --}}
                        <div class="flex items-end gap-1.5 h-44">
                            @foreach($custoMensal as $mes => $custo)
                                @php
                                    $altura = $maxCusto > 0 ? round(($custo / $maxCusto) * 100) : 0;
                                    $isAtual = $mes === $mesAtual;
                                    $isPast = $mes < $mesAtual || $ano < now()->year;
                                @endphp
                                <div
                                    class="flex-1 flex flex-col items-center gap-1.5 group relative cursor-default"
                                    x-data="{ open: false }"
                                    @mouseenter="open = true"
                                    @mouseleave="open = false"
                                >
                                    @if($custo > 0)
                                        <div
                                            x-show="open"
                                            x-transition:enter="transition ease-out duration-100"
                                            x-transition:enter-start="opacity-0 scale-95"
                                            x-transition:enter-end="opacity-100 scale-100"
                                            class="absolute bottom-full mb-2 z-20 bg-zinc-900 dark:bg-zinc-700 text-white text-[10px] font-medium rounded px-2 py-1 whitespace-nowrap pointer-events-none shadow-lg"
                                        >
                                            {{ number_format($custo, 2, ',', '.') }} €
                                            <div
                                                class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-zinc-900 dark:border-t-zinc-700"></div>
                                        </div>
                                    @endif

                                    <div class="w-full relative flex items-end" style="height: 144px;">
                                        <div
                                            class="w-full rounded-t-md transition-all duration-300
                                                @if($isAtual)
                                                    bg-blue-500 dark:bg-blue-400 shadow-[0_0_12px_rgba(59,130,246,0.4)]
                                                @elseif($isPast)
                                                    bg-zinc-400 dark:bg-zinc-500 group-hover:bg-zinc-500 dark:group-hover:bg-zinc-400
                                                @else
                                                    bg-zinc-150 dark:bg-zinc-800
                                                @endif"
                                            style="height: {{ max($altura, $custo > 0 ? 3 : 0) }}%;"
                                        ></div>
                                    </div>

                                    <span class="text-[10px] leading-none
                                        @if($isAtual) font-bold text-blue-500 dark:text-blue-400
                                        @else text-zinc-400 dark:text-zinc-500
                                        @endif">
                                        {{ $meses[$mes - 1] }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <flux:separator class="mt-4 mb-3"/>
                <div class="flex items-center gap-4 text-xs text-zinc-400">
                    @if($ano === now()->year)
                        <div class="flex items-center gap-1.5">
                            <div class="w-2.5 h-2.5 rounded-sm bg-blue-500 shadow-[0_0_6px_rgba(59,130,246,0.5)]"></div>
                            <span>Mês atual</span>
                        </div>
                    @endif
                    <div class="flex items-center gap-1.5">
                        <div class="w-2.5 h-2.5 rounded-sm bg-zinc-400 dark:bg-zinc-500"></div>
                        <span>Meses com custo</span>
                    </div>
                </div>
            </flux:card>

            {{-- Próximas manutenções --}}
            <flux:card class="space-y-4">
                <flux:heading size="md">Próximas Manutenções</flux:heading>

                @if($proximasManutencoes->isEmpty())
                    <flux:text size="sm" class="text-zinc-400">Nenhuma manutenção agendada.</flux:text>
                @else
                    <div class="space-y-3">
                        @foreach($proximasManutencoes->take(4) as $m)
                            @php
                                $scheduledAt = $m->scheduled_at instanceof \Carbon\Carbon
                                    ? $m->scheduled_at
                                    : \Carbon\Carbon::parse($m->scheduled_at);

                                $dias = now()->startOfDay()->diffInDays($scheduledAt, false);
                                $cor = match(true) {
                                    $dias < 0  => 'red',
                                    $dias <= 3 => 'yellow',
                                    $dias <= 7 => 'blue',
                                    default    => 'zinc',
                                };
                                $label = match(true) {
                                    $dias < 0  => 'Atrasada',
                                    $dias === 0 => 'Hoje',
                                    $dias === 1 => 'Amanhã',
                                    default    => 'em ' . $dias . 'd',
                                };

                                $url = $m->id
                                    ? route('manutencoes.show', $m->id)
                                    : route('planos_manutencoes.show', $m->plan->id);
                            @endphp
                            <a href="{{ $url }}" wire:navigate
                               class="flex items-center justify-between gap-2 p-2 rounded-lg hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium truncate">{{ $m->resource->name }}</p>
                                    <p class="text-xs text-zinc-400 truncate">
                                        {{ $scheduledAt->format('d/m/Y') }}
                                        @if($m->plan)
                                            · {{ $m->plan->name }}
                                        @endif
                                        @if(($m->status ?? null) === 'planned')
                                            · <span class="italic">Plano</span>
                                        @endif
                                    </p>
                                </div>
                                <flux:badge color="{{ $cor }}" size="sm" class="shrink-0">{{ $label }}</flux:badge>
                            </a>
                        @endforeach
                    </div>
                @endif
            </flux:card>

        </div>

        {{-- Manutenções recentes --}}
        <flux:card class="space-y-4">
            <flux:heading size="md">Recentemente Concluídas — {{ $ano }}</flux:heading>

            @if($recentes->isEmpty())
                <flux:text size="sm" class="text-zinc-400">Nenhuma manutenção concluída em {{ $ano }}.</flux:text>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 text-left text-zinc-500 text-xs uppercase tracking-wide">
                            <th class="pb-2 pr-4">Recurso</th>
                            <th class="pb-2 pr-4">Plano</th>
                            <th class="pb-2 pr-4">Concluída em</th>
                            <th class="pb-2 text-right">Custo</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach($recentes as $m)
                            <tr>
                                <td class="py-2 pr-4 font-medium">
                                    <a href="{{ route('manutencoes.show', $m) }}" wire:navigate class="hover:underline">
                                        {{ $m->resource->name }}
                                    </a>
                                </td>
                                <td class="py-2 pr-4">
                                    @if($m->plan)
                                        <a href="{{ route('planos_manutencoes.show', $m->plan) }}" wire:navigate>
                                            <flux:badge color="purple" size="sm">{{ $m->plan->name }}</flux:badge>
                                        </a>
                                    @else
                                        <span class="text-zinc-400">—</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-4 text-zinc-500">
                                    {{ $m->done_at?->format('d/m/Y H:i') ?? '—' }}
                                </td>
                                <td class="py-2 text-right font-semibold">
                                    {{ number_format($m->parts->sum(fn($p) => $p->pivot->quantity * $p->pivot->unit_cost_at_time), 2, ',', '.') }}
                                    €
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </flux:card>

    </flux:main>
</div>

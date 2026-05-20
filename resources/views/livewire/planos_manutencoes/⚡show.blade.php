<?php

use App\Models\MaintenancePlan;
use App\Models\Maintenance;
use Livewire\Volt\Component;

new class extends Component {

    public MaintenancePlan $plano_manutencao;

    public function rendering($view): void
    {
        $view->layoutData(['title' => $this->plano_manutencao->name]);
        $this->plano_manutencao->loadMissing(['resource', 'planParts.part', 'maintenances.parts']);
    }

    public function toggleAtivo(): void
    {
        $this->plano_manutencao->update(['is_active' => !$this->plano_manutencao->is_active]);
    }

    public function delete(): void
    {
        $this->plano_manutencao->delete();
        $this->redirect(route('planos_manutencoes.index'), navigate: true);
    }

    public bool $showModal = false;
    public function openModal(): void { $this->showModal = true; }

    public bool $showModalPecas = false;
    public function openModalPecas(): void { $this->showModalPecas = true; }
};
?>

<div>
    <flux:main container class="space-y-6">

        {{-- Cabeçalho --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <flux:button href="{{ route('planos_manutencoes.index') }}" wire:navigate icon="arrow-left" variant="subtle" size="sm" />
                <div>
                    <flux:heading size="xl" level="1">{{ $plano_manutencao->name }}</flux:heading>
                    <flux:text size="sm" class="text-zinc-400 mt-0.5">
                        Plano #{{ $plano_manutencao->id }} ·
                        <a href="{{ route('resource.show', $plano_manutencao->resource) }}" wire:navigate class="hover:underline">
                            {{ $plano_manutencao->resource->name }}
                        </a>
                    </flux:text>
                </div>
            </div>

            @if($plano_manutencao->is_active)
                <flux:badge color="green" icon="check-circle" size="lg">Ativo</flux:badge>
            @else
                <flux:badge color="zinc" icon="x-circle" size="lg">Inativo</flux:badge>
            @endif
        </div>

        <flux:separator variant="subtle" />

        @if($showModal)
            <livewire:manutencoes.planos.modal :plano="$plano_manutencao" />
        @endif

        @if($showModalPecas)
            <livewire:manutencoes.planos.pecas_modal :plano="$plano_manutencao" />
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Coluna principal --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Descrição --}}
                @if($plano_manutencao->description)
                    <flux:card class="space-y-2">
                        <flux:heading size="md">Descrição</flux:heading>
                        <flux:text class="text-base leading-relaxed text-zinc-700 dark:text-zinc-300 whitespace-pre-wrap">
                            {{ $plano_manutencao->description }}
                        </flux:text>
                    </flux:card>
                @endif

                {{-- Peças previstas --}}
                <flux:card class="space-y-4">
                    <div class="flex items-center justify-between">
                        <flux:heading size="md">Peças Previstas</flux:heading>
                        <div class="flex items-center gap-2">
                            @if($plano_manutencao->planParts->isNotEmpty())
                                <flux:badge color="zinc" size="sm">{{ $plano_manutencao->planParts->count() }} peça(s)</flux:badge>
                            @endif
                            <flux:button wire:click="openModalPecas" variant="filled" icon="pencil" size="sm">
                                Editar
                            </flux:button>
                        </div>
                    </div>

                    @if($plano_manutencao->planParts->isEmpty())
                        <flux:text class="text-zinc-400 text-sm">Nenhuma peça definida neste plano.</flux:text>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                <tr class="border-b border-zinc-200 dark:border-zinc-700 text-left text-zinc-500 text-xs uppercase tracking-wide">
                                    <th class="pb-2 pr-4">Referência</th>
                                    <th class="pb-2 pr-4">Peça</th>
                                    <th class="pb-2 pr-4 text-right">Qtd. prevista</th>
                                    <th class="pb-2 pr-4 text-right">Preço unit. atual</th>
                                    <th class="pb-2 text-right">Total estimado</th>
                                </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @foreach($plano_manutencao->planParts as $planPart)
                                    <tr>
                                        <td class="py-2 pr-4 text-zinc-400 font-mono text-xs">
                                            {{ $planPart->part?->reference ?? '—' }}
                                        </td>
                                        <td class="py-2 pr-4 font-medium">
                                            <a href="{{ route('pecas.show', $planPart->part) }}" wire:navigate class="hover:underline text-zinc-800 dark:text-white">
                                                {{ $planPart->part?->name ?? 'Peça não encontrada' }}
                                            </a>
                                        </td>
                                        <td class="py-2 pr-4 text-right">{{ $planPart->quantity }}</td>
                                        <td class="py-2 pr-4 text-right text-zinc-500">
                                            {{ number_format($planPart->part?->current_unit_cost ?? 0, 2, ',', '.') }} €
                                        </td>
                                        <td class="py-2 text-right font-semibold">
                                            {{ number_format($planPart->quantity * ($planPart->part?->current_unit_cost ?? 0), 2, ',', '.') }} €
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                                <tfoot>
                                <tr class="border-t-2 border-zinc-300 dark:border-zinc-600">
                                    <td colspan="4" class="pt-3 text-right font-semibold text-sm text-zinc-500">Total estimado</td>
                                    <td class="pt-3 text-right font-bold text-base">
                                        {{ number_format($plano_manutencao->planParts->sum(fn($p) => $p->quantity * ($p->part?->current_unit_cost ?? 0)), 2, ',', '.') }} €
                                    </td>
                                </tr>
                                </tfoot>
                            </table>
                        </div>
                    @endif
                </flux:card>

                {{-- Histórico de manutenções --}}
                <flux:card class="space-y-4">
                    <div class="flex items-center gap-2">
                        <flux:icon name="clock" variant="outline" class="w-5 h-5 text-zinc-400" />
                        <flux:heading size="md">Histórico de Manutenções</flux:heading>
                    </div>

                    @if($plano_manutencao->maintenances->isEmpty())
                        <flux:text class="text-zinc-400 text-sm">Nenhuma manutenção registada com este plano.</flux:text>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                <tr class="border-b border-zinc-200 dark:border-zinc-700 text-left text-zinc-500 text-xs uppercase tracking-wide">
                                    <th class="pb-2 pr-4">Agendada</th>
                                    <th class="pb-2 pr-4">Concluída</th>
                                    <th class="pb-2 pr-4">Estado</th>
                                    <th class="pb-2 text-right">Custo real</th>
                                    <th class="pb-2"></th>
                                </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @foreach($plano_manutencao->maintenances->sortByDesc('scheduled_at') as $m)
                                    @php
                                        $badge = match($m->status) {
                                            'done'        => ['color' => 'green',  'icon' => 'check-circle', 'label' => 'Concluída'],
                                            'in_progress' => ['color' => 'blue',   'icon' => 'wrench',       'label' => 'Em progresso'],
                                            'cancelled'   => ['color' => 'red',    'icon' => 'x-circle',     'label' => 'Cancelada'],
                                            default       => ['color' => 'yellow', 'icon' => 'clock',        'label' => 'Pendente'],
                                        };
                                        $custoReal = $m->parts->sum(fn($p) => $p->pivot->quantity * $p->pivot->unit_cost_at_time);
                                    @endphp
                                    <tr>
                                        <td class="py-2 pr-4 text-zinc-500">
                                            {{ $m->scheduled_at?->format('d/m/Y') ?? '—' }}
                                        </td>
                                        <td class="py-2 pr-4 text-zinc-500">
                                            {{ $m->done_at?->format('d/m/Y') ?? '—' }}
                                        </td>
                                        <td class="py-2 pr-4">
                                            <flux:badge color="{{ $badge['color'] }}" icon="{{ $badge['icon'] }}" size="sm">
                                                {{ $badge['label'] }}
                                            </flux:badge>
                                        </td>
                                        <td class="py-2 text-right font-semibold">
                                            @if($custoReal > 0)
                                                {{ number_format($custoReal, 2, ',', '.') }} €
                                            @else
                                                <span class="text-zinc-400">—</span>
                                            @endif
                                        </td>
                                        <td class="py-2 pl-4">
                                            <flux:button
                                                variant="subtle"
                                                size="sm"
                                                icon="eye"
                                                href="{{ route('manutencoes.show', $m) }}"
                                                wire:navigate
                                            />
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </flux:card>

            </div>

            {{-- Coluna lateral --}}
            <div class="space-y-6">

                {{-- Detalhes --}}
                <flux:card class="space-y-4">
                    <flux:heading size="md">Detalhes</flux:heading>

                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between gap-2">
                            <dt class="text-zinc-400">Recurso</dt>
                            <dd class="font-medium text-right">
                                <a href="{{ route('resource.show', $plano_manutencao->resource) }}" wire:navigate
                                   class="hover:underline text-primary-600 dark:text-primary-400">
                                    {{ $plano_manutencao->resource->name }}
                                </a>
                            </dd>
                        </div>

                        <div class="flex justify-between gap-2">
                            <dt class="text-zinc-400">Localização</dt>
                            <dd class="font-medium text-right">{{ $plano_manutencao->resource->location }}</dd>
                        </div>

                        <flux:separator variant="subtle" />

                        <div class="flex justify-between gap-2">
                            <dt class="text-zinc-400">Intervalo</dt>
                            <dd class="font-medium">{{ $plano_manutencao->interval_value }}
                                {{
                                    match($plano_manutencao->interval_unit) {
                                        'day'   => $plano_manutencao->interval_value == 1 ? 'dia' : 'dias',
                                        'month' => $plano_manutencao->interval_value == 1 ? 'mês' : 'meses',
                                        'year'  => $plano_manutencao->interval_value == 1 ? 'ano' : 'anos',
                                        default => $plano_manutencao->interval_unit
                                    }
                                }}
                            </dd>
                        </div>

                        <div class="flex justify-between gap-2">
                            <dt class="text-zinc-400">Início</dt>
                            <dd class="font-medium">{{ $plano_manutencao->started_at?->format('d/m/Y') ?? '—' }}</dd>
                        </div>

                        <flux:separator variant="subtle" />

                        <div class="flex justify-between gap-2">
                            <dt class="text-zinc-400">Notificação</dt>
                            <dd class="font-medium">{{ $plano_manutencao->notification_days_before }}d antes</dd>
                        </div>

                        @if($plano_manutencao->email_responsible)
                            <div class="flex justify-between gap-2">
                                <dt class="text-zinc-400">Email responsável</dt>
                                <dd class="font-medium text-right text-xs break-all">{{ $plano_manutencao->email_responsible }}</dd>
                            </div>
                        @endif

                        @if($plano_manutencao->last_notified_at)
                            <div class="flex justify-between gap-2">
                                <dt class="text-zinc-400">Última notificação</dt>
                                <dd class="font-medium text-xs">{{ $plano_manutencao->last_notified_at->format('d/m/Y H:i') }}</dd>
                            </div>
                        @endif
                    </dl>
                </flux:card>

                {{-- Ações --}}
                <flux:card class="space-y-4">
                    <flux:heading size="md">Ações</flux:heading>

                    <div class="flex flex-col gap-2">

                        <flux:button wire:click="toggleAtivo" variant="filled"
                                     icon="{{ $plano_manutencao->is_active ? 'pause' : 'play' }}"
                                     class="w-full justify-start">
                            {{ $plano_manutencao->is_active ? 'Desativar plano' : 'Ativar plano' }}
                        </flux:button>

                        <flux:separator variant="subtle" class="my-1" />

                        <flux:button wire:click="openModal" variant="filled" icon="pencil" class="w-full justify-start">
                            Editar
                        </flux:button>

                        <flux:button
                            wire:click="delete"
                            wire:confirm="Tem a certeza que quer apagar este plano? Todas as manutenções associadas serão também apagadas."
                            variant="danger" icon="trash" class="w-full justify-start">
                            Apagar
                        </flux:button>
                    </div>
                </flux:card>

            </div>
        </div>

    </flux:main>
</div>

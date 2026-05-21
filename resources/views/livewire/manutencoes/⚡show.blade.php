<?php

use App\Models\Maintenance;
use Livewire\Volt\Component;

new class extends Component {

    public Maintenance $manutencao;

    public function rendering($view): void
    {
        $view->layoutData(['title' => "Manutenção #{$this->manutencao->id}"]);
        $this->manutencao->loadMissing(['resource', 'plan.planParts', 'parts']);
    }

    public function updateStatus(string $status): void
    {
        $this->manutencao->update([
            'status'  => $status,
            'done_at' => $status === 'done' ? now() : null
        ]);
    }


    public function delete(): void
    {
        $this->manutencao->delete();
        $this->redirect(route('manutencoes.index'), navigate: true);
    }

    public bool $showModal = false;

    public function openModal() { $this->showModal = true; }

    public bool $showModal2 = false;

    public function openModal2() { $this->showModal2 = true; }
};
?>

<div>
    <flux:main container class="space-y-6">

        {{-- Cabeçalho --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <flux:button  href="{{ route('manutencoes.index') }}" wire:navigate icon="arrow-left" variant="subtle" size="sm" />
                <div>
                    <flux:heading size="xl" level="1">
                        {{ $manutencao->resource->name }}
                    </flux:heading>
                    <flux:text size="sm" class="text-zinc-400 mt-0.5">
                        Manutenção #{{ $manutencao->id }}
                        @if($manutencao->plan)
                            · <span class="text-purple-500">{{ $manutencao->plan->name }}</span>
                        @endif
                    </flux:text>
                </div>
            </div>

            @php
                $badge = $manutencao->status_badge;
            @endphp
            <flux:badge color="{{ $badge['color'] }}" icon="{{ $badge['icon'] }}" size="lg">
                {{ $badge['label'] }}
            </flux:badge>
        </div>

        <flux:separator variant="subtle" />



        @if($showModal)
            <livewire:manutencoes.modal :manutencao="$manutencao" />
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Coluna principal --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Notas --}}
                @if($manutencao->notes)
                    <flux:card class="space-y-2">
                        <flux:heading size="md">Notas</flux:heading>
                        <flux:text class="text-base leading-relaxed text-zinc-700 dark:text-zinc-300 whitespace-pre-wrap">
                            {{ $manutencao->notes }}
                        </flux:text>
                    </flux:card>
                @endif

                @if($showModal2)
                    <livewire:manutencoes.pecas_modal :manutencao="$manutencao" />
                @endif

                {{-- Peças utilizadas --}}
                <flux:card class="space-y-4">
                    <div class="flex items-center justify-between">
                        <flux:heading size="md">Peças Utilizadas</flux:heading>
                        @if($manutencao->parts->isNotEmpty())
                            <flux:badge color="zinc" size="sm">{{ $manutencao->parts->count() }} peça(s)</flux:badge>
                        @endif
                        <flux:button wire:click="openModal2" wire:navigate
                                     class="w-1/5 justify-start" variant="filled" icon="pencil"
                        >
                            Editar
                        </flux:button>
                    </div>

                    @if($manutencao->parts->isEmpty())
                        <flux:text class="text-zinc-400 text-sm">Nenhuma peça registada nesta manutenção.</flux:text>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                <tr class="border-b border-zinc-200 dark:border-zinc-700 text-left text-zinc-500 text-xs uppercase tracking-wide">
                                    <th class="pb-2 pr-4">Referência</th>
                                    <th class="pb-2 pr-4">Descrição</th>
                                    <th class="pb-2 pr-4 text-right">Qtd.</th>
                                    <th class="pb-2 pr-4 text-right">Preço unit.</th>
                                    <th class="pb-2 text-right">Total</th>
                                </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @foreach($manutencao->parts as $part)
                                    <tr>
                                        <td class="py-2 pr-4 text-zinc-400 font-mono text-xs">
                                            {{ $part->reference ?? '—' }}
                                        </td>
                                        <td class="py-2 pr-4 font-medium">
                                            <a href="{{ route('pecas.show', $part) }}" wire:navigate class="hover:underline text-zinc-800 dark:text-white">
                                                {{ $part->name }}
                                            </a>
                                        </td>
                                        <td class="py-2 pr-4 text-right">{{ $part->pivot->quantity }}</td>
                                        <td class="py-2 pr-4 text-right text-zinc-500">
                                            {{ number_format($part->pivot->unit_cost_at_time, 2, ',', '.') }} €
                                        </td>
                                        <td class="py-2 text-right font-semibold">
                                            {{ number_format($part->pivot->quantity * $part->pivot->unit_cost_at_time, 2, ',', '.') }} €
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                                <tfoot>
                                <tr class="border-t-2 border-zinc-300 dark:border-zinc-600">
                                    <td colspan="4" class="pt-3 text-right font-semibold text-sm text-zinc-500">Total</td>
                                    <td class="pt-3 text-right font-bold text-base">
                                        {{ number_format($manutencao->total_cost, 2, ',', '.') }}&nbsp;€
                                    </td>
                                </tr>
                                </tfoot>
                            </table>
                        </div>
                        @if($manutencao->plan && $manutencao->plan->planParts->isNotEmpty() && $manutencao->parts->isNotEmpty())
                            @php
                                $desvio = $manutencao->cost_deviation;
                            @endphp

                            <div class="flex items-center justify-end gap-2 pt-1">
                                <flux:text size="sm" class="text-zinc-500">Desvio:</flux:text>
                                @if($desvio > 0.01)
                                    <flux:badge color="red" icon="arrow-trending-up">
                                        +{{ number_format($desvio, 2, ',', '.') }} €
                                    </flux:badge>
                                @elseif($desvio < -0.01)
                                    <flux:badge color="green" icon="arrow-trending-down">
                                        {{ number_format($desvio, 2, ',', '.') }} €
                                    </flux:badge>
                                @else
                                    <flux:badge color="zinc" icon="minus">Sem desvio</flux:badge>
                                @endif
                            </div>
                        @endif
                    @endif
                </flux:card>

                {{-- Comparação com o plano --}}
                @if($manutencao->plan && $manutencao->plan->planParts->isNotEmpty())
                    <flux:card class="space-y-4">
                        <div class="flex items-center gap-2">
                            <flux:icon name="clipboard-document-list" variant="outline" class="w-5 h-5 text-purple-500" />
                            <flux:heading size="md">Peças Previstas no Plano</flux:heading>
                        </div>
                        <flux:text size="sm" class="text-zinc-400" >
                            Peças esperadas segundo o plano "{{ $manutencao->plan->name }}".
                        </flux:text>

                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                <tr class="border-b border-zinc-200 dark:border-zinc-700 text-left text-zinc-500 text-xs uppercase tracking-wide">
                                    <th class="pb-2 pr-4">Referência</th>
                                    <th class="pb-2 pr-4">Descrição</th>
                                    <th class="pb-2 pr-4 text-right">Qtd. prevista</th>
                                    <th class="pb-2 pr-4 text-right">Preço unit.</th>
                                    <th class="pb-2 text-right">Total previsto</th>
                                </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @foreach($manutencao->plan->planParts as $planPart)
                                    <tr>
                                        <td class="py-2 pr-4 text-zinc-400 font-mono text-xs">
                                            {{ $planPart->part?->reference ?? '—' }}
                                        </td>


                                        <td class="py-2 pr-4 font-medium">
                                            <a href="{{ route('pecas.show', $planPart->part) }}" wire:navigate class="hover:underline text-zinc-800 dark:text-white">
                                                {{ $planPart->part?->name ?? 'Peça Não Encontrada ou Apagada' }}
                                            </a>
                                        </td>

                                        <td class="py-2 pr-4 text-right">
                                            {{ $planPart->quantity }}
                                        </td>

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
                                    <td colspan="4" class="pt-3 text-right font-semibold text-sm text-zinc-500">Total previsto</td>

                                    <td class="pt-3 text-right font-bold text-base">
                                        {{ number_format($manutencao->plan->estimated_cost, 2, ',', '.') }}&nbsp;€
                                    </td>
                                </tr>
                                </tfoot>
                            </table>
                        </div>
                    </flux:card>
                @endif


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
                                <a href="{{ route('resource.show', $manutencao->resource) }}" wire:navigate
                                   class="hover:underline text-primary-600 dark:text-primary-400">
                                    {{ $manutencao->resource->name }}
                                </a>
                            </dd>
                        </div>

                        <div class="flex justify-between gap-2">
                            <dt class="text-zinc-400">Localização</dt>
                            <dd class="font-medium text-right">{{ $manutencao->resource->location }}</dd>
                        </div>

                        <flux:separator variant="subtle" />

                        <div class="flex justify-between gap-2">
                            <dt class="text-zinc-400">Plano</dt>
                            <dd class="text-right">
                                @if($manutencao->plan)
                                    <a href="{{ route('planos_manutencoes.show', $manutencao->plan) }}" wire:navigate>
                                        <flux:badge color="purple" size="sm">{{ $manutencao->plan->name }}</flux:badge>
                                    </a>
                                @else
                                    <span class="text-zinc-400">Sem plano</span>
                                @endif
                            </dd>
                        </div>

                        <div class="flex justify-between gap-2">
                            <dt class="text-zinc-400">Agendada</dt>
                            <dd class="font-medium">
                                {{ $manutencao->scheduled_at?->format('d/m/Y') ?? '—' }}
                            </dd>
                        </div>

                        @if($manutencao->done_at)
                            <div class="flex justify-between gap-2">
                                <dt class="text-zinc-400">Concluída</dt>
                                <dd class="font-medium">{{ $manutencao->done_at->format('d/m/Y H:i') }}</dd>
                            </div>
                        @endif

                        {{--

                       <div class="flex justify-between gap-2">
                            <dt class="text-zinc-400">Criada por</dt>
                            <dd class="font-medium">{{ $manutencao->createdBy->name }}</dd>
                        </div>

                        --}}

                        <flux:separator variant="subtle" />


                    </dl>
                </flux:card>

                {{-- Ações --}}
                <flux:card class="space-y-4" wire:key="card-acoes-{{ $manutencao->id }}">
                    <flux:heading size="md">Ações</flux:heading>

                    <div class="flex flex-col gap-2" wire:key="container-botoes-{{ $manutencao->id }}">

                        @if($manutencao->status === 'pending')
                            <div class="flex flex-col gap-2" wire:key="status-pending-block-{{ $manutencao->id }}">
                                <flux:button wire:key="btn-iniciar-pending-{{ $manutencao->id }}" wire:click="updateStatus('in_progress')" variant="filled" icon="play" class="w-full justify-start">
                                    Iniciar
                                </flux:button>
                                <flux:button wire:key="btn-concluir-pending-{{ $manutencao->id }}" wire:click="updateStatus('done')" variant="primary" icon="check" class="w-full justify-start">
                                    Marcar como Concluída
                                </flux:button>
                                <flux:button wire:key="btn-cancelar-pending-{{ $manutencao->id }}" wire:click="updateStatus('cancelled')" variant="danger" icon="x-mark" class="w-full justify-start">
                                    Cancelar
                                </flux:button>
                            </div>

                        @elseif($manutencao->status === 'in_progress')
                            <div class="flex flex-col gap-2" wire:key="status-inprogress-block-{{ $manutencao->id }}">
                                <flux:button wire:key="btn-concluir-progress-{{ $manutencao->id }}" wire:click="updateStatus('done')" variant="primary" icon="check" class="w-full justify-start">
                                    Marcar como Concluída
                                </flux:button>
                                <flux:button wire:key="btn-cancelar-progress-{{ $manutencao->id }}" wire:click="updateStatus('cancelled')" variant="danger" icon="x-mark" class="w-full justify-start">
                                    Cancelar
                                </flux:button>
                            </div>

                        @elseif(in_array($manutencao->status, ['done', 'cancelled']))
                            <div class="flex flex-col gap-2" wire:key="status-closed-block-{{ $manutencao->id }}">
                                <flux:button wire:key="btn-reabrir-closed-{{ $manutencao->id }}" wire:click="updateStatus('pending')" variant="subtle" icon="arrow-uturn-left" class="w-full justify-start">
                                    Reabrir como Pendente
                                </flux:button>
                            </div>
                        @endif

                        <flux:separator variant="subtle" class="my-1" wire:key="separator-{{ $manutencao->id }}" />

                        <flux:button wire:click="openModal" wire:navigate
                            class="w-full justify-start" variant="filled" icon="pencil"
                        >
                            Editar
                        </flux:button>

                        <flux:button wire:key="btn-apagar-{{ $manutencao->id }}"
                                     wire:click="delete"
                                     wire:confirm="Tem a certeza que quer apagar esta manutenção?"
                                     variant="danger" icon="trash" class="w-full justify-start">
                            Apagar
                        </flux:button>
                    </div>
                </flux:card>

            </div>
        </div>

    </flux:main>
</div>

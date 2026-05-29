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
};
?>

<div>
    <flux:main container class="space-y-6">

        {{-- Cabeçalho --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

            <div class="flex items-center gap-3">
                <flux:button @click="history.back()" icon="arrow-left" variant="subtle" size="sm" />
                <div>
                    <flux:heading size="xl" level="1">{{ $plano_manutencao->name }}</flux:heading>
                    <flux:text size="sm" class="text-zinc-400 mt-0.5">
                        <a href="{{ route('resource.show', $plano_manutencao->resource) }}" wire:navigate class="hover:underline">
                            Recurso: {{ $plano_manutencao->resource->name }}
                        </a>
                    </flux:text>
                </div>
            </div>

            <div class="flex items-center gap-2">

                <div class="flex items-center gap-2 bg-zinc-50 dark:bg-zinc-900/50 px-3 py-1.5 rounded-lg border border-zinc-200/60 dark:border-zinc-800/60 mr-2">
                    <flux:text size="xs" class="text-zinc-500 font-medium uppercase tracking-wider">Total:</flux:text>
                    <flux:text size="xs" class=" font-medium uppercase tracking-wider">{{ number_format($plano_manutencao->estimated_cost, 2, ',', '.') }}€</flux:text>
                </div>


                <flux:button
                    wire:click="delete"
                    wire:confirm="Tem a certeza que quer apagar este plano? Todas as manutenções associadas serão também apagadas."
                    variant="danger" icon="trash" class="w-full justify-start">
                    Apagar
                </flux:button>


                <flux:button @click="$dispatch('salvar-tudo')" type="button" variant="primary">
                    Atualizar Tudo
                </flux:button>
            </div>
        </div>

        <flux:separator variant="subtle" />

        <livewire:manutencoes.planos.modal :plano="$plano_manutencao" />

        <livewire:manutencoes.planos.tarefas_modal :plano="$plano_manutencao" />

        <livewire:manutencoes.planos.pecas_modal :plano="$plano_manutencao" />

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Coluna principal --}}
            <div class="lg:col-span-full space-y-6">

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
                                    <th class="pb-2 pr-4">Estado</th>
                                    <th class="pb-2 text-right">Custo</th>
                                    <th class="pb-2"></th>
                                </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @foreach($plano_manutencao->maintenances->sortByDesc('scheduled_at') as $manutencao)
                                    @php
                                        $badge = $manutencao->status_badge;
                                        $custoReal = $manutencao->parts->sum(fn($p) => $p->pivot->quantity * $p->pivot->unit_cost_at_time);
                                    @endphp
                                    <tr>
                                        <td class="py-2 pr-4 text-zinc-500">
                                            {{ $manutencao->scheduled_at?->format('d/m/Y') ?? '—' }}
                                        </td>

                                        <td class="py-2 pr-4">
                                            <flux:badge color="{{ $badge['color'] }}" icon="{{ $badge['icon'] }}" size="sm">
                                                {{ $badge['label'] }}
                                            </flux:badge>
                                        </td>
                                        <td class="py-2 text-right font-semibold">
                                            @if($custoReal > 0)
                                                {{ number_format($custoReal, 2, ',', '.') }}€
                                            @else
                                                <span class="text-zinc-400">—</span>
                                            @endif
                                        </td>
                                        <td class="py-2 pl-4">
                                            <flux:button
                                                variant="subtle"
                                                size="sm"
                                                icon="chevron-right"
                                                href="{{ route('manutencoes.show', $manutencao) }}"
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

        </div>

    </flux:main>
</div>

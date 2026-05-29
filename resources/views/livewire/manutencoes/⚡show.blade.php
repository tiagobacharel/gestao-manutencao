<?php

use App\Models\Maintenance;
use Livewire\Volt\Component;

new class extends Component {

    public Maintenance $manutencao;

    public function rendering($view): void
    {
        $view->layoutData(['title' => "Manutenção #{$this->manutencao->id}"]);
        $this->manutencao->load(['resource', 'plan.planParts.part', 'parts']);
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


};
?>

<div>
    <flux:main container class="space-y-6">

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-zinc-100 dark:border-zinc-800 pb-5">

            <div class="flex items-center gap-4">
                <flux:button @click="history.back()" icon="arrow-left" variant="subtle" size="sm" />
                <div class="space-y-0.5">
                    <flux:heading size="xl" level="1">
                        {{ $manutencao->resource->name }}
                    </flux:heading>
                    @if($manutencao->plan)
                        <flux:text size="sm">
                            Plano: <a href="/planos_manutencoes/{{$manutencao->plan->id}}" class="text-purple-600 dark:text-purple-400 font-medium hover:underline">{{ $manutencao->plan->name }}</a>
                        </flux:text>
                    @endif
                </div>
            </div>

            <div class="flex flex-col items-stretch gap-2 sm:flex-row sm:items-center sm:justify-end">

                <div class="flex items-center gap-2 bg-zinc-50 dark:bg-zinc-900/50 px-3 py-1.5 rounded-lg border border-zinc-200/60 dark:border-zinc-800/60 mr-2">
                    <flux:text size="xs" class="text-zinc-500 font-medium uppercase tracking-wider">Total:</flux:text>
                    <flux:text size="xs" class=" font-medium uppercase tracking-wider">{{ number_format($manutencao->total_cost, 2, ',', '.') }} €</flux:text>
                </div>

                @if($manutencao->plan && $manutencao->plan->planParts->isNotEmpty() && $manutencao->parts->isNotEmpty())
                    @php $desvio = $manutencao->cost_deviation; @endphp
                    <div class="flex items-center gap-2 bg-zinc-50 dark:bg-zinc-900/50 px-3 py-1.5 rounded-lg border border-zinc-200/60 dark:border-zinc-800/60 mr-2">
                        <flux:text size="xs" class="text-zinc-500 font-medium uppercase tracking-wider">Desvio:</flux:text>
                        @if($desvio > 0.01)
                            <flux:badge color="red" icon="arrow-trending-up" size="sm">
                                +{{ number_format($desvio, 2, ',', '.') }} €
                            </flux:badge>
                        @elseif($desvio < -0.01)
                            <flux:badge color="green" icon="arrow-trending-down" size="sm">
                                {{ number_format($desvio, 2, ',', '.') }} €
                            </flux:badge>
                        @else
                            <flux:badge color="zinc" icon="minus" size="sm">Sem desvio</flux:badge>
                        @endif
                    </div>
                @endif

                <div class="flex items-center gap-2">
                    <flux:button wire:key="btn-apagar-{{ $manutencao->id }}"
                                 wire:click="delete"
                                 wire:confirm="Tem a certeza que quer apagar esta manutenção?"
                                 variant="danger" icon="trash">
                        Apagar
                    </flux:button>

                    <flux:button @click="$dispatch('salvar-tudo')" type="button" variant="primary">
                        Atualizar Tudo
                    </flux:button>
                </div>

            </div>
        </div>


        <flux:separator variant="subtle" />



        <livewire:manutencoes.modal :manutencao="$manutencao" />

        <livewire:manutencoes.tarefas_modal :manutencao="$manutencao" />

        <livewire:manutencoes.pecas_modal :manutencao="$manutencao" />




    </flux:main>
</div>

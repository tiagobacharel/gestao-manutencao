<?php

use App\Models\Maintenance;
use App\Models\Resource;
use Livewire\Volt\Component;

new class extends Component {

    public Resource $recurso;

    public function rendering($view): void
    {
        $view->layoutData(['title' => $this->recurso->name]);
        $this->recurso->loadMissing('photos');
    }

    public function with(): array
    {
        return [
            'manutencoes' => Maintenance::where('resource_id', $this->recurso->id)
                ->with(['plan', 'parts'])
                ->latest('scheduled_at')
                ->limit(10)
                ->get(),
        ];
    }

    public function activate(){
        $this->recurso->status = 'active';
        $this->recurso->save();
    }

    public function deactivate(){
        $this->recurso->status = 'inactive';
        $this->recurso->save();
    }

    public bool $showModal = false;

    public function openModal()
    {
        $this->showModal = true;
    }

    public function delete()
    {
        $this->recurso->delete();

        return $this->redirect('/recursos', navigate: true);
    }


};
?>

<div>
    <div>
        <flux:main container class="space-y-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <flux:button icon="arrow-left" variant="subtle" size="sm" href="{{ route('recursos.index') }}" wire:navigate label="Voltar" />
                    <div>
                        <flux:heading size="xl" level="1">{{ $recurso->name }}</flux:heading>
                    </div>
                </div>

                <div>
                    @if($recurso->status === 'active')
                        <flux:badge color="green" icon="check-circle" size="lg">Operacional</flux:badge>
                    @else
                        <flux:badge color="red" icon="x-circle" size="lg">Inativo / Parado</flux:badge>
                    @endif
                </div>
            </div>

            <flux:separator variant="subtle" />

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <div class="lg:col-span-2 space-y-6">
                    <flux:card class="space-y-4">
                        <flux:heading size="lg">Descrição do Equipamento</flux:heading>
                        <flux:text class="text-base leading-relaxed text-zinc-700 dark:text-zinc-300 whitespace-pre-wrap">
                            {{ $recurso->description ?? 'Nenhuma descrição detalhada fornecida para este recurso.' }}
                        </flux:text>
                    </flux:card>

                    <x-recursos.resource-photos :photos="$recurso->photos" />

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <flux:card class="flex items-start gap-4">
                            <div class="p-3 rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-950 dark:text-primary-400">
                                <flux:icon name="map-pin" variant="outline" class="w-6 h-6" />
                            </div>
                            <div>
                                <flux:text size="sm" class="font-medium text-zinc-400">Localização na Fábrica</flux:text>
                                <flux:heading size="md" class="mt-1">{{ $recurso->location }}</flux:heading>
                            </div>
                        </flux:card>

                        <flux:card class="flex items-start gap-4">
                            <div class="p-3 rounded-lg bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                                <flux:icon name="briefcase" variant="outline" class="w-6 h-6" />
                            </div>
                            <div>
                                <flux:text size="sm" class="font-medium text-zinc-400">Secção / Departamento</flux:text>
                                <flux:heading size="md" class="mt-1">{{ $recurso->section }}</flux:heading>
                            </div>
                        </flux:card>
                    </div>

                    {{-- Histórico de manutenções --}}
                    <flux:card class="space-y-4">
                        <div class="flex items-center justify-between">
                            <flux:heading size="md">Histórico de Manutenções</flux:heading>
                            <flux:button variant="subtle" size="sm" icon="arrow-top-right-on-square"
                                         href="{{ route('manutencoes.index') }}" wire:navigate>
                                Ver todas
                            </flux:button>
                        </div>

                        @if($manutencoes->isEmpty())
                            <flux:text class="text-zinc-400 text-sm">
                                Nenhuma manutenção registada para este equipamento.
                            </flux:text>
                        @else
                            <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @foreach($manutencoes as $m)
                                    @php
                                        $badge = match($m->status) {
                                            'done'        => ['color' => 'green',  'icon' => 'check-circle', 'label' => 'Concluída'],
                                            'in_progress' => ['color' => 'blue',   'icon' => 'wrench',       'label' => 'Em Progresso'],
                                            'cancelled'   => ['color' => 'red',    'icon' => 'x-circle',     'label' => 'Cancelada'],
                                            default       => ['color' => 'yellow', 'icon' => 'clock',        'label' => 'Pendente'],
                                        };
                                    @endphp
                                    <a href="{{ route('manutencoes.show', $m) }}" wire:navigate
                                       class="flex items-center justify-between py-3 gap-4 hover:bg-zinc-50 dark:hover:bg-zinc-800 -mx-1 px-1 rounded-lg transition-colors group">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <flux:badge color="{{ $badge['color'] }}" icon="{{ $badge['icon'] }}" size="sm">
                                                {{ $badge['label'] }}
                                            </flux:badge>
                                            <div class="min-w-0">
                                                <flux:text size="sm" class="font-medium truncate">
                                                    {{ $m->scheduled_at?->format('d/m/Y') ?? '—' }}
                                                </flux:text>
                                                @if($m->plan)
                                                    <flux:text size="xs" class="text-zinc-400 truncate">{{ $m->plan->name }}</flux:text>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3 shrink-0">
                                            @if($m->parts->count())
                                                <flux:badge color="zinc" size="sm">{{ $m->parts->count() }} peça(s)</flux:badge>
                                            @endif
                                            @if($m->cost !== null)
                                                <span class="text-sm font-semibold">
                                                    {{ number_format($m->cost, 2, ',', '.') }} €
                                                </span>
                                            @endif
                                            <flux:icon name="chevron-right" variant="micro"
                                                       class="text-zinc-300 group-hover:text-zinc-500 transition-colors" />
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </flux:card>
                </div>

                <div class="space-y-6">
                    <flux:card class="space-y-4">
                        <flux:heading size="md">Ações de Manutenção</flux:heading>
                        <flux:text size="sm">Efetue operações diretas sobre este recurso do sistema.</flux:text>

                        <div class="flex flex-col gap-2">
                            <flux:button variant="primary" icon="wrench" class="w-full justify-start">
                                Agendar Manutenção
                            </flux:button>

                            <flux:button wire:click="openModal" variant="filled" icon="pencil-square" class="w-full justify-start" >
                                Editar
                            </flux:button>

                            <flux:button wire:click="delete" variant="danger" icon="trash" class="w-full justify-start" >
                                Apagar
                            </flux:button>

                            <flux:separator class="my-2" variant="subtle" />

                            @if($recurso->status === 'active')
                                <flux:button wire:key="btn-deactivate-{{ $recurso->id }}" variant="danger" icon="power" class="w-full justify-start" wire:click="deactivate">
                                    Marcar como Inativo
                                </flux:button>
                            @else
                                <flux:button wire:key="btn-activate-{{ $recurso->id }}" variant="primary" icon="power" class="w-full justify-start" wire:click="activate">
                                    Ativar Equipamento
                                </flux:button>
                            @endif
                        </div>
                    </flux:card>
                </div>
            </div>
        </flux:main>
    </div>

    @if($showModal)
        <livewire:recursos.modal :recurso="$recurso" />
    @endif
</div>

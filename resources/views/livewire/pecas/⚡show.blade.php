<?php

use App\Models\Part;
use Livewire\Volt\Component;

new class extends Component {

    public Part $part;
    public string $title = '';

    public function mount(Part $part): void
    {
        $this->part = $part;
        $this->title = "Peça #{$part->reference}";
    }

    public function rendering($view): void
    {
        $view->layoutData(['title' => $this->title]);
    }

    public function delete(): void
    {
        $this->part->delete();
        $this->redirect(route('pecas.index'), navigate: true);
    }

    public bool $showModal = false;

    public function openModal() { $this->showModal = true; }
};
?>

<div>
    <flux:main container class="space-y-6">

        {{-- Cabeçalho --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <flux:button href="{{ route('pecas.index') }}" wire:navigate icon="arrow-left" variant="subtle" size="sm"
                />
                <div>
                    <flux:heading size="xl" level="1">
                        {{ $part->name }}
                    </flux:heading>
                    <flux:text size="sm" class="text-zinc-400 mt-0.5">
                        Ref. <span class="font-mono">{{ $part->reference }}</span>
                    </flux:text>
                </div>
            </div>

            @php
                $stockBadge = $part->stock_badge;
            @endphp

            <flux:badge :color="$stockBadge['color']" :icon="$stockBadge['icon']" size="lg">
                {{ $stockBadge['label'] }}
            </flux:badge>
        </div>

        <flux:separator variant="subtle" />

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Coluna principal --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Descrição --}}
                @if($part->description)
                    <flux:card class="space-y-2">
                        <flux:heading size="md">Descrição</flux:heading>
                        <flux:text class="text-base leading-relaxed text-zinc-700 dark:text-zinc-300 whitespace-pre-wrap">
                            {{ $part->description }}
                        </flux:text>
                    </flux:card>
                @endif

                {{-- Informação de stock --}}
                <flux:card class="space-y-4">
                    <flux:heading size="md">Stock</flux:heading>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1 p-4 rounded-lg bg-zinc-50 dark:bg-zinc-800">
                            <flux:text size="sm" class="text-zinc-400">Quantidade atual</flux:text>
                            <span class="text-2xl font-bold">{{ $part->stock_current }}</span>
                            <flux:badge color="{{ $stockBadge['color'] }}" size="sm" class="w-fit">{{ $stockBadge['label'] }}</flux:badge>
                        </div>

                        <div class="flex flex-col gap-1 p-4 rounded-lg bg-zinc-50 dark:bg-zinc-800">
                            <flux:text size="sm" class="text-zinc-400">Valor em stock</flux:text>
                            <span class="text-2xl font-bold">
                                {{ number_format($part->stock_value, 2, ',', '.') }} €
                            </span>
                            <flux:text size="sm" class="text-zinc-400">
                                {{ $part->stock_current }} × {{ number_format($part->current_unit_cost, 2, ',', '.') }} €
                            </flux:text>
                        </div>
                    </div>
                </flux:card>

            </div>

            {{-- Coluna lateral --}}
            <div class="space-y-6">

                {{-- Detalhes --}}
                <flux:card class="space-y-4">
                    <flux:heading size="md">Detalhes</flux:heading>

                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between gap-2">
                            <dt class="text-zinc-400">Nome</dt>
                            <dd class="font-medium text-right">{{ $part->name }}</dd>
                        </div>

                        <div class="flex justify-between gap-2">
                            <dt class="text-zinc-400">Referência</dt>
                            <dd class="font-mono font-medium text-right">{{ $part->reference }}</dd>
                        </div>

                        <flux:separator variant="subtle" />

                        <div class="flex justify-between gap-2">
                            <dt class="text-zinc-400">Custo unitário</dt>
                            <dd class="font-medium text-right">
                                {{ number_format($part->current_unit_cost, 2, ',', '.') }} €
                            </dd>
                        </div>

                        <div class="flex justify-between gap-2">
                            <dt class="text-zinc-400">Stock atual</dt>
                            <dd class="font-medium text-right">{{ $part->stock_current }} un.</dd>
                        </div>

                        <flux:separator variant="subtle" />

                        <div class="flex justify-between gap-2">
                            <dt class="text-zinc-400">Criada em</dt>
                            <dd class="font-medium text-right">
                                {{ $part->created_at?->format('d/m/Y') ?? '—' }}
                            </dd>
                        </div>

                        <div class="flex justify-between gap-2">
                            <dt class="text-zinc-400">Atualizada em</dt>
                            <dd class="font-medium text-right">
                                {{ $part->updated_at?->format('d/m/Y') ?? '—' }}
                            </dd>
                        </div>
                    </dl>
                </flux:card>

                {{-- Ações --}}
                <flux:card class="space-y-4">
                    <flux:heading size="md">Ações</flux:heading>

                    <div class="flex flex-col gap-2">
                        <flux:button
                            variant="filled"
                            icon="pencil"
                            wire:click="openModal"
                            wire:navigate
                            class="w-full justify-start"
                        >
                            Editar
                        </flux:button>

                        <flux:separator variant="subtle" class="my-1" />

                        <flux:button
                            wire:click="delete"
                            wire:confirm="Tem a certeza que quer apagar esta peça?"
                            variant="danger"
                            icon="trash"
                            class="w-full justify-start"
                        >
                            Apagar
                        </flux:button>
                    </div>
                </flux:card>

            </div>
        </div>
        @if($showModal)
            <livewire:pecas_modal :part="$part" />
        @endif

    </flux:main>
</div>

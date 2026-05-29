<?php

use App\Models\Part;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public string $sortBy = 'created_at';
    public string $sortDir = 'desc';

    public function rendering($view): void
    {
        $view->layoutData(['title' => 'Peças']);
    }

    public function updated($propertyName): void
    {
        if (in_array($propertyName, ['search'])) {
            $this->resetPage();
        }
    }

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'asc';
        }
    }

    protected mixed $partsCache = null;

    public function with(): array
    {
        if ($this->partsCache == null) {
            $this->partsCache = Part::query()
                ->when($this->search, fn($q) => $q->where(fn($sub) => $sub
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('reference', 'like', "%{$this->search}%")
                    ->orWhere('description', 'like', "%{$this->search}%")
                ))
                ->orderBy($this->sortBy, $this->sortDir)
                ->paginate(13);
        }

        return [
            'configFiltros' => [
                [
                    'type' => 'text',
                    'model' => 'search',
                    'placeholder' => 'Procurar peças...',
                ],
            ],

            'valoresAtuais' => [
                'search' => $this->search,
            ],

            'parts' => $this->partsCache,


        ];
    }

    public bool $showModal = false;

    public function openModal() { $this->showModal = true; }
};
?>
<div>
    <flux:main container class="space-y-6">

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <flux:heading size="xl" level="1">Peças ({{ $parts->total() }})</flux:heading>
            <flux:button variant="primary" icon="plus" wire:click="openModal" wire:navigate class="w-full sm:w-auto">
                Nova Peça
            </flux:button>
        </div>

        <flux:separator variant="subtle" />

        @if($showModal)
            <livewire:pecas_modal />
        @endif

        <x-filtros-bar :config="$configFiltros" :valores="$valoresAtuais" />

        {{-- TELEMÓVEL --}}
        <div class="space-y-3 md:hidden ">
            @forelse($parts as $part)
                <div class="p-4 rounded-xl bg-white dark:bg-zinc-900/50 border border-zinc-200/80 dark:border-zinc-800/80 shadow-sm space-y-3">

                    <div class="flex items-center justify-between gap-2">
                        <span class="font-mono text-xs text-zinc-400">{{ $part->reference }}</span>

                        <flux:button
                            variant="subtle"
                            size="sm"
                            icon="pencil-square"
                            href="{{ route('pecas.show', $part) }}"
                            wire:navigate
                        />
                    </div>

                    <div class="space-y-1">
                        <div class="font-medium text-sm text-zinc-900 dark:text-white">
                            {{ $part->name }}
                        </div>
                        @if($part->description)
                            <p class="text-xs text-zinc-500 line-clamp-2">
                                {{ $part->description }}
                            </p>
                        @endif
                    </div>

                    <div class="flex items-center justify-between pt-2 border-t border-zinc-100 dark:border-zinc-800/60">
                        @php
                            $stockColor = match(true) {
                                $part->stock_current === 0 => 'red',
                                $part->stock_current <= 5  => 'yellow',
                                default                    => 'green',
                            };
                        @endphp
                        <div class="flex items-center gap-1.5">
                            <span class="text-[11px] text-zinc-400">Stock:</span>
                            <flux:badge color="{{ $stockColor }}" size="sm">
                                {{ $part->stock_current }}
                            </flux:badge>
                        </div>

                        <div class="text-sm font-medium text-zinc-900 dark:text-white">
                            {{ number_format($part->current_unit_cost, 2, ',', '.') }} €
                        </div>
                    </div>

                </div>
            @empty
                <div class="text-center py-12 border border-dashed rounded-xl border-zinc-200 dark:border-zinc-800 bg-zinc-50/30 dark:bg-zinc-900/10">
                    <flux:icon name="cube" class="size-8 mx-auto mb-2 opacity-40 text-zinc-400" />
                    <p class="text-sm text-zinc-400">Nenhuma peça encontrada.</p>
                </div>
            @endforelse
        </div>

        {{-- COMPUTADOR  --}}
        <flux:card class="p-0 overflow-hidden hidden md:block border-zinc-200/80 dark:border-zinc-800/80 shadow-sm">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column
                        sortable
                        :sorted="$sortBy === 'reference'"
                        :direction="$sortDir"
                        wire:click="sort('reference')"
                    >Referência</flux:table.column>

                    <flux:table.column
                        sortable
                        :sorted="$sortBy === 'name'"
                        :direction="$sortDir"
                        wire:click="sort('name')"
                    >Nome</flux:table.column>

                    <flux:table.column>Descrição</flux:table.column>

                    <flux:table.column
                        sortable
                        :sorted="$sortBy === 'stock_current'"
                        :direction="$sortDir"
                        wire:click="sort('stock_current')"
                    >Stock</flux:table.column>

                    <flux:table.column
                        sortable
                        :sorted="$sortBy === 'current_unit_cost'"
                        :direction="$sortDir"
                        wire:click="sort('current_unit_cost')"
                    >Custo Unit.</flux:table.column>

                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach($parts as $part)
                        <flux:table.row :key="$part->id">

                            <flux:table.cell>
                                <span class="font-mono text-xs text-zinc-400">{{ $part->reference }}</span>
                            </flux:table.cell>

                            <flux:table.cell>
                                <span class="font-medium text-sm">{{ $part->name }}</span>
                            </flux:table.cell>

                            <flux:table.cell>
                                @if($part->description)
                                    <span class="text-sm text-zinc-500 truncate block max-w-xs" title="{{ $part->description }}">
                                        {{ $part->description }}
                                    </span>
                                @else
                                    <span class="text-xs text-zinc-400">—</span>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>
                                @php
                                    $stockColor = match(true) {
                                        $part->stock_current === 0 => 'red',
                                        $part->stock_current <= 5  => 'yellow',
                                        default                    => 'green',
                                    };
                                @endphp
                                <flux:badge color="{{ $stockColor }}" size="sm">
                                    {{ $part->stock_current }}
                                </flux:badge>
                            </flux:table.cell>

                            <flux:table.cell>
                                <span class="text-sm font-medium">
                                    {{ number_format($part->current_unit_cost, 2, ',', '.') }} €
                                </span>
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:button
                                    variant="subtle"
                                    size="sm"
                                    icon="pencil-square"
                                    href="{{ route('pecas.show', $part) }}"
                                    wire:navigate
                                />
                            </flux:table.cell>

                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>

        {{ $parts->links('components.pagination', ['color' => 'primary']) }}

    </flux:main>
</div>


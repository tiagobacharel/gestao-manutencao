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

    public function with(): array
    {
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

            'parts' => Part::query()
                ->when($this->search, fn($q) => $q->where(fn($sub) => $sub
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('reference', 'like', "%{$this->search}%")
                    ->orWhere('description', 'like', "%{$this->search}%")
                ))
                ->orderBy($this->sortBy, $this->sortDir)
                ->paginate(13),
        ];
    }

    public bool $showModal = false;

    public function openModal() { $this->showModal = true; }
};
?>

<div>
    <flux:main container class="space-y-6">

        {{-- Header --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <flux:heading size="xl" level="1">Peças</flux:heading>
            <flux:button variant="primary" icon="plus"  wire:click="openModal" wire:navigate>
                Nova Peça
            </flux:button>
        </div>

        <flux:separator variant="subtle" />

        @if($showModal)
            <livewire:pecas.modal />
        @endif

        <x-filtros-bar :config="$configFiltros" :valores="$valoresAtuais" />

        {{-- Tabela --}}
        <flux:card class="p-0 overflow-hidden">
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
                    @forelse($parts as $part)
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
                                    icon="eye"
                                    href="{{ route('pecas.show', $part) }}"
                                    wire:navigate
                                />
                            </flux:table.cell>

                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6" class="text-center py-12 text-zinc-400">
                                <flux:icon name="cube" class="size-8 mx-auto mb-2 opacity-40" />
                                <p>Nenhuma peça encontrada.</p>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>

        {{ $parts->links('components.pagination', ['color' => 'primary']) }}

    </flux:main>
</div>

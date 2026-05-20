<?php

use App\Models\MaintenancePlan;
use App\Models\Resource;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public string $status = '';
    public $resource_id = '';
    public string $sortBy = 'name';
    public string $sortDir = 'asc';

    public function rendering($view): void
    {
        $view->layoutData(['title' => 'Planos de Manutenção']);
    }

    public function updated($propertyName): void
    {
        if (in_array($propertyName, ['search', 'status', 'resource_id'])) {
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

    public bool $showModal = false;

    public function openModal(): void
    {
        $this->showModal = true;
    }

    public function with(): array
    {
        return [
            'configFiltros' => [
                [
                    'type'        => 'text',
                    'model'       => 'search',
                    'placeholder' => 'Procurar planos...',
                ],
                [
                    'type'    => 'select',
                    'model'   => 'resource_id',
                    'label'   => 'Todos os recursos',
                    'options' => Resource::whereHas('maintenancePlans')
                        ->pluck('name', 'id')
                        ->toArray(),
                ],
                [
                    'type'    => 'select',
                    'model'   => 'status',
                    'label'   => 'Todos os estados',
                    'options' => [
                        '1' => 'Ativo',
                        '0' => 'Inativo',
                    ],
                ],
            ],

            'valoresAtuais' => [
                'search'      => $this->search,
                'status'      => $this->status,
                'resource_id' => $this->resource_id,
            ],

            'planos' => MaintenancePlan::query()
                ->with(['resource', 'planParts.part'])
                ->when($this->search, fn($q) => $q->where(function ($sub) {
                    $sub->where('name', 'like', "%{$this->search}%")
                        ->orWhere('description', 'like', "%{$this->search}%")
                        ->orWhereHas('resource', fn($r) => $r->where('name', 'like', "%{$this->search}%"));
                }))
                ->when($this->resource_id, fn($q) => $q->where('resource_id', $this->resource_id))
                ->when($this->status !== '', fn($q) => $q->where('is_active', (bool) $this->status))
                ->orderBy($this->sortBy, $this->sortDir)
                ->paginate(13),
        ];
    }
};
?>

<div>
    <flux:main container class="space-y-6">

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <flux:heading size="xl" level="1">Planos de Manutenção</flux:heading>
            <flux:button variant="primary" icon="plus" wire:click="openModal" wire:navigate>
                Novo Plano
            </flux:button>
        </div>

        <flux:separator variant="subtle" />

        @if($showModal)
            <livewire:manutencoes.planos.modal />
        @endif

        <x-filtros-bar :config="$configFiltros" :valores="$valoresAtuais" />

        <flux:card class="p-0 overflow-hidden">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column
                        sortable
                        :sorted="$sortBy === 'name'"
                        :direction="$sortDir"
                        wire:click="sort('name')"
                    >Nome</flux:table.column>
                    <flux:table.column>Recurso</flux:table.column>
                    <flux:table.column
                        sortable
                        :sorted="$sortBy === 'interval_days'"
                        :direction="$sortDir"
                        wire:click="sort('interval_days')"
                    >Intervalo</flux:table.column>
                    <flux:table.column>Peças previstas</flux:table.column>
                    <flux:table.column
                        sortable
                        :sorted="$sortBy === 'started_at'"
                        :direction="$sortDir"
                        wire:click="sort('started_at')"
                    >Início</flux:table.column>
                    <flux:table.column>Estado</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse($planos as $plano)
                        <flux:table.row :key="$plano->id">

                            <flux:table.cell>
                                <div class="flex flex-col">
                                    <span class="font-medium text-sm">{{ $plano->name }}</span>
                                    @if($plano->description)
                                        <span class="text-xs text-zinc-400 truncate max-w-48">{{ $plano->description }}</span>
                                    @endif
                                </div>
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="flex flex-col">
                                    <a href="{{ route('resource.show', $plano->resource) }}" wire:navigate>
                                        <span class="text-sm">{{ $plano->resource->name }}</span>
                                    </a>
                                    <span class="text-xs text-zinc-400">{{ $plano->resource->location }}</span>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:badge color="zinc" size="sm" icon="clock">
                                    {{ $plano->interval_value }}
                                    {{
                                        match($plano->interval_unit) {
                                            'day'   => $plano->interval_value == 1 ? 'dia' : 'dias',
                                            'month' => $plano->interval_value == 1 ? 'mês' : 'meses',
                                            'year'  => $plano->interval_value == 1 ? 'ano' : 'anos',
                                            default => $plano->interval_unit
                                        }
                                    }}
                                </flux:badge>
                            </flux:table.cell>

                            <flux:table.cell>
                                @if($plano->planParts->count())
                                    <flux:badge color="purple" size="sm">
                                        {{ $plano->planParts->count() }} peça(s)
                                    </flux:badge>
                                @else
                                    <span class="text-xs text-zinc-400">—</span>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>
                                <span class="text-sm text-zinc-500">
                                    {{ $plano->started_at?->format('d/m/Y') ?? '—' }}
                                </span>
                            </flux:table.cell>

                            <flux:table.cell>
                                @if($plano->is_active)
                                    <flux:badge color="green" icon="check-circle" size="sm">Ativo</flux:badge>
                                @else
                                    <flux:badge color="zinc" icon="x-circle" size="sm">Inativo</flux:badge>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:button
                                    variant="subtle"
                                    size="sm"
                                    icon="eye"
                                    href="{{ route('planos_manutencoes.show', $plano) }}"
                                    wire:navigate
                                />
                            </flux:table.cell>

                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="7" class="text-center py-12 text-zinc-400">
                                <flux:icon name="clipboard-document-list" class="size-8 mx-auto mb-2 opacity-40" />
                                <p>Nenhum plano encontrado.</p>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>

        {{ $planos->links('components.pagination', ['color' => 'primary']) }}

    </flux:main>
</div>

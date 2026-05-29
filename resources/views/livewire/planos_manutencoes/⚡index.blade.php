<?php

use App\Models\MaintenancePlan;
use App\Models\Resource;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public string $status = '';
    public $resource_id = '';
    public string $sortBy = 'created_at';
    public string $sortDir = 'desc';

    public string $resourceSearch = '';

    public function rendering($view): void
    {
        $view->layoutData(['title' => 'Planos de Manutenção']);
    }

    public function updated($propertyName): void
    {
        if (in_array($propertyName, ['search', 'status', 'resource_id', 'resourceSearch'])) {
            $this->resetPage();

            // Se o utilizador começar a digitar de novo após ter escolhido algo,
            // limpamos o ID antigo para o filtro passar a basear-se no texto escrito
            if ($propertyName === 'resourceSearch' && $this->resource_id) {
                $this->resource_id = '';
            }
        }
    }

    public bool $showModal = false;

    public function openModal(): void
    {
        $this->showModal = true;
    }

    public function selectResource($id, $name): void
    {
        $this->resource_id = $id;
        $this->resourceSearch = $name;
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

    #[Computed]
    public function searchedResources()
    {
        return Resource::whereHas('maintenancePlans')
            ->when($this->resourceSearch && !$this->resource_id, fn($q) => $q->where('name', 'like', "%{$this->resourceSearch}%"))
            ->limit(10)
            ->pluck('name', 'id')
            ->toArray();
    }


    protected mixed $planosCache = null;

    public function with(): array
    {
        if ($this->planosCache == null){
            $this->planosCache = MaintenancePlan::query()
                ->with(['resource', 'planParts.part'])
                ->when($this->search, fn($q) => $q->where(function ($sub) {
                    $sub->where('name', 'like', "%{$this->search}%")
                        ->orWhere('description', 'like', "%{$this->search}%")
                        ->orWhereHas('resource', fn($r) => $r->where('name', 'like', "%{$this->search}%"));
                }))
                ->when($this->resource_id, fn($q) => $q->where('resource_id', $this->resource_id))
                ->when(!$this->resource_id && $this->resourceSearch, fn($q) => $q->whereHas('resource', fn($r) => $r->where('name', 'like', "%{$this->resourceSearch}%")))

                ->when($this->status !== '', fn($q) => $q->where('is_active', (bool) $this->status))
                // No with(), substitui o orderBy simples por:
                ->when(
                    $this->sortBy === 'interval_value',
                    fn($q) => $q->orderByRaw("
                        CASE interval_unit
                            WHEN 'day'   THEN interval_value
                            WHEN 'month' THEN interval_value * 30
                            WHEN 'year'  THEN interval_value * 365
                        END {$this->sortDir}
                    "),
                    fn($q) => $q->orderBy($this->sortBy, $this->sortDir)
                )
                ->paginate(13);
        }

        return [
            'configFiltros' => [
                [
                    'type'        => 'text',
                    'model'       => 'search',
                    'placeholder' => 'Procurar planos...',
                ],
                [
                    'type' => 'custom-dropdown',
                    'model' => 'resource_id',
                    'searchModel' => 'resourceSearch',
                    'label' => 'Todos os recursos',
                    'selectMethod' => 'selectResource',
                    'computedOptions' => $this->searchedResources,
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
                'resourceSearch' => $this->resourceSearch, // Adicionado para controlo seguro na Blade
            ],

            'planos' => $this->planosCache,
        ];
    }
};
?>

<div>
    <flux:main container class="space-y-6">

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <flux:heading size="xl" level="1">Planos de Manutenção ({{ $planos->total() }})</flux:heading>
            <flux:button variant="primary" icon="plus" wire:click="openModal" wire:navigate>
                Novo Plano
            </flux:button>
        </div>

        <flux:separator variant="subtle" />

        @if($showModal)
            <livewire:manutencoes.planos.modal />
        @endif

        <x-filtros-bar :config="$configFiltros" :valores="$valoresAtuais" />

        {{-- 📱 EXIBIÇÃO EM CARTÕES APENAS PARA TELEMÓVEL (md:hidden) --}}
        <div class="space-y-3 md:hidden">
            @forelse($planos as $plano)
                <div class="p-4 rounded-xl bg-white dark:bg-zinc-900/50 border border-zinc-200/80 dark:border-zinc-800/80 shadow-sm space-y-3">

                    {{-- Topo: Nome do Plano e Botão de Editar --}}
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex flex-col min-w-0">
                            <span class="font-medium text-sm text-zinc-900 dark:text-white truncate">{{ $plano->name }}</span>
                            @if($plano->description)
                                <span class="text-xs text-zinc-400 truncate mt-0.5">{{ $plano->description }}</span>
                            @endif
                        </div>

                        <flux:button
                            variant="subtle"
                            size="sm"
                            icon="pencil-square"
                            href="{{ route('planos_manutencoes.show', $plano) }}"
                            wire:navigate
                        />
                    </div>

                    {{-- Centro: Recurso Associado --}}
                    <div class="p-2.5 rounded-lg bg-zinc-50 dark:bg-zinc-800/40 border border-zinc-100 dark:border-zinc-800/30">
                        <div class="text-[10px] text-zinc-400 dark:text-zinc-500 font-bold mb-1 uppercase tracking-wider">Recurso</div>
                        <div class="flex flex-col">
                            <a href="{{ route('resource.show', $plano->resource) }}" wire:navigate class="hover:underline">
                                <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ $plano->resource->name }}</span>
                            </a>
                            <span class="text-xs text-zinc-400 mt-0.5">{{ $plano->resource->location }}</span>
                        </div>
                    </div>

                    {{-- Rodapé: Badges de Intervalo, Peças e Estado --}}
                    <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-zinc-100 dark:border-zinc-800/60">
                        <div class="flex flex-wrap gap-1.5 items-center">
                            {{-- Intervalo --}}
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

                            {{-- Peças --}}
                            @if($plano->planParts->count())
                                <flux:badge color="purple" size="sm">
                                    {{ $plano->planParts->count() }} pçs
                                </flux:badge>
                            @endif
                        </div>

                        {{-- Estado --}}
                        <div>
                            @if($plano->is_active)
                                <flux:badge color="green" icon="check-circle" size="sm">Ativo</flux:badge>
                            @else
                                <flux:badge color="zinc" icon="x-circle" size="sm">Inativo</flux:badge>
                            @endif
                        </div>
                    </div>

                </div>
            @empty
                <div class="text-center py-12 border border-dashed rounded-xl border-zinc-200 dark:border-zinc-800 bg-zinc-50/30 dark:bg-zinc-900/10">
                    <flux:icon name="clipboard-document-list" class="size-8 mx-auto mb-2 opacity-40 text-zinc-400" />
                    <p class="text-sm text-zinc-400">Nenhum plano encontrado.</p>
                </div>
            @endforelse
        </div>

        {{-- 💻 EXIBIÇÃO EM TABELA PURA APENAS PARA COMPUTADOR (hidden md:block) --}}
        <flux:card class="p-0 overflow-hidden hidden md:block border-zinc-200/80 dark:border-zinc-800/80 shadow-sm">
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
                        :sorted="$sortBy === 'interval_value'"
                        :direction="$sortDir"
                        wire:click="sort('interval_value')"
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
                                    {{ $plano->started_at ? date('d/m/Y', strtotime($plano->started_at)) : '—' }}
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
                                    icon="pencil-square"
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

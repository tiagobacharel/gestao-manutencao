<?php

use App\Models\Maintenance;
use App\Models\MaintenancePart;
use App\Models\MaintenancePlan;
use App\Models\Resource;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public string $status = '';
    public string $sortBy = 'scheduled_at';
    public string $sortDir = 'desc';

    // IDs reais usados quando o utilizador clica numa opção
    public $resource_id = '';
    public $plan_id = '';

    // Texto digitado em tempo real nos inputs
    public string $resourceSearch = '';
    public string $planSearch = '';

    public function rendering($view)
    {
        $view->layoutData(['title' => 'Manutenções']);
    }

    // Se qualquer campo de texto ou ID mudar, faz reset à paginação
    public function updated($propertyName): void
    {
        if (in_array($propertyName, ['search', 'status', 'resource_id', 'plan_id', 'resourceSearch', 'planSearch'])) {
            $this->resetPage();

            // Se o utilizador começou a digitar de novo após ter escolhido algo,
            // limpamos o ID antigo para o filtro passar a basear-se no texto escrito
            if ($propertyName === 'resourceSearch' && $this->resource_id) {
                $this->resource_id = '';
            }
            if ($propertyName === 'planSearch' && $this->plan_id) {
                $this->plan_id = '';
            }
        }
    }

    public function selectResource($id, $name): void
    {
        $this->resource_id = $id;
        $this->resourceSearch = $name;
    }

    public function selectPlan($id, $name): void
    {
        $this->plan_id = $id;
        $this->planSearch = $name;
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
        return Resource::whereHas('maintenances')
            ->when($this->resourceSearch && !$this->resource_id, fn($q) => $q->where('name', 'like', "%{$this->resourceSearch}%"))
            ->limit(10)
            ->pluck('name', 'id')
            ->toArray();
    }

    #[Computed]
    public function searchedPlans()
    {
        return MaintenancePlan::whereHas('maintenances')
            ->when($this->planSearch && !$this->plan_id, fn($q) => $q->where('name', 'like', "%{$this->planSearch}%"))
            ->limit(10)
            ->pluck('name', 'id')
            ->toArray();
    }

    public function with(): array
    {
        return [
            'configFiltros' => [
                [
                    'type' => 'custom-dropdown',
                    'model' => 'resource_id',
                    'searchModel' => 'resourceSearch',
                    'label' => 'Todos os recursos',
                    'selectMethod' => 'selectResource',
                    'computedOptions' => $this->searchedResources,
                ],
                [
                    'type' => 'custom-dropdown',
                    'model' => 'plan_id',
                    'searchModel' => 'planSearch',
                    'label' => 'Todos os planos',
                    'selectMethod' => 'selectPlan',
                    'computedOptions' => $this->searchedPlans,
                ],
                [
                    'type' => 'select',
                    'model' => 'status',
                    'label' => 'Todos os estados',
                    'options' => [
                        'done' => 'Concluída',
                        'pending' => 'Pendente',
                        'in_progress' => 'Em progresso',
                        'cancelled' => 'Cancelada',
                    ],
                ],
            ],

            'valoresAtuais' => [
                'search' => $this->search,
                'status' => $this->status,
                'resource_id' => $this->resource_id,
                'plan_id' => $this->plan_id,
                'resourceSearch' => $this->resourceSearch,
                'planSearch' => $this->planSearch,
            ],

            'manutencoes' => Maintenance::query()
                ->with(['resource', 'plan', 'parts'])
                ->when($this->search, fn($q) => $q->where(fn($sub) => $sub->whereHas('resource', fn($r) => $r->where('name', 'like', "%{$this->search}%"))
                    ->orWhere('notes', 'like', "%{$this->search}%")
                ))
                ->when($this->status, fn($q) => $q->where('status', $this->status))

                // SOLUÇÃO: Filtra por ID se já escolheu, ou por texto se estiver apenas a escrever
                ->when($this->resource_id, fn($q) => $q->where('resource_id', $this->resource_id))
                ->when(!$this->resource_id && $this->resourceSearch, fn($q) => $q->whereHas('resource', fn($r) => $r->where('name', 'like', "%{$this->resourceSearch}%")))

                // SOLUÇÃO: Filtra por ID se já escolheu, ou por texto se estiver apenas a escrever
                ->when($this->plan_id, fn($q) => $q->where('maintenance_plan_id', $this->plan_id))
                ->when(!$this->plan_id && $this->planSearch, fn($q) => $q->whereHas('plan', fn($p) => $p->where('name', 'like', "%{$this->planSearch}%")))

                ->when(
                    $this->sortBy === 'cost',
                    fn($q) => $q->orderBy(
                        MaintenancePart::selectRaw('COALESCE(SUM(quantity * unit_cost), 0)')
                            ->whereColumn('maintenance_id', 'maintenances.id'),
                        $this->sortDir
                    ),
                    fn($q) => $q->orderBy($this->sortBy, $this->sortDir)
                )
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
            <flux:heading size="xl" level="1">Manutenções</flux:heading>
            <flux:button variant="primary" icon="plus" wire:click="openModal" wire:navigate>
                Nova Manutenção
            </flux:button>
        </div>

        <flux:separator variant="subtle"/>

        @if($showModal)
            <livewire:manutencoes.modal />
        @endif


        <x-filtros-bar :config="$configFiltros" :valores="$valoresAtuais"/>

        {{-- Tabela --}}
        <flux:card class="p-0 overflow-hidden">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column
                        sortable
                        :sorted="$sortBy === 'scheduled_at'"
                        :direction="$sortDir"
                        wire:click="sort('scheduled_at')"
                    >Data
                    </flux:table.column>
                    <flux:table.column>Recurso</flux:table.column>
                    <flux:table.column>Plano</flux:table.column>
                    <flux:table.column>Estado</flux:table.column>
                    <flux:table.column>Peças</flux:table.column>
                    <flux:table.column
                        sortable
                        :sorted="$sortBy === 'cost'"
                        :direction="$sortDir"
                        wire:click="sort('cost')"
                    >Custo
                    </flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse($manutencoes as $manutencao)
                        <flux:table.row :key="$manutencao->id">

                            <flux:table.cell>
                                <div class="flex flex-col">
                        <span class="text-sm font-medium">
                            {{ $manutencao->scheduled_at?->format('d/m/Y') ?? '—' }}
                        </span>
                                    @if($manutencao->done_at)
                                        <span class="text-xs text-zinc-400">
                                Feita: {{ $manutencao->done_at->format('d/m/Y') }}
                            </span>
                                    @endif
                                </div>
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="flex flex-col">
                                    <a href="{{ route('resource.show', $manutencao->resource) }}" wire:navigate>
                                        <span class="font-medium text-sm">{{ $manutencao->resource->name }}</span>
                                    </a>
                                    <span class="text-xs text-zinc-400">{{ $manutencao->resource->location }}</span>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell>
                                @if($manutencao->plan)
                                    <a href="{{ route('planos_manutencoes.show', $manutencao->plan) }}" wire:navigate>
                                        <flux:badge color="purple" size="sm">{{ $manutencao->plan->name }}</flux:badge>
                                    </a>
                                @else
                                    <span class="text-xs text-zinc-400">Sem plano</span>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>
                                @php
                                    $badge = $manutencao->status_badge;
                                @endphp
                                <flux:badge color="{{ $badge['color'] }}" icon="{{ $badge['icon'] }}" size="sm">
                                    {{ $badge['label'] }}
                                </flux:badge>
                            </flux:table.cell>

                            <flux:table.cell>
                                @if($manutencao->parts->count())
                                    <flux:badge color="zinc" size="sm">{{ $manutencao->parts->count() }} peça(s)
                                    </flux:badge>
                                @else
                                    <span class="text-xs text-zinc-400">—</span>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>
                                @php
                                    $total = $manutencao->total_cost
                                @endphp
                                @if($total > 0)
                                    <span class="text-sm font-medium">
                                        {{ number_format($total, 2, ',', '.') }} €
                                    </span>
                                @else
                                    <span class="text-xs text-zinc-400">—</span>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:button
                                    variant="subtle"
                                    size="sm"
                                    icon="eye"
                                    href="{{ route('manutencoes.show', $manutencao) }}"
                                    wire:navigate
                                />
                            </flux:table.cell>

                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="7" class="text-center py-12 text-zinc-400">
                                <flux:icon name="wrench" class="size-8 mx-auto mb-2 opacity-40"/>
                                <p>Nenhuma manutenção encontrada.</p>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>

        {{ $manutencoes->links('components.pagination', ['color' => 'primary']) }}

    </flux:main>

</div>

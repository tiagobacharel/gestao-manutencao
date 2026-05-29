<?php

use App\Models\Resource;
use Livewire\Volt\Component;
use Livewire\WithPagination;

    new class extends Component {
        use WithPagination;

        public $search = '';
        public $status = '';
        public $location = '';
        public $section = '';
        public string $sortBy = 'created_at';
        public string $sortDir = 'desc';

        public function rendering($view)
        {
            $view->layoutData(['title' => 'Recursos']);
        }

        public array $locationOptions = [];
        public array $sectionOptions = [];

        public function mount(): void
        {
            $this->locationOptions = Resource::distinct()
                ->whereNotNull('location')
                ->where('location', '!=', '')
                ->pluck('location', 'location')
                ->toArray();

            $this->sectionOptions = Resource::distinct()
                ->whereNotNull('section')
                ->where('section', '!=', '')
                ->pluck('section', 'section')
                ->toArray();
        }

        protected mixed $recursosCache = null;

        public function with(): array
        {
            if ($this->recursosCache == null){
                $this->recursosCache = Resource::query()
                    ->when($this->search, fn($q) => $q->where('name', 'like', '%'.$this->search.'%'))
                    ->when($this->status, fn($q) => $q->where('status', $this->status))
                    ->when($this->location, fn($q) => $q->where('location', $this->location))
                    ->when($this->section, fn($q) => $q->where('section', $this->section))
                    ->latest()
                    ->paginate(15);
            }
            return [
                'configFiltros' => [
                    [
                        'type' => 'text',
                        'model' => 'search',
                        'placeholder' => 'Procurar recursos...',
                    ],
                    [
                        'type' => 'select',
                        'model' => 'status',
                        'label' => 'Todos os estados',
                        'options' => [
                            'active' => 'Ativo',
                            'inactive' => 'Inativo',
                        ],
                    ],
                    [
                        'type' => 'select',
                        'model' => 'location',
                        'label' => 'Localização',
                        'options' => $this->locationOptions,
                    ],
                    [
                        'type' => 'select',
                        'model' => 'section',
                        'label' => 'Secção / Dept.',
                        'icon' => 'building-office',
                        'options' => $this->sectionOptions,
                        ],
                ],

                'valoresAtuais' => [
                    'search' => $this->search,
                    'status' => $this->status,
                    'location' => $this->location,
                    'section'  => $this->section,
                ],

                'recursos' => $this->recursosCache,
            ];
        }

        public bool $showModal = false;

        public function openModal() { $this->showModal = true; }

};
?>

<div class="space-y-6">

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" level="1">Recursos ({{ $recursos->total() }})</flux:heading>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="openModal" class="w-full sm:w-auto">
            Criar Recurso
        </flux:button>
    </div>

    <flux:separator variant="subtle" />

    @if($showModal)
        <livewire:recursos.modal />
    @endif

    <x-filtros-bar :config="$configFiltros" :valores="$valoresAtuais" />

    {{-- TELEMÓVEL --}}
    <div class="grid grid-cols-1 gap-4 md:hidden">
        @forelse ($recursos as $recurso)
            <a href="{{ route('resource.show', $recurso) }}" wire:navigate class="group decoration-none block">
                <flux:card class="h-full border-zinc-200 hover:border-primary-500 hover:shadow-lg dark:hover:bg-zinc-800">
                    <div class="flex flex-col h-full">
                        <div class="flex items-start justify-between">
                            <flux:heading size="lg" class="group-hover:text-primary-600 transition-colors">
                                {{ $recurso->name }}
                            </flux:heading>
                            <flux:icon name="pencil-square" class="text-zinc-400 group-hover:text-primary-500 transition-colors" variant="micro"/>
                        </div>

                        <flux:text class="mt-2 line-clamp-2 flex-grow">
                            {{ $recurso->description }}
                        </flux:text>

                        <div class="mt-4 flex items-center gap-4">
                            <flux:badge size="sm" :color="$recurso->status === 'active' ? 'green' : 'red'">
                                {{ $recurso->status === 'active' ? 'Ativo' : 'Inativo' }}
                            </flux:badge>

                            @if($recurso->location)
                                <div class="flex items-center gap-1.5">
                                    <flux:icon name="map-pin" variant="micro" class="text-zinc-400"/>
                                    <flux:text size="sm" class="text-zinc-400">{{ $recurso->location }}</flux:text>
                                </div>
                            @endif

                            @if($recurso->section)
                                <div class="flex items-center gap-1.5">
                                    <flux:icon name="building-office" variant="micro" class="text-zinc-400"/>
                                    <flux:text size="sm" class="text-zinc-400">{{ $recurso->section }}</flux:text>
                                </div>
                            @endif
                        </div>
                    </div>
                </flux:card>
            </a>
        @empty
            <div class="py-12 flex flex-col items-center justify-center border-2 border-dashed border-zinc-200 rounded-xl">
                <flux:icon name="archive-box" class="text-zinc-300 w-12 h-12" />
                <flux:heading class="mt-4">Nenhum recurso encontrado</flux:heading>
                <flux:subheading>Começa por criar o teu primeiro equipamento.</flux:subheading>
            </div>
        @endforelse
    </div>

    {{-- COMPUTADOR --}}
    @if($recursos->isNotEmpty())
        <flux:card class="p-0 overflow-hidden hidden md:block border-zinc-200/80 dark:border-zinc-800/80 shadow-sm">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Nome</flux:table.column>
                    <flux:table.column>Descrição</flux:table.column>
                    <flux:table.column>Localização</flux:table.column>
                    <flux:table.column>Secção</flux:table.column>
                    <flux:table.column>Estado</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach($recursos as $recurso)
                        <flux:table.row :key="$recurso->id">

                            <flux:table.cell>
                                <span class="font-medium text-sm text-zinc-900 dark:text-white">{{ $recurso->name }}</span>
                            </flux:table.cell>

                            <flux:table.cell>
                                @if($recurso->description)
                                    <span class="text-sm text-zinc-500 truncate block max-w-xs" title="{{ $recurso->description }}">
                                        {{ $recurso->description }}
                                    </span>
                                @else
                                    <span class="text-xs text-zinc-400">—</span>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>
                                <span class="text-sm text-zinc-500">{{ $recurso->location ?? '—' }}</span>
                            </flux:table.cell>

                            <flux:table.cell>
                                <span class="text-sm text-zinc-500">{{ $recurso->section ?? '—' }}</span>
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:badge size="sm" :color="$recurso->status === 'active' ? 'green' : 'red'">
                                    {{ $recurso->status === 'active' ? 'Ativo' : 'Inativo' }}
                                </flux:badge>
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:button
                                    variant="subtle"
                                    size="sm"
                                    icon="pencil-square"
                                    href="{{ route('resource.show', $recurso) }}"
                                    wire:navigate
                                />
                            </flux:table.cell>

                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif

    {{ $recursos->links('components.pagination', ['color' => 'primary']) }}
</div>




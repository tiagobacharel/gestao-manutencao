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

        public string $locationSearch = '';
        public string $sectionSearch = '';

        public function selectLocation(?string $id, ?string $label): void
        {
            $this->location = $label ?? '';
            $this->locationSearch = $label ?? '';
        }

        public function selectSection(?string $id, ?string $label): void
        {
            $this->section = $label ?? '';
            $this->sectionSearch = $label ?? '';
        }

        public function mount(): void
        {
        }

        public function updated($propertyName): void
        {
            if (in_array($propertyName, ['search', 'locationSearch', 'sectionSearch', 'status'])) {
                $this->resetPage();
            }
        }

        protected mixed $recursosCache = null;

        public function with(): array
        {
            $locationOptions = once(fn() => Resource::distinct()
                ->whereNotNull('location')
                ->where('location', '!=', '')
                ->when($this->locationSearch, fn($q) => $q->where('location', 'like', '%'.$this->locationSearch.'%'))
                ->limit(10)
                ->pluck('location', 'location')
                ->toArray());

            $sectionOptions = once(fn() => Resource::distinct()
                ->whereNotNull('section')
                ->where('section', '!=', '')
                ->when($this->sectionSearch, fn($q) => $q->where('section', 'like', '%'.$this->sectionSearch.'%'))
                ->limit(10)
                ->pluck('section', 'section')
                ->toArray());

            $recursos = once(fn() => Resource::query()
                ->when($this->search, fn($q) => $q->where('name', 'like', '%'.$this->search.'%'))
                ->when($this->status, fn($q) => $q->where('status', $this->status))
                ->when($this->locationSearch, fn($q) => $q->where('location', 'like', '%'.$this->locationSearch.'%'))
                ->when($this->sectionSearch, fn($q) => $q->where('section', 'like', '%'.$this->sectionSearch.'%'))
                ->latest()
                ->paginate(15));

            return [
                'configFiltros' => [
                    [
                        'type'        => 'text',
                        'model'       => 'search',
                        'placeholder' => 'Procurar recursos...',
                    ],
                    [
                        'type'            => 'custom-dropdown',
                        'model'           => 'location',
                        'searchModel'     => 'locationSearch',
                        'label'           => 'Localização',
                        'selectMethod'    => 'selectLocation',
                        'computedOptions' => $locationOptions,
                    ],
                    [
                        'type'            => 'custom-dropdown',
                        'model'           => 'section',
                        'searchModel'     => 'sectionSearch',
                        'label'           => 'Secção / Dept.',
                        'selectMethod'    => 'selectSection',
                        'computedOptions' => $sectionOptions,
                    ],
                    [
                        'type'    => 'select',
                        'model'   => 'status',
                        'label'   => 'Todos os estados',
                        'options' => [
                            'active'   => 'Ativo',
                            'inactive' => 'Inativo',
                        ],
                    ],
                ],

                'valoresAtuais' => [
                    'search'   => $this->search,
                    'status'   => $this->status,
                    'location' => $this->locationSearch,
                    'section'  => $this->sectionSearch,
                ],

                'recursos' => $recursos,
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




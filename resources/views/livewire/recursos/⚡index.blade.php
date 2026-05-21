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

    public function with(): array
    {
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
                    'options' => Resource::distinct()->whereNotNull('location')->where('location', '!=', '')->pluck('location', 'location')->toArray(),
                ],
                [
                    'type' => 'select',
                    'model' => 'section',
                    'label' => 'Secção / Dept.',
                    'icon' => 'building-office',
                    'options' => Resource::distinct()->whereNotNull('section')->where('location', '!=', '')->pluck('section', 'section')->toArray(),
                ],
            ],

            'valoresAtuais' => [
                'search' => $this->search,
                'status' => $this->status,
                'location' => $this->location,
                'section'  => $this->section,
            ],

            'recursos' => Resource::query()
                ->when($this->search, fn($q) => $q->where('name', 'like', '%'.$this->search.'%'))
                ->when($this->status, fn($q) => $q->where('status', $this->status))
                ->when($this->location, fn($q) => $q->where('location', $this->location))
                ->when($this->section, fn($q) => $q->where('section', $this->section))
                ->latest()
                ->paginate(15),
        ];
    }

    public bool $showModal = false;

    public function openModal() { $this->showModal = true; }

};
?>


<div class="space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl" level="1">Recursos</flux:heading>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="openModal">
            Criar Recurso
        </flux:button>
    </div>

    <flux:separator variant="subtle" />

    @if($showModal)
        <livewire:recursos.modal />
    @endif


    <x-filtros-bar :config="$configFiltros" :valores="$valoresAtuais" />

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse ($recursos as $recurso)
            <a href="{{ route('resource.show', $recurso) }}" wire:navigate class="group decoration-none">
                <flux:card class="h-full transition-all duration-200 border-zinc-200 hover:border-primary-500 hover:shadow-lg dark:hover:bg-zinc-800">
                    <div class="flex flex-col h-full">
                        <div class="flex items-start justify-between">
                            <flux:heading size="lg" class="group-hover:text-primary-600 transition-colors">
                                {{ $recurso->name }}
                            </flux:heading>
                            <flux:icon name="arrow-up-right" class="text-zinc-400 group-hover:text-primary-500 transition-colors" variant="micro"/>
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
            <div class="col-span-full py-12 flex flex-col items-center justify-center border-2 border-dashed border-zinc-200 rounded-xl">
                <flux:icon name="archive-box" class="text-zinc-300 w-12 h-12" />
                <flux:heading class="mt-4">Nenhum recurso encontrado</flux:heading>
                <flux:subheading>Começa por criar o teu primeiro equipamento.</flux:subheading>
            </div>
        @endforelse
    </div>
    {{ $recursos->links('components.pagination', ['color' => 'primary']) }}
</div>



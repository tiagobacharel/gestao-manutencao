<?php

use App\Models\Task;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public string $sortBy = 'created_at';
    public string $sortDir = 'desc';

    public function rendering($view): void
    {
        $view->layoutData(['title' => 'Tarefas']);
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

    protected mixed $tasksCache = null;

    public function with(): array
    {
        if ($this->tasksCache == null) {
            $this->tasksCache = Task::query()
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
                    'placeholder' => 'Procurar tarefas...',
                ],
            ],

            'valoresAtuais' => [
                'search' => $this->search,
            ],

            'tasks' => $this->tasksCache,
        ];
    }

    public bool $showModal = false;

    public function openModal() { $this->showModal = true; }
};
?>
<div>
    <flux:main container class="space-y-6">

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <flux:heading size="xl" level="1">Tarefas ({{ $tasks->total() }})</flux:heading>

            <flux:button variant="primary" icon="plus" wire:click="openModal" wire:navigate class="w-full sm:w-auto">
                Nova Tarefa
            </flux:button>
        </div>

        <flux:separator variant="subtle" />

        @if($showModal)
            <livewire:tarefas_modal />
        @endif

        <x-filtros-bar :config="$configFiltros" :valores="$valoresAtuais" />

        {{-- TELEMÓVEL --}}
        <div class="space-y-3 md:hidden">
            @forelse($tasks as $task)
                <div class="p-4 rounded-xl bg-white dark:bg-zinc-900/50 border border-zinc-200/80 dark:border-zinc-800/80 shadow-sm flex items-center justify-between gap-4">
                    <div class="flex-1 min-w-0 space-y-1">
                        <div class="font-medium text-sm text-zinc-900 dark:text-white truncate">
                            {{ $task->name }}
                        </div>
                        <div>
                            @if($task->description)
                                <p class="text-xs text-zinc-500 line-clamp-2">
                                    {{ $task->description }}
                                </p>
                            @else
                                <span class="text-[10px] text-zinc-400 dark:text-zinc-500 bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded">—</span>
                            @endif
                        </div>
                    </div>

                    <div class="shrink-0">
                        <flux:button
                            variant="subtle"
                            size="sm"
                            icon="pencil-square"
                            href="{{ route('tarefas.show', $task) }}"
                            wire:navigate
                        />
                    </div>
                </div>
            @empty
                <div class="text-center py-12 border border-dashed rounded-xl border-zinc-200 dark:border-zinc-800 bg-zinc-50/30 dark:bg-zinc-900/10">
                    <flux:icon name="cube" class="size-8 mx-auto mb-2 opacity-40 text-zinc-400" />
                    <p class="text-sm text-zinc-400">Nenhuma tarefa encontrada.</p>
                </div>
            @endforelse
        </div>

        {{-- COMPUTADOR --}}
        <flux:card class="p-0 overflow-hidden hidden md:block border-zinc-200/80 dark:border-zinc-800/80 shadow-sm">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column
                        sortable
                        :sorted="$sortBy === 'name'"
                        :direction="$sortDir"
                        wire:click="sort('name')"
                    >Nome</flux:table.column>

                    <flux:table.column>Descrição</flux:table.column>

                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach($tasks as $task)
                        <flux:table.row :key="$task->id">
                            <flux:table.cell>
                                <span class="font-medium text-sm">{{ $task->name }}</span>
                            </flux:table.cell>

                            <flux:table.cell>
                                @if($task->description)
                                    <span class="text-sm text-zinc-500 truncate block max-w-xs" title="{{ $task->description }}">
                                        {{ $task->description }}
                                    </span>
                                @else
                                    <span class="text-xs text-zinc-400">—</span>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:button
                                    variant="subtle"
                                    size="sm"
                                    icon="pencil-square"
                                    href="{{ route('tarefas.show', $task) }}"
                                    wire:navigate
                                />
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>

        {{ $tasks->links('components.pagination', ['color' => 'primary']) }}

    </flux:main>
</div>


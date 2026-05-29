<?php

use Livewire\Attributes\On;
use Livewire\Component;
use App\Models\Maintenance;
use App\Models\MaintenanceTask;
use App\Models\Task;
use App\Models\Part;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public ?Maintenance $manutencao = null;

    public array $maintenance_tasks = [];

    public array $statuses = [
        'pending' => 'Pendente',
        'in_progress' => 'Em curso',
        'completed' => 'Concluída',
    ];

    public function selectTask(int $index, int $id, string $label): void
    {
        $this->maintenance_tasks[$index]['task_id'] = (string)$id;
        $this->maintenance_tasks[$index]['search'] = $label;
        $this->maintenance_tasks[$index]['open'] = false;
    }

    public function openTaskDropdown(int $index): void
    {
        $this->maintenance_tasks[$index]['open'] = true;
    }

    public function closeTaskDropdown(int $index): void
    {
        $this->maintenance_tasks[$index]['open'] = false;
    }

    public function mount(?Maintenance $manutencao = null): void
    {
        if ($manutencao && $manutencao->exists) {
            $this->manutencao = $manutencao;

            $this->maintenance_tasks = MaintenanceTask::with(['task', 'parts.part'])
                ->where('maintenance_id', $manutencao->id)
                ->get()
                ->map(fn($mt) => [
                    'maintenance_task_id' => (string)$mt->id,
                    'task_id' => (string)$mt->task_id,
                    'status' => $mt->status,
                    'search' => $mt->task->name,
                    'open' => false,
                    'expanded' => false,
                    'parts' => $mt->parts
                        ->map(fn($p) => [
                            'part_id' => (string)$p->part_id,
                            'search' => $p->part->name . ' / ' . $p->part->reference,
                            'open' => false,
                            'quantity' => (int)$p->quantity,
                            'unit_cost_at_time' => (float)$p->unit_cost_at_time,
                        ])
                        ->toArray(),
                ])
                ->toArray();
        } else {
            $this->manutencao = new Maintenance();
        }
    }

    public function addTask(): void
    {
        $this->maintenance_tasks[] = [
            'maintenance_task_id' => '',
            'task_id' => '',
            'status' => 'pending',
            'search' => '',
            'open' => false,
            'expanded' => false,
            'parts' => [],
        ];
    }

    public function removeTask(int $index): void
    {
        unset($this->maintenance_tasks[$index]);
        $this->maintenance_tasks = array_values($this->maintenance_tasks);
    }

    public function toggleExpanded(int $index): void
    {
        $this->maintenance_tasks[$index]['expanded'] = !$this->maintenance_tasks[$index]['expanded'];
    }

    public function selectPeca(int $taskIndex, int $partIndex, int $id, string $label): void
    {
        $this->maintenance_tasks[$taskIndex]['parts'][$partIndex]['part_id'] = (string)$id;
        $this->maintenance_tasks[$taskIndex]['parts'][$partIndex]['search'] = $label;
        $this->maintenance_tasks[$taskIndex]['parts'][$partIndex]['open'] = false;
        $this->maintenance_tasks[$taskIndex]['parts'][$partIndex]['unit_cost_at_time'] =
            Part::where('id', $id)->value('current_unit_cost') ?? 0;
    }

    public function openPartDropdown(int $taskIndex, int $partIndex): void
    {
        $this->maintenance_tasks[$taskIndex]['parts'][$partIndex]['open'] = true;
    }

    public function closePartDropdown(int $taskIndex, int $partIndex): void
    {
        $this->maintenance_tasks[$taskIndex]['parts'][$partIndex]['open'] = false;
    }

    public function addPart(int $taskIndex): void
    {
        $this->maintenance_tasks[$taskIndex]['parts'][] = [
            'part_id' => '',
            'search' => '',
            'open' => false,
            'quantity' => 1,
            'unit_cost_at_time' => 0,
        ];
    }

    public function removePart(int $taskIndex, int $partIndex): void
    {
        unset($this->maintenance_tasks[$taskIndex]['parts'][$partIndex]);
        $this->maintenance_tasks[$taskIndex]['parts'] = array_values(
            $this->maintenance_tasks[$taskIndex]['parts']
        );
    }

    #[On('salvar-tudo')]
    public function save(): void
    {
        $this->validate(MaintenanceTask::rules());

        DB::transaction(function () {
            $taskSyncData = [];
            foreach ($this->maintenance_tasks as $item) {
                if (empty($item['task_id'])) continue;
                $taskSyncData[$item['task_id']] = ['status' => $item['status']];
            }
            $this->manutencao->tasks()->sync($taskSyncData);

            $maintenanceTasks = MaintenanceTask::where('maintenance_id', $this->manutencao->id)
                ->get()
                ->keyBy('task_id');

            foreach ($this->maintenance_tasks as $item) {
                if (empty($item['task_id'])) continue;

                $mt = $maintenanceTasks->get($item['task_id']);
                if (!$mt) continue;

                $this->manutencao->parts()
                    ->wherePivot('maintenance_task_id', $mt->id)
                    ->detach();

                foreach ($item['parts'] as $part) {
                    if (empty($part['part_id'])) continue;

                    $this->manutencao->parts()->attach($part['part_id'], [
                        'maintenance_task_id' => $mt->id,
                        'quantity' => $part['quantity'],
                        'unit_cost_at_time' => $part['unit_cost_at_time'] ?? 0.00,
                    ]);
                }
            }
        });

        Flux::toast(text: 'As tarefas foram atualizadas com sucesso!', variant: 'success', duration: 1000);

        $this->fechar();
    }

    public function fechar()
    {
        if (!$this->manutencao->exists) {
            return $this->redirect(
                request()->header('Referer') ?? route('manutencoes.index'),
                navigate: true
            );
        }

        $this->manutencao->refresh();
        $this->mount($this->manutencao);
        $this->resetErrorBag();

        return null;
    }

    public function with(): array
    {
        $limparTexto = function ($texto) {
            if (empty($texto)) return '';
            $semAcentos = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
            return str_replace([' ', "'", '"', '`', '~', '^', '/'], '', strtolower($semAcentos));
        };

        // --- TAREFAS: só pesquisa quando o dropdown está aberto ---
        $tarefas = [];
        $tarefasPadrao = null;

        foreach ($this->maintenance_tasks as $i => $task) {
            if (!($task['open'] ?? false)) {
                $tarefas[$i] = collect();
                continue;
            }

            $search = $task['search'] ?? '';

            if (strlen($search) < 1) {
                if (is_null($tarefasPadrao)) {
                    $tarefasPadrao = Task::orderBy('name')->limit(10)->get(['id', 'name', 'description']);
                }
                $tarefas[$i] = $tarefasPadrao;
                continue;
            }

            $tarefas[$i] = Task::orderBy('name')
                ->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%');
                })
                ->limit(10)
                ->get(['id', 'name', 'description']);
        }

        // --- PEÇAS: só pesquisa quando o dropdown está aberto ---
        // Os preços NÃO precisam de BD — já estão em unit_cost_at_time no array
        $pecas = [];
        $pecasPadrao = null;

        foreach ($this->maintenance_tasks as $ti => $task) {
            $pecas[$ti] = [];
            foreach ($task['parts'] as $pi => $part) {
                if (!($part['open'] ?? false)) {
                    $pecas[$ti][$pi] = collect();
                    continue;
                }

                $search = $part['search'] ?? '';

                if (blank($search)) {
                    if (is_null($pecasPadrao)) {
                        $pecasPadrao = Part::orderBy('name')->limit(10)->get(['id', 'name', 'reference']);
                    }
                    $pecas[$ti][$pi] = $pecasPadrao;
                    continue;
                }

                $termosLinha = array_filter(array_map($limparTexto, explode('/', $search)));

                if (empty($termosLinha)) {
                    $pecas[$ti][$pi] = collect();
                    continue;
                }

                $query = Part::orderBy('name');
                foreach ($termosLinha as $t) {
                    $query->where(function ($sub) use ($t) {
                        $sub->whereRaw("REPLACE(name, ' ', '') COLLATE utf8mb4_general_ci LIKE ?", ["%$t%"])
                            ->orWhereRaw("REPLACE(reference, ' ', '') COLLATE utf8mb4_general_ci LIKE ?", ["%$t%"]);
                    });
                }

                $resultados = $query->limit(50)->get(['id', 'name', 'reference']);

                $pecas[$ti][$pi] = $resultados->filter(fn($p) => collect($termosLinha)->every(fn($t) => str_contains($limparTexto($p->name) . $limparTexto($p->reference), $t)
                )
                )->take(10)->values();
            }
        }

        return ['tarefas' => $tarefas, 'pecas' => $pecas];
    }
};
?>

<div>
    <flux:card class="space-y-6">

        <div>
            <flux:heading size="lg">Tarefas da Manutenção</flux:heading>
            <flux:text size="sm" class="text-zinc-400 mt-1">
                Adicione ou remova as tarefas e as peças utilizadas em cada uma.
            </flux:text>
        </div>

        <flux:separator variant="subtle"/>

        <div class="space-y-4">

            @if(count($maintenance_tasks) === 0)
                <div class="text-center p-4 border border-dashed rounded-lg border-zinc-200 dark:border-zinc-800">
                    <flux:text size="sm" class="text-zinc-400">Nenhuma tarefa adicionada até ao momento.</flux:text>
                </div>
            @endif

            <div class="space-y-3">
                @foreach($maintenance_tasks as $index => $item)

                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700">

                        <div
                            class="grid grid-cols-12 gap-3 items-end p-3  rounded-t-lg md:flex md:gap-2 md:items-center">

                            <div class="col-span-12 md:flex-1 relative">
                                <flux:input
                                    wire:model.live.debounce.300ms="maintenance_tasks.{{ $index }}.search"
                                    wire:focus="openTaskDropdown({{ $index }})"
                                    wire:blur="closeTaskDropdown({{ $index }})"
                                    @click="$wire.openTaskDropdown({{ $index }})"
                                    @keydown.escape="$wire.closeTaskDropdown({{ $index }})"
                                    placeholder="Pesquisar tarefa..."
                                    autocomplete="off"
                                    icon="magnifying-glass"
                                />

                                @if($item['open'])
                                    <ul class="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 py-1 shadow-lg">
                                        @if(isset($tarefas[$index]))
                                            @forelse($tarefas[$index] as $tarefa)
                                                <li
                                                    wire:mousedown="selectTask({{ $index }}, {{ $tarefa->id }}, '{{ addslashes($tarefa->name) }}')"
                                                    class="cursor-pointer px-3 py-2 text-sm text-zinc-800 dark:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-700 flex flex-col gap-0.5"
                                                >
                                                    <span>{{ $tarefa->name }}</span>
                                                    @if($tarefa->description)
                                                        <span
                                                            class="text-zinc-400 text-xs truncate">{{ $tarefa->description }}</span>
                                                    @endif
                                                </li>
                                            @empty
                                                <li class="px-3 py-4 text-sm text-center text-zinc-400">Nenhuma tarefa
                                                    encontrada
                                                </li>
                                            @endforelse
                                        @endif
                                    </ul>
                                @endif
                            </div>

                            <div class="col-span-6 md:w-36 md:shrink-0">
                                <flux:select wire:model.live="maintenance_tasks.{{ $index }}.status" class="w-full">
                                    @foreach($statuses as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </flux:select>
                                @error("maintenance_tasks.$index.task_id")
                                <flux:error>{{ $message }}</flux:error>
                                @enderror
                            </div>

                            <div class="col-span-4 flex items-center justify-center h-10 md:h-auto md:shrink-0">
                                <button
                                    type="button"
                                    wire:click="toggleExpanded({{ $index }})"
                                    class="w-full flex items-center justify-center gap-1.5 px-2 py-2 rounded-md text-xs text-zinc-500 bg-zinc-200/50 dark:bg-zinc-700/50 hover:bg-zinc-100 dark:hover:bg-zinc-700 md:bg-transparent md:dark:bg-transparent transition-colors h-full"
                                >
                                    <span>{{ count($item['parts']) }} pçs</span>
                                    @if($item['expanded'])
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                             stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M4.5 15.75l7.5-7.5 7.5 7.5"/>
                                        </svg>
                                    @else
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                             stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                                        </svg>
                                    @endif
                                </button>
                            </div>

                            <div class="col-span-2 text-right md:shrink-0">
                                <flux:button
                                    type="button"
                                    variant="danger"
                                    icon="trash"
                                    wire:click="removeTask({{ $index }})"
                                    square
                                    class="w-full md:w-10"
                                />
                            </div>
                        </div>

                        @if($item['expanded'])
                            <div
                                class="p-3 border-t border-zinc-200 dark:border-zinc-700 space-y-3 bg-white dark:bg-zinc-900 rounded-b-lg">

                                @if(count($item['parts']) === 0)
                                    <div
                                        class="text-center py-3 border border-dashed rounded-lg border-zinc-200 dark:border-zinc-800">
                                        <flux:text size="sm" class="text-zinc-400">Nenhuma peça adicionada a esta
                                            tarefa.
                                        </flux:text>
                                    </div>
                                @endif

                                @foreach($item['parts'] as $pi => $part)
                                    @php $custoUnit = (float)($part['unit_cost_at_time'] ?? 0); @endphp

                                    <div
                                        class="grid grid-cols-12 gap-3 items-end p-2.5 rounded-lg bg-zinc-50/40 dark:bg-zinc-800/20 border border-zinc-100 dark:border-zinc-800/40 md:flex md:gap-2 md:items-center md:p-0 md:bg-transparent md:border-0">

                                        <div class="col-span-12 md:flex-1 relative">
                                            <flux:input
                                                wire:model.live.debounce.300ms="maintenance_tasks.{{ $index }}.parts.{{ $pi }}.search"
                                                wire:focus="openPartDropdown({{ $index }}, {{ $pi }})"
                                                wire:blur="closePartDropdown({{ $index }}, {{ $pi }})"
                                                @click="$wire.openPartDropdown({{ $index }}, {{ $pi }})"
                                                @keydown.escape="$wire.closeTaskDropdown({{ $index }}, {{ $pi }})"
                                                placeholder="Pesquisar peça..."
                                                autocomplete="off"
                                                icon="magnifying-glass"
                                                size="sm"
                                            />

                                            @if($part['open'])
                                                <ul class="absolute z-50 mt-1 max-h-48 w-full overflow-auto rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 py-1 shadow-lg">
                                                    @if(isset($pecas[$index][$pi]))
                                                        @forelse($pecas[$index][$pi] as $peca)
                                                            <li
                                                                wire:mousedown="selectPeca({{ $index }}, {{ $pi }}, {{ $peca->id }}, '{{ addslashes($peca->name . ' / ' . $peca->reference) }}')"
                                                                class="cursor-pointer px-3 py-2 text-sm text-zinc-800 dark:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-700 flex justify-between gap-2"
                                                            >
                                                                <span>{{ $peca->name }}</span>
                                                                <span
                                                                    class="text-zinc-400 text-xs">{{ $peca->reference }}</span>
                                                            </li>
                                                        @empty
                                                            <li class="px-3 py-4 text-sm text-center text-zinc-400">
                                                                Nenhuma peça encontrada
                                                            </li>
                                                        @endforelse
                                                    @endif
                                                </ul>
                                            @endif
                                        </div>

                                        <div class="col-span-4 md:w-20 md:shrink-0">
                                            <flux:input
                                                type="number"
                                                min="1"
                                                wire:model.live.debounce.300ms="maintenance_tasks.{{ $index }}.parts.{{ $pi }}.quantity"
                                                size="sm"
                                                class="w-full"
                                            />
                                            @error("maintenance_tasks.$index.parts.$pi.quantity")
                                            <flux:error>{{ $message }}</flux:error>
                                            @enderror
                                        </div>

                                        <div
                                            class="col-span-3 md:w-24 md:shrink-0 text-right text-xs text-zinc-500 h-9 flex flex-col justify-center">
                                            <span
                                                class="text-[10px] font-medium text-zinc-400 block md:hidden truncate">Custo</span>
                                            <div>{{ number_format($custoUnit, 2, ',', '.') }} €</div>
                                        </div>

                                        <div
                                            class="col-span-3 md:w-24 md:shrink-0 text-right text-sm font-medium text-zinc-800 dark:text-zinc-200 h-9 flex flex-col justify-center">
                                            <span
                                                class="text-[10px] font-medium text-zinc-400 block md:hidden truncate">Total</span>
                                            <div>{{ number_format(intval($part['quantity'] ?? 0) * $custoUnit, 2, ',', '.') }}
                                                €
                                            </div>
                                        </div>

                                        <div class="col-span-2 text-right md:w-auto">
                                            <flux:button
                                                type="button"
                                                variant="danger"
                                                icon="trash"
                                                wire:click="removePart({{ $index }}, {{ $pi }})"
                                                size="sm"
                                                square
                                                class="w-full md:w-10"
                                            />
                                        </div>
                                    </div>
                                @endforeach

                                @if(count($item['parts']) > 0)
                                    @php
                                        $totalTarefa = collect($item['parts'])->sum(
                                            fn($p) => intval($p['quantity'] ?? 0) * (float)($p['unit_cost_at_time'] ?? 0)
                                        );
                                    @endphp
                                    <div
                                        class="flex justify-between md:justify-end pt-2 border-t border-zinc-100 dark:border-zinc-800 text-sm px-1">
                                        <span class="text-zinc-500 md:mr-3">Total da tarefa:</span>
                                        <span class="font-bold text-zinc-900 dark:text-white">
                                            {{ number_format($totalTarefa, 2, ',', '.') }} €
                                        </span>
                                    </div>
                                @endif

                                <flux:button type="button" size="sm" icon="plus" wire:click="addPart({{ $index }})"
                                             class="w-full md:w-auto">
                                    Adicionar Peça
                                </flux:button>

                            </div>
                        @endif

                    </div>
                @endforeach

                @if(count($maintenance_tasks) > 0)
                    @php $countByStatus = collect($maintenance_tasks)->countBy('status'); @endphp
                    <div
                        class="flex flex-wrap justify-between md:justify-end gap-x-4 gap-y-1 pt-4 mt-2 text-sm text-zinc-500 px-1">
                        <span>Total: <strong
                                class="text-zinc-800 dark:text-white">{{ count($maintenance_tasks) }}</strong></span>
                        <span>Concluídas: <strong class="text-green-600">{{ $countByStatus['completed'] ?? 0 }}</strong></span>
                        <span>Em curso: <strong
                                class="text-yellow-600">{{ $countByStatus['in_progress'] ?? 0 }}</strong></span>
                        <span>Pendentes: <strong
                                class="text-zinc-400">{{ $countByStatus['pending'] ?? 0 }}</strong></span>
                    </div>
                @endif
            </div>

            <flux:button type="button" size="sm" icon="plus" wire:click="addTask" class="w-full md:w-auto">
                Adicionar Tarefa
            </flux:button>

        </div>

        <div class="flex items-center gap-2">
            <flux:button type="button" wire:click="save" variant="primary" class="w-full">
                Atualizar
            </flux:button>
            <flux:button type="button" wire:click="fechar" variant="danger" class="w-full">
                Recarregar
            </flux:button>
        </div>

    </flux:card>
</div>


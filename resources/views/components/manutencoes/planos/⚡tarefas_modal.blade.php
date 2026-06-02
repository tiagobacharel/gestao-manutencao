<?php

use Livewire\Attributes\On;
use Livewire\Component;
use App\Models\MaintenancePlan;
use App\Models\PlanTask;
use App\Models\PlanPart;
use App\Models\Task;
use App\Models\Part;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public ?MaintenancePlan $plano = null;

    public array $plan_tasks = [];

    public function selectTask(int $index, int $id, string $label): void
    {
        $this->plan_tasks[$index]['task_id'] = (string)$id;
        $this->plan_tasks[$index]['search'] = $label;
        $this->plan_tasks[$index]['open'] = false;
    }

    public function openTaskDropdown(int $index): void
    {
        $this->plan_tasks[$index]['open'] = true;
    }

    public function closeTaskDropdown(int $index): void
    {
        $this->plan_tasks[$index]['open'] = false;
    }

    public function mount(?MaintenancePlan $plano = null): void
    {
        if ($plano && $plano->exists) {
            $this->plano = $plano;

            $this->plan_tasks = PlanTask::with(['task', 'parts.part'])
                ->where('maintenance_plan_id', $plano->id)
                ->get()
                ->map(fn($pt) => [
                    'plan_task_id' => (string)$pt->id,
                    'task_id' => (string)$pt->task_id,
                    'search' => $pt->task->name,
                    'open' => false,
                    'expanded' => false,
                    'parts' => $pt->parts
                        ->map(fn($p) => [
                            'part_id' => (string)$p->part_id,
                            'search' => $p->part->name . ' / ' . $p->part->reference,
                            'open' => false,
                            'quantity' => (int)$p->quantity,
                        ])
                        ->toArray(),
                ])
                ->toArray();
        } else {
            $this->plano = new MaintenancePlan();
        }
    }

    public function addTask(): void
    {
        $this->plan_tasks[] = [
            'plan_task_id' => '',
            'task_id' => '',
            'search' => '',
            'open' => false,
            'expanded' => false,
            'parts' => [],
        ];
    }

    public function removeTask(int $index): void
    {
        unset($this->plan_tasks[$index]);
        $this->plan_tasks = array_values($this->plan_tasks);
    }

    public function toggleExpanded(int $index): void
    {
        $this->plan_tasks[$index]['expanded'] = !$this->plan_tasks[$index]['expanded'];
    }

    public function selectPeca(int $taskIndex, int $partIndex, int $id, string $label): void
    {
        $this->plan_tasks[$taskIndex]['parts'][$partIndex]['part_id'] = (string)$id;
        $this->plan_tasks[$taskIndex]['parts'][$partIndex]['search'] = $label;
        $this->plan_tasks[$taskIndex]['parts'][$partIndex]['open'] = false;
    }

    public function openPartDropdown(int $taskIndex, int $partIndex): void
    {
        $this->plan_tasks[$taskIndex]['parts'][$partIndex]['open'] = true;
    }

    public function closePartDropdown(int $taskIndex, int $partIndex): void
    {
        $this->plan_tasks[$taskIndex]['parts'][$partIndex]['open'] = false;
    }

    public function addPart(int $taskIndex): void
    {
        $this->plan_tasks[$taskIndex]['parts'][] = [
            'part_id' => '',
            'search' => '',
            'open' => false,
            'quantity' => 1,
        ];
    }

    public function removePart(int $taskIndex, int $partIndex): void
    {
        unset($this->plan_tasks[$taskIndex]['parts'][$partIndex]);
        $this->plan_tasks[$taskIndex]['parts'] = array_values(
            $this->plan_tasks[$taskIndex]['parts']
        );
    }

    #[On('salvar-tudo')]
    public function save(): void
    {
        $this->validate(PlanTask::rules($this));

        // Variável para rastrear se houve qualquer alteração na base de dados
        $hasChanges = DB::transaction(function () {
            $now = now();
            $formTasks = collect($this->plan_tasks)->filter(fn($t) => !empty($t['task_id']));
            $taskIds = $formTasks->pluck('task_id')->all();
            $changed = false;

            // 1. Sincronizar Tarefas (PlanTask)
            $existingTasks = PlanTask::where('maintenance_plan_id', $this->plano->id)->get()->keyBy('task_id');
            $newTaskIds = array_diff($taskIds, $existingTasks->keys()->all());

            if (!empty($newTaskIds)) {
                DB::table('plan_tasks')->insert(array_map(fn($id) => [
                    'maintenance_plan_id' => $this->plano->id, 'task_id' => $id, 'created_at' => $now, 'updated_at' => $now
                ], $newTaskIds));

                $existingTasks = PlanTask::where('maintenance_plan_id', $this->plano->id)->get()->keyBy('task_id');
                $changed = true;
            }

            $keptTaskIds = $existingTasks->whereIn('task_id', $taskIds)->pluck('id')->all();

            // 2. Sincronizar Peças (PlanPart)
            $existingParts = DB::table('plan_parts')->where('maintenance_plan_id', $this->plano->id)
                ->whereIn('plan_task_id', $keptTaskIds)->get()->keyBy(fn($p) => "{$p->plan_task_id}-{$p->part_id}");

            $partsToInsert = [];
            $keptPartKeys = [];

            foreach ($formTasks as $item) {
                $planTaskId = $existingTasks->get($item['task_id'])?->id;
                if (!$planTaskId) continue;

                foreach (collect($item['parts'])->filter(fn($p) => !empty($p['part_id'])) as $part) {
                    $key = "{$planTaskId}-{$part['part_id']}";
                    $keptPartKeys[] = $key;

                    if ($existingParts->has($key)) {
                        if ($existingParts->get($key)->quantity != $part['quantity']) {
                            DB::table('plan_parts')->where('id', $existingParts->get($key)->id)
                                ->update(['quantity' => $part['quantity'], 'updated_at' => $now]);
                            $changed = true;
                        }
                    } else {
                        $partsToInsert[] = [
                            'maintenance_plan_id' => $this->plano->id, 'plan_task_id' => $planTaskId,
                            'part_id' => $part['part_id'], 'quantity' => $part['quantity'], 'created_at' => $now, 'updated_at' => $now
                        ];
                    }
                }
            }

            if (!empty($partsToInsert)) {
                DB::table('plan_parts')->insert($partsToInsert);
                $changed = true;
            }

            // 3. Limpar Peças e Tarefas removidas
            $partsToDelete = $existingParts->filter(fn($p) => !in_array("{$p->plan_task_id}-{$p->part_id}", $keptPartKeys))->pluck('id');
            if ($partsToDelete->isNotEmpty()) {
                DB::table('plan_parts')->whereIn('id', $partsToDelete)->delete();
                $changed = true;
            }

            $tasksToDelete = PlanTask::where('maintenance_plan_id', $this->plano->id)->whereNotIn('id', $keptTaskIds)->pluck('id');
            if ($tasksToDelete->isNotEmpty()) {
                DB::table('plan_parts')->where('maintenance_plan_id', $this->plano->id)->whereIn('plan_task_id', $tasksToDelete)->delete();
                PlanTask::whereIn('id', $tasksToDelete)->delete();
                $changed = true;
            }

            return $changed;
        });

        // Exibe o toast com base na existência de alterações
        if ($hasChanges) {
            Flux::toast('As tarefas foram atualizadas com sucesso!', variant: 'success', duration: 1000);
        } else {
            Flux::toast('As tarefas não tem alterações!', variant: 'danger', duration: 1000);
        }

        $this->fechar();
    }



    public function fechar(): mixed
    {
        if (!$this->plano->exists) {
            return $this->redirect(
                request()->header('Referer') ?? route('planos_manutencoes.index'),
                navigate: true
            );
        }

        $this->mount($this->plano);
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

        // --- TAREFAS: só processa os dropdowns abertos ---
        $tarefas = [];
        $tarefasPadrao = null;

        foreach ($this->plan_tasks as $i => $task) {
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

        // --- PEÇAS SELECIONADAS: busca os preços de TODAS as peças já escolhidas ---
        $todasPartIds = [];
        foreach ($this->plan_tasks as $task) {
            foreach ($task['parts'] as $part) {
                $id = $part['part_id'] ?? null;
                if ($id) $todasPartIds[] = (int)$id;
            }
        }

        $pecasSelecionadas = collect();
        if (!empty($todasPartIds)) {
            $pecasSelecionadas = Part::whereIn('id', array_unique($todasPartIds))
                ->get(['id', 'name', 'reference', 'current_unit_cost'])
                ->keyBy('id');
        }

        // --- PEÇAS: resultados de pesquisa só quando aberto, preços sempre disponíveis ---
        $pecas = [];
        $pecasPadrao = null;

        foreach ($this->plan_tasks as $ti => $task) {
            $pecas[$ti] = [];
            foreach ($task['parts'] as $pi => $part) {
                $partId = $part['part_id'] ?? null;

                if (!($part['open'] ?? false)) {
                    // Dropdown fechado: só devolve a peça selecionada (para os preços)
                    $pecas[$ti][$pi] = $partId && $pecasSelecionadas->has($partId)
                        ? collect([$pecasSelecionadas->get($partId)])
                        : collect();
                    continue;
                }

                // Dropdown aberto: pesquisa normal
                $search = $part['search'] ?? '';

                if (blank($search)) {
                    if (is_null($pecasPadrao)) {
                        $pecasPadrao = Part::orderBy('name')->limit(10)->get(['id', 'name', 'reference', 'current_unit_cost']);
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

                $pecas[$ti][$pi] = $query->limit(10)->get(['id', 'name', 'reference', 'current_unit_cost']);
            }
        }

        return ['tarefas' => $tarefas, 'pecas' => $pecas];
    }

};
?>

<div>
    <flux:card class="space-y-6">

        <div>
            <flux:heading size="lg">Tarefas do Plano</flux:heading>
            <flux:text size="sm" class="text-zinc-400 mt-1">
                Adicione ou remova as tarefas e as peças associadas a cada uma.
            </flux:text>
        </div>

        <flux:separator variant="subtle"/>

        <div class="space-y-4">

            @if(count($plan_tasks) === 0)
                <div class="text-center p-4 border border-dashed rounded-lg border-zinc-200 dark:border-zinc-800">
                    <flux:text size="sm" class="text-zinc-400">Nenhuma tarefa adicionada até ao momento.</flux:text>
                </div>
            @endif

            <div class="space-y-3">
                @foreach($plan_tasks as $index => $item)

                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700">

                        {{-- Cabeçalho da tarefa --}}
                        <div
                            class="grid grid-cols-12 gap-3 items-end p-3 rounded-t-lg md:flex md:gap-2 md:items-center">

                            {{-- Pesquisa de tarefa --}}
                            <div class="col-span-12 md:flex-1 relative">
                                <flux:input
                                    wire:model.live.debounce.300ms="plan_tasks.{{ $index }}.search"
                                    wire:focus="openTaskDropdown({{ $index }})"
                                    wire:blur="closeTaskDropdown({{ $index }})"
                                    @click="$wire.openTaskDropdown({{ $index }})"
                                    @keydown.escape="$wire.closeTaskDropdown({{ $index }})"
                                    placeholder="Pesquisar tarefa..."
                                    autocomplete="off"
                                    icon="magnifying-glass"
                                    :invalid="$errors->has('plan_tasks.'.$index.'.task_id')"
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

                                @error("plan_tasks.$index.task_id")
                                <flux:error class="mt-1">{{ $message }}</flux:error>
                                @enderror
                            </div>

                            {{-- Botão acordeão + contador --}}
                            <div class="col-span-10 flex items-center justify-center h-10 md:h-auto md:shrink-0">
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

                            {{-- Botão apagar tarefa --}}
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

                        {{-- Acordeão: peças da tarefa --}}
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

                                    @php
                                        $pecaSelecionada = collect($pecas[$index][$pi] ?? [])->firstWhere('id', $part['part_id'] ?? null);
                                        $custoUnit = (float)($pecaSelecionada['current_unit_cost'] ?? 0);
                                    @endphp


                                    <div
                                        class="grid grid-cols-12 gap-3 items-end p-2.5 rounded-lg bg-zinc-50/40 dark:bg-zinc-800/20 border border-zinc-100 dark:border-zinc-800/40 md:flex md:gap-2 md:items-center md:p-0 md:bg-transparent md:border-0">

                                        {{-- Pesquisa de peça --}}
                                        <div class="col-span-12 md:flex-1 relative">
                                            <flux:input
                                                wire:model.live.debounce.300ms="plan_tasks.{{ $index }}.parts.{{ $pi }}.search"
                                                wire:focus="openPartDropdown({{ $index }}, {{ $pi }})"
                                                wire:blur="closePartDropdown({{ $index }}, {{ $pi }})"
                                                @click="$wire.openPartDropdown({{ $index }}, {{ $pi }})"
                                                @keydown.escape="$wire.closePartDropdown({{ $index }}, {{ $pi }})"
                                                placeholder="Pesquisar peça..."
                                                autocomplete="off"
                                                icon="magnifying-glass"
                                                size="sm"
                                                :invalid="$errors->has('plan_tasks.'.$index.'.parts.'.$pi.'.part_id')"
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

                                            @error("plan_tasks.$index.parts.$pi.part_id")
                                            <flux:error class="mt-1 text-xs">{{ $message }}</flux:error>
                                            @enderror
                                        </div>

                                        {{-- Quantidade --}}
                                        <div class="col-span-4 md:w-20 md:shrink-0">
                                            <flux:input
                                                type="number"
                                                min="1"
                                                wire:model.live.debounce.300ms="plan_tasks.{{ $index }}.parts.{{ $pi }}.quantity"
                                                size="sm"
                                                class="w-full"
                                            />
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

                                        {{-- Apagar peça --}}
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
                                        $totalTarefa = collect($item['parts'])->sum(function($p, $pi) use ($pecas, $index) {
                                            // Vai buscar o preço correto à lista de peças com base nos índices
                                            $pecaSelecionada = collect($pecas[$index][$pi] ?? [])->firstWhere('id', $p['part_id'] ?? null);
                                            $preco = (float)($pecaSelecionada['current_unit_cost'] ?? 0);
                                            $quantidade = intval($p['quantity'] ?? 0);

                                            return $quantidade * $preco;
                                        });
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
            </div>

            <flux:button type="button" size="sm" icon="plus" wire:click="addTask" class="w-full md:w-auto">
                Adicionar Tarefa
            </flux:button>

        </div>

        {{-- Botões --}}
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

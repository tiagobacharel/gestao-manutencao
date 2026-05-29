<?php

    use App\Models\MaintenancePlan;
    use App\Models\Part;
    use App\Models\PlanPart;
    use Livewire\Attributes\On;
    use Livewire\Component;

    new class extends Component {

        public ?MaintenancePlan $plano = null;

        public array $plan_parts = [];

        public function selectPeca(int $index, int $id, string $label): void
        {
            $this->plan_parts[$index]['part_id'] = (string)$id;
            $this->plan_parts[$index]['search'] = $label;
            $this->plan_parts[$index]['open'] = false;
            $this->plan_parts[$index]['current_unit_cost'] = Part::where('id', $id)->value('current_unit_cost') ?? 0;

        }

        public function mount(?MaintenancePlan $plano = null): void
        {
            if ($plano && $plano->exists) {
                $this->plano = $plano;

                $this->plan_parts = $plano->planParts()
                    ->select('plan_parts.part_id', 'parts.name', 'parts.reference', 'plan_parts.quantity', 'parts.current_unit_cost as unit_cost')
                    ->join('parts', 'parts.id', '=', 'plan_parts.part_id')
                    ->where('plan_parts.plan_task_id', null)
                    ->get()
                    ->map(fn($p) => [
                        'part_id' => (string)$p->part_id,
                        'quantity' => (int)$p->quantity,
                        'search' => $p->name . ' / ' . $p->reference,
                        'open' => false,
                        'current_unit_cost' => (float)$p->unit_cost,
                    ])
                    ->toArray();
            } else {
                $this->plano = new MaintenancePlan();
            }
        }

        public function addPart(): void
        {
            $this->plan_parts[] = [
                'part_id' => '',
                'quantity' => 1,
                'search' => '',
                'open' => false,
                'current_unit_cost' => 0,
            ];
        }

        public function removePart(int $index): void
        {
            unset($this->plan_parts[$index]);
            $this->plan_parts = array_values($this->plan_parts);
        }

        #[On('salvar-tudo')]
        public function save(): void
        {
            $this->validate(PlanPart::rules());

            DB::transaction(function () {
                $formPartIds = [];

                // Processar cada peça vinda do formulário individualmente
                foreach ($this->plan_parts as $item) {
                    if (empty($item['part_id'])) continue;

                    $formPartIds[] = $item['part_id'];

                    // Procura se já existe esta peça gravada ESPECIFICAMENTE sem tarefa
                    $pivotRow = $this->plano->parts()
                        ->wherePivot('part_id', $item['part_id'])
                        ->wherePivot('plan_task_id', null)
                        ->first();

                    if ($pivotRow) {
                        // Se já existe sem tarefa, atualiza apenas esta linha específica usando o ID da pivot
                        DB::table('plan_parts') // Confirma o nome exato da tua tabela pivot
                        ->where('id', $pivotRow->pivot->id)
                            ->update([
                                'quantity' => $item['quantity'],
                                'updated_at' => now(),
                            ]);
                    } else {
                        // Se não existe, insere um novo registo limpo sem tarefa
                        $this->plano->parts()->attach($item['part_id'], [
                            'plan_task_id' => null, // Substitui pelo nome correto da tua Fk de tarefas, se existir
                            'quantity' => $item['quantity'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                // Apagar apenas os registos sem tarefa que foram removidos do formulário
                $this->plano->parts()
                    ->wherePivot('plan_task_id', null) // Substitui pelo nome correto da tua Fk de tarefas, se existir
                    ->wherePivotNotIn('part_id', $formPartIds)
                    ->detach();
            });

            Flux::toast(text: 'As peças foram atualizadas com sucesso!', variant: 'success', duration: 1000);

            $this->fechar();
        }



        public function fechar()
        {
            if (!$this->plano->exists) {
                return $this->redirect(request()->header('Referer') ?? route('planos_manutencoes.index'), navigate: true);
            }

            $this->plano->refresh();

            $this->mount($this->plano);

            $this->resetErrorBag();

        }

        public function with(): array
        {
            $limparTexto = function($texto) {
                if (empty($texto)) return '';
                $semAcentos = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
                return str_replace([' ', "'", '"', '`', '~', '^', '/'], '', strtolower($semAcentos));
            };

            $pecas = [];
            $pecasPadrao = null;

            foreach ($this->plan_parts as $i => $part) {
                if (!($part['open'] ?? false)) {
                    $pecas[$i] = collect();
                    continue;
                }

                $search = $part['search'] ?? '';

                if (blank($search)) {
                    if (is_null($pecasPadrao)) {
                        $pecasPadrao = Part::orderBy('name')->limit(10)->get(['id', 'name', 'reference', 'current_unit_cost']);
                    }
                    $pecas[$i] = $pecasPadrao;
                    continue;
                }

                $termosOriginais = array_filter(explode('/', $search));
                $termosLinha = array_filter(array_map($limparTexto, $termosOriginais));

                if (empty($termosLinha)) {
                    $pecas[$i] = collect();
                    continue;
                }

                $query = Part::orderBy('name');
                foreach ($termosOriginais as $t) {
                    $t = trim($t);
                    if (blank($t)) continue;
                    $query->where(function ($sub) use ($t) {
                        $sub->where('name', 'like', '%' . $t . '%')
                            ->orWhere('reference', 'like', '%' . $t . '%');
                    });
                }

                $resultados = $query->limit(50)->get(['id', 'name', 'reference']);

                $pecas[$i] = $resultados->filter(fn($p) =>
                    collect($termosLinha)->every(fn($t) =>
                        str_contains($limparTexto($p->name) . $limparTexto($p->reference), $t)
                    )
                )->take(10)->values();
            }

            return ['pecas' => $pecas];
        }

    };
?>

<div>
    <flux:card class="space-y-6">

        <div>
            <flux:heading size="lg">Peças do Plano sem Tarefa</flux:heading>
            <flux:text size="sm" class="text-zinc-400 mt-1">
                Define as peças esperadas para o plano "{{ $plano->name }}".
            </flux:text>
        </div>

        <flux:separator variant="subtle"/>

        <div class="space-y-4">

            @if(count($plan_parts) === 0)
                <div class="text-center p-4 border border-dashed rounded-lg border-zinc-200 dark:border-zinc-800">
                    <flux:text size="sm" class="text-zinc-400">Nenhuma peça adicionada ao plano.</flux:text>
                </div>
            @endif

            {{-- COMPUTADOR --}}
            <div class="hidden md:block space-y-3">
                @foreach($plan_parts as $index => $item)
                    <div class="flex items-start gap-3"
                         wire:key="plan-part-row-desktop-{{ $index }}-{{ $item['part_id'] ?? 'new' }}">

                        <div class="flex-1 relative"
                             x-data="{ localOpen: false }"
                             @click.away="localOpen = false">

                            <flux:input
                                label="{{ $index === 0 ? 'Peça' : '' }}"
                                wire:model.live.debounce.300ms="plan_parts.{{ $index }}.search"
                                wire:focus="$set('plan_parts.{{ $index }}.open', true)"
                                @focus="localOpen = true"
                                @click="localOpen = true"
                                @keydown.escape="localOpen = false; $wire.set('plan_parts.{{ $index }}.open', false)"
                                placeholder="Pesquisar peça..."
                                autocomplete="off"
                                icon="magnifying-glass"
                            />

                            <div x-show="localOpen" x-cloak class="relative">
                                @if($item['open'] && isset($pecas[$index]))
                                    <ul class="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 py-1 shadow-lg">
                                        @forelse($pecas[$index] as $peca)
                                            <li
                                                @click="localOpen = false"
                                                wire:click="selectPeca({{ $index }}, {{ $peca->id }}, '{{ addslashes($peca->name . ' / ' . $peca->reference) }}')"
                                                class="cursor-pointer px-3 py-2 text-sm text-zinc-800 dark:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-700 flex justify-between gap-2"
                                            >
                                                <span>{{ $peca->name }}</span>
                                                <span class="text-zinc-400 text-xs">{{ $peca->reference }}</span>
                                            </li>
                                        @empty
                                            <li class="px-3 py-4 text-sm text-center text-zinc-400 dark:text-zinc-500">
                                                Nenhuma peça encontrada
                                            </li>
                                        @endforelse
                                    </ul>
                                @endif
                            </div>

                            @error("plan_parts.$index.part_id")
                            <flux:error>{{ $message }}</flux:error>
                            @enderror
                        </div>

                        <div class="w-32 shrink-0">
                            <flux:input
                                label="{{ $index === 0 ? 'Quantidade' : '' }}"
                                type="number"
                                min="1"
                                wire:model.live.debounce.300ms="plan_parts.{{ $index }}.quantity"
                            />
                            @error("plan_parts.$index.quantity")
                            <flux:error>{{ $message }}</flux:error>
                            @enderror
                        </div>

                        @php $custoUnitario = (float)($item['current_unit_cost'] ?? 0); @endphp

                        <div class="w-24 shrink-0 text-right text-sm text-zinc-500 flex flex-col justify-end">
                            @if($index === 0)
                                <span class="text-xs font-medium text-zinc-400 dark:text-zinc-500 mb-2 block truncate">Custo Unit.</span>
                            @endif
                            <div class="h-10 flex items-center justify-end">
                                {{ number_format($custoUnitario, 2, ',', '.') }} €
                            </div>
                        </div>

                        <div class="w-24 shrink-0 text-right text-sm font-medium text-zinc-800 dark:text-zinc-200 flex flex-col justify-end">
                            @if($index === 0)
                                <span class="text-xs font-medium text-zinc-400 dark:text-zinc-500 mb-2 block truncate">Total Est.</span>
                            @endif
                            <div class="h-10 flex items-center justify-end">
                                {{ number_format(intval($item['quantity'] ?? 0) * $custoUnitario, 2, ',', '.') }} €
                            </div>
                        </div>

                        <div class="shrink-0 {{ $index === 0 ? 'mt-6' : '' }}">
                            <flux:button type="button" variant="danger" icon="trash" wire:click="removePart({{ $index }})"/>
                        </div>

                    </div>
                @endforeach

            </div>

            {{-- TELEMÓVEL --}}
            <div class="md:hidden space-y-4">
                @foreach($plan_parts as $index => $item)
                    @php $custoUnitario = (float)($item['current_unit_cost'] ?? 0); @endphp

                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 space-y-3 bg-zinc-50 dark:bg-zinc-800/50"
                         wire:key="plan-part-row-mobile-{{ $index }}-{{ $item['part_id'] ?? 'new' }}">

                        <div class="flex items-end gap-2">
                            <div class="flex-1 relative"
                                 x-data="{ localOpen: false }"
                                 @click.away="localOpen = false">

                                <flux:input
                                    label="Peça"
                                    wire:model.live.debounce.300ms="plan_parts.{{ $index }}.search"
                                    wire:focus="$set('plan_parts.{{ $index }}.open', true)"
                                    @focus="localOpen = true"
                                    @click="localOpen = true"
                                    @touchstart.passive="localOpen = true"
                                    @keydown.escape="localOpen = false; $wire.set('plan_parts.{{ $index }}.open', false)"
                                    placeholder="Pesquisar peça..."
                                    autocomplete="off"
                                    icon="magnifying-glass"
                                />

                                <div x-show="localOpen" x-cloak class="relative">
                                    @if($item['open'] && isset($pecas[$index]))
                                        <ul class="absolute z-50 mt-1 max-h-48 w-full overflow-auto rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 py-1 shadow-lg">
                                            @forelse($pecas[$index] as $peca)
                                                <li
                                                    @touchend.prevent="localOpen = false; $wire.selectPeca({{ $index }}, {{ $peca->id }}, '{{ addslashes($peca->name . ' / ' . $peca->reference) }}')"
                                                    @click="localOpen = false"
                                                    wire:click="selectPeca({{ $index }}, {{ $peca->id }}, '{{ addslashes($peca->name . ' / ' . $peca->reference) }}')"
                                                    class="cursor-pointer px-3 py-3 text-sm text-zinc-800 dark:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-700 flex justify-between gap-2"
                                                >
                                                    <span>{{ $peca->name }}</span>
                                                    <span class="text-zinc-400 text-xs self-center">{{ $peca->reference }}</span>
                                                </li>
                                            @empty
                                                <li class="px-3 py-4 text-sm text-center text-zinc-400 dark:text-zinc-500">
                                                    Nenhuma peça encontrada
                                                </li>
                                            @endforelse
                                        </ul>
                                    @endif
                                </div>

                                @error("plan_parts.$index.part_id")
                                <flux:error>{{ $message }}</flux:error>
                                @enderror
                            </div>

                            <div class="shrink-0 pb-0.5">
                                <flux:button type="button" variant="danger" icon="trash" wire:click="removePart({{ $index }})"/>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <div class="w-28 shrink-0">
                                <flux:input
                                    label="Qtd."
                                    type="number"
                                    min="1"
                                    wire:model.live.debounce.300ms="plan_parts.{{ $index }}.quantity"
                                />
                                @error("plan_parts.$index.quantity")
                                <flux:error>{{ $message }}</flux:error>
                                @enderror
                            </div>

                            <div class="flex-1 flex justify-end gap-4 text-sm pt-5">
                                <div class="text-right">
                                    <div class="text-xs text-zinc-400 mb-0.5">Custo Unit.</div>
                                    <div class="text-zinc-600 dark:text-zinc-300">{{ number_format($custoUnitario, 2, ',', '.') }} €</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-xs text-zinc-400 mb-0.5">Total Est.</div>
                                    <div class="font-semibold text-zinc-900 dark:text-white">{{ number_format(intval($item['quantity'] ?? 0) * $custoUnitario, 2, ',', '.') }} €</div>
                                </div>
                            </div>
                        </div>

                    </div>
                @endforeach
            </div>

            @php
                $totalGeralPlan = $totalGeralPlan ?? collect($plan_parts)->sum(fn($item) => intval($item['quantity'] ?? 0) * (float)($item['current_unit_cost'] ?? 0));
            @endphp

            <div class="flex justify-between md:justify-end gap-3 pt-4">
                <div class="text-sm font-medium text-zinc-500 py-1">
                    Total Geral:
                </div>
                <div class="text-base font-bold text-zinc-900 dark:text-white py-0.5 md:w-24 text-right">
                    {{ number_format($totalGeralPlan, 2, ',', '.') }} €
                </div>
                <div class="hidden md:block w-10"></div>
            </div>


        </div>

        <flux:button type="button" size="sm" icon="plus" wire:click="addPart">
            Adicionar Peça
        </flux:button>

        <div class="flex gap-2 w-full">
            <flux:button type="button" variant="primary" wire:click="save" class="w-full">
                {{ $plano && $plano->exists ? 'Atualizar' : 'Criar' }}
            </flux:button>
            <flux:button type="button" wire:click="fechar" variant="danger" class="w-full">
                {{ $plano && $plano->exists ? 'Recarregar' : 'Cancelar' }}
            </flux:button>
        </div>

    </flux:card>
</div>

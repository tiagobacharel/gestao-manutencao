<?php

    use App\Models\MaintenancePart;
    use Livewire\Attributes\On;
    use Livewire\Component;
    use App\Models\Maintenance;
    use App\Models\Part;
    use Illuminate\Support\Facades\DB;

    new class extends Component {
        public ?Maintenance $manutencao = null;


        public array $maintenance_parts = [];

        public function selectPeca(int $index, int $id, string $label): void
        {
            $this->maintenance_parts[$index]['part_id'] = (string)$id;
            $this->maintenance_parts[$index]['search'] = $label;
            $this->maintenance_parts[$index]['open'] = false;
            $this->maintenance_parts[$index]['unit_cost_at_time'] = Part::where('id', $id)->value('current_unit_cost') ?? 0;
        }

        public function mount(?Maintenance $manutencao = null): void
        {
            if ($manutencao && $manutencao->exists) {
                $this->manutencao = $manutencao;

                $this->maintenance_parts = $manutencao->parts()
                    ->select('parts.id as part_id', 'parts.name', 'parts.reference', 'maintenance_parts.quantity', 'maintenance_parts.unit_cost_at_time as unit_cost')
                    ->where('maintenance_parts.maintenance_task_id', null)
                    ->get()
                    ->map(fn($part) => [
                        'part_id' => (string)$part->part_id,
                        'quantity' => (int)$part->quantity,
                        'search' => $part->name . ' / ' . $part->reference,
                        'open' => false,
                        'unit_cost_at_time' => (float)$part->unit_cost,
                    ])
                    ->toArray();
            } else {
                $this->manutencao = new Maintenance();
            }
        }

        public function addPart(): void
        {
            $this->maintenance_parts[] = [
                'part_id' => '',
                'quantity' => 1,
                'search' => '',
                'open' => false,
                'unit_cost_at_time' => 0,
            ];
        }

        public function removePart(int $index): void
        {
            unset($this->maintenance_parts[$index]);
            $this->maintenance_parts = array_values($this->maintenance_parts);
        }

        #[On('salvar-tudo')]
        public function save(): void
        {
            // 1. Validação centralizada e nativa
            $this->validate(MaintenancePart::rules($this));

            // 2. Transação que processa atualizações, inserções e remoções, retornando se houve mudanças
            $hasChanges = DB::transaction(function () {
                $now = now();
                $formParts = collect($this->maintenance_parts)->filter(fn($p) => !empty($p['part_id']));
                $formPartIds = $formParts->pluck('part_id')->all();

                $existingParts = DB::table('maintenance_parts')
                    ->where('maintenance_id', $this->manutencao->id)
                    ->whereNull('maintenance_task_id')->get()->keyBy('part_id');

                $inserts = [];
                $changed = false;

                // Processa atualizações e novos registos
                foreach ($formParts as $item) {
                    $partId = $item['part_id'];
                    $quantity = $item['quantity'];

                    if ($existingParts->has($partId)) {
                        $existing = $existingParts->get($partId);
                        if ($existing->quantity != $quantity) {
                            DB::table('maintenance_parts')->where('id', $existing->id)
                                ->update(['quantity' => $quantity, 'updated_at' => $now]);
                            $changed = true;
                        }
                    } else {
                        $inserts[] = [
                            'maintenance_id'      => $this->manutencao->id,
                            'maintenance_task_id' => null,
                            'part_id'             => $partId,
                            'quantity'            => $quantity,
                            'unit_cost_at_time'   => $item['unit_cost_at_time'] ?? 0.00,
                            'created_at'          => $now,
                            'updated_at'          => $now
                        ];
                    }
                }

                // Executa inserções pendentes
                if (!empty($inserts)) {
                    DB::table('maintenance_parts')->insert($inserts);
                    $changed = true;
                }

                // Remove as peças que deixaram de constar no formulário
                $deleteQuery = DB::table('maintenance_parts')
                    ->where('maintenance_id', $this->manutencao->id)
                    ->whereNull('maintenance_task_id')
                    ->whereNotIn('part_id', $formPartIds);

                if ($deleteQuery->exists()) {
                    $deleteQuery->delete();
                    $changed = true;
                }

                return $changed;
            });

            // 3. Feedback visual com base nas alterações detetadas
            if ($hasChanges) {
                Flux::toast('As peças foram atualizadas com sucesso!', variant: 'success', duration: 1000);
            } else {
                Flux::toast('As peças não tem alterações!', variant: 'danger', duration: 1000);
            }

            $this->fechar();
        }



        public function fechar()
        {
            if (!$this->manutencao->exists) {
                return $this->redirect(request()->header('Referer') ?? route('manutencoes.index'), navigate: true);
            }


            $this->mount($this->manutencao);

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

            foreach ($this->maintenance_parts as $i => $part) {
                if (!($part['open'] ?? false)) {
                    $pecas[$i] = collect();
                    continue;
                }

                $search = $part['search'] ?? '';

                if (blank($search)) {
                    if (is_null($pecasPadrao)) {
                        $pecasPadrao = Part::orderBy('name')->limit(10)->get(['id', 'name', 'reference']);
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
            <flux:heading size="lg">Peças sem Tarefa</flux:heading>
            <flux:text size="sm" class="text-zinc-400 mt-1">
                Adicione ou remova as peças gastas nesta manutenção.
            </flux:text>
        </div>

        <flux:separator variant="subtle"/>

        <div class="space-y-4">

            @if(count($maintenance_parts) === 0)
                <div class="text-center p-4 border border-dashed rounded-lg border-zinc-200 dark:border-zinc-800">
                    <flux:text size="sm" class="text-zinc-400">Nenhuma peça adicionada até ao momento.</flux:text>
                </div>
            @endif

            <div class="space-y-3">
                <div class="space-y-4">
                    @foreach($maintenance_parts as $index => $item)
                        @php
                            $custoUnitario = (float)($item['unit_cost_at_time'] ?? 0);
                        @endphp

                        {{-- TELEMÓVEL --}}
                        <div class="block md:hidden p-4 mb-4 rounded-xl bg-zinc-50/50 dark:bg-zinc-900/30 border border-zinc-200/60 dark:border-zinc-800/60 shadow-sm">
                            <div class="grid grid-cols-12 gap-3 items-end w-full">

                                <div class="col-span-12 relative" x-data="{ localOpen: false }" x-on:click.outside="localOpen = false">
                                    <flux:field>
                                        @if($index === 0) <flux:label class="mb-1 block">Peça</flux:label> @endif
                                        <flux:input
                                            wire:model.live.debounce.300ms="maintenance_parts.{{ $index }}.search"
                                            wire:focus="$set('maintenance_parts.{{ $index }}.open', true)"
                                            @focus="localOpen = true"
                                            @click="localOpen = true"
                                            @touchstart.passive="localOpen = true"
                                            @keydown.escape="localOpen = false"
                                            placeholder="Pesquisar nome / referencia"
                                            autocomplete="off"
                                            icon="magnifying-glass"
                                            :invalid="$errors->has('maintenance_parts.'.$index.'.part_id')"
                                        />
                                    </flux:field>

                                    <div x-show="localOpen" x-cloak class="relative">
                                        @if($item['open'] && isset($pecas[$index]))
                                            <ul class="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 py-1 shadow-lg">
                                                @forelse($pecas[$index] as $peca)
                                                    <li
                                                        @touchend.prevent="localOpen = false; $wire.selectPeca({{ $index }}, {{ $peca->id }}, '{{ addslashes($peca->name . ' / ' . $peca->reference) }}')"
                                                        wire:click="selectPeca({{ $index }}, {{ $peca->id }}, '{{ addslashes($peca->name . ' / ' . $peca->reference) }}')"
                                                        @click="localOpen = false"
                                                        class="cursor-pointer px-3 py-2 text-sm text-zinc-800 dark:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-700 flex justify-between gap-2"
                                                    >
                                                        <span>{{ $peca->name }}</span>
                                                        <span class="text-zinc-400 text-xs">{{ $peca->reference }}</span>
                                                    </li>
                                                @empty
                                                    <li class="px-3 py-4 text-sm text-center text-zinc-400 dark:text-zinc-500">Nenhuma peça encontrada</li>
                                                @endforelse
                                            </ul>
                                        @endif
                                    </div>
                                </div>

                                <div class="col-span-4">
                                    <flux:field>
                                        @if($index === 0) <flux:label class="mb-1 block">Quantidade</flux:label> @endif
                                        <flux:input type="number" min="1" wire:model.live.debounce.300ms="maintenance_parts.{{ $index }}.quantity" class="w-full" />
                                    </flux:field>
                                </div>

                                <div class="col-span-3 text-right text-sm text-zinc-500 flex flex-col justify-end pb-2">
                                    <span class="text-xs font-medium text-zinc-400 dark:text-zinc-500 mb-1 block truncate">Custo Un.</span>
                                    <div class="h-10 flex items-center justify-end">{{ number_format($custoUnitario, 2, ',', '.') }} €</div>
                                </div>

                                <div class="col-span-3 text-right text-sm font-medium text-zinc-800 dark:text-zinc-200 flex flex-col justify-end pb-2">
                                    <span class="text-xs font-medium text-zinc-400 dark:text-zinc-500 mb-1 block truncate">Total</span>
                                    <div class="h-10 flex items-center justify-end">{{ number_format(intval($item['quantity'] ?? 0) * $custoUnitario, 2, ',', '.') }} €</div>
                                </div>

                                <div class="col-span-2 text-right pr-0.5">
                                    <flux:button type="button" variant="danger" icon="trash" wire:click="removePart({{ $index }})" square class="w-full" />
                                </div>
                            </div>
                            @error("maintenance_parts.$index.quantity") <flux:error class="mt-2">{{ $message }}</flux:error> @enderror
                        </div>

                        {{-- COMPUTADOR --}}
                        <div class="hidden md:flex md:gap-2 md:items-start md:w-full md:mb-3">

                            <div class="md:flex-1 relative" x-data="{ localOpen: false }" x-on:click.outside="localOpen = false">
                                <flux:field>
                                    @if($index === 0) <flux:label class="mb-1.5 block">Peça</flux:label> @endif
                                    <flux:input
                                        wire:model.live.debounce.300ms="maintenance_parts.{{ $index }}.search"
                                        wire:focus="$set('maintenance_parts.{{ $index }}.open', true)"
                                        @focus="localOpen = true"
                                        @click="localOpen = true"
                                        @keydown.escape="localOpen = false"
                                        placeholder="Pesquisar nome / referencia"
                                        autocomplete="off"
                                        icon="magnifying-glass"
                                        :invalid="$errors->has('maintenance_parts.'.$index.'.part_id')"
                                    />
                                </flux:field>

                                <div x-show="localOpen" x-cloak class="relative">
                                    @if($item['open'] && isset($pecas[$index]))
                                        <ul class="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 py-1 shadow-lg">
                                            @forelse($pecas[$index] as $peca)
                                                <li
                                                    wire:click="selectPeca({{ $index }}, {{ $peca->id }}, '{{ addslashes($peca->name . ' / ' . $peca->reference) }}')"
                                                    @click="localOpen = false"
                                                    class="cursor-pointer px-3 py-2 text-sm text-zinc-800 dark:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-700 flex justify-between gap-2"
                                                >
                                                    <span>{{ $peca->name }}</span>
                                                    <span class="text-zinc-400 text-xs">{{ $peca->reference }}</span>
                                                </li>
                                            @empty
                                                <li class="px-3 py-4 text-sm text-center text-zinc-400 dark:text-zinc-500">Nenhuma peça encontrada</li>
                                            @endforelse
                                        </ul>
                                    @endif
                                </div>
                            </div>

                            <div class="md:w-24 md:shrink-0">
                                <flux:field>
                                    @if($index === 0) <flux:label class="mb-1.5 block">Quantidade</flux:label> @endif
                                    <flux:input type="number" min="1" wire:model.live.debounce.300ms="maintenance_parts.{{ $index }}.quantity" class="w-full" />
                                </flux:field>
                                @error("maintenance_parts.$index.quantity") <flux:error class="mt-1">{{ $message }}</flux:error> @enderror
                            </div>

                            <div class="md:w-24 md:shrink-0 text-right text-sm text-zinc-500 flex flex-col justify-end">
                                @if($index === 0) <span class="text-xs font-medium text-zinc-400 dark:text-zinc-500 mb-2 block truncate">Custo Unit.</span> @endif
                                <div class="h-10 flex items-center justify-end">{{ number_format($custoUnitario, 2, ',', '.') }} €</div>
                            </div>

                            <div class="md:w-24 md:shrink-0 text-right text-sm font-medium text-zinc-800 dark:text-zinc-200 flex flex-col justify-end">
                                @if($index === 0) <span class="text-xs font-medium text-zinc-400 dark:text-zinc-500 mb-2 block truncate">Total Est.</span> @endif
                                <div class="h-10 flex items-center justify-end">{{ number_format(intval($item['quantity'] ?? 0) * $custoUnitario, 2, ',', '.') }} €</div>
                            </div>

                            <div class="md:shrink-0 md:w-10 {{ $index === 0 ? 'md:mt-6' : '' }}">
                                <flux:button type="button" variant="danger" icon="trash" wire:click="removePart({{ $index }})" square class="w-full md:w-10" />
                            </div>

                        </div>
                    @endforeach
                </div>


                @php
                    $totalGeralPlan = collect($maintenance_parts)->sum(function($item) {
                        return intval($item['quantity'] ?? 0) * (float)($item['unit_cost_at_time'] ?? 0);
                    });
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

            <flux:button type="button" size="sm" icon="plus" wire:click="addPart" class="w-full md:w-auto">
                Adicionar Peça
            </flux:button>

        </div>

        <div class="flex items-center gap-2">
            <flux:button type="button" variant="primary" wire:click="save" class="w-full">
                {{ $manutencao && $manutencao->exists ? 'Atualizar' : 'Criar' }}
            </flux:button>
            <flux:button type="button" wire:click="fechar" variant="danger" class="w-full">
                {{ $manutencao && $manutencao->exists ? 'Recarregar' : 'Cancelar' }}
            </flux:button>
        </div>

    </flux:card>
</div>


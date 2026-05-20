<?php

use App\Models\MaintenancePlan;
use App\Models\Part;
use Livewire\Component;

new class extends Component {

    public ?MaintenancePlan $plano = null;

    public array $plan_parts = [];

    public function selectPeca(int $index, int $id, string $label): void
    {
        $this->plan_parts[$index]['part_id'] = (string) $id;
        $this->plan_parts[$index]['search']  = $label;
        $this->plan_parts[$index]['open']    = false;
    }

    public function mount(?MaintenancePlan $plano = null): void
    {
        if ($plano && $plano->exists) {
            $this->plano = $plano;

            $this->plan_parts = $plano->planParts()
                ->select('plan_parts.part_id', 'parts.name', 'parts.reference', 'plan_parts.quantity')
                ->join('parts', 'parts.id', '=', 'plan_parts.part_id')
                ->get()
                ->map(fn($p) => [
                    'part_id'  => (string) $p->part_id,
                    'quantity' => (int) $p->quantity,
                    'search'   => $p->name . ' / ' . $p->reference,
                    'open'     => false,
                ])
                ->toArray();
        } else {
            $this->plano = new MaintenancePlan();
        }
    }

    public function addPart(): void
    {
        $this->plan_parts[] = [
            'part_id'  => '',
            'quantity' => 1,
            'search'   => '',
            'open'     => false,
        ];
    }

    public function removePart(int $index): void
    {
        unset($this->plan_parts[$index]);
        $this->plan_parts = array_values($this->plan_parts);
    }

    public function save(): void
    {
        $this->validate([
            'plan_parts'            => ['nullable', 'array'],
            'plan_parts.*.part_id'  => ['required', 'exists:parts,id'],
            'plan_parts.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        \DB::transaction(function () {
            $syncData = [];

            foreach ($this->plan_parts as $item) {
                if (empty($item['part_id'])) continue;

                // Se vier duplicado, a última quantidade vence (igual ao primeiro componente)
                $syncData[$item['part_id']] = [
                    'quantity' => $item['quantity'],
                ];
            }

            // sync() faz delete das removidas + update das existentes + insert das novas
            $this->plano->parts()->sync($syncData);
        });

        $this->dispatch('pecas-plano-atualizadas');
        $this->fechar();
    }


    public function fechar(): mixed
    {
        return $this->redirect(request()->header('Referer') ?? route('planos_manutencoes.index'), navigate: true);
    }

    public function with(): array
    {
        $pecas = [];
        foreach ($this->plan_parts as $i => $part) {
            $search = $part['search'] ?? '';
            $pecas[$i] = Part::orderBy('name')
                ->when(
                    strlen($search) >= 1,
                    fn($q) => $q->where(function ($q) use ($search) {
                        $q->where('name', 'like', '%' . $search . '%')
                            ->orWhere('reference', 'like', '%' . $search . '%');
                    })
                )
                ->limit(10)
                ->get(['id', 'name', 'reference']);
        }

        return ['pecas' => $pecas];
    }
};
?>

<div>
    <flux:card class="space-y-6">

        <div>
            <flux:heading size="lg">Peças do Plano</flux:heading>
            <flux:text size="sm" class="text-zinc-400 mt-1">
                Define as peças esperadas para o plano "{{ $plano->name }}".
            </flux:text>
        </div>

        <flux:separator variant="subtle" />

        <div class="space-y-4">

            @if(count($plan_parts) === 0)
                <div class="text-center p-4 border border-dashed rounded-lg border-zinc-200 dark:border-zinc-800">
                    <flux:text size="sm" class="text-zinc-400">Nenhuma peça adicionada ao plano.</flux:text>
                </div>
            @endif

                <div class="space-y-3">
                    @foreach($plan_parts as $index => $item)
                        <div class="flex items-start gap-3" wire:key="plan-part-row-{{ $index }}-{{ $item['part_id'] ?? 'new' }}">

                            <div class="flex-1">
                                <flux:input
                                    label="{{ $index === 0 ? 'Peça' : '' }}"
                                    wire:model.live.debounce.300ms="plan_parts.{{ $index }}.search"
                                    wire:focus="$set('plan_parts.{{ $index }}.open', true)"
                                    @keydown.escape="$wire.set('plan_parts.{{ $index }}.open', false)"
                                    placeholder="Pesquisar peça..."
                                    autocomplete="off"
                                    icon="magnifying-glass"
                                />

                                <div class="relative">
                                    @if($item['open'] && isset($pecas[$index]))
                                        <ul class="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 py-1 shadow-lg">
                                            @forelse($pecas[$index] as $peca)
                                                <li
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
                                    label="{{ $index === 0 ? 'Qtd.' : '' }}"
                                    type="number"
                                    min="1"
                                    wire:model="plan_parts.{{ $index }}.quantity"
                                />
                                @error("plan_parts.$index.quantity")
                                <flux:error>{{ $message }}</flux:error>
                                @enderror
                            </div>

                            <div class="shrink-0 {{ $index === 0 ? 'mt-6' : '' }}">
                                <flux:button
                                    type="button"
                                    variant="danger"
                                    icon="trash"
                                    wire:click="removePart({{ $index }})"
                                />
                            </div>

                        </div>
                    @endforeach
                </div>

            <flux:button type="button" size="sm" icon="plus" wire:click="addPart">
                Adicionar Peça
            </flux:button>

        </div>

        <div class="flex gap-2 w-full">
            <flux:button type="button" variant="primary" wire:click="save" class="w-full">
                Gravar Peças
            </flux:button>
            <flux:button type="button" wire:click="fechar" variant="danger" class="w-full">
                Cancelar
            </flux:button>
        </div>

    </flux:card>
</div>

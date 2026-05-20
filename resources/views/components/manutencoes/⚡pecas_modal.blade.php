<?php

use Livewire\Component;
use App\Models\Maintenance;
use App\Models\Part;
use Illuminate\Support\Facades\DB;

new class extends Component
{
    public ?Maintenance $manutencao = null;


    public array $maintenance_parts = [];

    public function selectPeca(int $index, int $id, string $label): void
    {
        $this->maintenance_parts[$index]['part_id'] = (string) $id;
        $this->maintenance_parts[$index]['search']  = $label;
        $this->maintenance_parts[$index]['open']    = false;
    }

    public function mount(?Maintenance $manutencao = null): void
    {
        if ($manutencao && $manutencao->exists) {
            $this->manutencao = $manutencao;
            $this->maintenance_parts = $manutencao->parts()
                ->select('parts.id as part_id', 'parts.name', 'parts.reference', 'maintenance_parts.quantity')
                ->get()
                ->map(fn($part) => [
                    'part_id'  => (string) $part->part_id,
                    'quantity' => (int) $part->quantity,
                    'search'   => $part->name . ' / ' . $part->reference,
                    'open'     => false, // estava a faltar esta linha
                ])
                ->toArray();
        } else {
            $this->manutencao = new Maintenance();
        }
    }

    public function addPart(): void
    {
        $this->maintenance_parts[] = [
            'part_id'  => '',
            'quantity' => 1,
            'search'   => '',
            'open'     => false,
        ];
    }

    public function removePart(int $index): void
    {
        unset($this->maintenance_parts[$index]);
        $this->maintenance_parts = array_values($this->maintenance_parts);
    }

    public function save(): void
    {
        $this->validate([
            'maintenance_parts'            => ['nullable', 'array'],
            'maintenance_parts.*.part_id'  => ['required', 'exists:parts,id'],
            'maintenance_parts.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        DB::transaction(function () {
            $syncData = [];

            $partIds   = collect($this->maintenance_parts)->pluck('part_id')->filter()->toArray();
            $partCosts = Part::whereIn('id', $partIds)->pluck('current_unit_cost', 'id');

            foreach ($this->maintenance_parts as $item) {
                if (empty($item['part_id'])) continue;

                $syncData[$item['part_id']] = [
                    'quantity'          => $item['quantity'],
                    'unit_cost_at_time' => $partCosts[$item['part_id']] ?? 0.00,
                ];
            }

            $this->manutencao->parts()->sync($syncData);
        });

        $this->dispatch('pecas-atualizadas');
        $this->fechar();
    }

    public function fechar(): mixed
    {
        return $this->redirect(request()->header('Referer') ?? route('manutencoes.index'), navigate: true);
    }

    public function with(): array
    {
        $pecas = [];
        foreach ($this->maintenance_parts as $i => $part) {
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
            <flux:heading size="lg">Peças Utilizadas</flux:heading>
            <flux:text size="sm" class="text-zinc-400 mt-1">
                Adicione ou remova as peças gastas nesta manutenção.
            </flux:text>
        </div>

        <flux:separator variant="subtle" />

        <div class="space-y-4">

            @if(count($maintenance_parts) === 0)
                <div class="text-center p-4 border border-dashed rounded-lg border-zinc-200 dark:border-zinc-800">
                    <flux:text size="sm" class="text-zinc-400">Nenhuma peça adicionada até ao momento.</flux:text>
                </div>
            @endif

            <div class="space-y-3">
                @foreach($maintenance_parts as $index => $item)
                    <div class="flex gap-2 items-start"> {{-- linha inteira --}}

                        <div class="flex-1"> {{-- input de pesquisa --}}
                            <flux:input
                                label="{{ $index === 0 ? 'Peça' : '' }}"
                                wire:model.live.debounce.300ms="maintenance_parts.{{ $index }}.search"
                                wire:focus="$set('maintenance_parts.{{ $index }}.open', true)"
                                @keydown.escape="$wire.set('maintenance_parts.{{ $index }}.open', false)"
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

                            @error("maintenance_parts.$index.part_id")
                            <flux:error>{{ $message }}</flux:error>
                            @enderror
                        </div>

                        <div class="w-24 shrink-0"> {{-- quantidade --}}
                            <flux:input
                                label="{{ $index === 0 ? 'Qtd.' : '' }}"
                                type="number"
                                min="1"
                                wire:model="maintenance_parts.{{ $index }}.quantity"
                                class="w-full"
                            />
                            @error("maintenance_parts.$index.quantity")
                            <flux:error>{{ $message }}</flux:error>
                            @enderror
                        </div>

                        <div class="shrink-0 {{ $index === 0 ? 'mt-6' : '' }}"> {{-- botão apagar --}}
                            <flux:button
                                type="button"
                                variant="danger"
                                icon="trash"
                                wire:click="removePart({{ $index }})"
                                square
                            />
                        </div>

                    </div> {{-- fecha linha --}}
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

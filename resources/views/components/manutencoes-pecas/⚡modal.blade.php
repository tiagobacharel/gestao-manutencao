<?php

use Livewire\Volt\Component;
use App\Models\Maintenance;
use App\Models\Part;
use Illuminate\Support\Facades\DB;

new class extends Component
{
    // Vinculação direta do modelo
    public Maintenance $manutencao;

    // Propriedade que armazena as peças dinâmicas
    public array $maintenance_parts = [];

    public function mount(Maintenance $manutencao): void
    {
        $this->manutencao = $manutencao;

        if ($manutencao->exists) {
            // Carrega os dados da tabela pivot
            $this->maintenance_parts = $manutencao->parts()
                ->select('parts.id as part_id', 'maintenance_parts.quantity')
                ->get()
                ->map(fn($part) => [
                    'part_id' => (string) $part->part_id, // Cast para string evita bugs no <select>
                    'quantity' => (int) $part->quantity
                ])
                ->toArray();
        }
    }

    public function addPart(): void
    {
        $this->maintenance_parts[] = ['part_id' => '', 'quantity' => 1];
    }

    public function removePart(int $index): void
    {
        unset($this->maintenance_parts[$index]);
        // Reindexar o array é obrigatório para manter o Livewire funcional
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

            // Otimização: Procura todos os custos de uma só vez para evitar queries em loop (N+1)
            $partIds = collect($this->maintenance_parts)->pluck('part_id')->filter()->toArray();
            $partCosts = Part::whereIn('id', $partIds)->pluck('cost', 'id');

            foreach ($this->maintenance_parts as $item) {
                if (empty($item['part_id'])) continue;

                $syncData[$item['part_id']] = [
                    'quantity'          => $item['quantity'],
                    'unit_cost_at_time' => $partCosts[$item['item_id']] ?? 0.00
                ];
            }

            $this->manutencao->parts()->sync($syncData);
        });

        $this->dispatch('pecas-atualizadas');
    }

    public function with(): array
    {
        return [
            'pecas' => Part::orderBy('name')->pluck('name', 'id')
        ];
    }
};
?>

<div class="space-y-4">
    <div class="flex justify-between items-center">
        <div>
            <flux:heading size="md">Peças Utilizadas</flux:heading>
            <flux:text size="sm" class="text-zinc-400">Adicione as peças gastas nesta manutenção.</flux:text>
        </div>
        <flux:button type="button" size="sm" icon="plus" wire:click="addPart">
            Adicionar Peça
        </flux:button>
    </div>

    @if(count($maintenance_parts) === 0)
        <div class="text-center p-4 border border-dashed rounded-lg border-zinc-200 dark:border-zinc-800">
            <flux:text size="sm" class="text-zinc-400">Nenhuma peça adicionada até ao momento.</flux:text>
        </div>
    @endif

    <div class="space-y-3">
        @foreach($maintenance_parts as $index => $item)
            {{-- ATENÇÃO: wire:key alterado para usar o ID interno se existir, ou o index fixo --}}
            <div class="flex items-end gap-3" wire:key="part-row-{{ $index }}-{{ $item['part_id'] ?? 'new' }}">

                <!-- Seleção da Peça -->
                <div class="flex-1">
                    {{-- Correção do label condicional do Flux UI --}}
                    <flux:select
                        label="{{ $index === 0 ? 'Peça' : '' }}"
                        wire:model="maintenance_parts.{{ $index }}.part_id"
                        placeholder="Escolha a peça..."
                    >
                        @foreach($pecas as $id => $nome)
                            <flux:select.option value="{{ $id }}">{{ $nome }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <!-- Quantidade -->
                <div class="w-32">
                    {{-- Correção do label condicional do Flux UI --}}
                    <flux:input
                        label="{{ $index === 0 ? 'Qtd.' : '' }}"
                        type="number"
                        min="1"
                        wire:model="maintenance_parts.{{ $index }}.quantity"
                    />
                </div>

                <!-- Botão Remover -->
                {{-- Alinhamento do botão corrigido com classe condicional dependendo do label --}}
                <div class="{{ $index === 0 ? 'h-10 flex items-center' : '' }}">
                    <flux:button
                        type="button"
                        variant="danger"
                        icon="trash"
                        wire:click="removePart({{ $index }})"
                    />
                </div>
            </div>

            <!-- Validações de Erro -->
            @error("maintenance_parts.$index.part_id")
            <flux:error class="mt-1">{{ $message }}</flux:error>
            @enderror
            @error("maintenance_parts.$index.quantity")
            <flux:error class="mt-1">{{ $message }}</flux:error>
            @enderror
        @endforeach
    </div>

    {{-- Botão de Gravar incluído para testar a ação --}}
    <div class="flex justify-end mt-4">
        <flux:button type="button" variant="primary" wire:click="save">Gravar Peças</flux:button>
    </div>
</div>

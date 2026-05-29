<?php

use Livewire\Component;
use App\Models\Part;

new class extends Component
{
    public ?Part $part = null;

    public string $reference = '';
    public string $name = '';
    public string $description = '';
    public int $stock_current = 0;
    public string $current_unit_cost = '0.00';


    public function mount(?Part $part = null): void
    {
        if ($part && $part->exists) {
            $this->part             = $part;
            $this->reference        = $part->reference;
            $this->name             = $part->name;
            $this->description      = $part->description ?? '';
            $this->stock_current    = $part->stock_current;
            $this->current_unit_cost = number_format($part->current_unit_cost, 2, '.', '');
        } else {
            $this->part = new Part();
        }
    }


    public function save()
    {
        $this->validate(Part::rules($this->part?->id));

        $isNew = !$this->part->exists;

        $this->part->fill([
            'reference'         => $this->reference,
            'name'              => $this->name,
            'description'       => $this->description ?: null,
            'stock_current'     => $this->stock_current,
            'current_unit_cost' => $this->current_unit_cost,
        ]);
        $this->part->save();

        if ($isNew) {
            Flux::toast('A peça foi criada com sucesso!', variant: 'success', duration: 1000);

            return $this->redirect(route('pecas.index'), navigate: true);
        }

        Flux::toast('A peça foi atualizado com sucesso!', variant: 'success', duration: 1000);

        return $this->fechar();


    }

    public function fechar()
    {
        if(!$this->part->exists){
            return $this->redirect(request()->header('Referer') ?? route('pecas.index'), navigate: true);
        }

        $this->part->refresh();

        $this->name = $this->part->name;
        $this->reference = $this->part->reference;
        $this->description = $this->part->description ?? '';
        $this->stock_current = $this->part->stock_current;
        $this->current_unit_cost = $this->part->current_unit_cost;

        $this->resetErrorBag();
    }
};
?>

<div>
    <form wire:submit="save">
        <flux:card class="space-y-6">

            <div class="space-y-6">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:input
                        label="Referência"
                        name="reference"
                        wire:model.blur="reference"
                        placeholder="Ex: REF-001"
                        icon="tag"
                        copyable
                    />
                    <flux:input
                        label="Nome"
                        name="name"
                        wire:model.blur="name"
                        placeholder="Ex: Filtro de óleo"
                    />
                </div>

                <flux:textarea
                    label="Descrição"
                    name="description"
                    wire:model.blur="description"
                    placeholder="Detalhes da peça..."
                />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:input
                        label="Stock atual"
                        name="stock"
                        wire:model.blur="stock_current"
                        type="number"
                        min="0"
                        icon="cube"
                    />
                    <flux:input
                        label="Custo unitário (€) / Total: {{ number_format($part->stock_value, 2, ',', '.') }} €"
                        name="unit_cost"
                        wire:model.blur="current_unit_cost"
                        type="number"
                        min="0"
                        step="0.01"
                        icon="currency-euro"
                    />
                </div>
            </div>

            <div class="flex gap-2 w-full">
                <flux:button type="submit" variant="primary" class="w-full">
                    {{ $part && $part->exists ? 'Atualizar' : 'Criar' }}
                </flux:button>
                <flux:button wire:click="fechar" type="button" variant="danger" class="w-full">
                    {{ $part && $part->exists ? 'Recarregar' : 'Cancelar' }}
                </flux:button>
            </div>

        </flux:card>
    </form>
</div>

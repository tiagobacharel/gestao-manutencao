<?php

use App\Models\Part;
use Livewire\Volt\Component;

new class extends Component {

    public Part $part;
    public string $title = '';

    public function mount(Part $part): void
    {
        $this->part = $part;
        $this->title = "Peça #{$part->reference}";
    }

    public function rendering($view): void
    {
        $view->layoutData(['title' => $this->title]);
    }

    public function delete(): void
    {
        $this->part->delete();
        $this->redirect(route('pecas.index'), navigate: true);
    }

};
?>

<div>
    <flux:main container class="space-y-6">

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <flux:button @click="history.back()" icon="arrow-left" variant="subtle" size="sm" />
                <flux:heading size="xl" level="1">{{ $part->name }}</flux:heading>
            </div>

            @php
                $stockBadge = $part->stock_badge;
            @endphp


            <flux:button
                wire:click="delete"
                wire:confirm="Tem a certeza que quer apagar esta peça?"
                variant="danger"
                icon="trash"
                class=" justify-start w-full sm:w-auto"
            >
                Apagar
            </flux:button>
        </div>

        <flux:separator variant="subtle" />

        <livewire:pecas_modal :part="$part" />

    </flux:main>
</div>

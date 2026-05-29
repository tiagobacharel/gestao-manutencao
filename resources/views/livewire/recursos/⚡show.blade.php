<?php

use App\Models\Maintenance;
use App\Models\Resource;
use Livewire\Volt\Component;

new class extends Component {

    public Resource $recurso;

    public function rendering($view): void
    {
        $view->layoutData(['title' => $this->recurso->name]);
        $this->recurso->loadMissing('photos');
    }

    protected mixed $manutencoesCache = null;

    public function with(): array
    {
        if ($this->manutencoesCache == null) {
            $this->manutencoesCache = $this->recurso->maintenances()
                ->with(['plan', 'parts'])
                ->latest('scheduled_at')
                ->limit(5)
                ->get();
        }

        return [
            'manutencoes' => $this->manutencoesCache,
        ];
    }

    public function toggleStatus(): void
    {
        $this->recurso->status = $this->recurso->status === 'active' ? 'inactive' : 'active';
        $this->recurso->save();
    }


    public function delete()
    {
        $this->recurso->delete();

        return $this->redirect('/recursos', navigate: true);
    }


};
?>

<div>
    <div>
        <flux:main container class="space-y-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <flux:button @click="history.back()" icon="arrow-left" variant="subtle" size="sm" />


                </div>

                <div>
                    <flux:button wire:click="delete" wire:confirm="Tem a certeza que quer apagar esta tarefa?" variant="danger" icon="trash" class="w-full justify-start" >
                        Apagar
                    </flux:button>
                </div>

            </div>

            <flux:separator variant="subtle" />
            <div>
                <livewire:recursos.modal :recurso="$recurso" />

                <flux:card class="space-y-4">
                    <div class="flex items-center justify-between">
                        <flux:heading size="md">Histórico das utimas 5 Manutenções</flux:heading>
                    </div>

                    @if($manutencoes->isEmpty())
                        <flux:text class="text-zinc-400 text-sm">
                            Nenhuma manutenção registada para este equipamento.
                        </flux:text>
                    @else
                        <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach($manutencoes as $m)
                                @php
                                    $badge = $m->status_badge;
                                @endphp
                                <a href="{{ route('manutencoes.show', $m) }}" wire:navigate
                                   class="flex items-center justify-between py-3 gap-4 hover:bg-zinc-50 dark:hover:bg-zinc-800 -mx-1 px-1 rounded-lg transition-colors group">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <flux:badge color="{{ $badge['color'] }}" icon="{{ $badge['icon'] }}" size="sm">
                                            {{ $badge['label'] }}
                                        </flux:badge>
                                        <div class="min-w-0 hidden md:block">
                                            <flux:text size="sm" class="font-medium truncate">
                                                {{ $m->scheduled_at?->format('d/m/Y') ?? '—' }}
                                            </flux:text>
                                            @if($m->plan)
                                                <flux:text size="xs" class="text-zinc-400 truncate">{{ $m->plan->name }}</flux:text>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 shrink-0">
                                        @if($m->parts->count())
                                            <flux:badge color="zinc" size="sm">{{ $m->parts->count() }} peça(s)</flux:badge>
                                        @endif
                                        @if($m->total_cost !== null)
                                            <span class="text-sm font-semibold">
                                                        {{ number_format($m->total_cost, 2, ',', '.') }} €
                                                    </span>
                                        @endif
                                        <flux:icon name="chevron-right" variant="micro"
                                                   class="text-zinc-300 group-hover:text-zinc-500 transition-colors" />
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </flux:card>

            </div>

        </flux:main>
    </div>
</div>

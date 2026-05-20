<?php

use Livewire\Component;
use App\Models\Maintenance;
use App\Models\MaintenancePlan;
use App\Models\Resource;

new class extends Component
{
    public ?Maintenance $manutencao = null;

    public $maintenance_plan_id = '';
    public $resource_id = '';
    public $scheduled_at = '';
    public $status = 'pending';
    public $notes = '';

    public string $recurso_search = '';
    public bool $recurso_open = false;

    public string $plano_search = '';
    public bool $plano_open = false;


    public function selectRecurso(int $id, string $nome): void
    {
        $this->resource_id    = $id;
        $this->recurso_search = $nome;
        $this->recurso_open   = false;
    }

    public function selectPlano(int $id, string $nome): void
    {
        $this->maintenance_plan_id = $id;
        $this->plano_search        = $nome;
        $this->plano_open          = false;
    }

    public function mount(?Maintenance $manutencao = null): void
    {
        if ($manutencao && $manutencao->exists) {
            $this->manutencao           = $manutencao;
            $this->maintenance_plan_id  = $manutencao->maintenance_plan_id ?? '';
            $this->resource_id          = $manutencao->resource_id;
            $this->scheduled_at         = $manutencao->scheduled_at?->format('Y-m-d') ?? '';
            $this->status               = $manutencao->status;
            $this->notes                = $manutencao->notes ?? '';

            $this->recurso_search = $manutencao->resource->name ?? '';

            $this->plano_search = $manutencao->maintenancePlan->name ?? '';

        } else {
            $this->manutencao = new Maintenance();
        }
    }

    public function save(): void
    {
        $this->validate(Maintenance::rules());

        $this->manutencao->fill([
            'maintenance_plan_id' => $this->maintenance_plan_id ?: null,
            'resource_id'         => $this->resource_id,
            'scheduled_at'        => $this->scheduled_at ?: null,
            'status'              => $this->status,
            'notes'               => $this->notes ?: null,
            /*
              'created_by'          => $this->manutencao->exists
                ? $this->manutencao->created_by
                : auth()->id(),
             */
        ]);
        $this->manutencao->save();

        $this->dispatch('manutencao-saved');
        $this->fechar();
    }

    public function fechar()
    {
        return $this->redirect(request()->header('Referer') ?? route('manutencoes.index'), navigate: true);
    }

    public function with(): array
    {
        return [
            'recursos' => Resource::orderBy('name')
                ->when(
                    strlen($this->recurso_search) >= 1,
                    fn($q) => $q->where('name', 'like', '%' . $this->recurso_search . '%')
                )
                ->limit(10)
                ->pluck('name', 'id'),
            'planos' => MaintenancePlan::orderBy('name')
                ->when(
                    strlen($this->plano_search) >= 1,
                    fn($q) => $q->where('name', 'like', '%' . $this->plano_search . '%')
                )
                ->limit(10)
                ->pluck('name', 'id'),
        ];
    }
};
?>

<div>
    <form wire:submit="save">
        <flux:card class="space-y-6">

            <div>
                <flux:heading size="lg">
                    {{ $manutencao && $manutencao->exists ? 'Editar Manutenção' : 'Nova Manutenção' }}
                </flux:heading>
                <flux:text size="sm" class="text-zinc-400 mt-1">
                    {{ $manutencao && $manutencao->exists ? "Manutenção #{$manutencao->id}" : 'Preenche os dados abaixo.' }}
                </flux:text>
            </div>

            <flux:separator variant="subtle" />

            <div class="space-y-4">

                <div x-data="{ open: @entangle('recurso_open') }">
                    <flux:input
                        label="Recurso"
                        wire:model.live.debounce.300ms="recurso_search"
                        wire:focus="$set('recurso_open', true)"
                        @click="open = true"
                        @keydown.escape="open = false"
                        @keydown.tab="open = false"
                        placeholder="Pesquisar recurso..."
                        autocomplete="off"
                        icon="magnifying-glass"
                    />

                    <div class="relative">
                        <ul
                            x-show="open"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            @click.outside="open = false"
                            class="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 py-1 shadow-lg"
                        >
                            @forelse($recursos as $id => $nome)
                                <li
                                    wire:click="selectRecurso({{ $id }}, '{{ addslashes($nome) }}')"
                                    @click="open = false"
                                    class="cursor-pointer px-3 py-2 text-sm text-zinc-800 dark:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-700 flex items-center gap-2"
                                >
                                    {{ $nome }}
                                </li>
                            @empty
                                <li class="px-3 py-4 text-sm text-center text-zinc-400 dark:text-zinc-500">
                                    Nenhum recurso encontrado
                                </li>
                            @endforelse
                        </ul>
                    </div>

                    <flux:error name="resource_id" />
                </div>

                <div x-data="{ open: @entangle('plano_open') }">
                    <flux:input
                        label="Plano de manutenção"
                        wire:model.live.debounce.300ms="plano_search"
                        wire:focus="$set('plano_open', true)"
                        @click="open = true"
                        @keydown.escape="open = false"
                        @keydown.tab="open = false"
                        placeholder="Sem plano (opcional)"
                        autocomplete="off"
                        icon="magnifying-glass"
                    />

                    <div class="relative">
                        <ul
                            x-show="open"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            @click.outside="open = false"
                            class="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 py-1 shadow-lg"
                        >
                            <li
                                wire:click="$set('maintenance_plan_id', ''); $set('plano_search', ''); $set('plano_open', false)"
                                @click="open = false"
                                class="cursor-pointer px-3 py-2 text-sm text-zinc-400 dark:text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-700 italic"
                            >
                                Sem plano
                            </li>
                            @forelse($planos as $id => $nome)
                                <li
                                    wire:click="selectPlano({{ $id }}, '{{ addslashes($nome) }}')"
                                    @click="open = false"
                                    class="cursor-pointer px-3 py-2 text-sm text-zinc-800 dark:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-700"
                                >
                                    {{ $nome }}
                                </li>
                            @empty
                                <li class="px-3 py-4 text-sm text-center text-zinc-400 dark:text-zinc-500">
                                    Nenhum plano encontrado
                                </li>
                            @endforelse
                        </ul>
                    </div>

                    <flux:error name="maintenance_plan_id" />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:input
                        label="Data agendada"
                        wire:model="scheduled_at"
                        type="date"
                        icon="calendar"
                    />

                    <flux:select label="Estado" wire:model="status">
                        <flux:select.option value="pending">Pendente</flux:select.option>
                        <flux:select.option value="in_progress">Em progresso</flux:select.option>
                        <flux:select.option value="done">Concluída</flux:select.option>
                        <flux:select.option value="cancelled">Cancelada</flux:select.option>
                    </flux:select>
                </div>

                <flux:textarea
                    label="Notas"
                    wire:model.blur="notes"
                    placeholder="Observações sobre a manutenção..."
                    rows="3"
                />

            </div>

            <div class="flex gap-2 w-full">
                <flux:button type="submit" variant="primary" class="w-full">
                    {{ $manutencao && $manutencao->exists ? 'Atualizar' : 'Criar' }}
                </flux:button>
                <flux:button type="button" wire:click="fechar" variant="danger" class="w-full">
                    Cancelar
                </flux:button>
            </div>

        </flux:card>
    </form>
</div>

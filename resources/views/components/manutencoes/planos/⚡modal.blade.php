<?php

use App\Models\MaintenancePlan;
use App\Models\Resource;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {

    public ?MaintenancePlan $plano = null;

    public $resource_id = '';
    public string $name = '';
    public string $interval_value = '1';
    public string $interval_unit = 'month';
    public string $description = '';
    public bool $is_active = true;
    public string $started_at = '';
    public string $email_responsible = '';
    public string $notification_days_before = '7';

    public string $recurso_search = '';
    public bool $recurso_open = false;


    public function selectRecurso(int $id, string $nome): void
    {
        $this->resource_id = $id;
        $this->recurso_search = $nome;
        $this->recurso_open = false;
    }

    public function mount(?MaintenancePlan $plano = null): void
    {
        if ($plano && $plano->exists) {
            $this->plano = $plano;
            $this->resource_id = $plano->resource_id;
            $this->name = $plano->name;
            $this->interval_value = (string)$plano->interval_value;
            $this->interval_unit = $plano->interval_unit;
            $this->description = $plano->description ?? '';
            $this->is_active = $plano->is_active;
            $this->started_at = $plano->started_at?->format('Y-m-d') ?? '';
            $this->email_responsible = $plano->email_responsible ?? '';
            $this->notification_days_before = (string)$plano->notification_days_before;

            $this->recurso_search = $plano->resource->name ?? '';
        } else {
            $this->plano = new MaintenancePlan();
        }
    }


    #[On('salvar-tudo')]
    public function save()
    {
        $this->validate(MaintenancePlan::rules());

        $isNew = !$this->plano->exists;

        $this->plano->fill([
            'resource_id' => $this->resource_id,
            'name' => $this->name,
            'interval_value' => $this->interval_value,
            'interval_unit' => $this->interval_unit,
            'description' => $this->description ?: null,
            'is_active' => $this->is_active,
            'started_at' => $this->started_at ?: null,
            'email_responsible' => $this->email_responsible ?: null,
            'notification_days_before' => $this->notification_days_before,
        ]);
        $this->plano->save();

        if ($isNew) {
            Flux::toast('O plano foi criada com sucesso!', variant: 'success', duration: 1000);

            return $this->redirect(route('planos_manutencoes.index'), navigate: true);
        }

        Flux::toast(text: 'O plano foi atualizado com sucesso!', variant: 'success', duration: 1000);

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
        return [
            'recursos' => Resource::orderBy('name')
                ->when(
                    strlen($this->recurso_search) >= 1,
                    fn($q) => $q->where('name', 'like', '%' . $this->recurso_search . '%')
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
                    {{ $plano && $plano->exists ? 'Editar Plano' : 'Novo Plano de Manutenção' }}
                </flux:heading>
            </div>

            <flux:separator variant="subtle"/>

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

                    <flux:error name="resource_id"/>
                </div>

                <flux:input label="Nome do plano" wire:model="name" placeholder="Ex: Manutenção trimestral"/>
                @error('name')
                <flux:error>{{ $message }}</flux:error> @enderror

                <flux:textarea
                    label="Descrição"
                    wire:model.blur="description"
                    placeholder="Descreve o plano de manutenção..."
                    rows="3"
                />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Bloco de Intervalo Composto -->
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Intervalo de
                            Repetição</label>
                        <div class="grid grid-cols-3 gap-2">
                            <!-- Valor Numérico -->
                            <div class="col-span-1">
                                <flux:input
                                    wire:model="interval_value"
                                    type="number"
                                    min="1"
                                    placeholder="Ex: 3"
                                />
                            </div>
                            <!-- Unidade de Tempo -->
                            <div class="col-span-2">
                                <flux:select wire:model="interval_unit">
                                    <flux:select.option value="day">Dias</flux:select.option>
                                    <flux:select.option value="month">Meses</flux:select.option>
                                    <flux:select.option value="year">Anos</flux:select.option>
                                </flux:select>
                            </div>
                        </div>
                        @error('interval_value')
                        <flux:error class="mt-1">{{ $message }}</flux:error> @enderror
                        @error('interval_unit')
                        <flux:error class="mt-1">{{ $message }}</flux:error> @enderror
                    </div>

                    <!-- Data de Início -->
                    <div>
                        <flux:input
                            label="Data de início"
                            wire:model="started_at"
                            type="date"
                            icon="calendar"
                        />
                        @error('started_at')
                        <flux:error class="mt-1">{{ $message }}</flux:error> @enderror
                    </div>
                </div>


                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:input
                        label="Email do responsável"
                        wire:model="email_responsible"
                        type="email"
                        placeholder="responsavel@empresa.com"
                        icon="envelope"
                    />

                    <flux:input
                        label="Notificar X dias antes"
                        wire:model="notification_days_before"
                        type="number"
                        min="0"
                        placeholder="Ex: 7"
                    />
                    @error('notification_days_before')
                    <flux:error>{{ $message }}</flux:error> @enderror
                </div>

                <flux:checkbox wire:model="is_active" label="Plano ativo"/>

            </div>

            <div class="flex gap-2 w-full">
                <flux:button type="submit" variant="primary" class="w-full">
                    {{ $plano && $plano->exists ? 'Atualizar' : 'Criar' }}
                </flux:button>
                <flux:button type="button" wire:click="fechar" variant="danger" class="w-full">
                    {{ $plano && $plano->exists ? 'Recarregar' : 'Cancelar' }}
                </flux:button>
            </div>

        </flux:card>
    </form>
</div>

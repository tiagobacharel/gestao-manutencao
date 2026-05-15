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

    protected function rules(): array
    {
        return [
            'maintenance_plan_id' => ['nullable', 'exists:maintenance_plans,id'],
            'resource_id'         => ['required', 'exists:resources,id'],
            'scheduled_at'        => ['nullable', 'date'],
            'status'              => ['required', 'in:pending,in_progress,done,cancelled'],
            'notes'               => ['nullable', 'string'],
        ];
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
        } else {
            $this->manutencao = new Maintenance();
        }
    }

    public function save(): void
    {
        $this->validate();

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
            'recursos' => Resource::orderBy('name')->pluck('name', 'id'),
            'planos'   => MaintenancePlan::orderBy('name')->pluck('name', 'id'),
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

                <flux:select
                    label="Recurso"
                    wire:model="resource_id"
                    placeholder="Seleciona um recurso..."
                >
                    @foreach($recursos as $id => $nome)
                        <flux:select.option value="{{ $id }}">{{ $nome }}</flux:select.option>
                    @endforeach
                </flux:select>
                @error('resource_id')
                <flux:error>{{ $message }}</flux:error>
                @enderror

                <flux:select
                    label="Plano de manutenção"
                    wire:model="maintenance_plan_id"
                    placeholder="Sem plano (opcional)"
                >
                    <flux:select.option value="">Sem plano</flux:select.option>
                    @foreach($planos as $id => $nome)
                        <flux:select.option value="{{ $id }}">{{ $nome }}</flux:select.option>
                    @endforeach
                </flux:select>

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

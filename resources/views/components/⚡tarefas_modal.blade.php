<?php

use Livewire\Component;
use App\Models\Task;

new class extends Component
{
    public ?Task $task = null;

    public string $name = '';
    public string $description = '';

    public function mount(?Task $task = null): void
    {
        if ($task && $task->exists) {
            $this->task        = $task;
            $this->name        = $task->name;
            $this->description = $task->description ?? '';
        } else {
            $this->task = new Task();
        }
    }

    public function save(): mixed
    {
        $this->validate(Task::rules($this->task->id));

        $isNew = !$this->task->exists;

        $this->task->fill([
            'name'        => $this->name,
            'description' => $this->description ?: null,
        ]);

        if ($this->task->isDirty()) {
            $this->task->save();
            Flux::toast(
                $isNew ? 'A tarefa foi criada com sucesso!' : 'A tarefa foi atualizada com sucesso!',
                variant: 'success',
                duration: 1000,
            );
        }else{
            Flux::toast(
                'A tarefa não tem alterações!',
                variant: 'danger',
                duration: 1000,
            );
        }

        return $isNew
            ? $this->redirect(route('tarefas.index'), navigate: true)
            : $this->fechar();
    }

    public function fechar(): mixed
    {
        if (!$this->task->exists) {
            return $this->redirect(request()->header('Referer') ?? route('tasks.index'), navigate: true);
        }


        $this->name        = $this->task->name;
        $this->description = $this->task->description ?? '';

        $this->resetErrorBag();

        return null;
    }
};
?>

<div>
    <form wire:submit="save">
        <flux:card class="space-y-6">

            <div class="space-y-4">

                <flux:input
                    label="Nome"
                    name="name"
                    wire:model.blur="name"
                    placeholder="Ex: Inspecionar travões"
                    icon="clipboard-document-list"
                />

                <flux:textarea
                    label="Descrição"
                    name="description"
                    wire:model.blur="description"
                    placeholder="Detalhes da tarefa..."
                />

            </div>

            <div class="flex gap-2 w-full">
                <flux:button type="submit" variant="primary" class="w-full">
                    {{ $task && $task->exists ? 'Atualizar' : 'Criar' }}
                </flux:button>
                <flux:button wire:click="fechar" type="button" variant="danger" class="w-full">
                    {{ $task && $task->exists ? 'Recarregar' : 'Cancelar' }}
                </flux:button>
            </div>

        </flux:card>
    </form>
</div>

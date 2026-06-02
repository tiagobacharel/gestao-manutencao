<?php

use App\Models\Task;
use Livewire\Volt\Component;

new class extends Component {

    public Task $task;
    public string $title = '';

    public function mount(Task $task): void
    {
        $this->task  = $task;
        $this->title = "Tarefa: {$task->name}";
    }

    public function rendering($view): void
    {
        $view->layoutData(['title' => $this->title]);
    }

    public function delete(): void
    {
        $this->task->delete();
        $this->redirect(route('tarefas.index'), navigate: true);
    }
};
?>

<div>
    <flux:main container class="space-y-6">

        {{-- Cabeçalho --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <flux:button @click="history.back()" icon="arrow-left" variant="subtle" size="sm" />
                <flux:heading size="xl" level="1">{{ $task->name }}</flux:heading>
            </div>

            <flux:button
                wire:click="delete"
                wire:confirm="Tem a certeza que quer apagar esta tarefa?"
                variant="danger"
                icon="trash"
                class="justify-start"
            >
                Apagar
            </flux:button>
        </div>

        <flux:separator variant="subtle" />

        <livewire:tarefas_modal :task="$task" />

    </flux:main>
</div>

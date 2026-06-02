@props([
    'config' => [],
    'valores' => [],
])

@php
    $temFiltroAtivo = collect($config)->contains(function($campo) use ($valores) {
        $modelo = $campo['model'];
        $buscaModelo = $campo['searchModel'] ?? null;

        $idPreenchido = isset($valores[$modelo]) && $valores[$modelo] !== '';
        $textoPreenchido = $buscaModelo && isset($valores[$buscaModelo]) && $valores[$buscaModelo] !== '';

        return $idPreenchido || $textoPreenchido;
    });

    $jsResetProperties = collect($config)
        ->flatMap(function($campo) {
            $resets = ["\$wire.set('{$campo['model']}', '', true)"];

            if (isset($campo['searchModel'])) {
                $resets[] = "\$wire.set('{$campo['searchModel']}', '', true)";
            }

            return $resets;
        })
        ->implode('; ');
@endphp

<div class="flex flex-wrap items-end gap-4">

    @foreach($config as $campo)
        {{-- Input de Texto Normal --}}
        @if(($campo['type'] ?? 'text') === 'text')
            <div class="flex-1 min-w-[250px]">
                <flux:input
                    autocomplete="off"
                    wire:model.live.debounce.300ms="{{ $campo['model'] }}"
                    icon="{{ $campo['icon'] ?? 'magnifying-glass' }}"
                    placeholder="{{ $campo['placeholder'] ?? 'Procurar...' }}"
                />
            </div>
        @endif

        {{-- Dropdowns Estáticos (ex: Status) --}}
        @if(($campo['type'] ?? 'text') === 'select')
            <div class="w-full md:w-48">
                <flux:select wire:model.live="{{ $campo['model'] }}">
                    <flux:select.option value="">{{ $campo['label'] }}</flux:select.option>
                    @foreach(($campo['options'] ?? []) as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        @endif

        {{-- DROPDOWN CUSTOMIZADO COM ALPINEJS (Filtra ao digitar E ao escolher) --}}
        @if($campo['type'] === 'custom-dropdown')
            <div class="w-full md:w-64 relative" x-data="{ open: false }">
                <flux:input
                    autocomplete="off"
                    wire:model.live.debounce.300ms="{{ $campo['searchModel'] }}"
                    placeholder="{{ $campo['label'] }}"
                    @focus="open = true"
                    @click="open = true"
                    icon="magnifying-glass"
                />

                <ul
                    x-show="open"
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-75"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    @click.outside="open = false"
                    class="absolute left-0 right-0 z-50 mt-1 max-h-60 overflow-auto rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 py-1 shadow-lg"
                    style="display: none;"
                >
                    {{-- Opção fixa: Clicar aqui limpa o input e remove o filtro textual imediatamente --}}
                    <li
                        wire:click="$set('{{ $campo['model'] }}', ''); $set('{{ $campo['searchModel'] }}', '')"
                        @click="open = false"
                        class="cursor-pointer px-3 py-2 text-sm text-zinc-400 dark:text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-700 font-medium border-b border-zinc-100 dark:border-zinc-700"
                    >
                        {{ $campo['label'] }}
                    </li>

                    @forelse($campo['computedOptions'] as $id => $nome)
                        <li
                            wire:click="{{ $campo['selectMethod'] }}('{{ addslashes($id) }}', '{{ addslashes($nome) }}')"
                            @click="open = false"
                            class="cursor-pointer px-3 py-2 text-sm text-zinc-800 dark:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-700"
                        >
                            {{ $nome }}
                        </li>
                    @empty
                        <li class="px-3 py-4 text-sm text-center text-zinc-400 dark:text-zinc-500">
                            Nenhum registo encontrado
                        </li>
                    @endforelse
                </ul>
            </div>
        @endif

    @endforeach

    {{-- Botão Limpar com Refresh Automático via Livewire.navigate --}}
    @if($temFiltroAtivo)
        <flux:button
            variant="ghost"
            icon="x-mark"
            x-data
            @click="
                {{ $jsResetProperties }};
                $wire.$refresh();
                Livewire.navigate(window.location.pathname);
            "
        >
            Limpar
        </flux:button>
    @endif

</div>

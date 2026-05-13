@props([
    'config' => [],
    'valores' => [],
])

@php
    // Detecta se há algum filtro preenchido para exibir o botão
    $temFiltroAtivo = collect($config)->contains(function($campo) use ($valores) {
        $modelo = $campo['model'];
        return isset($valores[$modelo]) && $valores[$modelo] !== '';
    });

    // Cria a string de reset para o AlpineJS limpar as propriedades no Livewire
    // Exemplo: $wire.search = ''; $wire.status = '';
    $jsResetProperties = collect($config)
        ->map(fn($campo) => "\$wire.set('{$campo['model']}', '', true)")
        ->implode('; ');
@endphp

<div class="flex flex-wrap items-end gap-4">

    @foreach($config as $campo)
        {{-- Input de Texto --}}
        @if(($campo['type'] ?? 'text') === 'text')
            <div class="flex-1 min-w-[250px]">
                <flux:input
                    wire:model.live.debounce.300ms="{{ $campo['model'] }}"
                    icon="{{ $campo['icon'] ?? 'magnifying-glass' }}"
                    placeholder="{{ $campo['placeholder'] ?? 'Procurar...' }}"
                />
            </div>
        @endif

        {{-- Dropdowns --}}
        @if(($campo['type'] ?? 'text') === 'select')
            <div class="w-full md:w-48">
                <flux:select
                    wire:model.live="{{ $campo['model'] }}"
                    icon="{{ $campo['icon'] ?? '' }}"
                >
                    <flux:select.option value="">{{ $campo['label'] }}</flux:select.option>
                    @foreach(($campo['options'] ?? []) as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
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

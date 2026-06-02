<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Yudo' }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @fluxAppearance
</head>
<body class="min-h-screen bg-white dark:bg-zinc-800 antialiased">

@persist('toast')
<flux:toast.group position="top end" class="pt-6 pr-6 space-y-3" expanded>
    <flux:toast class="!bg-slate-900 !text-white !rounded-xl !shadow-2xl !border !border-slate-800 !p-4 !backdrop-blur-md" />
</flux:toast.group>
@endpersist

<!-- Contentor Flexbox para alinhar a sidebar e o conteúdo principal lado a lado -->
<div class="flex min-h-screen">

    <!-- Sidebar fixa através de sticky e h-screen (evita que role com a página) -->
    <flux:sidebar stashable class="sticky top-0 h-screen bg-zinc-50 dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-700 flex flex-col">

        <flux:sidebar.toggle class="lg:hidden" icon="x-mark" inset="left" />

        <flux:brand href="/" wire:navigate :logo="asset('imagens/yudo.png')" class="px-2" />

        <!-- Lista de navegação com flex-1 e overflow-y-auto para garantir que todos os itens aparecem sempre -->
        <flux:navlist class="mt-6 flex-1 overflow-y-auto min-h-0">
            <flux:navlist.item icon="home" href="/" wire:navigate>Home</flux:navlist.item>
            <flux:navlist.item icon="calendar" href="/calendar" wire:navigate>Calendário</flux:navlist.item>
            <flux:navlist.item icon="clipboard-document-check" href="/tarefas" wire:navigate>Tarefas</flux:navlist.item>
            <flux:navlist.item icon="wrench" href="/manutencoes" wire:navigate>Manutenções</flux:navlist.item>
            <flux:navlist.item icon="document-text" href="/planos_manutencoes" wire:navigate>Planos de Manutenções</flux:navlist.item>
            <flux:navlist.item icon="inbox" href="/recursos" wire:navigate>Recursos</flux:navlist.item>
            <flux:navlist.item icon="clipboard-document-list" href="/pecas" wire:navigate>Peças</flux:navlist.item>
        </flux:navlist>

        <flux:spacer />

        <div class="px-2 py-4 border-t border-zinc-200 dark:border-zinc-700">
            <flux:button
                x-data
                x-on:click="$flux.dark = !$flux.dark"
                variant="subtle"
                square
                class="w-full justify-start gap-3"
                aria-label="Alternar tema"
            >
                <div class="flex items-center gap-3">
                    <div class="relative size-5">
                        <flux:icon.sun class="hidden dark:block size-5 text-zinc-400 hover:text-zinc-200" />
                        <flux:icon.moon class="block dark:hidden size-5 text-zinc-500 hover:text-zinc-700" />
                    </div>
                    <span class="text-sm font-medium dark:text-zinc-400 text-zinc-500">Alternar Tema</span>
                </div>
            </flux:button>
        </div>
    </flux:sidebar>

    <!-- Área do conteúdo principal (ocupa o espaço restante e centraliza corretamente com 'container') -->
    <div class="flex-1 flex flex-col min-w-0">

        <flux:header class="lg:hidden bg-zinc-50 dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-700">
            <flux:sidebar.toggle icon="bars-2" inset="left" />
        </flux:header>

        <flux:main container>
            {{ $slot }}
        </flux:main>
    </div>

</div>

@fluxScripts
</body>
</html>

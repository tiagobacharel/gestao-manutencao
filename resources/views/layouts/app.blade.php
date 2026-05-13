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
<flux:header container class="bg-zinc-50 dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-700">
    <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

    <flux:brand href="/" :logo="asset('imagens/yudo.png')" class="max-lg:hidden!"/>

    <flux:navbar class="-mb-px max-lg:hidden">
        <flux:navbar.item icon="home" href="/" wire:navigate>Home</flux:navbar.item>
        <flux:navbar.item icon="inbox" :badge="App\Models\Resource::count()" href="/recursos" wire:navigate>Recursos</flux:navbar.item>
        <flux:navbar.item icon="wrench" href="/manutencoes" wire:navigate>Manutenções</flux:navbar.item>
        <flux:navbar.item icon="calendar" href="/calendar" wire:navigate>Calendário</flux:navigate>
    </flux:navbar>

    <flux:spacer />

    <flux:button
        x-data
        x-on:click="$flux.dark = !$flux.dark"
        variant="subtle"
        square
        aria-label="Alternar tema"
    >
        <!-- O Flux alterna os ícones automaticamente com base na classe da app -->
        <flux:icon.sun class="hidden dark:block size-5 text-zinc-400 hover:text-zinc-200" />
        <flux:icon.moon class="block dark:hidden size-5 text-zinc-500 hover:text-zinc-700" />
    </flux:button>
</flux:header>

<flux:sidebar sticky collapsible="mobile" class="lg:hidden bg-zinc-50 dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-700">
    <flux:sidebar.header>
        <flux:sidebar.brand href="#" :logo="asset('imagens/yudo.png')"/>
        <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
    </flux:sidebar.header>

    <flux:sidebar.nav>
        <flux:sidebar.item icon="home" href="/" wire:navigate>Home</flux:sidebar.item>
        <flux:sidebar.item icon="inbox" :badge="App\Models\Resource::count()" href="/recursos" wire:navigate>Recursos</flux:sidebar.item>
        <flux:sidebar.item icon="wrench" href="/manutencoes" wire:navigate>Manutenções</flux:sidebar.item>
        <flux:sidebar.item icon="calendar" href="/calendar" wire:navigate>Calendário</flux:sidebar.item>
    </flux:sidebar.nav>

    <flux:sidebar.spacer />

    <!-- Botão de Alternância no Menu Lateral (Telemóvel) -->
    <flux:sidebar.nav>
        <flux:sidebar.item
            icon="swatch"
            href="#"
            x-data
            x-on:click.prevent="$flux.dark = !$flux.dark"
        >
            Alternar Tema
        </flux:sidebar.item>
        <flux:sidebar.item icon="cog-6-tooth" href="#">Settings</flux:sidebar.item>
        <flux:sidebar.item icon="information-circle" href="#">Help</flux:sidebar.item>
    </flux:sidebar.nav>
</flux:sidebar>

<flux:main container>
    <flux:heading size="xl" level="1">
        {{ $slot }}
    </flux:heading>

    <flux:separator variant="subtle" />
</flux:main>

</body>

@fluxScripts
</body>
</html>

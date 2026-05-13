@if ($paginator->hasPages())
    <div class="mt-12 flex justify-center">
        <nav class="inline-flex items-center gap-1.5 p-1.5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-sm" aria-label="Pagination">

            @if ($paginator->onFirstPage())
                <flux:button disabled size="sm" variant="filled">Anterior</flux:button>
            @else
                <flux:button wire:click="previousPage" size="sm" variant="ghost">Anterior</flux:button>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="inline-flex items-center justify-center w-9 h-9 text-sm font-medium text-zinc-400 select-none">
                        {{ $element }}
                    </span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <flux:button variant="primary" size="sm" class="!font-bold pointer-events-none">
                                {{ $page }}
                            </flux:button>
                        @else
                            <flux:button wire:click="setPage({{ $page }})" variant="ghost" size="sm">
                                {{ $page }}
                            </flux:button>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <flux:button wire:click="nextPage" size="sm" variant="ghost">Seguinte</flux:button>
            @else
                <flux:button disabled size="sm" variant="filled">Seguinte</flux:button>
            @endif

        </nav>
    </div>
@endif

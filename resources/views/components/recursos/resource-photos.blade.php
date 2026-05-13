@props(['photos'])

@if($photos->isNotEmpty())
    @php
        $photoUrls = $photos->pluck('url')->values()->toJson();
    @endphp

    <flux:card class="space-y-4" x-data='{
        currentPage: 0,
        perPage: 6,
        photos: {{ $photoUrls }},
        get totalPages() { return Math.ceil(this.photos.length / this.perPage) },
        get visiblePhotos() { return this.photos.slice(this.currentPage * this.perPage, (this.currentPage + 1) * this.perPage) },
        prev() { if (this.currentPage > 0) this.currentPage-- },
        next() { if (this.currentPage < this.totalPages - 1) this.currentPage++ }
    }'>
        <div class="flex items-center justify-between">
            <flux:heading size="lg">Fotos</flux:heading>
            <flux:text size="sm" class="text-zinc-400">
                <span x-text="currentPage + 1"></span> / <span x-text="totalPages"></span>
            </flux:text>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            <template x-for="(url, i) in visiblePhotos" :key="i">
                <a :href="url" target="_blank" class="group aspect-square block overflow-hidden rounded-lg">
                    <img
                        :src="url"
                        alt="Foto do recurso"
                        class="w-full h-full object-cover transition-transform duration-200 group-hover:scale-105"
                    />
                </a>
            </template>
        </div>

        <div class="flex items-center justify-between pt-1" x-show="totalPages > 1">
            <flux:button
                icon="arrow-left"
                variant="subtle"
                size="sm"
                x-on:click="prev"
                x-bind:disabled="currentPage === 0"
            >
                Anterior
            </flux:button>

            <div class="flex gap-1.5 items-center">
                <template x-for="i in totalPages" :key="i">
                    <button
                        x-on:click="currentPage = i - 1"
                        x-bind:class="currentPage === i - 1
                            ? 'w-2 h-2 rounded-full bg-zinc-800 dark:bg-zinc-100'
                            : 'w-2 h-2 rounded-full bg-zinc-300 dark:bg-zinc-600'"
                    ></button>
                </template>
            </div>

            <flux:button
                icon-trailing="arrow-right"
                variant="subtle"
                size="sm"
                x-on:click="next"
                x-bind:disabled="currentPage === totalPages - 1"
            >
                Próximo
            </flux:button>
        </div>
    </flux:card>
@endif

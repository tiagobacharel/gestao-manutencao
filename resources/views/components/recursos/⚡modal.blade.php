<?php

use Livewire\Component;
use Livewire\Attributes\Validate;
use Livewire\WithFileUploads;
use App\Models\Resource;
use App\Models\ResourcePhoto;
use Illuminate\Support\Facades\Storage;

new class extends Component
{
    use WithFileUploads;

    public ?Resource $recurso = null;

    public $name = '';
    public $description = '';
    public $location = '';
    public $section = '';
    public $status = 'active';

    public array $photos = [];
    public array $existingPhotos = [];


    public function mount(?Resource $recurso = null)
    {
        if ($recurso && $recurso->exists) {
            $this->recurso      = $recurso;
            $this->name         = $recurso->name;
            $this->description  = $recurso->description;
            $this->location     = $recurso->location;
            $this->section      = $recurso->section;
            $this->status       = $recurso->status;
            $this->existingPhotos = $recurso->photos->toArray();
        } else {
            $this->recurso = new Resource();
        }
    }

    public function removeExistingPhoto(int $photoId): void
    {
        $photo = ResourcePhoto::findOrFail($photoId);
        abort_unless($this->recurso->exists && $photo->resource_id === $this->recurso->id, 403);

        Storage::disk($photo->disk)->delete($photo->path);
        $photo->delete();

        $this->existingPhotos = $this->recurso->fresh()->photos->toArray();
    }

    public function removeNewPhoto(int $index): void
    {
        array_splice($this->photos, $index, 1);
    }

    public function save()
    {
        $this->validate(
            array_merge(
                Resource::rules(),
                ResourcePhoto::rules()
            )
        );


        $this->recurso->fill([
            'name'        => $this->name,
            'description' => $this->description,
            'location'    => $this->location,
            'section'     => $this->section,
            'status'      => $this->status,
        ]);
        $this->recurso->save();

        foreach ($this->photos as $photo) {
            $path = $photo->store("resources/{$this->recurso->id}", 'public');
            $this->recurso->photos()->create(['path' => $path, 'disk' => 'public']);
        }

        return $this->fechar();
    }

    public function fechar()
    {
        return $this->redirect(request()->header('Referer'), navigate: true);
    }
};
?>

<div>
    <form wire:submit="save">
        <flux:card class="space-y-6">

            <div class="space-y-6">
                <flux:input
                    label="Nome"
                    name="name"
                    wire:model.blur="name"
                    placeholder="Ex: Projetor Sala A"
                    copyable
                />

                <flux:textarea
                    label="Descrição"
                    name="description"
                    wire:model.blur="description"
                    placeholder="Detalhes do recurso..."
                />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:input label="Localização" name="location" wire:model.blur="location" icon="map-pin" />
                    <flux:input label="Secção / Departamento" name="section" wire:model.blur="section" icon="building-office" />
                </div>

                <flux:select label="Status" name="status" wire:model="status">
                    <flux:select.option value="active">Ativo</flux:select.option>
                    <flux:select.option value="inactive">Inativo</flux:select.option>
                </flux:select>
            </div>

            {{-- Fotos já gravadas --}}
            @if (count($existingPhotos))
                <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-3">
                    @foreach ($existingPhotos as $photo)
                        <div class="relative group aspect-square">
                            <img
                                src="{{ $photo['url'] }}"  {{-- ← simples, vem do model --}}
                            alt="Foto do recurso"
                                class="w-full h-full object-cover rounded-lg"
                            />
                            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg flex items-center justify-center">
                                <flux:button
                                    type="button"
                                    wire:click="removeExistingPhoto({{ $photo['id'] }})"
                                    wire:confirm="Remover esta foto permanentemente?"
                                    variant="danger"
                                    size="xs"
                                    icon="trash"
                                />
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

                {{-- Upload + pré-visualização de novas fotos --}}
                <flux:file-upload wire:model="photos" multiple>
                    <flux:file-upload.dropzone
                        heading="Arrasta fotos ou clica para selecionar"
                        text="PNG, JPG, WEBP — máx. 4 MB cada"
                    />

                    <div class="mt-3 flex flex-col gap-2">
                        @foreach ($photos as $i => $photo)
                            <flux:file-item
                                :heading="$photo->getClientOriginalName()"
                                :image="$photo->temporaryUrl()"
                                :size="$photo->getSize()"
                            >
                                <x-slot name="actions">
                                    <flux:file-item.remove
                                        wire:click="removeNewPhoto({{ $i }})"
                                        aria-label="Remover {{ $photo->getClientOriginalName() }}"
                                    />
                                </x-slot>
                            </flux:file-item>
                        @endforeach
                    </div>
                </flux:file-upload>

                @error('photos.*')
                <flux:error>{{ $message }}</flux:error>
                @enderror
            </div>
            <div class="flex gap-2 w-full">
                <flux:button type="submit" variant="primary" class="w-full">
                    {{ $recurso && $recurso->exists ? 'Atualizar' : 'Criar' }}
                </flux:button>
                <flux:button wire:click="fechar" variant="danger" class="w-full">
                    Cancelar
                </flux:button>
            </div>

        </flux:card>
    </form>
</div>

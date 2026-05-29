<?php

use App\Models\Resource;
use App\Models\ResourcePhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;


uses(RefreshDatabase::class);

test('relação do ResourcePhoto', function () {
    $resource = Resource::factory()->create();

    $resourcePhoto = ResourcePhoto::create([
        'resource_id' => $resource->id,
        'path'        => 'photos/imagem.jpg',
        'disk'        => 'public'
    ]);

    expect($resourcePhoto->resource)->toBeInstanceOf(Resource::class);
    expect($resourcePhoto->resource->id)->toBe($resource->id);

});


test('Url ResourcePhoto', function () {
    Storage::fake('public');

    $photo = new ResourcePhoto([
        'disk' => 'public',
        'path' => 'photos/imagem1.jpg'
    ]);

    $expectedUrl = Storage::disk('public')->url('photos/imagem1.jpg');

    expect($photo->url)->toBe($expectedUrl);

});


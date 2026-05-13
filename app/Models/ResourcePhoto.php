<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ResourcePhoto extends Model
{
    protected $fillable = ['resource_id', 'path', 'disk'];

    public static function rules()
    {
        return [
            'photos.*' => 'image|max:4096',
        ];
    }
    protected $appends = ['url'];

    public function resource()
    {
        return $this->belongsTo(Resource::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}

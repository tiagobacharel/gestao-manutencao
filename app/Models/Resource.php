<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;


class Resource extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'description',
        'location',
        'section',
        'status'
    ];

    public static function rules()
    {
        return [
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'location'    => 'nullable|string',
            'section'     => 'nullable|string',
            'status'      => 'required|string',
        ];
    }

    public function photos()
    {
        return $this->hasMany(ResourcePhoto::class);
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class);
    }

    public function maintenancePlans(): HasMany
    {
        return $this->hasMany(MaintenancePlan::class);
    }


}

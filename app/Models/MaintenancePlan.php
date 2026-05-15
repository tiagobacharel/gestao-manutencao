<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenancePlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'resource_id',
        'name',
        'interval_days',
        'description',
        'is_active',
        'started_at',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'started_at' => 'date',
    ];

    public function resource()
    {
        return $this->belongsTo(Resource::class);
    }

    public function maintenances()
    {
        return $this->hasMany(Maintenance::class);
    }

    public function parts()
    {
        return $this->belongsToMany(Part::class, 'plan_parts', 'maintenance_plan_id', 'part_id')
            ->withPivot('quantity');
    }

    public function planParts(): HasMany
    {
        return $this->hasMany(PlanPart::class, 'maintenance_plan_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Maintenance extends Model
{
    use HasFactory;

    protected $fillable = [
        'maintenance_plan_id',
        'resource_id',
        'created_by',
        'scheduled_at',
        'done_at',
        'status',
        'notes'
    ];

    protected $casts = [
        'scheduled_at' => 'date',
        'done_at' => 'datetime',
    ];

    public function plan()
    {
        return $this->belongsTo(MaintenancePlan::class, 'maintenance_plan_id');
    }

    public function resource()
    {
        return $this->belongsTo(Resource::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function parts()
    {
        return $this->belongsToMany(Part::class, 'maintenance_parts')
            ->withPivot('quantity', 'unit_cost_at_time')
            ->withTimestamps();
    }


}

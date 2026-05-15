<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MaintenancePart extends Model
{
    use HasFactory;
    protected $fillable = [
        'maintenance_id',
        'part_id',
        'quantity',
        'unit_cost_at_time',
    ];

    protected $casts = [
        'unit_cost_at_time' => 'decimal:2',
    ];

    public function maintenance()
    {
        return $this->belongsTo(Maintenance::class);
    }

    public function getTotalCostAttribute(): float
    {
        return $this->quantity * $this->unit_cost_at_time;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanPart extends Model
{
    use HasFactory;

    // Define os campos protegidos/permitidos se necessário (Mass Assignment)
    protected $fillable = [
        'maintenance_plan_id',
        'part_id',
        'quantity'
    ];

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class, 'part_id');
    }

    public function maintenancePlan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class, 'maintenance_plan_id');
    }
}

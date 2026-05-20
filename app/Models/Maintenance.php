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
        #'created_by',
        'scheduled_at',
        'done_at',
        'status',
        'notes'
    ];

    protected function rules(): array
    {
        return [
            'maintenance_plan_id' => ['nullable', 'exists:maintenance_plans,id'],
            'resource_id'         => ['required', 'exists:resources,id'],
            'scheduled_at'        => ['nullable', 'date'],
            'status'              => ['required', 'in:pending,in_progress,done,cancelled'],
            'notes'               => ['nullable', 'string'],
        ];
    }

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

    /*public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }*/

    public function parts()
    {
        return $this->belongsToMany(Part::class, 'maintenance_parts')
            ->withPivot('quantity', 'unit_cost_at_time')
            ->withTimestamps();
    }

    public function getTotalCostAttribute(): float
    {
        return $this->parts->sum(
            fn($part) => $part->pivot->quantity * $part->pivot->unit_cost_at_time
        );
    }

    public function getCostDeviationAttribute(): ?float
    {
        if (!$this->plan) return null;
        return $this->total_cost - $this->plan->estimated_cost;
    }
    public function getStatusBadgeAttribute(): array
    {
        return match($this->status) {
            'done'        => ['color' => 'green',  'icon' => 'check-circle', 'label' => 'Concluída'],
            'in_progress' => ['color' => 'blue',   'icon' => 'wrench',       'label' => 'Em Progresso'],
            'cancelled'   => ['color' => 'red',    'icon' => 'x-circle',     'label' => 'Cancelada'],
            default       => ['color' => 'yellow', 'icon' => 'clock',        'label' => 'Pendente'],
        };
    }


}

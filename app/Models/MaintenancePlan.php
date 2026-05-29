<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenancePlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'resource_id',
        'name',
        'interval_value',
        'interval_unit',
        'description',
        'is_active',
        'started_at',
        'email_responsible',
        'notification_days_before',
        'last_notified_at',
    ];

    protected function rules(): array
    {
        return [
            'resource_id'               => ['required', 'exists:resources,id'],
            'name'                      => ['required', 'string', 'max:255'],
            'interval_value'            => ['required', 'integer', 'min:1'],
            'interval_unit'             => ['required', 'in:day,month,year'],
            'description'               => ['nullable', 'string'],
            'is_active'                 => ['boolean'],
            'started_at'                => ['nullable', 'date'],
            'email_responsible'         => ['nullable', 'email', 'max:255'],
            'notification_days_before'  => ['required', 'integer', 'min:0'],
        ];
    }

    protected $casts = [
        'is_active'          => 'boolean',
        'started_at'         => 'date',
        'interval_value'     => 'integer',
        'last_notified_at'   => 'datetime',
    ];

    public function getNextMaintenanceDate(): Carbon
    {
        $baseDate = $this->started_at ? Carbon::parse($this->started_at) : Carbon::now();

        return match ($this->interval_unit) {
            'day'   => $baseDate->addDays($this->interval_value),
            'month' => $baseDate->addMonths($this->interval_value),
            'year'  => $baseDate->addYears($this->interval_value),
            default => $baseDate,
        };
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class, 'maintenance_plan_id');
    }

    public function parts(): BelongsToMany
    {
        return $this->belongsToMany(Part::class, 'plan_parts', 'maintenance_plan_id', 'part_id')
            ->withPivot('id', 'quantity');
    }

    public function planParts(): HasMany
    {
        return $this->hasMany(PlanPart::class, 'maintenance_plan_id');
    }

    public function tasks() {
        return $this->belongsToMany(Task::class, 'plan_tasks');
    }


    public function getEstimatedCostAttribute(): float
    {
        return $this->planParts->sum(
            fn($p) => $p->quantity * ($p->part?->current_unit_cost ?? 0)
        );
    }

}

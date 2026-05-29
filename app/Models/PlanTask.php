<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanTask extends Model
{
    protected $fillable = [
        'maintenance_plan_id',
        'task_id',
    ];

    public function maintenance_plan()
    {
        return $this->belongsTo(MaintenancePlan::class, 'maintenance_plan_id');
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function parts()
    {
        return $this->hasMany(PlanPart::class);
    }

    public static function rules()
    {
        return [
            'plan_tasks.*.task_id' => 'required|exists:tasks,id',
            'plan_tasks.*.parts.*.part_id'  => 'required|exists:parts,id',
            'plan_tasks.*.parts.*.quantity' => 'required|integer|min:1',
        ];
    }
}

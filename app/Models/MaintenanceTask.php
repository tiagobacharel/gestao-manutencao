<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceTask extends Model
{
    protected $fillable = [
        'maintenance_id',
        'task_id',
        'status',
    ];

    public function maintenance()
    {
        return $this->belongsTo(Maintenance::class);
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function parts()
    {
        return $this->hasMany(MaintenancePart::class);
    }

    public static function rules()
    {
        return [
            'maintenance_tasks'                    => ['array'],
            'maintenance_tasks.*.task_id'          => ['required', 'exists:tasks,id'],
            'maintenance_tasks.*.status'           => ['required', 'in:pending,in_progress,completed'],
            'maintenance_tasks.*.parts'            => ['array'],
            'maintenance_tasks.*.parts.*.part_id'  => ['required_with:maintenance_tasks.*.parts.*.quantity', 'exists:parts,id'],
            'maintenance_tasks.*.parts.*.quantity' => ['required', 'integer', 'min:1'],

        ];
    }
}

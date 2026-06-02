<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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

    public static function rules($component = null)
    {
        $maintenanceTasks = collect($component?->maintenance_tasks ?? []);

        $taskIds = $maintenanceTasks->pluck('task_id')->filter()->unique()->all();
        $dbTasks = !empty($taskIds) ? DB::table('tasks')->whereIn('id', $taskIds)->get()->keyBy('id') : collect();

        $partIds = $maintenanceTasks->pluck('parts')->flatten(1)->pluck('part_id')->filter()->unique()->all();
        $dbParts = !empty($partIds) ? DB::table('parts')->whereIn('id', $partIds)->get()->keyBy('id') : collect();

        $validTasks = [];
        $validParts = [];

        foreach ($maintenanceTasks as $item) {
            $taskId = $item['task_id'] ?? null;
            $tSearch = trim($item['search'] ?? '');
            $dbTask = $dbTasks->get($taskId);

            if ($dbTask && $tSearch === $dbTask->name) {
                $validTasks[] = $taskId;
            }

            foreach ($item['parts'] ?? [] as $part) {
                $partId = $part['part_id'] ?? null;
                $pSearch = trim($part['search'] ?? '');
                $dbPart = $dbParts->get($partId);

                // A peça só é válida se o ID existir e o texto for igual a "Nome / Referência"
                if ($dbPart && $pSearch === "{$dbPart->name} / {$dbPart->reference}") {
                    $validParts[] = $partId;
                }
            }
        }

        return [
            'maintenance_tasks'                    => ['array'],
            'maintenance_tasks.*.task_id'          => ['required', Rule::in(array_unique($validTasks))],
            'maintenance_tasks.*.status'           => ['required', 'in:pending,in_progress,completed'],
            'maintenance_tasks.*.parts'            => ['array'],
            'maintenance_tasks.*.parts.*.part_id'  => ['required', Rule::in(array_unique($validParts))],
            'maintenance_tasks.*.parts.*.quantity' => ['required', 'integer', 'min:1'],

        ];
    }
}

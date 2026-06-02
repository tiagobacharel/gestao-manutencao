<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

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


    public static function rules($component = null)
    {
        $planTasks = collect($component?->plan_tasks ?? []);

        $taskIds = $planTasks->pluck('task_id')->filter()->unique()->all();
        $dbTasks = !empty($taskIds) ? DB::table('tasks')->whereIn('id', $taskIds)->get()->keyBy('id') : collect();

        $partIds = $planTasks->pluck('parts')->flatten(1)->pluck('part_id')->filter()->unique()->all();
        $dbParts = !empty($partIds) ? DB::table('parts')->whereIn('id', $partIds)->get()->keyBy('id') : collect();

         $validTasks = [];
        $validParts = [];

        foreach ($planTasks as $item) {
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
            'plan_tasks'                    => ['nullable', 'array'],
            'plan_tasks.*.task_id'          => ['required', Rule::in(array_unique($validTasks))],
            'plan_tasks.*.parts'            => ['nullable', 'array'],
            'plan_tasks.*.parts.*.part_id'  => ['required', Rule::in(array_unique($validParts))],
            'plan_tasks.*.parts.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }


}

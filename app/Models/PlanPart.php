<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PlanPart extends Model
{
    use HasFactory;

    // Define os campos protegidos/permitidos se necessário (Mass Assignment)
    protected $fillable = [
        'maintenance_plan_id',
        'part_id',
        'plan_task_id',
        'quantity',
    ];

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class, 'part_id');
    }

    public function maintenancePlan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class, 'maintenance_plan_id');
    }

    public static function rules($component = null)
    {
        $planParts = collect($component?->plan_parts ?? []);

        // 1. Carrega todas as peças em lote (apenas 1 query à base de dados)
        $partIds = $planParts->pluck('part_id')->filter()->unique()->all();
        $dbParts = !empty($partIds) ? DB::table('parts')->whereIn('id', $partIds)->get()->keyBy('id') : collect();

        // 2. Filtra os IDs válidos apenas se o texto (search) bater com o registo real
        $validParts = [];

        foreach ($planParts as $item) {
            $partId = $item['part_id'] ?? null;
            $search = trim($item['search'] ?? '');
            $peca = $dbParts->get($partId);

            // A peça só é válida se o ID existir e o texto for igual a "Nome / Referência"
            if ($peca && $search === "{$peca->name} / {$peca->reference}") {
                $validParts[] = $partId;
            }
        }

        // 3. Se houver texto escrito mas sem ID, a regra 'required' no part_id vai falhar.
        // Se o ID foi adulterado ou o texto mudou, a regra 'Rule::in' vai falhar.
        return [
            'plan_parts'            => ['nullable', 'array'],
            'plan_parts.*.part_id'  => ['required', 'integer', Rule::in(array_unique($validParts))],
            'plan_parts.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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

    public function part()
    {
        return $this->belongsTo(Part::class);
    }

    public function getTotalCostAttribute(): float
    {
        return $this->quantity * $this->unit_cost_at_time;
    }

    public static function rules($component = null)
    {
        $maintenanceParts = collect($component?->maintenance_parts ?? []);

        // 1. Carrega todas as peças em lote (apenas 1 query à base de dados)
        $partIds = $maintenanceParts->pluck('part_id')->filter()->unique()->all();
        $dbParts = !empty($partIds) ? DB::table('parts')->whereIn('id', $partIds)->get()->keyBy('id') : collect();

        // 2. Filtra os IDs válidos apenas se o texto (search) bater com o registo real
        $validParts = [];

        foreach ($maintenanceParts as $item) {
            $partId = $item['part_id'] ?? null;
            $search = trim($item['search'] ?? '');
            $peca = $dbParts->get($partId);

            // A peça só é válida se o ID existir e o texto for igual a "Nome / Referência"
            if ($peca && $search === "{$peca->name} / {$peca->reference}") {
                $validParts[] = $partId;
            }
        }

        return [
            'maintenance_parts'            => ['nullable', 'array'],
            'maintenance_parts.*.part_id'  => ['required', 'integer', Rule::in(array_unique($validParts))],
            'maintenance_parts.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Part extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'name',
        'description',
        'stock_current',
        'current_unit_cost'
    ];

    public function getStockBadgeAttribute(): array
    {
        return match(true) {
            $this->stock_current === 0 => ['color' => 'red',    'icon' => 'x-circle',           'label' => 'Sem stock'],
            $this->stock_current <= 5  => ['color' => 'yellow', 'icon' => 'exclamation-triangle','label' => 'Stock baixo'],
            default                    => ['color' => 'green',  'icon' => 'check-circle',        'label' => 'Em stock'],
        };
    }

    public function getStockValueAttribute(): float
    {
        return $this->stock_current * $this->current_unit_cost;
    }

    protected function rules(?int $ignoreId = null): array
    {
        $uniqueReference = $ignoreId
            ? "unique:parts,reference,{$ignoreId}"
            : 'unique:parts,reference';

        return [
            'reference'         => ['required', 'string', 'max:255', $uniqueReference],
            'name'              => ['required', 'string', 'max:255'],
            'description'       => ['nullable', 'string'],
            'stock_current'     => ['required', 'integer', 'min:0'],
            'current_unit_cost' => ['required', 'numeric', 'min:0'],
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskFactory extends Factory
{
    protected $model = Task::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tarefasGerais = [
            'Trocar óleo do motor',
            'Substituir filtro de ar',
            'Substituir filtro de combustível',
            'Inspecionar pastilhas de travão',
            'Alinhamento e calibragem de rodas',
            'Substituir bateria',
            'Recarregar ar condicionado',
            'Substituir velas de ignição',
            'Substituir correia de distribuição',
            'Verificar nível do líquido de refrigeração',
            'Inspecionar sistema elétrico',
            'Substituir escovas limpa-vidros',
            'Lubrificação de articulações',
            'Testar pressão dos pneus',
            'Limpeza e higienização do habitáculo'
        ];

        return [
            'name' => fake()->randomElement($tarefasGerais),
            'description' => fake()->boolean(70) ? fake()->sentence(10) : null, // 70% de hipótese de ter descrição
        ];
    }
}

<?php

namespace Database\Seeders;

use App\Models\Maintenance;
use App\Models\MaintenancePlan;
use App\Models\Part;
use App\Models\Resource;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Evita que o Laravel guarde o histórico de queries na memória RAM
        DB::disableQueryLog();

        // Eleva o teto de memória do PHP para este processo
        ini_set('memory_limit', '512M');

        User::factory()->create([
            'name'  => 'Test User',
            'email' => 'test@example.com',
        ]);

        Resource::factory(500)->create();

        $totalParts = 200000;
        $chunkSize = 5000;
        $now = now();

        for ($i = 0; $i < $totalParts; $i += $chunkSize) {
            $partsChunk = Part::factory($chunkSize)->make()->toArray();

            foreach ($partsChunk as &$part) {
                $part['created_at'] = $part['created_at'] ?? $now;
                $part['updated_at'] = $part['updated_at'] ?? $now;
            }

            Part::insert($partsChunk);
        }

        $parts = Part::pluck('id')->map(fn($id) => new Part(['id' => $id]));

        $tasks = Task::factory(2500)->create();

        MaintenancePlan::factory(100)
            ->withPlanTasks(5, 20, $tasks)
            ->withPlanParts(10, 50, $parts)
            ->create();

        Maintenance::factory(150)
            ->withPlanBasedTasks()
            ->withPlanBasedParts()
            ->create();
    }
}

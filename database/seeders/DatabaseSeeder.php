<?php

namespace Database\Seeders;

use App\Models\Maintenance;
use App\Models\MaintenancePlan;
use App\Models\Part;
use App\Models\Resource;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name'  => 'Test User',
            'email' => 'test@example.com',
        ]);

        Resource::factory(5000)->create();

        $parts = Part::factory(10000)->create();

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

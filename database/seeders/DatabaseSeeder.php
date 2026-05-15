<?php

namespace Database\Seeders;

use App\Models\Maintenance;
use App\Models\MaintenancePlan;
use App\Models\Part;
use App\Models\Resource;
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

        Resource::factory(100)->create();

        $parts = Part::factory(50)->create();

        MaintenancePlan::factory(20)
            ->withPlanParts(1, 5, $parts)
            ->create();

        Maintenance::factory(50)
            ->withPlanBasedParts()
            ->create();
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Database\Factories\Employee;
use Database\Factories\Division;
use Database\Factories\Position;
use Database\Factories\Unit;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $divisions = Division::factory()->count(3)->create();
        $positions = Position::factory()->count(4)->create();

        // 2. Create units linked to those divisions
        $units = Unit::factory()->count(5)->recycle($divisions)->create();

        // 3. Create employees linked randomly to the created divisions, units, and positions
        Employee::factory()
            ->count(20)
            ->recycle($divisions)
            ->recycle($units)
            ->recycle($positions)
            ->create();
    }
}

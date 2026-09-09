<?php

namespace Database\Seeders;

use App\Models\DiningTable;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tables = [
            ['name' => 'მაგიდა 01', 'capacity' => 2, 'x' => 18, 'y' => 24],
            ['name' => 'მაგიდა 02', 'capacity' => 4, 'x' => 38, 'y' => 24],
            ['name' => 'მაგიდა 03', 'capacity' => 4, 'x' => 58, 'y' => 24],
            ['name' => 'მაგიდა 04', 'capacity' => 2, 'x' => 78, 'y' => 24],
            ['name' => 'მაგიდა 05', 'capacity' => 4, 'x' => 22, 'y' => 50],
            ['name' => 'მაგიდა 06', 'capacity' => 6, 'x' => 45, 'y' => 50],
            ['name' => 'მაგიდა 07', 'capacity' => 4, 'x' => 70, 'y' => 50],
            ['name' => 'მაგიდა 08', 'capacity' => 2, 'x' => 85, 'y' => 50],
            ['name' => 'მაგიდა 09', 'capacity' => 4, 'x' => 18, 'y' => 76],
            ['name' => 'მაგიდა 10', 'capacity' => 6, 'x' => 40, 'y' => 76],
            ['name' => 'მაგიდა 11', 'capacity' => 8, 'x' => 65, 'y' => 76],
            ['name' => 'მაგიდა 12', 'capacity' => 4, 'x' => 84, 'y' => 76],
        ];

        foreach ($tables as $table) {
            DiningTable::updateOrCreate(
                ['name' => $table['name']],
                $table + ['active' => true],
            );
        }

        // The one-time migration imports the photo menu. Do not overwrite admin edits on redeploy.
    }
}

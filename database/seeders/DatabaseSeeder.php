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

        MenuItem::query()->where('name', 'აჭარული ხაჭაპური')->delete();

        $menu = [
            ['category' => 'ცივი კერძები', 'name' => 'ქართული ყველის ასორტი', 'price' => 2800],
            ['category' => 'ცივი კერძები', 'name' => 'ფხალის ასორტი', 'price' => 2400],
            ['category' => 'ცივი კერძები', 'name' => 'ბადრიჯანი ნიგვზით', 'price' => 1800],
            ['category' => 'ცხელი კერძები', 'name' => 'ხინკალი ქალაქური', 'price' => 220],
            ['category' => 'ცხელი კერძები', 'name' => 'ოჯახური', 'price' => 3200],
            ['category' => 'ცხელი კერძები', 'name' => 'ჩქმერული', 'price' => 3600],
            ['category' => 'ცომეული', 'name' => 'იმერული ხაჭაპური', 'price' => 2000],
            ['category' => 'დესერტი', 'name' => 'ქართული ნუგბარი', 'price' => 1600],
            ['category' => 'უალკოჰოლო', 'name' => 'ლიმონათი', 'price' => 600],
            ['category' => 'უალკოჰოლო', 'name' => 'წყალი', 'price' => 400],
        ];

        foreach ($menu as $item) {
            MenuItem::updateOrCreate(
                ['name' => $item['name']],
                $item + ['active' => true],
            );
        }
    }
}

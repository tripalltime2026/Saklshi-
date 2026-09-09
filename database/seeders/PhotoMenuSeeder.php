<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PhotoMenuSeeder extends Seeder
{
    public function run(): void
    {
        $rows = json_decode(file_get_contents(database_path('data/menu-2026-09-09.json')), true, 512, JSON_THROW_ON_ERROR);
        DB::transaction(function () use ($rows) {
            // Retire only unchanged demo records; preserve IDs, custom dishes and booking snapshots.
            $demo = [
                ['ქართული ყველის ასორტი', 'ცივი კერძები', 2800],
                ['ფხალის ასორტი', 'ცივი კერძები', 2400],
                ['ბადრიჯანი ნიგვზით', 'ცივი კერძები', 1800],
                ['ხინკალი ქალაქური', 'ცხელი კერძები', 220],
                ['ოჯახური', 'ცხელი კერძები', 3200],
                ['ჩქმერული', 'ცხელი კერძები', 3600],
                ['იმერული ხაჭაპური', 'ცომეული', 2000],
                ['ქართული ნუგბარი', 'დესერტი', 1600],
            ];
            foreach ($demo as [$name, $category, $price]) {
                DB::table('menu_items')->whereNull('source_key')->where(compact('name', 'category', 'price'))->update(['active' => false]);
            }
            foreach ($rows as $row) {
                if (DB::table('menu_items')->where('source_key', $row['source_key'])->exists()) continue;
                $existing = DB::table('menu_items')->whereNull('source_key')->where('name', $row['name'])
                    ->where(function ($q) use ($row) {
                        $q->where('category', $row['category']);
                        if (in_array($row['name'], ['წყალი', 'ლიმონათი'], true)) $q->orWhere('category', 'უალკოჰოლო');
                    })->first();
                $values = $row + ['updated_at' => now()];
                if ($existing) {
                    DB::table('menu_items')->where('id', $existing->id)->update($values);
                } else {
                    DB::table('menu_items')->insert($values + ['active' => true, 'created_at' => now()]);
                }
            }
        });
    }
}

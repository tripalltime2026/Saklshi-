<?php

namespace Database\Seeders;

use App\Models\DiningTable;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // New installations start closed until an administrator sets verified capacity.
        // Existing capacity is imported once by the guest-capacity migration.
        // The one-time migration imports the photo menu. Do not overwrite admin edits on redeploy.
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('menu_items', 'source_key')) {
            Schema::table('menu_items', function (Blueprint $table) {
                $table->string('source_key', 80)->nullable()->unique();
                $table->string('name_en', 240)->nullable();
                $table->text('description')->nullable();
                $table->text('description_en')->nullable();
                $table->unsignedInteger('sort_order')->default(9999);
            });
        }
        (new \Database\Seeders\PhotoMenuSeeder)->run();
    }

    public function down(): void
    {
        // Keep the imported menu and reservation history on code rollback.
    }
};

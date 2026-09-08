<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('occasion', 32)->nullable()->index();
            $table->unsignedSmallInteger('birth_year')->nullable();
            $table->string('source', 64)->default('Website')->index();
        });

        DB::table('menu_items')->where('name', 'აჭარული ხაჭაპური')->delete();
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex(['occasion']);
            $table->dropIndex(['source']);
            $table->dropColumn(['occasion', 'birth_year', 'source']);
        });
    }
};

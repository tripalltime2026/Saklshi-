<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('booking_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('capacity')->default(0);
            $table->unsignedSmallInteger('max_party_size')->default(20);
            $table->unsignedSmallInteger('duration_minutes')->default(120);
            $table->unsignedSmallInteger('buffer_minutes')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        DB::table('booking_settings')->insert([
            'id' => 1,
            'capacity' => (int) DB::table('dining_tables')->where('active', true)->sum('capacity'),
            'max_party_size' => 20, 'duration_minutes' => 120, 'buffer_minutes' => 0,
            'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::table('reservations', function (Blueprint $table) {
            $table->unsignedBigInteger('dining_table_id')->nullable()->change();
            $table->unsignedSmallInteger('capacity_end_minute')->nullable();
            $table->index(['visit_date', 'status', 'start_minute'], 'reservation_capacity_lookup');
        });
        DB::table('reservations')->update(['capacity_end_minute' => DB::raw('end_minute')]);
        Schema::create('reservation_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->string('actor', 120);
            $table->timestamp('created_at');
        });
        Schema::create('booking_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action', 80);
            $table->string('actor', 120);
            $table->json('before');
            $table->json('after');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        // New reservations deliberately have no table; reverting NOT NULL would lose data.
        throw new RuntimeException('Guest-count reservations require a forward migration; do not roll back into mandatory table assignment.');
    }
};

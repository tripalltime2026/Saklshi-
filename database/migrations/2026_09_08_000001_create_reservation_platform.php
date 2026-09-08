<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dining_tables', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->unsignedTinyInteger('capacity');
            $table->unsignedTinyInteger('x');
            $table->unsignedTinyInteger('y');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('category', 120)->index();
            $table->unsignedInteger('price');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 12)->unique();
            $table->date('visit_date')->index();
            $table->unsignedSmallInteger('start_minute');
            $table->unsignedSmallInteger('end_minute');
            $table->foreignId('dining_table_id')->constrained('dining_tables')->restrictOnDelete();
            $table->unsignedTinyInteger('guests');
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('phone', 24)->index();
            $table->unsignedTinyInteger('birth_day');
            $table->unsignedTinyInteger('birth_month');
            $table->boolean('marketing_consent')->default(false);
            $table->timestamp('consent_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 24)->default('confirmed')->index();
            $table->timestamps();
            $table->index(['visit_date', 'dining_table_id']);
        });

        Schema::create('reservation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_item_id')->nullable()->constrained('menu_items')->nullOnDelete();
            $table->string('name', 160);
            $table->unsignedInteger('unit_price');
            $table->unsignedTinyInteger('quantity');
            $table->timestamps();
        });

        Schema::create('booking_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dining_table_id')->constrained('dining_tables')->cascadeOnDelete();
            $table->date('visit_date');
            $table->unsignedSmallInteger('minute');
            $table->unique(['dining_table_id', 'visit_date', 'minute'], 'unique_table_slot');
            $table->index(['visit_date', 'minute']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_slots');
        Schema::dropIfExists('reservation_items');
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('dining_tables');
    }
};

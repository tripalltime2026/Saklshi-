<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 80)->unique();
            $table->string('city', 120)->nullable();
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('timezone', 64)->default('Asia/Tbilisi');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });

        DB::table('branches')->insert([
            'id' => 1,
            'name' => 'ბათუმის სახლი',
            'slug' => 'batumi',
            'city' => 'ბათუმი',
            'address' => 'ფარნავაზ მეფის 69',
            'timezone' => 'Asia/Tbilisi',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('branch_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->boolean('closed')->default(false);
            $table->timestamps();
            $table->unique(['branch_id', 'weekday']);
        });

        Schema::create('branch_special_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->date('date');
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->boolean('closed')->default(false);
            $table->unsignedInteger('capacity_override')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->unique(['branch_id', 'date']);
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 24)->unique();
            $table->string('email')->nullable()->unique();
            $table->string('password')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('locale', 8)->default('ka');
            $table->boolean('active')->default(true)->index();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('first_name', 80)->nullable();
            $table->string('last_name', 80)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('avatar_path')->nullable();
            $table->boolean('marketing_consent')->default(false);
            $table->timestamp('marketing_consent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('phone_verification_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 24)->index();
            $table->string('purpose', 32)->default('login');
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['phone', 'purpose', 'expires_at']);
        });

        Schema::table('booking_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->default(1)->unique();
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->default(1)->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->default(1)->index();
        });

        Schema::table('dining_tables', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->default(1)->index();
        });

        Schema::table('booking_slots', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->default(1)->index();
        });

        Schema::table('booking_audit_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->default(1)->index();
        });
    }

    public function down(): void
    {
        Schema::table('booking_audit_logs', function (Blueprint $table) {
            $table->dropColumn('branch_id');
        });
        Schema::table('booking_slots', function (Blueprint $table) {
            $table->dropColumn('branch_id');
        });
        Schema::table('dining_tables', function (Blueprint $table) {
            $table->dropColumn('branch_id');
        });
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn('branch_id');
        });
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['branch_id', 'user_id']);
        });
        Schema::table('booking_settings', function (Blueprint $table) {
            $table->dropUnique(['branch_id']);
            $table->dropColumn('branch_id');
        });

        Schema::dropIfExists('phone_verification_codes');
        Schema::dropIfExists('user_profiles');
        Schema::dropIfExists('users');
        Schema::dropIfExists('branch_special_hours');
        Schema::dropIfExists('branch_hours');
        Schema::dropIfExists('branches');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Parrainage : code personnel, filleuls inscrits et jours offerts au parrain. */
return new class extends Migration
{
    public function up(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE licenses MODIFY source ENUM('stripe','manual','trial','free','code','referral') NOT NULL");
        }
        Schema::table('users', function (Blueprint $table) {
            $table->string('referral_code', 16)->nullable()->unique();
            $table->foreignId('referred_by_id')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referee_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending'); // pending (e-mail à confirmer), rewarded, banked, used, capped
            $table->unsignedSmallInteger('days')->default(0);
            $table->foreignId('license_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('rewarded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referred_by_id');
            $table->dropUnique(['referral_code']);
            $table->dropColumn('referral_code');
        });
    }
};

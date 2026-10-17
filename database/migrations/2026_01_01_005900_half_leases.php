<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fiche demi-pension : proposée par le propriétaire (invitation), acceptée par la demi-pension (accès « Demi-pension »),
 * avec ses jours habituels, ses dates (essai, fin, préavis) et son tarif. Libellé d'un partage gardé jusqu'à l'acceptation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('half_leases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('horse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invitation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('grant_id')->nullable()->constrained('horse_access_grants')->nullOnDelete();
            $table->string('name', 150);
            $table->string('email');
            $table->string('phone', 40)->nullable();
            $table->date('starts_on');
            $table->date('trial_ends_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedSmallInteger('notice_days')->nullable();
            $table->json('weekdays')->nullable();
            $table->decimal('monthly_amount', 10, 2)->nullable();
            $table->unsignedTinyInteger('payment_day')->nullable();
            $table->json('permissions')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('proposed');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('days_applied_at')->nullable();
            $table->timestamp('trial_reminded_at')->nullable();
            $table->timestamps();
            $table->index(['horse_id', 'status']);
            $table->index(['user_id', 'status']);
        });
        Schema::table('invitations', function (Blueprint $table) {
            $table->string('label', 60)->nullable()->after('permissions');
        });
    }

    public function down(): void
    {
        Schema::table('invitations', fn (Blueprint $table) => $table->dropColumn('label'));
        Schema::dropIfExists('half_leases');
    }
};

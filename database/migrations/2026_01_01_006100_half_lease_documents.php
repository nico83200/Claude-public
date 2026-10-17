<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documents d'une demi-pension (contrat signé, assurance, licence…) : rangés avec les documents du cheval mais rattachés
 * à la fiche (visibles du propriétaire et de la demi-pension seulement), avec une date de validité et ses rappels.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('horse_documents', function (Blueprint $table) {
            $table->string('category', 30)->default('other')->change();
            $table->date('valid_until')->nullable()->after('is_sensitive');
            $table->timestamp('expiry_warned_at')->nullable()->after('valid_until');
            $table->timestamp('expired_notified_at')->nullable()->after('expiry_warned_at');
        });
    }

    public function down(): void
    {
        Schema::table('horse_documents', function (Blueprint $table) {
            $table->dropColumn(['valid_until', 'expiry_warned_at', 'expired_notified_at']);
        });
    }
};

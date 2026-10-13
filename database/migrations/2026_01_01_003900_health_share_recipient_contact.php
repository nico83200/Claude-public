<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Résumé vétérinaire : coordonnées du destinataire et dernier envoi du lien. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('health_shares', function (Blueprint $table) {
            $table->string('recipient_email')->nullable()->after('recipient');
            $table->string('recipient_phone', 40)->nullable()->after('recipient_email');
            $table->timestamp('emailed_at')->nullable()->after('recipient_phone');
        });
    }

    public function down(): void
    {
        Schema::table('health_shares', fn (Blueprint $table) => $table->dropColumn(['recipient_email', 'recipient_phone', 'emailed_at']));
    }
};

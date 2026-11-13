<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Historique des actions (2.53) : toutes les saisies y sont notées ; tri par date et recherche par personne rapides. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('created_at', 'audit_created_idx');
            $table->index(['user_id', 'created_at'], 'audit_user_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_created_idx');
            $table->dropIndex('audit_user_created_idx');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Lecture de facture par l'assistant : nouvelle source de consigne (« document »), comptée dans le quota. */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            DB::statement("ALTER TABLE assistant_requests MODIFY input_mode ENUM('voice', 'text', 'document') NOT NULL DEFAULT 'text'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            DB::statement("UPDATE assistant_requests SET input_mode = 'text' WHERE input_mode = 'document'");
            DB::statement("ALTER TABLE assistant_requests MODIFY input_mode ENUM('voice', 'text') NOT NULL DEFAULT 'text'");
        }
    }
};

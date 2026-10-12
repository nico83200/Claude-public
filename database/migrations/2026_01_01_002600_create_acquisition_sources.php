<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Sources de provenance (affiches, Instagram, flyers, partenaires…), gérées dans l'administration. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acquisition_sources', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('label', 80);
            $table->timestamps();
        });
        $now = now();
        DB::table('acquisition_sources')->insert([
            ['key' => 'affiche', 'label' => 'Affiches (QR code)', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'instagram', 'label' => 'Instagram (lien en bio)', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'instagram-story', 'label' => 'Instagram (story)', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('acquisition_sources');
    }
};

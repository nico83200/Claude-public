<?php

use App\Models\CareRecord;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Les contrôles prévus des soins ne sont plus ajoutés d'office à l'agenda (« Contrôle : … ») :
 * ceux encore à venir sont retirés ; Santé les indique et propose de les ajouter à l'agenda.
 * Les contrôles réalisés restent dans l'historique de l'agenda. Le soin garde sa date de contrôle.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('calendar_events')->where('source_type', (new CareRecord)->getMorphClass())
            ->whereIn('status', ['planned', 'confirmed'])->where('title', 'like', 'Contrôle :%')->whereNull('deleted_at')
            ->update(['deleted_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void {}
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Espaces personnels au nom du cavalier : « Espace de Nicolas LEFEVRE » devient « Nicolas LEFEVRE »
 * (nom actuel du compte ; les espaces renommés autrement par leur propriétaire ne changent pas).
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('organizations')->leftJoin('users', 'users.id', '=', 'organizations.owner_id')
            ->where('organizations.type', 'personal')->where('organizations.name', 'like', 'Espace de %')
            ->get(['organizations.id', 'organizations.name', 'users.name as owner_name']);
        foreach ($rows as $row) {
            $name = trim((string) ($row->owner_name ?: mb_substr($row->name, mb_strlen('Espace de '))));
            if ($name !== '') {
                DB::table('organizations')->where('id', $row->id)->update(['name' => mb_substr($name, 0, 255)]);
            }
        }
    }

    public function down(): void
    {
        // Pas de retour en arrière : l'ancien libellé n'apporte rien et peut avoir été modifié depuis.
    }
};

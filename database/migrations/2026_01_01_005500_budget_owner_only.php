<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Budget du cheval réservé au propriétaire (2.42.1) : les droits « Consulter les dépenses » et « Saisir des dépenses »
 * sont retirés des partages, des écuries de rattachement et des invitations à un cheval. Les dépenses déjà saisies restent.
 */
return new class extends Migration
{
    private const OWNER_ONLY = ['expenses.view', 'expenses.create'];

    public function up(): void
    {
        foreach (['horse_access_grants', 'horse_organization_assignments', 'invitations'] as $table) {
            DB::table($table)->whereNotNull('permissions')->orderBy('id')->each(function ($row) use ($table) {
                $perms = json_decode((string) $row->permissions, true);
                if (! is_array($perms) || ! array_intersect($perms, self::OWNER_ONLY)) {
                    return;
                }
                DB::table($table)->where('id', $row->id)->update(['permissions' => json_encode(array_values(array_diff($perms, self::OWNER_ONLY)))]);
            });
        }
    }

    public function down(): void
    {
        // Droits retirés volontairement : rien à rétablir.
    }
};

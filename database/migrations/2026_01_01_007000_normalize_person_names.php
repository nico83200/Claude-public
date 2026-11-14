<?php

use App\Support\PersonName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2.55 : noms et prénoms écrits de la même manière partout (« Prénom Nom »). Les noms déjà enregistrés sont réécrits,
 * ainsi que le nom des espaces personnels qui reprend celui de leur propriétaire.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'users' => ['name'], 'user_profiles' => ['first_name', 'last_name'], 'professionals' => ['first_name', 'last_name'],
        'half_leases' => ['name'], 'horses' => ['emergency_contact_name'], 'horse_ownerships' => ['owner_name'],
        'boarding_contracts' => ['owner_name'], 'boarding_statements' => ['owner_name'], 'riding_sessions' => ['rider_name'],
    ];

    public function up(): void
    {
        // Espaces personnels nommés comme leur propriétaire : suivent le nouveau nom.
        $personal = DB::table('organizations')->join('users', 'users.id', '=', 'organizations.owner_id')
            ->where('organizations.type', 'personal')->whereColumn('organizations.name', 'users.name')->pluck('organizations.id');

        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $column) {
                DB::table($table)->whereNotNull($column)->where($column, '!=', '')->select(['id', $column])->orderBy('id')
                    ->chunkById(500, function ($rows) use ($table, $column) {
                        foreach ($rows as $row) {
                            $formatted = PersonName::format($row->{$column});
                            if ($formatted !== $row->{$column}) {
                                DB::table($table)->where('id', $row->id)->update([$column => $formatted]);
                            }
                        }
                    });
            }
        }

        foreach (DB::table('organizations')->join('users', 'users.id', '=', 'organizations.owner_id')->whereIn('organizations.id', $personal)
            ->get(['organizations.id', 'users.name']) as $row) {
            DB::table('organizations')->where('id', $row->id)->update(['name' => $row->name]);
        }
    }

    public function down(): void
    {
        // Pas de retour : l'écriture d'origine n'est pas conservée.
    }
};

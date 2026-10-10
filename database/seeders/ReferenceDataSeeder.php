<?php

namespace Database\Seeders;

use App\Models\CareCategory;
use App\Models\ExpenseCategory;
use App\Models\FeatureFlag;
use App\Models\HorseBreed;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Perm;
use Illuminate\Database\Seeder;

/**
 * Données de référence indispensables en production (idempotent).
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Perm::horseLabels() as $key => $label) {
            Permission::updateOrCreate(['key' => $key], ['label' => $label, 'group' => 'horse']);
        }
        foreach (Perm::orgLabels() as $key => $label) {
            Permission::updateOrCreate(['key' => $key], ['label' => $label, 'group' => 'organization']);
        }

        foreach (Perm::systemRoles() as $key => $def) {
            $role = Role::firstOrCreate(['organization_id' => null, 'key' => $key], ['name' => $def['name'], 'is_system' => true]);
            $role->update(['name' => $def['name'], 'is_system' => true]);
            $role->syncPermissionKeys($def['perms']);
        }

        $care = [
            'veterinary' => ['Vétérinaire', 'red'], 'farrier' => ['Maréchal-ferrant', 'amber'], 'osteopathy' => ['Ostéopathe', 'violet'],
            'dentistry' => ['Dentiste équin', 'cyan'], 'vaccination' => ['Vaccination', 'emerald'], 'deworming' => ['Vermifugation', 'lime'],
            'exams' => ['Examens et analyses', 'sky'], 'imaging' => ['Imagerie', 'indigo'], 'injury' => ['Blessure / incident', 'rose'],
            'treatment' => ['Traitement', 'purple'], 'routine' => ['Soins courants', 'slate'], 'specialist' => ['Consultation spécialisée', 'blue'],
        ];
        foreach ($care as $key => [$name, $color]) {
            CareCategory::updateOrCreate(['organization_id' => null, 'key' => $key], ['name' => $name, 'color' => $color]);
        }

        $expenses = [
            'boarding' => 'Pension', 'feed' => 'Alimentation', 'veterinary' => 'Vétérinaire', 'farrier' => 'Maréchal-ferrant',
            'osteopathy' => 'Ostéopathe', 'dentistry' => 'Dentiste', 'tack' => 'Matériel', 'equipment' => 'Équipement',
            'competition' => 'Concours', 'transport' => 'Transport', 'insurance' => 'Assurance', 'other' => 'Autres',
        ];
        foreach ($expenses as $key => $name) {
            ExpenseCategory::updateOrCreate(['key' => $key], ['name' => $name]);
        }

        foreach (['Selle Français', 'Pur-sang', 'Anglo-arabe', 'Arabe', 'Trotteur Français', 'KWPN', 'Hanovrien', 'Holsteiner', 'Oldenbourg', 'Pure Race Espagnole', 'Lusitanien', 'Frison', 'Connemara', 'Welsh', 'Shetland', 'Poney Français de Selle', 'Haflinger', 'Fjord', 'Mérens', 'Camargue', 'Comtois', 'Percheron', 'Breton', 'Appaloosa', 'Paint Horse', 'Quarter Horse', 'Irish Cob', 'Islandais', 'Barbe', 'Akhal-Teke', 'Origine non constatée', 'Autre'] as $breed) {
            HorseBreed::firstOrCreate(['name' => $breed]);
        }

        $flags = [
            'registration' => ['Inscriptions ouvertes', true],
            'horse_search' => ['Recherche d\'identité sur Internet', true],
            'offline' => ['Mode hors ligne', true],
            'billing' => ['Souscription en ligne (Stripe)', true],
            'email_notifications' => ['Envoi des notifications par email', true],
        ];
        foreach ($flags as $key => [$label, $enabled]) {
            FeatureFlag::firstOrCreate(['key' => $key], ['label' => $label, 'enabled_globally' => $enabled]);
        }

        $this->call(ExerciseLibrarySeeder::class);
    }
}

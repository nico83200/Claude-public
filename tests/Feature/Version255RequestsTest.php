<?php

namespace Tests\Feature;

use App\Models\Professional;
use App\Models\User;
use App\Support\PersonName;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Noms et prénoms écrits de la même manière partout : première lettre en majuscule, la suite en minuscules. */
class Version255RequestsTest extends TestCase
{
    public function test_the_rule(): void
    {
        $cases = [
            'paul MARTIN' => 'Paul Martin', 'LÉA DURAND' => 'Léa Durand', 'jean-PIERRE dupont' => 'Jean-Pierre Dupont',
            'charles DE GAULLE' => 'Charles de Gaulle', 'anne d\'artagnan' => 'Anne d\'Artagnan', 'o\'neil' => 'O\'Neil',
            '  marie   claire ' => 'Marie Claire', 'Claire Dupont (démo)' => 'Claire Dupont (démo)', 'ÉLOÏSE étienne-LÉGER' => 'Éloïse Étienne-Léger',
        ];
        foreach ($cases as $typed => $expected) {
            $this->assertSame($expected, PersonName::format($typed), $typed);
        }
    }

    public function test_names_are_written_the_same_way_whatever_is_typed(): void
    {
        $this->post(route('register.store'), ['name' => 'jean-PIERRE DUPONT', 'email' => 'jp@exemple.fr', 'password' => 'Mot2Passe!longue', 'password_confirmation' => 'Mot2Passe!longue', 'terms' => '1']);
        $user = User::where('email', 'jp@exemple.fr')->sole();
        $this->assertSame(['Jean-Pierre Dupont', 'Jean-Pierre Dupont'], [$user->name, $user->personalOrganization()->name]);

        // Changement de nom : l'espace personnel suit, écrit pareil.
        $this->actingAs($user)->put(route('user-profile-information.update'), ['name' => 'JEAN DUPONT', 'email' => 'jp@exemple.fr']);
        $this->assertSame(['Jean Dupont', 'Jean Dupont'], [$user->fresh()->name, $user->fresh()->personalOrganization()->name]);

        // Intervenant : prénom et nom séparés.
        $this->actingAs($user)->post(route('professionals.store'), ['first_name' => 'JULIE', 'last_name' => 'bernard', 'kind' => 'veterinarian']);
        $this->assertSame('Julie Bernard', Professional::sole()->fullName());
    }

    public function test_existing_names_are_rewritten_by_the_update(): void
    {
        $user = $this->user(['name' => 'Paul Martin']);
        DB::table('users')->where('id', $user->id)->update(['name' => 'paul MARTIN']);
        DB::table('organizations')->where('id', $user->personalOrganization()->id)->update(['name' => 'paul MARTIN']);
        $stable = $this->stable($user, 'Écurie DU LAC');
        $pro = Professional::forceCreate(['organization_id' => $user->personalOrganization()->id, 'kind' => 'farrier', 'first_name' => 'x', 'last_name' => 'y']);
        DB::table('professionals')->where('id', $pro->id)->update(['first_name' => 'MARC', 'last_name' => 'ROUX']);

        (require database_path('migrations/2026_01_01_007000_normalize_person_names.php'))->up();
        $this->assertSame(['Paul Martin', 'Paul Martin'], [$user->fresh()->name, $user->fresh()->personalOrganization()->name]);
        $this->assertSame('Marc Roux', $pro->fresh()->fullName());
        $this->assertSame('Écurie DU LAC', $stable->fresh()->name, 'Le nom d\'une écurie n\'est pas un nom de personne.');
    }
}

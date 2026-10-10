<?php

namespace Database\Seeders;

use App\Models\CareCategory;
use App\Models\CareRecord;
use App\Models\Exercise;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Horse;
use App\Models\HorseAccessGrant;
use App\Models\HorseBreed;
use App\Models\HorseOrganizationAssignment;
use App\Models\License;
use App\Models\Organization;
use App\Models\Professional;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\HorseService;
use App\Services\OrganizationService;
use App\Services\RidingSessionService;
use App\Support\Perm;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Données de DÉMONSTRATION, clairement identifiées (is_demo = true, domaine
 *
 * @demo.jackcie.test). Refusé en production.
 *
 * php artisan db:seed --class=DemoSeeder
 * Comptes : proprietaire@demo.jackcie.test / cavaliere@demo.jackcie.test /
 *           gerant@demo.jackcie.test — mot de passe : Demo2026!demo
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Les données de démonstration ne peuvent pas être chargées en production.');
        }
        $orgs = app(OrganizationService::class);
        $horses = app(HorseService::class);
        $sessions = app(RidingSessionService::class);

        $mk = function (string $email, string $name) use ($orgs) {
            $u = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => 'Demo2026!demo', 'terms_accepted_at' => now()]);
            $u->forceFill(['email_verified_at' => now()])->save();
            if (! $u->personalOrganization()) {
                $orgs->createPersonal($u);
            }
            $u->personalOrganization()->forceFill(['is_demo' => true])->save();

            return $u->fresh();
        };

        $owner = $mk('proprietaire@demo.jackcie.test', 'Claire Dupont (démo)');
        $rider = $mk('cavaliere@demo.jackcie.test', 'Léa Martin (démo)');
        $manager = $mk('gerant@demo.jackcie.test', 'Paul Bernard (démo)');

        $personal = $owner->personalOrganization();
        License::firstOrCreate(['organization_id' => $personal->id, 'source' => 'manual'], ['subscription_plan_id' => SubscriptionPlan::where('slug', 'multi-chevaux')->value('id'), 'status' => 'active', 'starts_at' => now(), 'notes' => 'Démonstration']);

        $stable = Organization::where('slug', 'ecurie-demo')->first();
        if (! $stable) {
            $stable = $orgs->createStable($manager, ['name' => 'Écurie des Tilleuls (démo)', 'city' => 'Saumur']);
            $stable->forceFill(['slug' => 'ecurie-demo', 'is_demo' => true])->save();
            License::create(['organization_id' => $stable->id, 'subscription_plan_id' => SubscriptionPlan::where('slug', 'ecurie')->value('id'), 'source' => 'manual', 'status' => 'active', 'starts_at' => now(), 'notes' => 'Démonstration']);
        }

        if (Horse::where('organization_id', $personal->id)->exists()) {
            return;
        }

        $sf = HorseBreed::where('name', 'Selle Français')->value('id');
        $h1 = $horses->create($personal, $owner, ['official_name' => 'UNIVERS DES TILLEULS', 'usual_name' => 'Uni', 'sex' => 'gelding', 'birth_year' => 2008, 'horse_breed_id' => $sf, 'coat' => 'Bai', 'height_cm' => 166, 'main_discipline' => 'jumping', 'particularities' => 'Tique au montoir, calme en extérieur.', 'precautions' => 'Ne pas attacher court : recule.', 'care_instructions' => 'Guêtres aux antérieurs pour le travail.', 'identifiers' => ['sire' => '08123456K']], true);
        $h2 = $horses->create($personal, $owner, ['official_name' => 'CAPUCINE', 'sex' => 'female', 'birth_year' => 2014, 'horse_breed_id' => HorseBreed::where('name', 'Connemara')->value('id'), 'coat' => 'Gris', 'main_discipline' => 'leisure'], true);
        foreach ([$h1, $h2] as $h) {
            $h->forceFill(['is_demo' => true])->save();
        }

        HorseAccessGrant::create(['horse_id' => $h1->id, 'user_id' => $rider->id, 'label' => 'Demi-pension', 'permissions' => Perm::halfLeasePreset(), 'granted_by' => $owner->id, 'expires_at' => now()->addMonths(6)]);
        HorseOrganizationAssignment::create(['horse_id' => $h1->id, 'organization_id' => $stable->id, 'kind' => 'boarding', 'permissions' => Perm::boardingPreset(), 'status' => 'active', 'starts_at' => now()->subMonths(3)]);

        $vet = new Professional(['first_name' => 'Anne', 'last_name' => 'Leroy', 'kind' => 'veterinarian', 'phone' => '02 00 00 00 00']);
        $vet->organization_id = $personal->id;
        $vet->save();
        $h1->professionals()->attach($vet->id, ['is_primary' => true]);

        $care = new CareRecord(['care_category_id' => CareCategory::where('key', 'vaccination')->value('id'), 'professional_id' => $vet->id, 'performed_at' => now()->subMonths(5), 'reason' => 'Rappel grippe-tétanos', 'cost' => 75, 'next_check_on' => now()->addMonth()]);
        $care->horse_id = $h1->id;
        $care->author_id = $owner->id;
        $care->save();

        $ex = Exercise::with('category')->where('scope', 'default')->limit(4)->get();
        foreach ([14, 7, 3] as $i => $daysAgo) {
            $s = $sessions->create($h1, $rider, ['scheduled_at' => now()->subDays($daysAgo)->setTime(18, 0), 'session_type' => $i === 1 ? 'poles' : 'dressage', 'objective' => 'Régularité', 'planned_minutes' => 45], $ex->map(fn ($e) => ['exercise_id' => $e->id, 'phase' => $e->category?->key === 'warmup' ? 'warmup' : 'main'])->all());
            $s->items()->update(['status' => 'done']);
            $sessions->complete($s, ['actual_minutes' => 45, 'rider_feeling' => 4, 'horse_behavior' => 4, 'progress' => 'Transitions plus fluides', 'to_rework' => $i === 2 ? 'Rectitude sur la ligne du milieu' : null]);
        }
        $sessions->create($h1, $rider, ['scheduled_at' => now()->addDay()->setTime(18, 0), 'session_type' => 'dressage', 'objective' => 'Cession à la jambe'], $ex->take(2)->map(fn ($e) => ['exercise_id' => $e->id])->all());

        foreach ([['boarding', 380], ['farrier', 95], ['feed', 62.4]] as [$cat, $amount]) {
            $e = new Expense(['horse_id' => $h1->id, 'expense_category_id' => ExpenseCategory::where('key', $cat)->value('id'), 'spent_on' => now()->subDays(rand(1, 25)), 'amount' => $amount]);
            $e->organization_id = $personal->id;
            $e->author_id = $owner->id;
            $e->save();
        }
    }
}

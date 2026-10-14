<?php
// Données de démonstration supplémentaires pour les captures du site (base LOCALE uniquement, jamais en production).
// Usage : php artisan tinker --execute "require 'scripts/site/demo-ecurie.php';"
use App\Models\{BoardingCharge, BoardingContract, CareCategory, CareRecord, Exercise, Horse, HorseBreed, Organization, User};
use App\Services\{BoardingBilling, HorseService, RidingSessionService};

abort_if(app()->environment('production'), 403, 'Script réservé à la base de démonstration locale.');
$stable = Organization::where('slug', 'ecurie-demo')->firstOrFail();
$manager = User::where('email', 'gerant@demo.jackcie.test')->firstOrFail();
if (Horse::where('organization_id', $stable->id)->count() >= 6) {
    echo "Déjà fait\n";

    return;
}
$horses = app(HorseService::class);
$sessions = app(RidingSessionService::class);
$breed = fn ($n) => HorseBreed::where('name', $n)->value('id');
$list = [
    ['ALTESSE DU VAL', 'Altesse', 'female', 2012, 'Selle Français', 'Alezan', 'Sophie Martin', 420],
    ['BALOO DES PRÉS', 'Baloo', 'gelding', 2010, 'Connemara', 'Gris', 'Julien Petit', 395],
    ['CÉLESTE DE LA ROCHE', 'Céleste', 'female', 2016, 'Pur-sang anglais', 'Bai brun', 'Émilie Laurent', 450],
    ['DOLLAR SAINT-JEAN', 'Dollar', 'gelding', 2011, 'Selle Français', 'Bai', 'Marc Rousseau', 420],
    ['ÉCLAIR DU MOULIN', 'Éclair', 'male', 2015, 'Pur-sang arabe', 'Gris', 'Nathalie Garnier', 480],
    ['FLOCON', 'Flocon', 'gelding', 2009, 'Haflinger', 'Alezan crins lavés', 'Chloé Bernard', 380],
    ['GIPSY KING', 'Gipsy', 'gelding', 2013, 'Irish Cob', 'Pie noir', 'Thomas Lefèvre', 395],
    ['HERMINE DES TILLEULS', 'Hermine', 'female', 2017, 'Selle Français', 'Noir', 'Laura Dubois', 450],
];
$ex = Exercise::with('category')->where('scope', 'default')->limit(4)->get();
$vacc = CareCategory::where('key', 'vaccination')->value('id');
$farrier = CareCategory::where('key', 'farrier')->value('id') ?? $vacc;
foreach ($list as $i => [$official, $usual, $sex, $year, $b, $coat, $owner, $price]) {
    $h = $horses->create($stable, $manager, ['official_name' => $official, 'usual_name' => $usual, 'sex' => $sex, 'birth_year' => $year, 'horse_breed_id' => $breed($b), 'coat' => $coat, 'main_discipline' => ['jumping', 'dressage', 'leisure', 'eventing'][$i % 4]], true);
    $h->forceFill(['is_demo' => true])->save();
    $c = new BoardingContract(['horse_id' => $h->id, 'owner_name' => $owner, 'owner_email' => null, 'label' => $price >= 450 ? 'Pension box + paddock' : 'Pension box', 'monthly_amount' => $price, 'starts_on' => now()->subMonths(rand(4, 20))->startOfMonth()]);
    $c->organization_id = $stable->id;
    $c->save();
    foreach ([['Ferrure avant', 1, 75], ['Sortie paddock', rand(4, 12), 6], ['Complément alimentaire', 1, 32]] as $k => [$label, $q, $u]) {
        if (($i + $k) % 2) {
            $ch = new BoardingCharge(['horse_id' => $h->id, 'charged_on' => now()->startOfMonth()->addDays(rand(1, 10)), 'label' => $label, 'quantity' => $q, 'unit_price' => $u, 'author_id' => $manager->id]);
            $ch->organization_id = $stable->id;
            $ch->save();
        }
    }
    $care = new CareRecord(['care_category_id' => $i % 2 ? $vacc : $farrier, 'performed_at' => now()->subWeeks(rand(3, 30)), 'reason' => $i % 2 ? 'Rappel grippe-tétanos' : 'Ferrure', 'next_check_on' => now()->addDays(rand(2, 25))]);
    $care->horse_id = $h->id;
    $care->author_id = $manager->id;
    $care->save();
    foreach ([rand(0, 6), rand(7, 20)] as $d) {
        $sessions->create($h, $manager, ['scheduled_at' => now()->addDays($d)->setTime([9, 10, 14, 17, 18][rand(0, 4)], 0), 'session_type' => ['dressage', 'jumping', 'poles', 'lunging'][rand(0, 3)], 'planned_minutes' => 45], $ex->take(2)->map(fn ($e) => ['exercise_id' => $e->id])->all());
    }
}
// Relevés du mois préparés (pension au prorata + prestations).
app(BoardingBilling::class)->generate($stable, now()->format('Y-m'));
echo "Écurie de démonstration enrichie\n";

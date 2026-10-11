<?php
require '/home/user/Claude-public/vendor/autoload.php';
$app = require '/home/user/Claude-public/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\{User, Horse, FeedingPlan, RidingSession};
// Base commune (pension récurrente, cours du matin) puis ajustements pour les visuels
passthru('php /tmp/claude-0/-home-user-Claude-public/b11dbf7a-2a75-575f-a224-4c6377325b29/scratchpad/tuto/fixtures.php 0');
RidingSession::where('instructor', 'Jimmy')->update(['instructor' => 'Léo']);
RidingSession::all()->each->touch(); // titres du calendrier recalculés
$u = User::where('email', 'proprietaire@demo.jackcie.test')->first();
$uni = Horse::find(1);
FeedingPlan::where('horse_id', $uni->id)->get()->each(fn ($p) => $p->entries()->delete() || true)->each->delete();
$plan = FeedingPlan::create(['horse_id' => $uni->id, 'author_id' => $u->id, 'name' => 'Ration hiver', 'starts_on' => now()->subWeeks(2)->toDateString(), 'instructions' => 'Foin à volonté le soir. Mash le dimanche.']);
foreach ([['07:30', 'Granulés sport', 1.5, 'kg', '2x/jour', false], ['12:00', 'Foin', 4, 'kg', null, false], ['18:00', 'Granulés sport', 1.5, 'kg', null, false], ['18:00', 'Huile de lin', 50, 'g', '1x/jour', true]] as [$t, $f, $q, $unit, $fr, $sup]) {
    $plan->entries()->create(['time_of_day' => $t, 'feed' => $f, 'quantity' => $q, 'unit' => $unit, 'frequency' => $fr, 'is_supplement' => $sup]);
}
User::where('email', 'gerant@demo.jackcie.test')->update(['current_organization_id' => 4]);
echo "ok\n";

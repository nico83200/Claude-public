<?php

namespace App\Http\Controllers;

use App\Models\CareRecord;
use App\Models\HealthObservation;
use App\Models\Horse;
use App\Models\Treatment;
use App\Services\HorseAccess;
use App\Support\Perm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

class HealthOverviewController extends Controller
{
    /** Onglets de la page Santé du menu : la page du cheval correspondante, avec ses boutons et formulaires. */
    private const TABS = [
        'alimentation' => [FeedingController::class, 'show', Perm::FEEDING_VIEW],
        'suivi' => [DailyLogController::class, 'index', Perm::HORSE_VIEW],
        'poids' => [MeasurementController::class, 'index', Perm::HORSE_VIEW],
    ];

    public function __invoke(Request $request, HorseAccess $access)
    {
        $user = $request->user();
        if ($tab = self::TABS[$request->query('onglet')] ?? null) {
            $horses = Horse::with('organization')->whereIn('id', $access->horseIdsWith($user, $tab[2]))->whereNull('archived_at')->orderBy('official_name')->get();
            $horse = $horses->firstWhere('id', $request->integer('cheval')) ?? $horses->first();
            if (! $horse) {
                return view('health.hub-empty', ['healthHub' => ['tab' => $request->query('onglet'), 'horses' => $horses, 'horse' => null]]);
            }
            // Cheval relu seul (comme sur sa propre page) : ses relations se chargent à la demande.
            $horse = Horse::findOrFail($horse->id);
            View::share('healthHub', ['tab' => $request->query('onglet'), 'horses' => $horses, 'horse' => $horse]);

            return app()->call([app($tab[0]), $tab[1]], ['horse' => $horse]);
        }
        $healthIds = $access->horseIdsWith($user, Perm::HEALTH_VIEW);
        $treatIds = $access->horseIdsWith($user, Perm::TREATMENTS_VIEW);

        return view('health.overview', [
            'due' => CareRecord::with(['horse', 'category', 'professional'])->whereIn('horse_id', $healthIds)->whereNotNull('next_check_on')
                ->where('next_check_on', '<=', now()->addDays(60)->toDateString())
                ->whereNotExists(fn ($q) => $q->selectRaw(1)->from('care_records as later')->whereColumn('later.horse_id', 'care_records.horse_id')
                    ->whereColumn('later.care_category_id', 'care_records.care_category_id')->whereColumn('later.performed_at', '>', 'care_records.performed_at')->whereNull('later.deleted_at'))
                ->orderBy('next_check_on')->get(),
            'treatments' => Treatment::with(['horse', 'responsible'])->whereIn('horse_id', $treatIds)->current()->orderBy('ends_on')->get(),
            'recent' => CareRecord::with(['horse', 'category'])->whereIn('horse_id', $healthIds)->orderByDesc('performed_at')->limit(15)->get(),
            'alerts' => HealthObservation::with(['horse', 'author'])->whereIn('horse_id', $healthIds)->whereNull('resolved_at')->where('severity', '!=', 'info')->latest('observed_at')->get(),
            // Résumé pour le vétérinaire : chevaux dont on voit la santé (choix proposé s'il y en a plusieurs).
            'vetHorses' => $vetHorses = Horse::with('organization')->whereIn('id', $healthIds)->whereNull('archived_at')->orderBy('official_name')->get(),
            // Soin ou ordonnance : chevaux dont on peut modifier la santé.
            'editHorses' => $vetHorses->whereIn('id', $access->horseIdsWith($user, Perm::HEALTH_EDIT))->values(),
        ]);
    }
}

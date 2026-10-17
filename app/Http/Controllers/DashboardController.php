<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\CareRecord;
use App\Models\DailyLog;
use App\Models\Expense;
use App\Models\HealthObservation;
use App\Models\Horse;
use App\Models\HorseAccessGrant;
use App\Models\HorseOrganizationAssignment;
use App\Models\Invitation;
use App\Models\RecurringExpense;
use App\Models\RidingSession;
use App\Models\Treatment;
use App\Services\Entitlements;
use App\Services\HorseAccess;
use App\Services\Onboarding;
use App\Services\RidingDays;
use App\Services\ToValidate;
use App\Services\Upsell;
use App\Support\Perm;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /** Une échéance s'affiche sur la carte du cheval à partir de 30 jours avant sa date. */
    public const DUE_SOON_DAYS = 30;

    public function __invoke(Request $request, HorseAccess $access)
    {
        $user = $request->user();
        // Un seul cheval, ou cheval actif choisi : l'accueil est son tableau de bord.
        if ($focus = $access->focusHorse($user)) {
            return $this->solo($request, $access, Horse::with(['breed', 'organization'])->findOrFail($focus->id));
        }
        $horses = $access->accessibleQuery($user)->whereNull('archived_at')->with('breed')->orderBy('official_name')->get();
        $access->preload($horses);
        $ids = fn (string $perm) => $horses->filter(fn ($h) => in_array($perm, $access->permissions($user, $h), true))->pluck('id');

        $calendarIds = $ids(Perm::CALENDAR_VIEW);
        $healthIds = $ids(Perm::HEALTH_VIEW);
        $treatIds = $ids(Perm::TREATMENTS_VIEW);
        $sessionIds = $ids(Perm::SESSIONS_VIEW);
        $expenseIds = $ids(Perm::EXPENSES_VIEW);

        // Prochaine échéance de suivi par cheval (pastille sur la carte du cheval) : seulement dans les 30 jours, ou en retard.
        $nextDue = CareRecord::with('category')->whereIn('horse_id', $healthIds)->whereNotNull('next_check_on')->whereNull('next_check_done_at')
            ->where('next_check_on', '<=', now()->addDays(self::DUE_SOON_DAYS)->toDateString())
            ->orderBy('next_check_on')->get()->groupBy('horse_id')->map->first();

        return view('dashboard', [
            'upsell' => app(Upsell::class)->upgrade($this->currentOrganization(), $request->user()),
            'onboarding' => app(Onboarding::class)->steps($user, $horses),
            // Fiche d'urgence pas encore préparée : proposée sur l'accueil (masquable).
            'emergencyCta' => $user->profile?->emergency_cta_dismissed_at ? null
                : $horses->first(fn ($h) => ! $h->emergency_token && $access->can($user, $h, Perm::HORSE_EDIT)),
            'horses' => $horses,
            'nextDue' => $nextDue,
            'today' => CalendarEvent::with('horse')->whereIn('horse_id', $calendarIds)->visibleSessions($sessionIds)->where('status', '!=', 'cancelled')
                ->whereBetween('starts_at', [now()->startOfDay(), now()->endOfDay()])->orderBy('all_day', 'desc')->orderBy('starts_at')->get(),
            'events' => CalendarEvent::with('horse')->whereIn('horse_id', $calendarIds)->visibleSessions($sessionIds)->where('status', '!=', 'cancelled')
                ->whereBetween('starts_at', [now()->addDay()->startOfDay(), now()->addDays(14)])->orderBy('starts_at')->limit(6)->get(),
            'careDue' => CareRecord::with(['horse', 'category'])->whereIn('horse_id', $healthIds)->whereNotNull('next_check_on')->whereNull('next_check_done_at')
                ->whereBetween('next_check_on', [now()->subDays(7)->toDateString(), now()->addDays(30)->toDateString()])->orderBy('next_check_on')->limit(8)->get(),
            'treatments' => Treatment::with(['horse', 'administrations' => fn ($q) => $q->where('administered_at', '>=', today())])->whereIn('horse_id', $treatIds)->current()->orderBy('horse_id')->limit(30)->get(),
            // Tous les chevaux : état du jour de chacun (suivi noté, alerte) et gestes possibles.
            'todayLogs' => DailyLog::whereIn('horse_id', $horses->pluck('id'))->where('logged_at', '>=', today())->latest('logged_at')->get()->groupBy('horse_id')->map->first(),
            'alertsByHorse' => HealthObservation::whereIn('horse_id', $horses->pluck('id'))->whereNull('resolved_at')->where('severity', '!=', 'info')->latest('observed_at')->get()->groupBy('horse_id')->map->first(),
            'logIds' => $ids(Perm::DAILYLOG_CREATE)->all(),
            'healthEditIds' => $ids(Perm::HEALTH_EDIT)->all(),
            // Échéances à prévoir, regroupées par type de soin : un rendez-vous pour plusieurs chevaux.
            'toPlan' => self::pendingChecks($ids(Perm::HEALTH_EDIT), 45)->groupBy('care_category_id')->filter(fn ($g) => $g->count() > 1),
            'sessions' => RidingSession::with(['horse', 'rider'])->whereIn('horse_id', $sessionIds)->orderByDesc('scheduled_at')->limit(6)->get(),
            'observations' => HealthObservation::with(['horse', 'author'])->whereIn('horse_id', $horses->pluck('id'))->whereNull('resolved_at')
                ->where('severity', '!=', 'info')->latest('observed_at')->limit(5)->get(),
            'logs' => DailyLog::with(['horse', 'author'])->whereIn('horse_id', $horses->pluck('id'))->latest('logged_at')->limit(5)->get(),
            'expenses' => Expense::with(['horse', 'category'])->whereIn('horse_id', $expenseIds)->orderByDesc('spent_on')->limit(5)->get(),
            'invitations' => $this->invitations($user),
            // Rendez-vous et séances passés à valider (tous les chevaux).
            'toValidate' => app(ToValidate::class)->items($user),
            'pendingAssignments' => $user->memberships->filter(fn ($m) => in_array(Perm::ORG_MANAGE, $m->role->permissionKeys(), true))->isNotEmpty()
                ? HorseOrganizationAssignment::with(['horse', 'organization'])->where('status', 'pending')
                    ->whereIn('organization_id', $user->memberships->filter(fn ($m) => in_array(Perm::ORG_MANAGE, $m->role->permissionKeys(), true))->pluck('organization_id'))->get()
                : collect(),
        ]);
    }

    /** Tableau de bord du cheval unique : tout ce qui fait sa vie, sans passer par la liste des chevaux. */
    private function solo(Request $request, HorseAccess $access, Horse $horse)
    {
        $user = $request->user();
        $perms = $access->permissions($user, $horse);
        $has = fn (string $p) => in_array($p, $perms, true);
        $entitlements = app(Entitlements::class);
        $budgetIncluded = $entitlements->hasFeature($horse->organization, 'budget');

        return view('dashboard-solo', [
            'horse' => $horse,
            // Plusieurs chevaux : puces pour passer d'un cheval à l'autre sans ouvrir le menu.
            'otherHorses' => $access->hasSeveralHorses($user)
                ? $access->accessibleHorses($user)->whereNull('archived_at')->sortBy(fn ($h) => mb_strtolower($h->shortName()))->values() : collect(),
            'perms' => $perms,
            'upsell' => app(Upsell::class)->upgrade($this->currentOrganization(), $user),
            'onboarding' => app(Onboarding::class)->steps($user, collect([$horse])),
            'emergencyCta' => $user->profile?->emergency_cta_dismissed_at || $horse->emergency_token || ! $has(Perm::HORSE_EDIT) ? null : $horse,
            'invitations' => $this->invitations($user),
            'toValidate' => app(ToValidate::class)->items($user, $horse),
            // Jours de monte des 7 prochains jours (cheval partagé).
            'ridingWeek' => $has(Perm::SESSIONS_VIEW) || $has(Perm::CALENDAR_VIEW) ? app(RidingDays::class)->between(collect([$horse]), today(), today()->addDays(6)) : [],
            'pendingAssignments' => collect(),
            'todayLog' => DailyLog::where('horse_id', $horse->id)->where('logged_at', '>=', today())->latest('logged_at')->first(),
            // Régularité du suivi : jours notés sur les 7 derniers jours.
            'logDays' => DailyLog::where('horse_id', $horse->id)->where('logged_at', '>=', today()->subDays(6))->pluck('logged_at')->map->toDateString()->unique()->count(),
            'week' => $has(Perm::CALENDAR_VIEW) ? CalendarEvent::where('horse_id', $horse->id)->visibleSessions($has(Perm::SESSIONS_VIEW) ? [$horse->id] : [])
                ->where('status', '!=', 'cancelled')->whereBetween('starts_at', [now()->startOfDay(), now()->addDays(7)->endOfDay()])
                ->orderBy('starts_at')->limit(8)->get() : collect(),
            'treatments' => $has(Perm::TREATMENTS_VIEW) ? Treatment::where('horse_id', $horse->id)->current()
                ->with(['administrations' => fn ($q) => $q->where('administered_at', '>=', today())])->get() : collect(),
            // « Administré » : droits santé, ou responsable désigné du traitement avec le droit de suivi (comme le contrôleur).
            'canAdminister' => fn ($t) => $has(Perm::HEALTH_EDIT) || ($t->responsible_user_id === $user->id && $has(Perm::DAILYLOG_CREATE)),
            'checks' => $checks = $has(Perm::HEALTH_VIEW) ? app(CareRecordController::class)->nextChecks($horse) : collect(),
            // Rendez-vous groupé : autres chevaux avec la même échéance à prévoir (pas encore dans l'agenda).
            'groupable' => $has(Perm::HEALTH_EDIT) ? $this->groupable($user, $access, $horse, $checks) : collect(),
            'observations' => HealthObservation::where('horse_id', $horse->id)->whereNull('resolved_at')->where('severity', '!=', 'info')->latest('observed_at')->limit(3)->get(),
            'weights' => $horse->measurements()->reorder()->whereNotNull('weight_kg')->latest('measured_on')->latest('id')->limit(2)->get(),
            'rations' => $has(Perm::FEEDING_VIEW) ? $horse->rations()->activeToday()->with('entries')->get() : collect(),
            'sessionsWeek' => $has(Perm::SESSIONS_VIEW) ? RidingSession::where('horse_id', $horse->id)->where('status', 'completed')
                ->whereBetween('scheduled_at', [now()->startOfWeek(), now()])->count() : null,
            'lastSession' => $has(Perm::SESSIONS_VIEW) ? RidingSession::with('rider')->where('horse_id', $horse->id)->where('scheduled_at', '<=', now())
                ->whereIn('status', ['completed', 'in_progress'])->latest('scheduled_at')->first() : null,
            'budget' => $has(Perm::EXPENSES_VIEW) && $budgetIncluded ? [
                'month' => (float) Expense::where('horse_id', $horse->id)->whereBetween('spent_on', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount'),
                'unpaid' => (float) Expense::where('horse_id', $horse->id)->whereNull('paid_on')->where('spent_on', '<=', today())->sum('amount'),
                'next' => RecurringExpense::where('horse_id', $horse->id)->whereNull('paused_at')->orderBy('next_due_on')->get()->first(fn ($r) => $r->isActive()),
            ] : null,
            'budgetLocked' => $has(Perm::EXPENSES_VIEW) && ! $budgetIncluded,
            'shares' => $has(Perm::HORSE_MANAGE) ? HorseAccessGrant::current()->where('horse_id', $horse->id)->with('user')->get() : null,
        ]);
    }

    /** Échéances des autres chevaux, par catégorie, pouvant partager le rendez-vous du cheval affiché. */
    private function groupable($user, HorseAccess $access, Horse $horse, $checks)
    {
        $ids = collect($access->horseIdsWith($user, Perm::HEALTH_EDIT))->reject(fn ($id) => (int) $id === $horse->id)->values();
        $categories = $checks->map(fn ($n) => $n['care']->care_category_id)->unique()->values();
        if ($ids->isEmpty() || $categories->isEmpty()) {
            return collect();
        }

        return self::pendingChecks($ids, 90, $categories)->groupBy('care_category_id');
    }

    /**
     * Prochaines échéances à prévoir (dernier soin de chaque catégorie, contrôle pas encore fait ni dans l'agenda),
     * dans les jours à venir ou en retard.
     */
    public static function pendingChecks($horseIds, int $days, $categories = null)
    {
        return CareRecord::with(['horse', 'category', 'professional'])->whereIn('horse_id', Horse::whereIn('id', collect($horseIds))->whereNull('archived_at')->select('id'))
            ->when($categories, fn ($q) => $q->whereIn('care_category_id', $categories))
            ->whereNotNull('next_check_on')->whereNull('next_check_done_at')
            ->where('next_check_on', '<=', now()->addDays($days)->toDateString())
            ->whereNotExists(fn ($q) => $q->selectRaw(1)->from('care_records as later')->whereColumn('later.horse_id', 'care_records.horse_id')
                ->whereColumn('later.care_category_id', 'care_records.care_category_id')->whereColumn('later.performed_at', '>', 'care_records.performed_at')->whereNull('later.deleted_at'))
            ->whereNotIn('id', CalendarEvent::where('source_type', (new CareRecord)->getMorphClass())->whereNotNull('source_id')->where('status', '!=', 'cancelled')->select('source_id'))
            ->orderBy('next_check_on')->get();
    }

    private function invitations($user)
    {
        return Invitation::pending()->where('email', $user->email)->with(['horse', 'organization', 'inviter'])->get();
    }
}

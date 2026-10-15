<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\CareRecord;
use App\Models\DailyLog;
use App\Models\Expense;
use App\Models\HealthObservation;
use App\Models\HorseOrganizationAssignment;
use App\Models\Invitation;
use App\Models\RidingSession;
use App\Models\Treatment;
use App\Services\HorseAccess;
use App\Services\Onboarding;
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
        $horses = $access->accessibleQuery($user)->whereNull('archived_at')->with('breed')->orderBy('official_name')->get();
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
            'treatments' => Treatment::with('horse')->whereIn('horse_id', $treatIds)->current()->limit(8)->get(),
            'sessions' => RidingSession::with(['horse', 'rider'])->whereIn('horse_id', $sessionIds)->orderByDesc('scheduled_at')->limit(6)->get(),
            'observations' => HealthObservation::with(['horse', 'author'])->whereIn('horse_id', $horses->pluck('id'))->whereNull('resolved_at')
                ->where('severity', '!=', 'info')->latest('observed_at')->limit(5)->get(),
            'logs' => DailyLog::with(['horse', 'author'])->whereIn('horse_id', $horses->pluck('id'))->latest('logged_at')->limit(5)->get(),
            'expenses' => Expense::with(['horse', 'category'])->whereIn('horse_id', $expenseIds)->orderByDesc('spent_on')->limit(5)->get(),
            'invitations' => Invitation::pending()->where('email', $user->email)->with(['horse', 'organization', 'inviter'])->get(),
            'pendingAssignments' => $user->memberships->filter(fn ($m) => in_array(Perm::ORG_MANAGE, $m->role->permissionKeys(), true))->isNotEmpty()
                ? HorseOrganizationAssignment::with(['horse', 'organization'])->where('status', 'pending')
                    ->whereIn('organization_id', $user->memberships->filter(fn ($m) => in_array(Perm::ORG_MANAGE, $m->role->permissionKeys(), true))->pluck('organization_id'))->get()
                : collect(),
        ]);
    }
}

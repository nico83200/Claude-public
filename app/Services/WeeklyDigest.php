<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\Expense;
use App\Models\RidingSession;
use App\Models\Treatment;
use App\Models\User;
use App\Support\Perm;
use Illuminate\Support\Carbon;

/**
 * Récapitulatif de la semaine à venir : agenda (rendez-vous, soins prévus, séances),
 * traitements en cours, paiements à prévoir, bilans de séance en attente et budget du mois.
 * Ne reprend que ce que l'utilisateur a le droit de voir.
 */
class WeeklyDigest
{
    public function __construct(private HorseAccess $access, private CalendarService $calendar) {}

    /**
     * @return array{from: Carbon, to: Carbon, events: \Illuminate\Support\Collection, treatments: \Illuminate\Support\Collection,
     *               payments: \Illuminate\Support\Collection, debriefs: \Illuminate\Support\Collection, month_total: float, month_count: int}
     */
    public function for(User $user, ?Carbon $now = null): array
    {
        $now ??= now();
        // Le dimanche soir, « la semaine » est celle qui commence le lendemain.
        $from = $now->copy()->addDay()->startOfDay();
        $to = $from->copy()->addDays(6)->endOfDay();

        $calendarIds = $this->access->horseIdsWith($user, Perm::CALENDAR_VIEW);
        $orgIds = $user->memberships->pluck('organization_id');
        $events = CalendarEvent::with('horse')
            ->where(fn ($q) => $q->whereIn('horse_id', $calendarIds ?: [0])->orWhere(fn ($w) => $w->whereNull('horse_id')->whereIn('organization_id', $orgIds)))
            ->visibleSessions($this->access->horseIdsWith($user, Perm::SESSIONS_VIEW))
            ->whereNotIn('status', ['cancelled', 'done'])
            ->where(fn ($q) => $q->whereBetween('starts_at', [$from, $to])
                ->orWhere(fn ($r) => $r->whereNotNull('recurrence')->where('starts_at', '<=', $to)->where(fn ($u) => $u->whereNull('recurrence_until')->orWhere('recurrence_until', '>=', $from->toDateString()))))
            ->get();
        $events = $this->calendar->expand($events, $from, $to)->filter(fn ($o) => ! $o['event']->horse?->archived_at)->values();

        $healthIds = $this->access->horseIdsWith($user, Perm::HEALTH_VIEW);
        $treatments = Treatment::with('horse')->whereIn('horse_id', $healthIds ?: [0])->where('status', 'active')
            ->where('starts_on', '<=', $to->toDateString())->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $from->toDateString()))
            ->orderBy('ends_on')->get()->filter(fn ($t) => $t->horse && ! $t->horse->archived_at)->values();

        $expenseHorses = $this->access->horseIdsWith($user, Perm::EXPENSES_VIEW);
        $orgView = $user->memberships->filter(fn ($m) => in_array(Perm::EXPENSES_ORG_VIEW, $m->role?->permissionKeys() ?? [], true))->pluck('organization_id');
        $expenses = Expense::query()->where(fn ($q) => $q->whereIn('horse_id', $expenseHorses ?: [0])->orWhereIn('organization_id', $orgView));
        $payments = (clone $expenses)->with(['horse', 'category'])->whereNull('paid_on')->where('spent_on', '<=', $to->toDateString())->orderBy('spent_on')->limit(20)->get();
        $month = (clone $expenses)->whereBetween('spent_on', [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString()]);

        $debriefs = RidingSession::with('horse')->whereIn('status', ['planned', 'in_progress'])
            ->where(fn ($q) => $q->where('rider_id', $user->id)->orWhere(fn ($w) => $w->whereNull('rider_id')->where('created_by', $user->id)))
            ->whereBetween('scheduled_at', [$now->copy()->subDays(14), $now])->orderBy('scheduled_at')->get()
            ->filter(fn ($s) => $s->horse && ! $s->horse->archived_at)->values();

        return [
            'from' => $from, 'to' => $to,
            'events' => $events, 'treatments' => $treatments, 'payments' => $payments, 'debriefs' => $debriefs,
            'month_total' => (float) $month->sum('amount'), 'month_count' => (clone $month)->count(),
        ];
    }

    public function isEmpty(array $digest): bool
    {
        return $digest['events']->isEmpty() && $digest['treatments']->isEmpty() && $digest['payments']->isEmpty() && $digest['debriefs']->isEmpty();
    }

    /** Résumé court (notification sur le téléphone). */
    public function summary(array $d): string
    {
        $parts = array_filter([
            $d['events']->count() ? $d['events']->count().' rendez-vous ou activité'.($d['events']->count() > 1 ? 's' : '') : null,
            $d['treatments']->count() ? $d['treatments']->count().' traitement'.($d['treatments']->count() > 1 ? 's' : '').' en cours' : null,
            $d['payments']->count() ? $d['payments']->count().' paiement'.($d['payments']->count() > 1 ? 's' : '').' à prévoir' : null,
            $d['debriefs']->count() ? $d['debriefs']->count().' bilan'.($d['debriefs']->count() > 1 ? 's' : '').' de séance à faire' : null,
        ]);

        return $parts ? 'Cette semaine : '.implode(', ', $parts).'.' : 'Rien de prévu cette semaine.';
    }

    /** Texte complet (e-mail) : une ligne par élément, regroupé par rubrique. */
    public function text(array $d): string
    {
        $eur = fn ($v) => number_format((float) $v, 2, ',', ' ').' €';
        $blocks = [];
        if ($d['events']->isNotEmpty()) {
            $blocks[] = "**Au programme**\n".$d['events']->take(15)->map(fn ($o) => '• '.ucfirst($o['at']->translatedFormat('l j')).($o['event']->all_day ? '' : ' à '.$o['at']->format('H:i')).' – '.$o['event']->title.($o['event']->horse && ! str_contains($o['event']->title, $o['event']->horse->shortName()) ? ' ('.$o['event']->horse->shortName().')' : ''))->implode("\n");
        }
        if ($d['treatments']->isNotEmpty()) {
            $blocks[] = "**Traitements en cours**\n".$d['treatments']->map(fn ($t) => '• '.$t->horse->shortName().' : '.$t->product.($t->ends_on ? ' jusqu\'au '.$t->ends_on->format('d/m') : ''))->implode("\n");
        }
        if ($d['payments']->isNotEmpty()) {
            $blocks[] = "**Paiements à prévoir**\n".$d['payments']->map(fn ($e) => '• '.($e->spent_on->isPast() && ! $e->spent_on->isToday() ? 'En retard – ' : $e->spent_on->format('d/m').' – ').($e->comment ?: $e->category?->name).($e->horse ? ' ('.$e->horse->shortName().')' : '').' : '.$eur($e->amount))->implode("\n");
        }
        if ($d['debriefs']->isNotEmpty()) {
            $blocks[] = "**Bilans de séance à faire**\n".$d['debriefs']->map(fn ($s) => '• '.$s->scheduled_at->format('d/m').' – '.$s->title().' ('.$s->horse->shortName().')')->implode("\n");
        }
        $blocks[] = '**Budget du mois** : '.$eur($d['month_total']).' ('.$d['month_count'].' dépense'.($d['month_count'] > 1 ? 's' : '').').';

        return implode("\n\n", $blocks);
    }
}

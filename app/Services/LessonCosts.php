<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\InstructorRate;
use App\Models\LessonCharge;
use App\Models\Professional;
use App\Models\RidingSession;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Support\Perm;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Frais de cours : coût d'un cours (particulier, collectif par cavalier), d'un coaching en concours et du déplacement
 * de l'enseignant, calculés d'après ses tarifs quand la séance est faite (ou le concours validé), au nom de la personne
 * qui paie (par défaut le cavalier).
 *
 * Le budget du cheval reste réservé au propriétaire : les frais d'une demi-pension ou d'un cavalier restent dans son
 * espace (page « Frais de cours ») ; ceux du propriétaire sont aussi recopiés dans le budget du cheval
 * (catégorie « Cours et coaching »), payé et montant tenus à jour dans les deux sens.
 */
class LessonCosts
{
    public const LESSONS = ['private_lesson', 'group_lesson'];

    private static bool $syncing = false;

    public function __construct(private HorseAccess $access) {}

    /** Prix d'un cours selon les tarifs de l'enseignant : ['amount' => float, 'label' => string] ou null. */
    public function price(?Professional $coach, string $kind, ?int $minutes): ?array
    {
        if (! $coach) {
            return null;
        }
        $rates = $coach->rates->where('kind', $kind)->values();
        $name = $coach->fullName();
        $title = InstructorRate::KINDS[$kind] ?? 'Cours';
        if ($rates->isEmpty()) {
            // Tarif habituel de la fiche (avant les tarifs détaillés) : prix d'un cours particulier.
            return $kind === 'private_lesson' && (float) $coach->usual_rate > 0
                ? ['amount' => (float) $coach->usual_rate, 'label' => $title.($minutes ? ' '.$minutes.' min' : '').' avec '.$name] : null;
        }
        $session = $rates->where('unit', 'session');
        $hourly = $rates->firstWhere('unit', 'hour');
        $exact = $minutes ? $session->first(fn ($r) => (int) $r->duration_minutes === $minutes) : null;
        $label = fn (?int $m) => $title.($m ? ' '.$m.' min' : '').' avec '.$name;
        if ($exact) {
            return ['amount' => (float) $exact->amount, 'label' => $label($minutes)];
        }
        if ($hourly && $minutes) {
            return ['amount' => round((float) $hourly->amount * $minutes / 60, 2), 'label' => $label($minutes)];
        }
        $flat = $session->first(fn ($r) => ! $r->duration_minutes) ?? $session->sortBy(fn ($r) => abs((int) $r->duration_minutes - (int) $minutes))->first();
        if ($flat) {
            return ['amount' => (float) $flat->amount, 'label' => $label($flat->duration_minutes ?: $minutes)];
        }

        return $hourly ? ['amount' => (float) $hourly->amount, 'label' => $label(60)] : null;
    }

    /** Prix d'une séance : prix saisi (0 : cours offert, pas de frais), sinon tarif de l'enseignant ; null si ce n'est pas un cours. */
    public function sessionPrice(RidingSession $session): ?array
    {
        if (! $session->isLesson()) {
            return null;
        }
        if ($session->lesson_price !== null) {
            return (float) $session->lesson_price > 0
                ? ['amount' => (float) $session->lesson_price, 'label' => $session->title(), 'manual' => true] : null;
        }
        $session->loadMissing('coach.rates');
        $price = $this->price($session->coach, $session->session_type, $session->durationMinutes() ?: null);

        return $price ? $price + ['manual' => false] : null;
    }

    /** Personne qui paie : choisie sur la carte « Coût du cours », sinon le cavalier, sinon l'auteur de la séance. */
    public function payerOf(RidingSession $session): ?User
    {
        $id = $session->payer_id ?: $session->rider_id ?: $session->created_by;

        return $id ? User::find($id) : null;
    }

    /** Personnes à qui le cours peut être mis à la charge : l'utilisateur et le cavalier de la séance (s'il a un compte). */
    public function payerChoices(RidingSession $session, User $user): array
    {
        $choices = [$user->id => 'Moi'];
        if ($session->rider_id && $session->rider_id !== $user->id && ($rider = User::find($session->rider_id))) {
            $choices[$rider->id] = $rider->name.' (cavalier)';
        }

        return $choices;
    }

    /** Peut fixer le prix et la personne qui paie (et voit le coût prévu) : qui paie, ou qui a créé la séance. */
    public function canSetCost(RidingSession $session, User $user): bool
    {
        return in_array($user->id, array_filter([$this->payerOf($session)?->id, $session->created_by]), true);
    }

    /** Coaching d'un concours visible par l'utilisateur. */
    public function visibleEventCharges(CalendarEvent $event, User $user)
    {
        return LessonCharge::withTrashed()->with(['payer'])->where('calendar_event_id', $event->id)->get()->filter(fn (LessonCharge $c) => $this->canSee($c, $user))->values();
    }

    /** Frais d'une séance visibles par l'utilisateur : les siens, ceux qu'il a notés, ceux du budget s'il le voit. */
    public function visibleCharges(RidingSession $session, User $user)
    {
        return LessonCharge::withTrashed()->with(['payer', 'professional'])->where('riding_session_id', $session->id)->orderByRaw("kind = 'travel'")->orderBy('id')->get()
            ->filter(fn (LessonCharge $c) => $this->canSee($c, $user))->values();
    }

    public function canSee(LessonCharge $charge, User $user): bool
    {
        $charge->loadMissing('horse');

        return $charge->payer_id === $user->id || $charge->created_by === $user->id
            || ($charge->expense_id && $charge->horse && $this->access->can($user, $charge->horse, Perm::EXPENSES_VIEW));
    }

    /** Séance enregistrée : son coût est créé, mis à jour ou retiré, avec le déplacement du jour. */
    public function fromSession(RidingSession $session): void
    {
        if (self::$syncing) {
            return;
        }
        $this->guard(function () use ($session) {
            $session->loadMissing('horse');
            $charge = LessonCharge::withTrashed()->where('riding_session_id', $session->id)->whereIn('kind', self::LESSONS)->first();
            $price = $this->sessionPrice($session);
            $payer = $this->payerOf($session);
            $due = $price && $payer && $session->horse && $session->status === 'completed' && ! $session->trashed();
            if (! $due) {
                // Plus de frais (séance pas faite, annulée, cours offert…) : retirés tant qu'ils ne sont pas payés.
                if ($charge && ! $charge->trashed() && ! $charge->paid_on) {
                    $this->forget($charge);
                }
            } elseif (! $charge?->trashed()) {
                $isNew = ! $charge;
                $charge ??= new LessonCharge(['riding_session_id' => $session->id, 'created_by' => auth()->id() ?? $session->created_by]);
                $charge->fill(['horse_id' => $session->horse_id, 'payer_id' => $payer->id, 'professional_id' => $session->professional_id, 'kind' => $session->session_type,
                    'label' => Str::limit($price['label'], 150, ''), 'amount' => $price['amount'], 'charged_on' => $session->scheduled_at->toDateString()])->save();
                $this->mirror($charge);
                if ($isNew) {
                    $this->tell($charge);
                }
            }
            // Déplacement : une fois par jour, par enseignant et par personne qui paie (ancien jour compris si la séance a bougé).
            $this->syncTravel($session->professional_id, $payer?->id, $session->scheduled_at);
            $previous = $session->getPrevious();
            if (array_intersect(array_keys($previous), ['professional_id', 'payer_id', 'rider_id', 'scheduled_at'])) {
                $old = array_merge($session->getAttributes(), $previous);
                $this->syncTravel($old['professional_id'] ? (int) $old['professional_id'] : null, (int) (($old['payer_id'] ?: $old['rider_id']) ?: $old['created_by']) ?: null,
                    $old['scheduled_at'] ? Carbon::parse($old['scheduled_at']) : null);
            }
        });
    }

    /** Séance supprimée : ses frais aussi (retrouvés si la suppression est annulée). */
    public function sessionDeleted(RidingSession $session): void
    {
        $this->guard(function () use ($session) {
            LessonCharge::where('riding_session_id', $session->id)->whereIn('kind', self::LESSONS)->get()->each(fn ($c) => $this->trash($c, true));
            $this->syncTravel($session->professional_id, $this->payerOf($session)?->id, $session->scheduled_at);
        });
    }

    public function sessionRestored(RidingSession $session): void
    {
        $this->guard(function () use ($session) {
            LessonCharge::onlyTrashed()->where('riding_session_id', $session->id)->where('removed_with_source', true)->get()->each(fn ($c) => $this->untrash($c));
        });
        $this->fromSession($session);
    }

    /** Déplacement de l'enseignant : sur le premier cours fait du jour (pour cette personne), selon son tarif « par venue ». */
    public function syncTravel(?int $professionalId, ?int $payerId, ?Carbon $day): void
    {
        if (! $professionalId || ! $payerId || ! $day) {
            return;
        }
        $coach = Professional::with('rates')->find($professionalId);
        $rate = $coach?->rates->firstWhere('kind', 'travel');
        $first = RidingSession::with('horse')->where('professional_id', $professionalId)->whereDate('scheduled_at', $day->toDateString())
            ->where('status', 'completed')->whereIn('session_type', self::LESSONS)->orderBy('scheduled_at')->orderBy('id')->get()
            ->first(fn (RidingSession $s) => $s->horse && $this->payerOf($s)?->id === $payerId && $this->sessionPrice($s));
        $charge = LessonCharge::withTrashed()->where('kind', 'travel')->where('professional_id', $professionalId)->where('payer_id', $payerId)
            ->whereDate('charged_on', $day->toDateString())->first();
        if (! $first || ! $rate) {
            if ($charge && ! $charge->trashed() && ! $charge->paid_on) {
                $this->forget($charge);
            }

            return;
        }
        if ($charge?->trashed()) {
            return; // retiré volontairement
        }
        $isNew = ! $charge;
        $charge ??= new LessonCharge(['kind' => 'travel', 'professional_id' => $professionalId, 'payer_id' => $payerId, 'created_by' => auth()->id() ?? $first->created_by]);
        $charge->fill(['horse_id' => $first->horse_id, 'riding_session_id' => $first->id, 'label' => Str::limit('Déplacement de '.$coach->fullName(), 150, ''),
            'amount' => (float) $rate->amount, 'charged_on' => $day->toDateString()])->save();
        $this->mirror($charge);
        if ($isNew) {
            $this->tell($charge);
        }
    }

    /** Concours avec un enseignant (intervenant de l'événement) : coaching au jour ou au parcours, une fois le concours validé. */
    public function fromEvent(CalendarEvent $event): void
    {
        if (self::$syncing) {
            return;
        }
        $this->guard(function () use ($event) {
            $charge = LessonCharge::withTrashed()->where('calendar_event_id', $event->id)->where('kind', 'coaching')->first();
            $coach = $event->professional_id ? Professional::with('rates')->find($event->professional_id) : null;
            $rate = $coach?->rates->firstWhere('kind', 'coaching');
            $payerId = $event->responsible_user_id ?: $event->created_by;
            $due = $event->type === 'competition' && $event->horse_id && $rate && $payerId && $event->status === 'done' && ! $event->recurrence && ! $event->trashed();
            if (! $due) {
                if ($charge && ! $charge->trashed() && ! $charge->paid_on) {
                    $this->forget($charge);
                }

                return;
            }
            if ($charge?->trashed()) {
                return;
            }
            $count = $rate->unit === 'round' ? max(1, (int) $event->coaching_rounds) : max(1, (int) ceil(((int) $event->duration_minutes ?: 60) / 1440));
            $what = $rate->unit === 'round' ? 'parcours' : ($count > 1 ? 'jours' : 'jour');
            $isNew = ! $charge;
            $charge ??= new LessonCharge(['calendar_event_id' => $event->id, 'kind' => 'coaching', 'created_by' => auth()->id() ?? $event->created_by]);
            $charge->fill(['horse_id' => $event->horse_id, 'payer_id' => $payerId, 'professional_id' => $coach->id,
                'label' => Str::limit('Coaching ('.$count.' '.$what.') avec '.$coach->fullName().' – '.$event->title, 150, ''),
                'amount' => round((float) $rate->amount * $count, 2), 'charged_on' => $event->starts_at->toDateString()])->save();
            $this->mirror($charge);
            if ($isNew) {
                $this->tell($charge);
            }
        });
    }

    public function eventDeleted(CalendarEvent $event): void
    {
        $this->guard(fn () => LessonCharge::where('calendar_event_id', $event->id)->get()->each(fn ($c) => $this->trash($c, true)));
    }

    /** Payé (ou de nouveau à payer), recopié sur la dépense du budget. */
    public function markPaid(LessonCharge $charge, ?Carbon $on): void
    {
        $this->guard(function () use ($charge, $on) {
            $charge->update(['paid_on' => $on?->toDateString()]);
            $this->mirror($charge);
        });
    }

    /** Frais retirés à la main (la séance garde son prix : ils ne reviennent pas d'eux-mêmes). */
    public function remove(LessonCharge $charge): void
    {
        $this->guard(fn () => $this->trash($charge, false));
    }

    public function restore(LessonCharge $charge): void
    {
        $this->guard(fn () => $this->untrash($charge));
    }

    /** Dépense recopiée modifiée dans le budget (montant, date, payé) : les frais suivent ; le montant devient le prix du cours. */
    public function fromExpense(Expense $expense): void
    {
        if (self::$syncing || ! $expense->wasChanged(['amount', 'paid_on', 'spent_on'])) {
            return;
        }
        $this->guard(function () use ($expense) {
            $charge = LessonCharge::where('expense_id', $expense->id)->first();
            if (! $charge) {
                return;
            }
            $charge->update(['amount' => $expense->amount, 'paid_on' => $expense->paid_on, 'charged_on' => $expense->spent_on]);
            if ($expense->wasChanged('amount') && in_array($charge->kind, self::LESSONS, true) && ($session = RidingSession::find($charge->riding_session_id))) {
                $session->forceFill(['lesson_price' => $expense->amount])->saveQuietly();
            }
        });
    }

    public function expenseDeleted(Expense $expense): void
    {
        if (self::$syncing) {
            return;
        }
        $this->guard(fn () => LessonCharge::where('expense_id', $expense->id)->get()->each(fn ($c) => $c->delete()));
    }

    public function expenseRestored(Expense $expense): void
    {
        if (self::$syncing) {
            return;
        }
        $this->guard(fn () => LessonCharge::onlyTrashed()->where('expense_id', $expense->id)->get()->each(fn ($c) => $c->restore()));
    }

    /**
     * Budget du cheval : les frais de son propriétaire (ou d'un membre de son espace qui voit le budget) y sont recopiés,
     * jamais ceux d'une demi-pension ou d'un cavalier.
     */
    private function mirror(LessonCharge $charge): void
    {
        $charge->loadMissing(['horse', 'payer', 'professional']);
        $expense = $charge->expense_id ? Expense::withTrashed()->find($charge->expense_id) : null;
        $inBudget = $charge->horse && $charge->payer && ! $charge->trashed()
            && in_array(Perm::EXPENSES_VIEW, $this->access->permissions($charge->payer, $charge->horse), true);
        if (! $inBudget) {
            if ($expense && $charge->trashed()) {
                $expense->trashed() || $expense->delete();
            } elseif ($expense) {
                $expense->forceDelete();
                $charge->forceFill(['expense_id' => null])->saveQuietly();
            }

            return;
        }
        $expense ??= new Expense;
        if ($expense->trashed()) {
            $expense->restore();
        }
        $expense->forceFill([
            'organization_id' => $charge->horse->organization_id, 'author_id' => $expense->author_id ?? $charge->payer_id, 'horse_id' => $charge->horse_id,
            'expense_category_id' => ExpenseCategory::where('key', 'lessons')->value('id') ?? ExpenseCategory::where('key', 'other')->value('id'),
            'professional_id' => $charge->professional && $charge->professional->organization_id === $charge->horse->organization_id ? $charge->professional_id : null,
            'spent_on' => $charge->charged_on, 'amount' => $charge->amount, 'paid_on' => $charge->paid_on,
            'supplier' => $charge->professional?->fullName(), 'comment' => $charge->label,
        ])->save();
        if ($charge->expense_id !== $expense->id) {
            $charge->forceFill(['expense_id' => $expense->id])->saveQuietly();
        }
    }

    /** Frais notés par quelqu'un d'autre : la personne qui paie est prévenue. */
    private function tell(LessonCharge $charge): void
    {
        $by = auth()->user();
        if (! $by || $by->id === $charge->payer_id) {
            return;
        }
        $charge->loadMissing(['payer', 'horse']);
        $charge->payer?->notify(new AppNotification('lesson_cost', 'Frais de cours à votre charge',
            $by->name.' a noté « '.$charge->label.' » du '.$charge->charged_on->translatedFormat('j F').' avec '.$charge->horse?->shortName().' à votre charge : '.number_format((float) $charge->amount, 2, ',', ' ').' €.',
            route('lesson-costs.index')));
    }

    /** Frais qui n'ont plus lieu d'être (pas encore payés) : effacés, avec leur dépense. */
    private function forget(LessonCharge $charge): void
    {
        if ($charge->expense_id && ($expense = Expense::withTrashed()->find($charge->expense_id))) {
            $expense->forceDelete();
        }
        $charge->forceDelete();
    }

    private function trash(LessonCharge $charge, bool $withSource): void
    {
        $charge->forceFill(['removed_with_source' => $withSource])->save();
        $charge->delete();
        $this->mirror($charge);
    }

    private function untrash(LessonCharge $charge): void
    {
        $charge->restore();
        $charge->forceFill(['removed_with_source' => false])->save();
        $this->mirror($charge);
    }

    private function guard(callable $fn): void
    {
        $was = self::$syncing;
        self::$syncing = true;
        try {
            $fn();
        } finally {
            self::$syncing = $was;
        }
    }
}

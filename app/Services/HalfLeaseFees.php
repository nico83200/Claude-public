<?php

namespace App\Services;

use App\Models\BoardingStatement;
use App\Models\HalfLease;
use App\Models\Income;
use App\Models\RecurringIncome;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Support\Fr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Mensualités de demi-pension. Cheval d'un particulier : une recette mensuelle « Demi-pension » dans le budget du cheval,
 * dont la demi-pension voit les échéances (le propriétaire seul les note reçues). Cheval d'un espace écurie : la mensualité
 * passe sur le relevé mensuel de la personne (BoardingBilling). Premier mois complet ou au prorata, mois sans mensualité.
 */
class HalfLeaseFees
{
    /** Non reçue N jours après l'échéance : le propriétaire est prévenu (une fois). */
    public const OVERDUE_DAYS = 7;

    public const FIRST_MONTH = ['full' => 'Mois complet', 'prorata' => 'Au prorata des jours restants'];

    public function __construct(private RecurringIncomes $recurring) {}

    /** Cheval d'un espace écurie : mensualités sur le relevé mensuel plutôt que dans un budget. */
    public function viaStatement(HalfLease $lease): bool
    {
        return $lease->horse?->organization?->type === 'stable';
    }

    public function paymentDay(HalfLease $lease): int
    {
        return min(28, $lease->payment_day ?: $lease->starts_on->day);
    }

    /** Première échéance régulière : jour de paiement du mois de début, ou du mois suivant si la demi-pension commence après. */
    public function firstRegularDue(HalfLease $lease): Carbon
    {
        $due = $lease->starts_on->copy()->startOfMonth()->day($this->paymentDay($lease));

        return $due->gte($lease->starts_on) ? $due : $due->addMonthNoOverflow();
    }

    /** Début après le jour de paiement : le premier mois est une échéance à part, à la date de début (complet ou au prorata). */
    public function firstMonth(HalfLease $lease): ?array
    {
        if ($lease->starts_on->copy()->startOfMonth()->day($this->paymentDay($lease))->gte($lease->starts_on)) {
            return null;
        }
        $days = $lease->starts_on->daysInMonth;
        $left = $days - $lease->starts_on->day + 1;
        $prorata = $lease->first_month === 'prorata';

        return [
            'due' => $lease->starts_on->copy(),
            'amount' => $prorata ? round((float) $lease->monthly_amount * $left / $days, 2) : (float) $lease->monthly_amount,
            'detail' => $prorata ? $left.' jours sur '.$days.' ('.$lease->starts_on->format('d/m').' – '.$lease->starts_on->copy()->endOfMonth()->format('d/m').')' : 'Mois complet',
        ];
    }

    /** Recette mensuelle du propriétaire tenue à jour : acceptation, modification, fin, tarif à 0. */
    public function sync(HalfLease $lease): void
    {
        $lease->loadMissing('horse.organization');
        $rec = $lease->recurring_income_id ? RecurringIncome::find($lease->recurring_income_id) : null;
        $wanted = in_array($lease->status, ['active', 'ended'], true) && (float) $lease->monthly_amount > 0 && ! $this->viaStatement($lease);
        if (! $wanted) {
            // Plus de mensualité : la récurrence s'arrête, les échéances déjà comptées restent dans le budget.
            if ($rec) {
                $rec->delete();
                $lease->forceFill(['recurring_income_id' => null])->save();
            }

            return;
        }
        $attrs = ['category' => 'half_lease', 'label' => 'Demi-pension · '.$lease->riderName(), 'amount' => $lease->monthly_amount, 'frequency' => 'monthly',
            'payer' => $lease->riderName(), 'ends_on' => $lease->ends_on?->toDateString()];
        if (! $rec) {
            $first = $this->firstRegularDue($lease);
            $rec = new RecurringIncome($attrs + ['horse_id' => $lease->horse_id, 'starts_on' => $first, 'next_due_on' => $first]);
            $rec->organization_id = $lease->horse->organization_id;
            $rec->author_id = $lease->created_by;
            $rec->half_lease_id = $lease->id;
            $rec->save();
            $lease->forceFill(['recurring_income_id' => $rec->id])->save();
        } else {
            $rec->fill($attrs);
            // Pas encore d'échéance : l'échéancier suit le début ; sinon un nouveau jour de paiement vaut à partir du prochain mois non compté.
            if (! $rec->occurrences()->withTrashed()->exists()) {
                $rec->starts_on = $rec->next_due_on = $this->firstRegularDue($lease);
            } elseif ($rec->starts_on->day !== $this->paymentDay($lease)) {
                $month = $rec->next_due_on->copy()->startOfMonth();
                while ($rec->occurrences()->withTrashed()->whereBetween('received_on', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])->exists()) {
                    $month->addMonthNoOverflow();
                }
                $rec->starts_on = $rec->next_due_on = $month->day($this->paymentDay($lease));
            }
            $rec->save();
        }
        $this->firstMonthIncome($lease);
        $this->recurring->generate($rec);
    }

    /** Mois sans mensualité (vacances…) : l'échéance du mois, pas encore reçue, est retirée et ne revient pas. */
    public function skipMonth(HalfLease $lease, string $month): void
    {
        $lease->update(['skipped_months' => collect($lease->skipped_months ?? [])->push($month)->unique()->sort()->values()->all()]);
        $start = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfDay();
        Income::where('half_lease_id', $lease->id)->whereNull('collected_on')->whereBetween('received_on', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()])->get()->each->delete();
    }

    public function unskipMonth(HalfLease $lease, string $month): void
    {
        $lease->update(['skipped_months' => collect($lease->skipped_months ?? [])->reject(fn ($m) => $m === $month)->values()->all()]);
    }

    /** Échéances (budget du propriétaire) ou lignes de relevés (écurie) de la demi-pension, la plus récente d'abord. */
    public function schedule(HalfLease $lease): Collection
    {
        if ($this->viaStatement($lease)) {
            return BoardingStatement::where('organization_id', $lease->horse->organization_id)->where('owner_user_id', $lease->user_id)->where('status', '!=', 'draft')->latest('period')->get()
                ->flatMap(fn (BoardingStatement $s) => collect($s->lines)->where('half_lease_id', $lease->id)->map(fn ($l) => [
                    'month' => ucfirst(Carbon::createFromFormat('Y-m-d', $s->period.'-01')->translatedFormat('F Y')), 'amount' => (float) $l['amount'], 'detail' => $l['detail'] ?? null,
                    'paid' => $s->status === 'paid', 'paid_on' => $s->paid_on, 'statement' => $s, 'income' => null, 'due' => $s->sent_at,
                ]))->values();
        }

        return Income::where('half_lease_id', $lease->id)->orderByDesc('received_on')->get()->map(fn (Income $i) => [
            'month' => ucfirst($i->received_on->translatedFormat('F Y')), 'amount' => (float) $i->amount, 'detail' => $i->comment,
            'paid' => $i->collected_on !== null, 'paid_on' => $i->collected_on, 'statement' => null, 'income' => $i, 'due' => $i->received_on,
        ]);
    }

    /** Prochaine échéance prévue (budget) : date et montant. */
    public function next(HalfLease $lease): ?array
    {
        if ($this->viaStatement($lease) || ! $lease->recurring_income_id || ! ($rec = RecurringIncome::find($lease->recurring_income_id)) || ! $rec->isActive()) {
            return null;
        }
        $due = $rec->next_due_on->copy();
        for ($i = 0; $i < 24 && in_array($due->format('Y-m'), $lease->skipped_months ?? [], true); $i++) {
            $due = $rec->dueAfter($due);
        }

        return $lease->ends_on && $due->gt($lease->ends_on) ? null : ['due' => $due, 'amount' => (float) $rec->amount];
    }

    /** Demi-pensions de la personne (côté demi-pension) avec leurs échéances, pour « Mes frais » et le budget. */
    public function forRider(User $user): Collection
    {
        return HalfLease::with('horse.organization')->where('user_id', $user->id)->whereIn('status', ['active', 'ended'])->whereNotNull('monthly_amount')
            ->where('monthly_amount', '>', 0)->latest('starts_on')->get()
            ->map(fn (HalfLease $lease) => ['lease' => $lease, 'schedule' => $this->schedule($lease), 'next' => $this->next($lease)]);
    }

    /**
     * Tâche quotidienne : premier mois arrivé à échéance, demi-pension prévenue le jour de l'échéance,
     * propriétaire prévenu d'une mensualité non reçue après OVERDUE_DAYS jours.
     */
    public function daily(): int
    {
        foreach (HalfLease::where('status', 'active')->whereNotNull('recurring_income_id')->where('starts_on', '<=', today()->toDateString())->get() as $lease) {
            $this->firstMonthIncome($lease);
        }
        $sent = 0;
        $due = Income::whereNotNull('half_lease_id')->whereNull('collected_on')->whereNull('notified_at')
            ->whereBetween('received_on', [today()->subDays(3)->toDateString(), today()->toDateString()])->get();
        foreach ($due as $income) {
            $lease = HalfLease::with(['horse', 'user', 'creator'])->find($income->half_lease_id);
            if ($lease?->user) {
                $lease->user->notify(new AppNotification('half_lease', 'Demi-pension de '.$income->received_on->translatedFormat('F').' : '.$this->eur($income->amount),
                    'Mensualité de la demi-pension sur '.$lease->horse->shortName().' à régler à '.$lease->creator?->name.($income->received_on->isToday() ? ' aujourd\'hui' : ' (échéance du '.Fr::day($income->received_on, false).')').'.',
                    route('half-leases.show', $lease)));
                $sent++;
            }
            $income->forceFill(['notified_at' => now()])->save();
        }
        $late = Income::whereNotNull('half_lease_id')->whereNull('collected_on')->whereNull('reminded_at')
            ->where('received_on', '<=', today()->subDays(self::OVERDUE_DAYS)->toDateString())->get();
        foreach ($late as $income) {
            $lease = HalfLease::with(['horse', 'creator'])->find($income->half_lease_id);
            if ($lease?->creator) {
                $lease->creator->notify(new AppNotification('half_lease', 'Demi-pension non reçue : '.$lease->riderName(),
                    'La mensualité de '.$income->received_on->translatedFormat('F').' ('.$this->eur($income->amount).') de '.$lease->riderName().' pour '.$lease->horse->shortName().' n\'est pas encore notée reçue.',
                    route('half-leases.show', $lease)));
                $sent++;
            }
            $income->forceFill(['reminded_at' => now()])->save();
        }

        return $sent;
    }

    /** Premier mois (début après le jour de paiement) : une recette à part, à la date de début, une fois arrivée. */
    private function firstMonthIncome(HalfLease $lease): void
    {
        $first = $this->firstMonth($lease);
        if (! $first || $first['due']->isFuture() || $lease->status !== 'active' || (float) $lease->monthly_amount <= 0
            || in_array($first['due']->format('Y-m'), $lease->skipped_months ?? [], true)
            || Income::withTrashed()->where('half_lease_id', $lease->id)->whereNull('recurring_income_id')->whereDate('received_on', $first['due'])->exists()) {
            return;
        }
        $income = new Income(['horse_id' => $lease->horse_id, 'category' => 'half_lease', 'received_on' => $first['due']->toDateString(), 'amount' => $first['amount'],
            'payer' => $lease->riderName(), 'comment' => 'Demi-pension · '.$lease->riderName().' · premier mois'.($lease->first_month === 'prorata' ? ' ('.$first['detail'].')' : '')]);
        $income->forceFill(['organization_id' => $lease->horse->organization_id, 'author_id' => $lease->created_by, 'half_lease_id' => $lease->id, 'collected_on' => null])->save();
    }

    private function eur($v): string
    {
        return number_format((float) $v, 2, ',', ' ').' €';
    }
}

<?php

namespace App\Services;

use App\Models\BoardingCharge;
use App\Models\BoardingContract;
use App\Models\BoardingStatement;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Horse;
use App\Models\LessonCharge;
use App\Models\Organization;
use App\Models\Professional;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Support\Perm;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Facturation de la pension aux propriétaires (espace écurie).
 * Chaque mois, un relevé par propriétaire : pension de chacun de ses chevaux (au prorata des jours
 * de présence) et prestations du mois. À l'envoi, le propriétaire qui a un compte est prévenu et la
 * somme apparaît « à payer » dans son budget ; elle passe à « payée » quand l'écurie note le paiement.
 * Les cours donnés par les enseignants de l'écurie y sont repris, au nom de la personne qui les paie
 * (propriétaire, demi-pension, cavalier) : ils figurent déjà dans ses frais de cours (et dans son budget
 * s'il est propriétaire), le relevé ne les ajoute donc pas une seconde fois ; le paiement les marque payés.
 */
class BoardingBilling
{
    /** Chevaux en pension : appartenant à l'écurie ou confiés à elle (rattachement actif). */
    public function horses(Organization $stable): Collection
    {
        $assigned = Horse::whereHas('assignments', fn ($q) => $q->where('organization_id', $stable->id)->where('status', 'active'))->whereNull('archived_at')->get();

        return Horse::where('organization_id', $stable->id)->whereNull('archived_at')->get()->merge($assigned)->unique('id')->sortBy('official_name')->values();
    }

    /** Propriétaire proposé pour un cheval : responsable de l'espace d'origine, sinon propriétaire enregistré. */
    public function suggestOwner(Horse $horse, Organization $stable): array
    {
        if ($horse->organization_id !== $stable->id && ($owner = $horse->organization->owner)) {
            return ['user_id' => $owner->id, 'name' => $owner->name, 'email' => $owner->email];
        }
        $ownership = $horse->ownerships()->whereNull('ended_on')->whereIn('role', ['owner', 'co_owner'])->with('user')->first();

        return $ownership?->user
            ? ['user_id' => $ownership->user->id, 'name' => $ownership->user->name, 'email' => $ownership->user->email]
            : ['user_id' => null, 'name' => (string) $ownership?->owner_name, 'email' => null];
    }

    /**
     * Relevés du mois (AAAA-MM) : les brouillons sont recalculés, les relevés envoyés ou payés ne bougent plus.
     *
     * @return Collection<int, BoardingStatement>
     */
    public function generate(Organization $stable, string $period): Collection
    {
        $start = Carbon::createFromFormat('Y-m-d', $period.'-01')->startOfDay();
        $end = $start->copy()->endOfMonth();
        $days = $start->daysInMonth;

        return DB::transaction(function () use ($stable, $period, $start, $end, $days) {
            // Brouillons recalculés : leurs prestations redeviennent disponibles.
            $drafts = BoardingStatement::where('organization_id', $stable->id)->where('period', $period)->where('status', 'draft')->pluck('id');
            BoardingCharge::whereIn('statement_id', $drafts)->update(['statement_id' => null]);
            LessonCharge::whereIn('boarding_statement_id', $drafts)->update(['boarding_statement_id' => null]);
            BoardingStatement::whereIn('id', $drafts)->delete();
            $locked = BoardingStatement::where('organization_id', $stable->id)->where('period', $period)->get()
                ->map(fn ($s) => $s->owner_user_id ? 'u'.$s->owner_user_id : 'n'.md5(mb_strtolower(trim($s->owner_name).'|'.trim((string) $s->owner_email))))->all();

            $contracts = BoardingContract::with('horse')->where('organization_id', $stable->id)
                ->where('starts_on', '<=', $end)->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $start))->get();
            $charges = BoardingCharge::with('horse')->where('organization_id', $stable->id)->whereNull('statement_id')
                ->whereBetween('charged_on', [$start->toDateString(), $end->toDateString()])->orderBy('charged_on')->get();

            $groups = [];
            foreach ($contracts as $c) {
                $from = $c->starts_on->max($start);
                $to = $c->ends_on ? $c->ends_on->min($end) : $end;
                $present = (int) $from->diffInDays($to) + 1;
                $amount = round((float) $c->monthly_amount * $present / $days, 2);
                $groups[$c->ownerKey()]['contract'] ??= $c;
                $groups[$c->ownerKey()]['lines'][] = [
                    'horse' => $c->horse->shortName(), 'horse_id' => $c->horse_id, 'kind' => 'pension', 'label' => $c->label,
                    'detail' => $present < $days ? $present.' jours sur '.$days.' ('.$from->format('d/m').' – '.$to->format('d/m').')' : 'Mois complet',
                    'quantity' => 1, 'unit_price' => $amount, 'amount' => $amount,
                ];
            }
            foreach ($charges as $ch) {
                // La prestation va au propriétaire du contrat en cours à sa date.
                $c = $contracts->first(fn ($c) => $c->horse_id === $ch->horse_id && $c->starts_on->lte($ch->charged_on) && (! $c->ends_on || $c->ends_on->gte($ch->charged_on)));
                if (! $c) {
                    continue;
                }
                $groups[$c->ownerKey()]['contract'] ??= $c;
                $groups[$c->ownerKey()]['lines'][] = [
                    'horse' => $ch->horse->shortName(), 'horse_id' => $ch->horse_id, 'kind' => 'charge', 'label' => $ch->label, 'detail' => $ch->charged_on->format('d/m'),
                    'quantity' => (float) $ch->quantity, 'unit_price' => (float) $ch->unit_price, 'amount' => $ch->total(), 'charge_id' => $ch->id,
                ];
            }

            // Cours donnés par les enseignants de l'écurie : sur le relevé de la personne qui les paie.
            $lessons = LessonCharge::with(['horse', 'payer'])->whereNull('boarding_statement_id')->where('amount', '>', 0)
                ->whereIn('professional_id', Professional::where('organization_id', $stable->id)->select('id'))
                ->whereBetween('charged_on', [$start->toDateString(), $end->toDateString()])->orderBy('charged_on')->orderBy('id')->get();
            foreach ($lessons as $l) {
                // Pas de relevé à soi-même : cours notés au nom de quelqu'un qui gère la facturation de l'écurie.
                if (! $l->payer || $l->payer->canInOrg($stable->id, Perm::EXPENSES_ORG_VIEW)) {
                    continue;
                }
                $key = 'u'.$l->payer_id;
                $groups[$key]['payer'] ??= $l->payer;
                $groups[$key]['lines'][] = [
                    'horse' => $l->horse?->shortName(), 'horse_id' => $l->horse_id, 'kind' => 'lesson', 'label' => $l->label, 'detail' => $l->charged_on->format('d/m'),
                    'quantity' => 1, 'unit_price' => (float) $l->amount, 'amount' => (float) $l->amount, 'lesson_charge_id' => $l->id,
                ];
            }

            $created = collect();
            foreach ($groups as $key => $g) {
                if (in_array($key, $locked, true)) {
                    continue;
                }
                $c = $g['contract'] ?? null;
                $payer = $g['payer'] ?? null;
                $statement = new BoardingStatement([
                    'number' => $this->nextNumber($stable, $period), 'period' => $period, 'owner_user_id' => $c ? $c->owner_user_id : $payer->id,
                    'owner_name' => $c ? $c->owner_name : $payer->name, 'owner_email' => $c ? $c->owner_email : $payer->email, 'lines' => $g['lines'],
                    'total' => round(array_sum(array_column($g['lines'], 'amount')), 2), 'status' => 'draft',
                ]);
                $statement->organization_id = $stable->id;
                $statement->save();
                BoardingCharge::whereIn('id', array_filter(array_column($g['lines'], 'charge_id')))->update(['statement_id' => $statement->id]);
                LessonCharge::whereIn('id', array_filter(array_column($g['lines'], 'lesson_charge_id')))->update(['boarding_statement_id' => $statement->id]);
                $created->push($statement);
            }

            return $created;
        });
    }

    /** Envoi : notification au propriétaire et somme « à payer » dans son budget (chevaux de son espace). */
    public function send(BoardingStatement $statement): void
    {
        DB::transaction(function () use ($statement) {
            $statement->update(['status' => 'sent', 'sent_at' => now()]);
            $owner = $statement->owner;
            if (! $owner) {
                return;
            }
            $category = ExpenseCategory::where('key', 'boarding')->value('id') ?? ExpenseCategory::value('id');
            // Les cours sont déjà dans les frais de cours (et le budget du propriétaire) : seules la pension et les prestations y sont ajoutées.
            foreach (collect($statement->lines)->reject(fn ($l) => ($l['kind'] ?? null) === 'lesson')->groupBy('horse_id') as $horseId => $lines) {
                $horse = Horse::find($horseId);
                // Seulement pour un cheval de l'espace du propriétaire : l'écurie n'écrit jamais dans son propre budget ici.
                if (! $horse || $horse->organization_id === $statement->organization_id || ! $owner->memberships->contains('organization_id', $horse->organization_id)) {
                    continue;
                }
                $expense = new Expense(['horse_id' => $horse->id, 'expense_category_id' => $category, 'spent_on' => $statement->created_at->toDateString(),
                    'amount' => round($lines->sum('amount'), 2), 'supplier' => $statement->organization->name,
                    'comment' => 'Relevé de pension '.$statement->number.' – '.$statement->periodLabel(), 'boarding_statement_id' => $statement->id]);
                $expense->organization_id = $horse->organization_id;
                $expense->author_id = null;
                $expense->paid_on = null;
                $expense->save();
            }
            $owner->notify(new AppNotification('payment_due', $statement->title().' – '.$statement->periodLabel(),
                $statement->organization->name.' vous adresse le relevé '.$statement->number.' : '.number_format((float) $statement->total, 2, ',', ' ').' €.',
                route('boarding.statement', $statement)));
        });
    }

    public function markPaid(BoardingStatement $statement, Carbon $on): void
    {
        DB::transaction(function () use ($statement, $on) {
            $statement->update(['status' => 'paid', 'paid_on' => $on->toDateString()]);
            Expense::where('boarding_statement_id', $statement->id)->whereNull('paid_on')->update(['paid_on' => $on->toDateString()]);
        });
        // Cours du relevé : payés dans les frais de cours (et dans le budget du propriétaire).
        foreach (LessonCharge::where('boarding_statement_id', $statement->id)->whereNull('paid_on')->get() as $charge) {
            app(LessonCosts::class)->markPaid($charge, $on);
        }
    }

    public function canView(User $user, BoardingStatement $statement): bool
    {
        return $statement->owner_user_id === $user->id || $user->canInOrg($statement->organization_id, Perm::EXPENSES_ORG_VIEW);
    }

    private function nextNumber(Organization $stable, string $period): string
    {
        $prefix = 'P'.str_replace('-', '', $period).'-';
        $last = BoardingStatement::where('organization_id', $stable->id)->where('number', 'like', $prefix.'%')->orderByDesc('number')->value('number');

        return $prefix.str_pad((string) ((int) substr((string) $last, strlen($prefix)) + 1), 3, '0', STR_PAD_LEFT);
    }
}

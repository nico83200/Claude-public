<?php

namespace App\Services;

use App\Models\BoardingStatement;
use App\Models\CalendarEvent;
use App\Models\DailyLog;
use App\Models\Expense;
use App\Models\FeedingPlan;
use App\Models\HealthObservation;
use App\Models\Horse;
use App\Models\HorseLocation;
use App\Models\HorseMeasurement;
use App\Models\RecurringExpense;
use App\Models\TreatmentAdministration;
use App\Models\User;
use App\Support\Perm;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Tout ce qui est daté dans l'application, rassemblé dans l'agenda : pesées, observations,
 * administrations de traitement, journal, dépenses, échéances, pension, alimentation, déplacements,
 * et pesées à faire. Les éléments sont lus à la source (aucune copie : rien ne peut diverger),
 * avec les droits de l'utilisateur sur chaque cheval, et ouvrent la page d'où ils viennent.
 */
class AgendaJournal
{
    public const TYPES = [
        'weight' => 'Pesée', 'observation' => 'Observation', 'administration' => 'Administration',
        'log' => 'Journal', 'expense' => 'Dépense', 'due' => 'Échéance', 'boarding' => 'Pension',
        'feeding' => 'Alimentation', 'location' => 'Déplacement',
    ];

    public const COLORS = [
        'weight' => 'teal', 'observation' => 'rose', 'administration' => 'purple', 'log' => 'slate', 'expense' => 'blue',
        'due' => 'blue', 'boarding' => 'blue', 'feeding' => 'lime', 'location' => 'sky',
    ];

    public function __construct(private HorseAccess $access) {}

    /** @return Collection<int, array{event: CalendarEvent, at: Carbon, url: string, journal: true}> */
    public function between(User $user, Carbon $from, Carbon $to, ?int $horseId = null, ?string $type = null): Collection
    {
        if ($type && ! isset(self::TYPES[$type])) {
            return collect();
        }
        $ids = function (string ...$perms) use ($user, $horseId) {
            $all = collect($perms)->flatMap(fn ($p) => $this->access->horseIdsWith($user, $p))->unique();

            return $horseId ? $all->intersect([$horseId])->values()->all() : $all->values()->all();
        };
        $want = fn (string $t) => ! $type || $type === $t;
        $horses = Horse::whereIn('id', $ids(Perm::HORSE_VIEW))->get()->keyBy('id');
        $out = collect();
        $add = function (string $t, ?Horse $horse, Carbon $at, string $title, string $url, bool $allDay = false, string $status = 'done') use ($out) {
            $event = (new CalendarEvent)->forceFill(['type' => $t, 'title' => Str::limit($title, 150), 'all_day' => $allDay, 'status' => $status, 'horse_id' => $horse?->id]);
            $event->setRelation('horse', $horse)->setRelation('professional', null)->setRelation('responsible', null);
            $out->push(['event' => $event, 'at' => $at, 'url' => $url, 'journal' => true]);
        };
        [$d0, $d1] = [$from->toDateString(), $to->toDateString()];

        if ($want('weight')) {
            foreach (HorseMeasurement::whereIn('horse_id', $ids(Perm::HORSE_VIEW))->whereBetween('measured_on', [$d0, $d1])->get() as $m) {
                $title = $m->weight_kg ? 'Poids : '.rtrim(rtrim(number_format((float) $m->weight_kg, 1, ',', ' '), '0'), ',').' kg' : 'Mesure';
                $add('weight', $horses[$m->horse_id] ?? null, $m->measured_on->copy()->startOfDay(), $title, route('horses.weight', $m->horse_id), true);
            }
            // Pesées à faire (rappel régulier choisi dans Poids).
            foreach ($horses->whereIn('id', $ids(Perm::HORSE_VIEW))->filter(fn ($h) => $h->weigh_every_weeks) as $h) {
                $due = $h->nextWeighingOn();
                $show = $due->lt(today()) ? today() : $due;
                if ($show->between($from->copy()->startOfDay(), $to)) {
                    $add('weight', $h, $show->copy()->startOfDay(), 'Pesée à faire'.($due->lt(today()) ? ' (en retard)' : ''), route('horses.weight', $h), true, 'planned');
                }
            }
        }
        if ($want('observation')) {
            foreach (HealthObservation::whereIn('horse_id', $ids(Perm::HEALTH_VIEW, Perm::TREATMENTS_VIEW))->whereBetween('observed_at', [$from, $to])->get() as $o) {
                $add('observation', $horses[$o->horse_id] ?? null, $o->observed_at, (HealthObservation::SEVERITIES[$o->severity] ?? 'Observation').' : '.$o->body, route('horses.care.index', $o->horse_id));
            }
        }
        if ($want('administration')) {
            foreach (TreatmentAdministration::with('treatment')->whereHas('treatment', fn ($q) => $q->whereIn('horse_id', $ids(Perm::HEALTH_VIEW, Perm::TREATMENTS_VIEW)))->whereBetween('administered_at', [$from, $to])->get() as $a) {
                $add('administration', $horses[$a->treatment->horse_id] ?? null, $a->administered_at, 'Donné : '.$a->treatment->product, route('horses.care.index', $a->treatment->horse_id));
            }
        }
        if ($want('log')) {
            foreach (DailyLog::whereIn('horse_id', $ids(Perm::HORSE_VIEW))->whereBetween('logged_at', [$from, $to])->get() as $l) {
                $text = $l->anomalies ?: ($l->observations ?: ($l->activity ?: 'État '.mb_strtolower(DailyLog::STATE[$l->general_state] ?? '')));
                $add('log', $horses[$l->horse_id] ?? null, $l->logged_at, 'Journal : '.$text, route('horses.logs.index', $l->horse_id));
            }
        }
        if ($want('feeding')) {
            foreach (FeedingPlan::whereIn('horse_id', $ids(Perm::FEEDING_VIEW))->whereBetween('starts_on', [$d0, $d1])->get() as $f) {
                $add('feeding', $horses[$f->horse_id] ?? null, $f->starts_on->copy()->startOfDay(), 'Nouvelle ration : '.$f->name, route('horses.feeding', $f->horse_id), true);
            }
        }
        if ($want('location')) {
            foreach (HorseLocation::whereIn('horse_id', $ids(Perm::HORSE_VIEW))->whereBetween('started_on', [$d0, $d1])->get() as $l) {
                $add('location', $horses[$l->horse_id] ?? null, $l->started_on->copy()->startOfDay(), 'Arrivée : '.$l->label, route('horses.show', $l->horse_id), true);
            }
        }
        $money = fn ($v) => number_format((float) $v, 2, ',', ' ').' €';
        if ($want('expense')) {
            // Les dépenses liées à un soin sont déjà visibles avec le soin.
            foreach (Expense::with('category')->whereIn('horse_id', $ids(Perm::EXPENSES_VIEW))->whereNull('care_record_id')->whereBetween('spent_on', [$d0, $d1])->get() as $e) {
                $add('expense', $horses[$e->horse_id] ?? null, $e->spent_on->copy()->startOfDay(), ($e->category?->name ?? 'Dépense').' : '.$money($e->amount).($e->supplier ? ' – '.$e->supplier : ''), route('budget.index', ['horse' => $e->horse_id, 'year' => $e->spent_on->year]), true);
            }
        }
        if ($want('due')) {
            foreach (RecurringExpense::whereIn('horse_id', $ids(Perm::EXPENSES_VIEW))->whereNull('paused_at')->where('next_due_on', '<=', $d1)->get() as $r) {
                $at = $r->next_due_on->copy();
                for ($i = 0; $i < 400 && $at->lte($to); $i++, $at = $r->dueAfter($at)) {
                    if ($r->ends_on && $at->gt($r->ends_on)) {
                        break;
                    }
                    if ($at->gte($from->copy()->startOfDay())) {
                        $add('due', $horses[$r->horse_id] ?? null, $at->copy()->startOfDay(), 'À payer : '.$r->label.' – '.$money($r->amount), route('budget.index', ['horse' => $r->horse_id]), true, 'planned');
                    }
                }
            }
        }
        if ($want('boarding') && ! $horseId) {
            $orgs = $user->memberships->pluck('organization_id')->filter(fn ($o) => $user->canInOrg($o, Perm::EXPENSES_ORG_VIEW));
            foreach (BoardingStatement::whereIn('organization_id', $orgs)->whereNotNull('sent_at')->where(fn ($q) => $q->whereBetween('sent_at', [$from, $to])->orWhereBetween('paid_on', [$d0, $d1]))->get() as $s) {
                $label = $s->title().' '.$s->number.' – '.($s->owner_name ?: 'propriétaire').' – '.$money($s->total);
                if ($s->sent_at->between($from, $to)) {
                    $add('boarding', null, $s->sent_at, $label.' (envoyé)', route('boarding.statement', $s), true, $s->paid_on ? 'done' : 'planned');
                }
                if ($s->paid_on && $s->paid_on->between($from->copy()->startOfDay(), $to)) {
                    $add('boarding', null, $s->paid_on->copy()->startOfDay(), $label.' (payé)', route('boarding.statement', $s), true);
                }
            }
        }

        return $out;
    }
}

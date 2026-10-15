<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\CareCategory;
use App\Models\CareRecord;
use App\Models\Horse;
use App\Models\Treatment;
use App\Support\Perm;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Synchronisation Agenda ↔ Santé.
 * - Un soin du carnet de santé a son événement dans l'agenda (réalisé, ou prévu s'il est daté dans le futur),
 *   quelle que soit l'origine du soin (formulaire, assistant, facture).
 * - Le prochain contrôle d'un soin n'est plus ajouté d'office à l'agenda : Santé l'indique et propose de l'y ajouter
 *   à sa date d'échéance (planCheck), seulement pour la prochaine échéance de chaque type de soin.
 * - Un rendez-vous de soin (maréchalerie, vétérinaire, soin) déjà passé à sa saisie, ou marqué « réalisé »,
 *   crée le soin correspondant dans le carnet de santé ; modifier ou supprimer l'un met à jour l'autre.
 * - Déplacer un contrôle prévu dans l'agenda change la date du contrôle ; le marquer « réalisé » enregistre le soin.
 * - Un traitement a son début (et sa fin) dans l'agenda ; un traitement saisi dans l'agenda est créé dans Santé.
 * Les rendez-vous à venir restent des rendez-vous : ils apparaissent dans Santé, à valider une fois passés.
 */
class CareCalendarSync
{
    /** Type d'événement → catégorie de soin. */
    public const CATEGORY_FOR_EVENT = ['farrier' => 'farrier', 'veterinary' => 'veterinary', 'care' => 'routine'];

    private static bool $syncing = false;

    public function __construct(private CalendarService $calendar, private HorseAccess $access) {}

    public static function eventTypeFor(?string $categoryKey): string
    {
        return match ($categoryKey) {
            'veterinary', 'vaccination', 'exams', 'imaging', 'specialist' => 'veterinary',
            'farrier' => 'farrier',
            default => 'care',
        };
    }

    /** Soin créé ou modifié : son événement dans l'agenda, et son contrôle prévu. */
    public function fromCare(CareRecord $care): void
    {
        if (self::$syncing || ! $care->horse || ! $care->performed_at) {
            return;
        }
        $this->guard(function () use ($care) {
            $care->loadMissing(['category', 'horse']);
            $event = CalendarEvent::where('care_record_id', $care->id)->first() ?? new CalendarEvent;
            $event->forceFill([
                'horse_id' => $care->horse_id,
                'organization_id' => $event->organization_id ?? $care->horse->organization_id,
                'created_by' => $event->created_by ?? $care->author_id,
                'type' => $event->exists && isset(self::CATEGORY_FOR_EVENT[$event->type]) ? $event->type : self::eventTypeFor($care->category?->key),
                'title' => $event->exists ? $event->title : Str::limit(($care->category?->name ?? 'Soin').($care->reason ? ' – '.$care->reason : ''), 150, ''),
                'starts_at' => $care->performed_at,
                'duration_minutes' => $event->duration_minutes ?: 30,
                'professional_id' => $care->professional_id,
                'status' => $care->performed_at->isFuture() ? ($event->status === 'confirmed' ? 'confirmed' : 'planned') : 'done',
                'care_record_id' => $care->id,
            ])->save();
            $this->syncCheck($care);
        });
    }

    /**
     * Contrôle prévu du soin déjà ajouté à l'agenda (planCheck) : il suit la date du soin, l'heure choisie dans l'agenda
     * étant conservée. Rien n'est créé d'office.
     */
    private function syncCheck(CareRecord $care): void
    {
        $existing = CalendarEvent::where('source_type', $care->getMorphClass())->where('source_id', $care->id)->first();
        if (! $existing) {
            return;
        }
        $at = null;
        if ($care->next_check_on) {
            $day = Carbon::parse($care->next_check_on);
            $at = $existing->starts_at->isSameDay($day) ? $existing->starts_at : $day->copy()->setTime($existing->starts_at->hour, $existing->starts_at->minute);
        }
        $this->calendar->syncFor($care, $care->horse, $existing->type, $existing->title, $at,
            ['professional_id' => $care->professional_id ?? $existing->professional_id]
            + ($care->next_check_done_at ? ['status' => 'done'] : ($existing->status === 'done' ? ['status' => 'planned'] : [])));
    }

    /** Prochain contrôle ajouté à l'agenda à la demande, à sa date d'échéance (9 h, modifiable dans l'agenda). */
    public function planCheck(CareRecord $care): ?CalendarEvent
    {
        if (! $care->next_check_on || $care->next_check_done_at) {
            return null;
        }
        $care->loadMissing(['category', 'horse']);
        $existing = CalendarEvent::where('source_type', $care->getMorphClass())->where('source_id', $care->id)->first();
        if ($existing) {
            return $existing;
        }
        $event = null;
        $this->guard(function () use ($care, &$event) {
            $event = $this->calendar->syncFor($care, $care->horse, self::eventTypeFor($care->category?->key),
                Str::limit(($care->category?->name ?? 'Soin').' – '.$care->horse->shortName(), 150, ''),
                Carbon::parse($care->next_check_on)->setTime(9, 0),
                ['professional_id' => $care->professional_id, 'duration_minutes' => 30, 'status' => 'planned']);
        });

        return $event;
    }

    /** Soin supprimé : son événement (et son contrôle prévu) disparaît de l'agenda. */
    public function careDeleted(CareRecord $care): void
    {
        if (self::$syncing) {
            return;
        }
        $this->guard(fn () => CalendarEvent::where('care_record_id', $care->id)
            ->orWhere(fn ($q) => $q->where('source_type', $care->getMorphClass())->where('source_id', $care->id))->get()->each->delete());
    }

    /** Traitement créé ou modifié : son début et sa fin dans l'agenda. */
    public function fromTreatment(Treatment $treatment): void
    {
        if (self::$syncing || ! $treatment->horse || ! $treatment->starts_on) {
            return;
        }
        $this->guard(function () use ($treatment) {
            $horse = $treatment->horse;
            $event = CalendarEvent::where('treatment_id', $treatment->id)->first() ?? new CalendarEvent;
            $start = Carbon::parse($treatment->starts_on);
            $event->forceFill([
                'horse_id' => $treatment->horse_id,
                'organization_id' => $event->organization_id ?? $horse->organization_id,
                'created_by' => $event->created_by ?? $treatment->author_id,
                'type' => 'treatment',
                'title' => Str::limit($treatment->product, 150, ''),
                'starts_at' => $event->exists && $event->starts_at->isSameDay($start) ? $event->starts_at : $start->copy()->setTime(8, 0),
                'all_day' => $event->exists ? $event->all_day : true,
                'duration_minutes' => $event->duration_minutes ?: 15,
                'notes' => $treatment->instructions,
                'responsible_user_id' => $treatment->responsible_user_id,
                'status' => $treatment->status === 'stopped' && $start->isFuture() ? 'cancelled' : ($start->isFuture() ? 'planned' : 'done'),
                'treatment_id' => $treatment->id,
            ])->save();
            $end = $treatment->ends_on && $treatment->status === 'active' ? Carbon::parse($treatment->ends_on)->setTime(18, 0) : null;
            $this->calendar->syncFor($treatment, $horse, 'treatment', 'Fin de traitement : '.$treatment->product.' – '.$horse->shortName(), $end, [
                'responsible_user_id' => $treatment->responsible_user_id, 'duration_minutes' => 15,
            ]);
        });
    }

    public function treatmentDeleted(Treatment $treatment): void
    {
        if (self::$syncing) {
            return;
        }
        $this->guard(fn () => CalendarEvent::where('treatment_id', $treatment->id)
            ->orWhere(fn ($q) => $q->where('source_type', $treatment->getMorphClass())->where('source_id', $treatment->id))->get()->each->delete());
    }

    /** Événement créé ou modifié dans l'agenda : répercussion dans Santé. */
    public function fromEvent(CalendarEvent $event, bool $created = false): void
    {
        if (self::$syncing || ! $event->horse_id) {
            return;
        }
        if ($event->source_type === (new CareRecord)->getMorphClass()) {
            $this->fromCheckEvent($event);

            return;
        }
        if ($event->source_type === (new Treatment)->getMorphClass()) {
            if ($event->isDirty('starts_at') || $event->wasChanged('starts_at')) {
                $this->guard(fn () => Treatment::find($event->source_id)?->forceFill(['ends_on' => $event->starts_at->toDateString()])->saveQuietly());
            }

            return;
        }
        if ($event->source_type || $event->recurrence) {
            return;
        }
        if ($event->type === 'treatment' || $event->treatment_id) {
            $this->fromTreatmentEvent($event);

            return;
        }
        $key = self::CATEGORY_FOR_EVENT[$event->type] ?? null;
        if (! $key || $event->status === 'cancelled') {
            return;
        }
        $care = $event->care_record_id ? CareRecord::find($event->care_record_id) : null;
        $done = $event->status === 'done' || ($created && $event->starts_at->lte(now()));
        if (! $care && (! $done || ! $this->mayEditHealth($event))) {
            return;
        }
        $this->guard(function () use ($event, $care, $key) {
            $event->loadMissing('horse');
            if (! $care) {
                $care = new CareRecord;
                $care->horse_id = $event->horse_id;
                $care->author_id = auth()->id() ?? $event->created_by;
                $care->care_category_id = CareCategory::visibleTo($event->horse->organization_id)->where('key', $key)->value('id');
                $care->reason = Str::limit($event->title, 250, '');
                $care->observations = $event->notes;
            }
            $care->performed_at = $event->starts_at;
            $care->professional_id = $event->professional_id;
            $care->save();
            $event->forceFill(['care_record_id' => $care->id, 'status' => $event->starts_at->isFuture() ? $event->status : 'done'])->saveQuietly();
        });
    }

    /**
     * Contrôle prévu modifié dans l'agenda : la date suit dans le soin ;
     * « réalisé » enregistre le nouveau soin (même catégorie) et clôt le contrôle.
     */
    private function fromCheckEvent(CalendarEvent $event): void
    {
        $previous = CareRecord::with(['category', 'horse'])->find($event->source_id);
        if (! $previous) {
            return;
        }
        if ($event->status !== 'done' || $previous->next_check_done_at) {
            $this->guard(fn () => $previous->forceFill(['next_check_on' => $event->starts_at->toDateString()])->saveQuietly());

            return;
        }
        if (! $this->mayEditHealth($event)) {
            return;
        }
        $care = null;
        $this->guard(function () use ($event, $previous, &$care) {
            $care = new CareRecord;
            $care->horse_id = $previous->horse_id;
            $care->author_id = auth()->id() ?? $event->created_by;
            $care->care_category_id = $previous->care_category_id;
            $care->reason = 'Contrôle';
            $care->observations = $event->notes;
            $care->performed_at = $event->starts_at->isFuture() ? now() : $event->starts_at;
            $care->professional_id = $event->professional_id ?? $previous->professional_id;
            $rule = app(CareReminders::class)->ruleFor($previous->horse, $previous->care_category_id);
            if ($rule && $rule['enabled']) {
                $care->next_check_on = CareReminders::addInterval($care->performed_at, $rule['interval_value'], $rule['interval_unit'])->toDateString();
            }
            $care->save();
            $previous->forceFill(['next_check_done_at' => now()])->saveQuietly();
            // L'événement devient celui du soin réalisé.
            $event->forceFill(['source_type' => null, 'source_id' => null, 'care_record_id' => $care->id, 'starts_at' => $care->performed_at])->saveQuietly();
        });
        $this->fromCare($care);
    }

    /** Traitement saisi ou modifié depuis l'agenda. */
    private function fromTreatmentEvent(CalendarEvent $event): void
    {
        $treatment = $event->treatment_id ? Treatment::find($event->treatment_id) : null;
        if (! $treatment && ($event->type !== 'treatment' || ! $this->mayEditHealth($event))) {
            return;
        }
        $this->guard(function () use ($event, $treatment) {
            if (! $treatment) {
                $treatment = new Treatment(['status' => 'active']);
                $treatment->horse_id = $event->horse_id;
                $treatment->author_id = auth()->id() ?? $event->created_by;
            }
            $treatment->fill([
                'product' => $event->title,
                'starts_on' => $event->starts_at->toDateString(),
                'instructions' => $event->notes,
                'responsible_user_id' => $event->responsible_user_id ?? $treatment->responsible_user_id,
            ]);
            if ($event->status === 'cancelled' && $treatment->status === 'active') {
                $treatment->status = 'stopped';
            }
            $treatment->save();
            $event->forceFill(['treatment_id' => $treatment->id])->saveQuietly();
        });
    }

    /** Événement supprimé : l'élément qui lui est lié suit. */
    public function eventDeleted(CalendarEvent $event): void
    {
        if (self::$syncing) {
            return;
        }
        $this->guard(function () use ($event) {
            if ($event->care_record_id) {
                CareRecord::whereKey($event->care_record_id)->first()?->delete();
            }
            if ($event->source_type === (new CareRecord)->getMorphClass()) {
                // Supprimer le contrôle prévu l'annule (le soin d'origine est conservé).
                CareRecord::find($event->source_id)?->forceFill(['next_check_on' => null])->saveQuietly();
            }
            if ($event->source_type === (new Treatment)->getMorphClass()) {
                Treatment::find($event->source_id)?->forceFill(['ends_on' => null])->saveQuietly();
            }
            if ($event->treatment_id && ($treatment = Treatment::find($event->treatment_id)) && ! $treatment->administrations()->exists()) {
                $treatment->delete();
                CalendarEvent::where('source_type', $treatment->getMorphClass())->where('source_id', $treatment->id)->get()->each->delete();
            }
        });
    }

    /** Rattache un rendez-vous validé (« Valider l'intervention ») au soin enregistré, sans doublon. */
    public function linkValidated(CalendarEvent $event, CareRecord $care): void
    {
        $this->guard(function () use ($event, $care) {
            CalendarEvent::where('care_record_id', $care->id)->whereKeyNot($event->id)->get()->each->forceDelete();
            $event->forceFill(['care_record_id' => $care->id, 'status' => 'done', 'starts_at' => $care->performed_at, 'professional_id' => $care->professional_id ?? $event->professional_id])->saveQuietly();
        });
    }

    /** Créer un soin ou un traitement depuis l'agenda demande les droits « santé » sur le cheval. */
    private function mayEditHealth(CalendarEvent $event): bool
    {
        $user = auth()->user();
        $horse = $event->horse ?? Horse::find($event->horse_id);

        return ! $user || ($horse && $this->access->can($user, $horse, Perm::HEALTH_EDIT));
    }

    /** Reprise de l'existant : soins sans événement, rendez-vous réalisés sans soin, traitements sans événement. */
    public function backfill(): void
    {
        CareRecord::with(['category', 'horse'])->whereHas('horse')->whereDoesntHave('calendarEvent')->chunkById(200, fn ($cares) => $cares->each(fn ($c) => $this->fromCare($c)));
        CalendarEvent::whereIn('type', array_keys(self::CATEGORY_FOR_EVENT))->where('status', 'done')->whereNull('care_record_id')
            ->whereNotNull('horse_id')->whereNull('recurrence')->whereNull('source_type')
            ->chunkById(200, fn ($events) => $events->each(fn ($e) => $this->fromEvent($e)));
    }

    public function backfillTreatments(): void
    {
        Treatment::with('horse')->whereHas('horse')->whereNotIn('id', CalendarEvent::whereNotNull('treatment_id')->select('treatment_id'))
            ->chunkById(200, fn ($ts) => $ts->each(fn ($t) => $this->fromTreatment($t)));
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

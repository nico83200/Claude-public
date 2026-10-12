<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\CareCategory;
use App\Models\CareRecord;
use Illuminate\Support\Str;

/**
 * Synchronisation Agenda ↔ Santé.
 * - Un soin du carnet de santé a son événement dans l'agenda (réalisé, ou prévu s'il est daté dans le futur).
 * - Un rendez-vous de soin (maréchalerie, vétérinaire, soin) déjà passé à sa saisie, ou marqué « réalisé »,
 *   crée le soin correspondant dans le carnet de santé ; modifier ou supprimer l'un met à jour l'autre.
 * Les rendez-vous à venir restent des rendez-vous : ils apparaissent dans Santé, à valider une fois passés.
 */
class CareCalendarSync
{
    /** Type d'événement → catégorie de soin. */
    public const CATEGORY_FOR_EVENT = ['farrier' => 'farrier', 'veterinary' => 'veterinary', 'care' => 'routine'];

    private static bool $syncing = false;

    public static function eventTypeFor(?string $categoryKey): string
    {
        return match ($categoryKey) {
            'veterinary', 'vaccination', 'exams', 'imaging', 'specialist' => 'veterinary',
            'farrier' => 'farrier',
            default => 'care',
        };
    }

    /** Soin créé ou modifié : son événement dans l'agenda. */
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
                'title' => $event->exists ? $event->title : Str::limit(($care->category?->name ?? 'Soin').($care->reason ? ' – '.$care->reason : ''), 250, ''),
                'starts_at' => $care->performed_at,
                'duration_minutes' => $event->duration_minutes ?: 30,
                'professional_id' => $care->professional_id,
                'status' => $care->performed_at->isFuture() ? ($event->status === 'confirmed' ? 'confirmed' : 'planned') : 'done',
                'care_record_id' => $care->id,
            ])->save();
        });
    }

    /** Soin supprimé : son événement disparaît de l'agenda. */
    public function careDeleted(CareRecord $care): void
    {
        if (self::$syncing) {
            return;
        }
        $this->guard(fn () => CalendarEvent::where('care_record_id', $care->id)->get()->each->delete());
    }

    /** Événement créé ou modifié : soin correspondant s'il est réalisé (ou saisi après coup). */
    public function fromEvent(CalendarEvent $event, bool $created = false): void
    {
        $key = self::CATEGORY_FOR_EVENT[$event->type] ?? null;
        if (self::$syncing || ! $key || ! $event->horse_id || $event->recurrence || $event->status === 'cancelled') {
            return;
        }
        $care = $event->care_record_id ? CareRecord::find($event->care_record_id) : null;
        $done = $event->status === 'done' || ($created && $event->starts_at->lte(now()));
        if (! $care && ! $done) {
            return;
        }
        $this->guard(function () use ($event, $care, $key) {
            $event->loadMissing('horse');
            if (! $care) {
                $care = new CareRecord;
                $care->horse_id = $event->horse_id;
                $care->author_id = $event->created_by;
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

    /** Événement supprimé : le soin qui lui est lié disparaît aussi du carnet de santé. */
    public function eventDeleted(CalendarEvent $event): void
    {
        if (self::$syncing || ! $event->care_record_id) {
            return;
        }
        $this->guard(fn () => CareRecord::whereKey($event->care_record_id)->first()?->delete());
    }

    /** Rattache un rendez-vous validé (« Valider l'intervention ») au soin enregistré, sans doublon. */
    public function linkValidated(CalendarEvent $event, CareRecord $care): void
    {
        $this->guard(function () use ($event, $care) {
            CalendarEvent::where('care_record_id', $care->id)->whereKeyNot($event->id)->get()->each->forceDelete();
            $event->forceFill(['care_record_id' => $care->id, 'status' => 'done', 'starts_at' => $care->performed_at, 'professional_id' => $care->professional_id ?? $event->professional_id])->saveQuietly();
        });
    }

    /** Reprise de l'existant : soins sans événement, rendez-vous réalisés sans soin. */
    public function backfill(): void
    {
        CareRecord::with(['category', 'horse'])->whereHas('horse')->whereDoesntHave('calendarEvent')->chunkById(200, fn ($cares) => $cares->each(fn ($c) => $this->fromCare($c)));
        CalendarEvent::whereIn('type', array_keys(self::CATEGORY_FOR_EVENT))->where('status', 'done')->whereNull('care_record_id')
            ->whereNotNull('horse_id')->whereNull('recurrence')->whereNull('source_type')
            ->chunkById(200, fn ($events) => $events->each(fn ($e) => $this->fromEvent($e)));
    }

    private function guard(callable $fn): void
    {
        self::$syncing = true;
        try {
            $fn();
        } finally {
            self::$syncing = false;
        }
    }
}

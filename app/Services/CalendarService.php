<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\Horse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CalendarService
{
    /** Crée ou met à jour l'événement lié à une source (soin, traitement…), sans doublon. */
    public function syncFor(Model $source, Horse $horse, string $type, string $title, ?Carbon $startsAt, array $extra = []): ?CalendarEvent
    {
        $existing = CalendarEvent::where('source_type', $source->getMorphClass())->where('source_id', $source->getKey())->first();
        if (! $startsAt) {
            $existing?->delete();

            return null;
        }
        $attributes = array_merge([
            'horse_id' => $horse->id, 'type' => $type, 'title' => $title, 'starts_at' => $startsAt,
            'source_type' => $source->getMorphClass(), 'source_id' => $source->getKey(),
        ], $extra);

        if ($existing) {
            $existing->update($attributes);

            return $existing;
        }
        $event = new CalendarEvent($attributes);
        $event->organization_id = $horse->organization_id;
        $event->created_by = auth()->id();
        $event->save();

        return $event;
    }

    /**
     * Développe les événements récurrents sur une période (occurrences virtuelles,
     * non enregistrées : aucune duplication en base).
     */
    public function expand(Collection $events, Carbon $from, Carbon $to): Collection
    {
        $out = collect();
        foreach ($events as $event) {
            if (! $event->recurrence) {
                if ($event->starts_at->between($from, $to)) {
                    $out->push(['event' => $event, 'at' => $event->starts_at]);
                }

                continue;
            }
            $at = $event->starts_at->copy();
            $until = $event->recurrence_until ? $event->recurrence_until->copy()->endOfDay() : $to;
            $step = max(1, (int) $event->recurrence_interval);
            $guard = 0;
            while ($at->lte($to) && $at->lte($until) && $guard++ < 1000) {
                if ($at->gte($from)) {
                    $out->push(['event' => $event, 'at' => $at->copy()]);
                }
                $at = match ($event->recurrence) {
                    'daily' => $at->addDays($step),
                    'weekly' => $at->addWeeks($step),
                    'monthly' => $at->addMonthsNoOverflow($step),
                    default => $to->copy()->addDay(),
                };
            }
        }

        return $out->sortBy(fn ($o) => $o['at']->timestamp)->values();
    }
}

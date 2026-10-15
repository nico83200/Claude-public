<?php

namespace App\Http\Controllers;

use App\Models\FeedingPlan;
use App\Models\Horse;
use App\Services\Audit;
use App\Support\Perm;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Alimentation : plusieurs rations suivies en même temps (matin, soir, complément sur 15 jours…).
 * Chaque ration peut être modifiée, avoir une date de fin (ou une durée) qui l'archive automatiquement
 * avec notification (feeding:archive), être archivée à la main, reprise, ou supprimée en cas d'erreur.
 */
class FeedingController extends Controller
{
    public function show(Horse $horse)
    {
        $this->authorizeHorse($horse, Perm::FEEDING_VIEW);
        $plans = $horse->feedingPlans()->with(['entries', 'author'])->get();
        [$ongoing, $archived] = $plans->partition(fn (FeedingPlan $p) => ! $p->isArchived());

        return view('horses.feeding', [
            'horse' => $horse,
            'rations' => $ongoing->sortBy(fn ($p) => [$p->isUpcoming() ? 1 : 0, $p->name])->values(),
            'archived' => $archived->sortByDesc(fn ($p) => $p->archived_at ?? $p->ends_on)->values(),
            'canEdit' => $this->horseCan($horse, Perm::FEEDING_EDIT),
        ]);
    }

    public function store(Request $request, Horse $horse)
    {
        $this->authorizeHorse($horse, Perm::FEEDING_EDIT);
        $data = $this->validated($request);
        $plan = DB::transaction(function () use ($request, $horse, $data) {
            $plan = new FeedingPlan(['horse_id' => $horse->id, 'author_id' => $request->user()->id] + $this->attributes($data));
            $plan->save();
            $this->saveEntries($plan, $data);

            return $plan;
        });
        Audit::log('feeding.created', $plan, [], $horse->organization_id);

        return redirect()->route('horses.feeding', $horse)->with('success', 'Ration « '.$plan->name.' » enregistrée.'.($plan->ends_on ? ' Elle sera archivée après le '.$plan->ends_on->format('d/m/Y').'.' : ''));
    }

    public function update(Request $request, Horse $horse, FeedingPlan $ration)
    {
        $this->authorizeHorse($horse, Perm::FEEDING_EDIT);
        $data = $this->validated($request);
        DB::transaction(function () use ($ration, $data) {
            $attributes = $this->attributes($data);
            // Date de fin repoussée : la ration redevient suivie.
            if ($ration->archived_at && (! $attributes['ends_on'] || Carbon::parse($attributes['ends_on'])->gte(today()))) {
                $attributes['archived_at'] = null;
            }
            $ration->update($attributes);
            $ration->entries()->delete();
            $this->saveEntries($ration, $data);
        });
        Audit::log('feeding.updated', $ration, [], $horse->organization_id);

        return redirect()->route('horses.feeding', $horse)->with('success', 'Ration « '.$ration->name.' » modifiée.');
    }

    /** Archiver (fin de la ration aujourd'hui) ou reprendre une ration archivée. */
    public function archive(Request $request, Horse $horse, FeedingPlan $ration)
    {
        $this->authorizeHorse($horse, Perm::FEEDING_EDIT);
        if ($request->boolean('restore')) {
            $ration->update(['archived_at' => null, 'ends_on' => $ration->ends_on && $ration->ends_on->lt(today()) ? null : $ration->ends_on]);
            $message = 'Ration « '.$ration->name.' » reprise.';
        } else {
            $ration->update(['archived_at' => now(), 'ends_on' => $ration->ends_on && $ration->ends_on->lt(today()) ? $ration->ends_on : today()]);
            $message = 'Ration « '.$ration->name.' » archivée : elle reste dans l\'historique.';
        }
        Audit::log($request->boolean('restore') ? 'feeding.restored' : 'feeding.archived', $ration, [], $horse->organization_id);

        return redirect()->route('horses.feeding', $horse)->with('success', $message);
    }

    /** Suppression définitive (ration saisie par erreur). */
    public function destroy(Horse $horse, FeedingPlan $ration)
    {
        $this->authorizeHorse($horse, Perm::FEEDING_EDIT);
        DB::transaction(function () use ($ration) {
            $ration->entries()->delete();
            $ration->delete();
        });
        Audit::log('feeding.deleted', $ration, ['name' => $ration->name], $horse->organization_id);

        return redirect()->route('horses.feeding', $horse)->with('success', 'Ration « '.$ration->name.' » supprimée.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'starts_on' => ['required', 'date'],
            'end_mode' => ['nullable', 'in:none,date,duration'],
            'ends_on' => ['nullable', 'required_if:end_mode,date', 'date', 'after_or_equal:starts_on'],
            'duration_value' => ['nullable', 'required_if:end_mode,duration', 'integer', 'min:1', 'max:365'],
            'duration_unit' => ['nullable', 'in:days,weeks,months'],
            'entries' => ['array', 'max:30'],
            'entries.*.feed' => ['nullable', 'string', 'max:120'],
            'entries.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'entries.*.unit' => ['nullable', 'string', 'max:20'],
            'entries.*.time_of_day' => ['nullable', 'date_format:H:i'],
            'entries.*.frequency' => ['nullable', 'string', 'max:60'],
            'entries.*.is_supplement' => ['nullable', 'boolean'],
            'entries.*.note' => ['nullable', 'string', 'max:255'],
        ], [
            'ends_on.after_or_equal' => 'La date de fin doit suivre la date de début.',
            'ends_on.required_if' => 'Indiquez la date de fin.',
            'duration_value.required_if' => 'Indiquez la durée.',
        ]);
    }

    /** Fin de la ration : aucune, une date, ou une durée comptée à partir du début (jour de début inclus). */
    private function attributes(array $data): array
    {
        $start = Carbon::parse($data['starts_on']);
        $ends = match ($data['end_mode'] ?? 'none') {
            'date' => Carbon::parse($data['ends_on']),
            'duration' => match ($data['duration_unit'] ?? 'days') {
                'weeks' => $start->copy()->addWeeks((int) $data['duration_value'])->subDay(),
                'months' => $start->copy()->addMonthsNoOverflow((int) $data['duration_value'])->subDay(),
                default => $start->copy()->addDays((int) $data['duration_value'] - 1),
            },
            default => null,
        };

        return ['name' => $data['name'], 'instructions' => $data['instructions'] ?? null, 'starts_on' => $start->toDateString(), 'ends_on' => $ends?->toDateString()];
    }

    private function saveEntries(FeedingPlan $plan, array $data): void
    {
        foreach ($data['entries'] ?? [] as $entry) {
            if (! empty($entry['feed'])) {
                $plan->entries()->create([
                    'feed' => $entry['feed'], 'quantity' => $entry['quantity'] ?? null, 'unit' => ($entry['unit'] ?? null) ?: 'kg',
                    'time_of_day' => $entry['time_of_day'] ?? null, 'frequency' => $entry['frequency'] ?? null,
                    'is_supplement' => (bool) ($entry['is_supplement'] ?? false), 'note' => $entry['note'] ?? null,
                ]);
            }
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\FeedingPlan;
use App\Models\Horse;
use App\Support\Perm;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Plans alimentaires : un nouveau plan clôt le précédent, ce qui conserve
 * l'historique complet des modifications.
 */
class FeedingController extends Controller
{
    public function show(Horse $horse)
    {
        $this->authorizeHorse($horse, Perm::FEEDING_VIEW);
        $plans = $horse->feedingPlans()->with(['entries', 'author'])->get();

        return view('horses.feeding', [
            'horse' => $horse,
            'current' => $plans->first(fn ($p) => ! $p->ends_on || $p->ends_on->isFuture() || $p->ends_on->isToday()),
            'history' => $plans,
            'canEdit' => $this->horseCan($horse, Perm::FEEDING_EDIT),
        ]);
    }

    public function store(Request $request, Horse $horse)
    {
        $this->authorizeHorse($horse, Perm::FEEDING_EDIT);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'starts_on' => ['required', 'date'],
            'entries' => ['array', 'max:30'],
            'entries.*.feed' => ['nullable', 'string', 'max:120'],
            'entries.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'entries.*.unit' => ['nullable', 'string', 'max:20'],
            'entries.*.time_of_day' => ['nullable', 'date_format:H:i'],
            'entries.*.frequency' => ['nullable', 'string', 'max:60'],
            'entries.*.is_supplement' => ['nullable', 'boolean'],
            'entries.*.note' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($request, $horse, $data) {
            FeedingPlan::where('horse_id', $horse->id)->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $data['starts_on']))
                ->update(['ends_on' => Carbon::parse($data['starts_on'])->subDay()->toDateString()]);
            $plan = FeedingPlan::create(['horse_id' => $horse->id, 'author_id' => $request->user()->id, 'name' => $data['name'], 'instructions' => $data['instructions'] ?? null, 'starts_on' => $data['starts_on']]);
            foreach ($data['entries'] ?? [] as $entry) {
                if (! empty($entry['feed'])) {
                    $plan->entries()->create([
                        'feed' => $entry['feed'], 'quantity' => $entry['quantity'] ?? null, 'unit' => ($entry['unit'] ?? null) ?: 'kg',
                        'time_of_day' => $entry['time_of_day'] ?? null, 'frequency' => $entry['frequency'] ?? null,
                        'is_supplement' => (bool) ($entry['is_supplement'] ?? false), 'note' => $entry['note'] ?? null,
                    ]);
                }
            }
        });

        return back()->with('success', 'Nouveau plan alimentaire enregistré ; le précédent est conservé dans l\'historique.');
    }
}

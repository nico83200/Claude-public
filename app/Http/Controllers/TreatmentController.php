<?php

namespace App\Http\Controllers;

use App\Models\Horse;
use App\Models\Treatment;
use App\Models\TreatmentAdministration;
use App\Services\Audit;
use App\Services\CalendarService;
use App\Support\Perm;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Suivi des traitements prescrits. L'application enregistre ce qui a été
 * prescrit par un professionnel ; elle ne calcule ni ne propose de posologie.
 */
class TreatmentController extends Controller
{
    public function __construct(private CalendarService $calendar) {}

    public function store(Request $request, Horse $horse)
    {
        $this->authorizeHorse($horse, Perm::HEALTH_EDIT);
        $data = $this->validated($request, $horse);
        $treatment = new Treatment($data);
        $treatment->horse_id = $horse->id;
        $treatment->author_id = $request->user()->id;
        $treatment->status = 'active';
        $treatment->save();
        Audit::log('treatment.created', $treatment, [], $horse->organization_id);

        return back()->with('success', 'Traitement enregistré.');
    }

    public function update(Request $request, Horse $horse, Treatment $treatment)
    {
        $this->authorizeHorse($horse, Perm::HEALTH_EDIT);
        $data = $this->validated($request, $horse) + $request->validate(['status' => ['required', Rule::in(array_keys(Treatment::STATUSES))]]);
        $treatment->update($data);

        return back()->with('success', 'Traitement mis à jour.');
    }

    /** Suppression d'un traitement saisi par erreur (avec ses administrations cochées et ses dates dans l'agenda). */
    public function destroy(Horse $horse, Treatment $treatment)
    {
        $this->authorizeHorse($horse, Perm::HEALTH_EDIT);
        $treatment->administrations()->delete();
        $treatment->delete();
        Audit::log('treatment.deleted', $treatment, ['product' => $treatment->product], $horse->organization_id);

        return back()->with('success', 'Traitement « '.$treatment->product.' » supprimé.');
    }

    public function administer(Request $request, Horse $horse, Treatment $treatment)
    {
        // Les responsables désignés et les soignants peuvent cocher une administration.
        abort_unless($this->horseCan($horse, Perm::HEALTH_EDIT) || ($treatment->responsible_user_id === $request->user()->id && $this->horseCan($horse, Perm::DAILYLOG_CREATE)), 403);
        $data = $request->validate(['administered_at' => ['nullable', 'date'], 'note' => ['nullable', 'string', 'max:255']]);
        TreatmentAdministration::create([
            'treatment_id' => $treatment->id, 'administered_by' => $request->user()->id,
            'administered_at' => $data['administered_at'] ?? now(), 'note' => $data['note'] ?? null,
        ]);

        return back()->with('success', 'Administration enregistrée.');
    }

    private function validated(Request $request, Horse $horse): array
    {
        return $request->validate([
            'product' => ['required', 'string', 'max:150'],
            'dosage' => ['nullable', 'string', 'max:255'],
            'frequency' => ['nullable', 'string', 'max:255'],
            'times_per_day' => ['nullable', 'integer', 'min:1', 'max:24'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'track_administrations' => ['nullable', 'boolean'],
            'responsible_user_id' => ['nullable', Rule::exists('organization_members', 'user_id')->where('organization_id', $horse->organization_id)],
            'care_record_id' => ['nullable', Rule::exists('care_records', 'id')->where('horse_id', $horse->id)],
        ]);
    }
}

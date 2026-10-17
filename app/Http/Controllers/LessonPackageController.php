<?php

namespace App\Http\Controllers;

use App\Models\LessonPackage;
use App\Services\HorseAccess;
use App\Services\LessonCosts;
use App\Support\Perm;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Cartes de cours et forfaits mensuels de chacun (page « Frais de cours »). */
class LessonPackageController extends Controller
{
    public function __construct(private LessonCosts $costs) {}

    public function store(Request $request, HorseAccess $access)
    {
        $user = $request->user();
        $data = $request->validate([
            'professional_id' => ['required', 'integer', Rule::exists('professionals', 'id')
                ->where(fn ($q) => $q->whereIn('organization_id', $user->memberships->pluck('organization_id'))->where('kind', 'instructor')->whereNull('archived_at'))],
            'horse_id' => ['required', 'integer', Rule::in($access->horseIdsWith($user, Perm::SESSIONS_VIEW))],
            'kind' => ['required', Rule::in(array_keys(LessonPackage::KINDS))],
            'lessons' => ['required', 'integer', 'min:1', 'max:200'],
            'price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ], [], ['professional_id' => 'enseignant', 'horse_id' => 'cheval', 'lessons' => 'nombre de cours', 'price' => 'prix', 'starts_on' => 'début', 'ends_on' => 'fin']);
        $package = LessonPackage::create($data + ['payer_id' => $user->id]);
        $this->costs->packageSaved($package);

        return back()->with('success', $package->title().' ajouté'.($package->kind === 'card' ? 'e' : '').' : les cours faits avec cet enseignant y sont décomptés.');
    }

    public function destroy(Request $request, LessonPackage $package)
    {
        abort_unless($package->payer_id === $request->user()->id, 403);
        $package->delete();
        $this->costs->packageDeleted($package);

        return back()->with('success', 'Supprimé : les cours qu\'il couvrait sont de nouveau comptés au tarif de l\'enseignant.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\InstructorRate;
use App\Models\Professional;
use App\Support\Perm;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Tarifs d'un enseignant (fiche intervenant) : cours particulier, cours collectif par cavalier, coaching, déplacement. */
class InstructorRateController extends Controller
{
    public function store(Request $request, Professional $professional)
    {
        $this->authorizeOrg($professional->organization, Perm::PROFESSIONALS_MANAGE);
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(InstructorRate::KINDS))],
            'unit' => ['required', 'string', 'max:10'],
            'amount' => ['required', 'numeric', 'min:0', 'max:100000'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:600'],
            'label' => ['nullable', 'string', 'max:80'],
        ]);
        if (! array_key_exists($data['unit'], InstructorRate::UNITS[$data['kind']])) {
            throw ValidationException::withMessages(['unit' => 'Cette unité ne convient pas à ce tarif.']);
        }
        // La durée ne sert qu'aux tarifs « par cours » des cours particuliers et collectifs.
        if ($data['unit'] !== 'session') {
            $data['duration_minutes'] = null;
        }
        $professional->rates()->create($data);

        return back()->with('success', 'Tarif ajouté : il s\'applique aux prochains cours faits avec '.$professional->fullName().'.');
    }

    public function destroy(Professional $professional, InstructorRate $rate)
    {
        $this->authorizeOrg($professional->organization, Perm::PROFESSIONALS_MANAGE);
        $rate->delete();

        return back()->with('success', 'Tarif supprimé.');
    }
}

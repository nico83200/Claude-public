<?php

namespace App\Http\Controllers;

use App\Models\Horse;
use App\Models\HorseOrigin;
use App\Models\HorseRelationship;
use App\Services\HorseAccess;
use App\Support\Perm;
use Illuminate\Http\Request;

class PedigreeController extends Controller
{
    public function show(Request $request, Horse $horse, HorseAccess $access)
    {
        $this->authorizeHorse($horse, Perm::HORSE_VIEW);
        $pedigree = $horse->pedigree()->with('related')->get()->keyBy('relation');
        // Lien vers la fiche d'un parent uniquement si l'utilisateur y a accès.
        $linkable = $pedigree->filter(fn ($r) => $r->related && $access->can($request->user(), $r->related, Perm::HORSE_VIEW))->keys()->all();

        return view('horses.pedigree', [
            'horse' => $horse->load('origin', 'breed'),
            'pedigree' => $pedigree,
            'linkable' => $linkable,
            'canEdit' => $this->horseCan($horse, Perm::HORSE_EDIT),
            'candidates' => $access->accessibleQuery($request->user())->where('id', '!=', $horse->id)->orderBy('official_name')->pluck('official_name', 'id'),
        ]);
    }

    public function update(Request $request, Horse $horse, HorseAccess $access)
    {
        $this->authorizeHorse($horse, Perm::HORSE_EDIT);
        $rules = ['studbook' => ['nullable', 'string', 'max:120'], 'bloodline' => ['nullable', 'string', 'max:255'], 'origin_notes' => ['nullable', 'string', 'max:5000']];
        foreach (array_keys(HorseRelationship::RELATIONS) as $rel) {
            $rules["rel.$rel.name"] = ['nullable', 'string', 'max:150'];
            $rules["rel.$rel.breed"] = ['nullable', 'string', 'max:100'];
            $rules["rel.$rel.reference"] = ['nullable', 'string', 'max:255'];
            $rules["rel.$rel.horse_id"] = ['nullable', 'integer'];
        }
        $data = $request->validate($rules);

        HorseOrigin::updateOrCreate(['horse_id' => $horse->id], ['studbook' => $data['studbook'] ?? null, 'bloodline' => $data['bloodline'] ?? null, 'notes' => $data['origin_notes'] ?? null]);

        foreach (array_keys(HorseRelationship::RELATIONS) as $rel) {
            $row = $data['rel'][$rel] ?? [];
            $relatedId = ! empty($row['horse_id']) ? (int) $row['horse_id'] : null;
            // On ne peut lier qu'un cheval auquel on a accès.
            if ($relatedId && ! $access->accessibleQuery($request->user())->whereKey($relatedId)->exists()) {
                $relatedId = null;
            }
            if (empty($row['name']) && ! $relatedId) {
                HorseRelationship::where('horse_id', $horse->id)->where('relation', $rel)->delete();

                continue;
            }
            HorseRelationship::updateOrCreate(['horse_id' => $horse->id, 'relation' => $rel], [
                'related_horse_id' => $relatedId,
                'related_name' => $row['name'] ?? Horse::find($relatedId)?->official_name,
                'related_breed' => $row['breed'] ?? null,
                'external_reference' => $row['reference'] ?? null,
            ]);
        }

        return redirect()->route('horses.pedigree', $horse)->with('success', 'Généalogie enregistrée.');
    }
}

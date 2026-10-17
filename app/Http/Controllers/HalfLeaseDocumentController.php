<?php

namespace App\Http\Controllers;

use App\Models\HalfLease;
use App\Models\HorseDocument;
use App\Notifications\AppNotification;
use App\Services\Audit;
use App\Support\Perm;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Documents d'une demi-pension : déposés et consultés par le propriétaire et la demi-pension, personne d'autre. */
class HalfLeaseDocumentController extends Controller
{
    public function store(Request $request, HalfLease $halfLease)
    {
        $user = $request->user();
        $manager = $this->party($halfLease);
        abort_unless($halfLease->isOpen() && ($manager || $halfLease->user_id === $user->id), 403);
        $org = $halfLease->horse->organization;
        if (! $this->entitlements()->hasFeature($org, 'documents')) {
            return back()->with('warning', 'Le dépôt de documents n\'est pas inclus dans l\'offre de l\'espace '.$org->name.'.');
        }
        $data = $request->validate([
            'file' => ['required', 'file', 'max:'.config('equine.uploads.max_kb'), 'mimes:'.implode(',', config('equine.uploads.mimes'))],
            'category' => ['required', Rule::in(array_keys(HorseDocument::HALF_LEASE_CATEGORIES))],
            'title' => ['nullable', 'string', 'max:150'],
            'valid_until' => ['nullable', 'date'],
        ]);
        $file = $request->file('file');
        if (! $this->entitlements()->canStore($org, $file->getSize())) {
            return back()->with('error', 'Espace de stockage de l\'offre insuffisant.');
        }
        $document = HorseDocumentController::storeFor($halfLease->horse, $file, [
            'category' => $data['category'], 'is_sensitive' => false,
            'title' => $data['title'] ?? HorseDocument::HALF_LEASE_CATEGORIES[$data['category']].' · '.$halfLease->riderName(),
        ], $user->id, $halfLease);
        $document->forceFill(['valid_until' => $data['valid_until'] ?? null])->save();
        Audit::log('half_lease.document_added', $document, ['category' => $data['category']], $org->id);
        // L'autre partie est prévenue (contrat déposé par le propriétaire, assurance par la demi-pension…).
        $other = $user->id === $halfLease->user_id ? $halfLease->creator : $halfLease->user;
        $other?->notify(new AppNotification('half_lease', 'Document de la demi-pension : '.HorseDocument::HALF_LEASE_CATEGORIES[$data['category']],
            $user->name.' a déposé « '.$document->title.' » sur la fiche de la demi-pension ('.$halfLease->horse->shortName().').', route('half-leases.show', $halfLease)));

        return back()->with('success', 'Document ajouté à la fiche de la demi-pension.');
    }

    public function show(Request $request, HalfLease $halfLease, HorseDocument $document)
    {
        abort_unless($this->party($halfLease) || $halfLease->isRider($request->user()), 404);

        return HorseDocumentController::serve($request, $document, $halfLease->horse->organization_id);
    }

    public function destroy(Request $request, HalfLease $halfLease, HorseDocument $document)
    {
        abort_unless($document->uploaded_by === $request->user()->id || $this->party($halfLease), 403);
        $document->delete();
        Audit::log('half_lease.document_deleted', $document, ['title' => $document->title], $halfLease->horse->organization_id);

        return back()->with('success', 'Document retiré de la fiche.');
    }

    /** Côté propriétaire : gestion du cheval. */
    private function party(HalfLease $lease): bool
    {
        return $this->horseCan($lease->horse, Perm::HORSE_MANAGE);
    }
}

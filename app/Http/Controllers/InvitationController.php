<?php

namespace App\Http\Controllers;

use App\Models\HalfLease;
use App\Models\Invitation;
use App\Services\InvitationService;
use App\Support\Fr;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    public function show(string $token, InvitationService $service)
    {
        $invitation = $service->findByToken($token);
        abort_unless($invitation, 404);
        session(['url.intended' => route('invitations.show', $token)]);

        return view('invitations.show', ['invitation' => $invitation->load(['horse', 'organization', 'role', 'inviter']), 'token' => $token]);
    }

    public function accept(Request $request, string $token, InvitationService $service)
    {
        $invitation = $service->findByToken($token);
        abort_unless($invitation, 404);
        $service->accept($invitation, $request->user());
        if ($lease = HalfLease::where('invitation_id', $invitation->id)->first()) {
            return $this->accepted($lease);
        }

        return $invitation->type === 'horse_access'
            ? redirect()->route('horses.show', $invitation->horse_id)->with('success', 'Accès activé.')
            : redirect()->route('dashboard')->with('success', 'Vous avez rejoint l\'espace '.$invitation->organization->name.'.');
    }

    /**
     * Réponse depuis le tableau de bord, sans le lien reçu par email (utile dans l'application
     * installée, dont la session est distincte du navigateur). Le service vérifie l'adresse.
     */
    public function respond(Request $request, Invitation $invitation, InvitationService $service)
    {
        abort_unless(strtolower($invitation->email) === strtolower($request->user()->email) && $request->user()->hasVerifiedEmail() && $invitation->isPending(), 404);
        if ($request->input('decision') === 'decline') {
            $service->decline($invitation);

            return back()->with('success', 'Invitation refusée.');
        }
        $service->accept($invitation, $request->user());
        if ($lease = HalfLease::where('invitation_id', $invitation->id)->first()) {
            return $this->accepted($lease);
        }
        // Depuis la page Partages : on y reste pour traiter les invitations suivantes.
        if ($request->boolean('stay')) {
            return back()->with('success', $invitation->type === 'horse_access' ? 'Accès à '.$invitation->horse?->shortName().' activé.' : 'Vous avez rejoint l\'espace '.$invitation->organization->name.'.');
        }

        return $invitation->type === 'horse_access'
            ? redirect()->route('horses.show', $invitation->horse_id)->with('success', 'Accès activé.')
            : redirect()->route('dashboard')->with('success', 'Vous avez rejoint l\'espace '.$invitation->organization->name.'.');
    }

    /** Toutes les invitations reçues en attente, acceptées d'un geste (page Partages). */
    public function acceptAll(Request $request, InvitationService $service)
    {
        $user = $request->user();
        abort_unless($user->hasVerifiedEmail(), 403);
        $accepted = 0;
        $leases = HalfLease::whereNotNull('invitation_id')->where('status', 'proposed')->pluck('invitation_id');
        foreach (Invitation::pending()->where('email', strtolower($user->email))->whereNotIn('id', $leases)->with(['inviter', 'organization', 'role'])->get() as $invitation) {
            $service->accept($invitation, $user);
            $accepted++;
        }

        return back()->with('success', $accepted ? Fr::count($accepted, 'invitation acceptée', 'invitations acceptées').'.' : 'Aucune invitation en attente.');
    }

    /** Demi-pension acceptée : retour sur sa fiche. */
    private function accepted(HalfLease $lease)
    {
        return redirect()->route('half-leases.show', $lease)->with('success', 'Demi-pension acceptée : vous avez accès au dossier de '.$lease->horse->shortName()
            .($lease->weekdays ? ', vos jours habituels ('.$lease->daysLabel().') '.($lease->starts_on->isFuture() ? 'commencent le '.$lease->starts_on->format('d/m/Y') : 'sont dans les jours de monte') : '').'.');
    }

    public function decline(Request $request, string $token, InvitationService $service)
    {
        $invitation = $service->findByToken($token);
        abort_unless($invitation && strtolower($invitation->email) === strtolower($request->user()->email), 404);
        $service->decline($invitation);

        return redirect()->route('dashboard')->with('success', 'Invitation refusée.');
    }
}

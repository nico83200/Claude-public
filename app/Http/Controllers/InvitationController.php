<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Services\InvitationService;
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
            $invitation->update(['revoked_at' => now()]);

            return back()->with('success', 'Invitation refusée.');
        }
        $service->accept($invitation, $request->user());

        return $invitation->type === 'horse_access'
            ? redirect()->route('horses.show', $invitation->horse_id)->with('success', 'Accès activé.')
            : redirect()->route('dashboard')->with('success', 'Vous avez rejoint l\'espace '.$invitation->organization->name.'.');
    }

    public function decline(Request $request, string $token, InvitationService $service)
    {
        $invitation = $service->findByToken($token);
        abort_unless($invitation && strtolower($invitation->email) === strtolower($request->user()->email), 404);
        $invitation->update(['revoked_at' => now()]);

        return redirect()->route('dashboard')->with('success', 'Invitation refusée.');
    }
}

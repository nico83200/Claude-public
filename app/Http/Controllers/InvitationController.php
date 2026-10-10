<?php

namespace App\Http\Controllers;

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

    public function decline(Request $request, string $token, InvitationService $service)
    {
        $invitation = $service->findByToken($token);
        abort_unless($invitation && strtolower($invitation->email) === strtolower($request->user()->email), 404);
        $invitation->update(['revoked_at' => now()]);

        return redirect()->route('dashboard')->with('success', 'Invitation refusée.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CalendarFeed;
use Illuminate\Http\Request;

/** Abonnement à l'agenda depuis Google Agenda / Calendrier Apple (adresse secrète, sans connexion). */
class CalendarFeedController extends Controller
{
    public function show(string $token, CalendarFeed $feed)
    {
        $user = strlen($token) >= 32 ? User::where('calendar_token', $token)->whereNull('suspended_at')->first() : null;
        abort_unless($user, 404);

        return response($feed->render($user), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="jackcie.ics"',
            'Cache-Control' => 'private, max-age=900',
        ]);
    }

    /** Nouvelle adresse : l'ancienne cesse de fonctionner (agenda partagé par erreur, téléphone perdu…). */
    public function regenerate(Request $request, CalendarFeed $feed)
    {
        abort_unless($feed->enabledFor($request->user()), 403);
        $feed->regenerate($request->user());

        return back()->with('success', 'Nouvelle adresse de synchronisation créée : l\'ancienne ne fonctionne plus. Ajoutez la nouvelle dans votre agenda.');
    }
}

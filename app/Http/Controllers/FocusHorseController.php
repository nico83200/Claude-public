<?php

namespace App\Http\Controllers;

use App\Services\HorseAccess;
use Illuminate\Http\Request;

/** Choix du cheval actif (plusieurs chevaux) : l'application s'organise autour de lui, ou de tous les chevaux. */
class FocusHorseController extends Controller
{
    public function __invoke(Request $request, HorseAccess $access)
    {
        $data = $request->validate(['horse' => ['required', 'regex:/^(all|\d+)$/']]);
        $data['horse'] = (string) $data['horse'];
        $user = $request->user();
        $horse = null;
        if ($data['horse'] !== 'all') {
            $horse = $access->accessibleHorses($user)->whereNull('archived_at')->firstWhere('id', (int) $data['horse']);
            abort_unless($horse, 404);
        }
        $access->setFocus($user, $horse);

        return redirect()->route('dashboard')->with('success', $horse ? 'Vous suivez '.$horse->shortName().'.' : 'Vous voyez tous vos chevaux.');
    }
}

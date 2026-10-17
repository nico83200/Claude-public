<?php

namespace App\Http\Controllers;

use App\Services\HorseAccess;
use App\Support\HorseSwitch;
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
        // Puces en haut d'une page (budget, santé, séances, agenda, dossier…) : on rouvre cette page pour le cheval choisi.
        $back = $request->validate(['back' => ['nullable', 'string', 'max:60'], 'query' => ['nullable', 'array']]);
        $url = filled($back['back'] ?? null) ? HorseSwitch::url($back['back'], $horse, collect($back['query'] ?? [])->filter(fn ($v) => is_scalar($v))->all()) : null;

        return ($url ? redirect()->to($url) : redirect()->route('dashboard'))->with('success', $horse ? 'Vous suivez '.$horse->shortName().'.' : 'Vous voyez tous vos chevaux.');
    }
}

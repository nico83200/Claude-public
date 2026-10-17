<?php

namespace App\Http\Controllers;

use App\Services\HorseAccess;
use App\Services\ToValidate;
use Illuminate\Http\Request;

/** Page « À valider » : tous les rendez-vous et séances passés à valider (l'accueil n'en montre que les premiers). */
class ToValidateController extends Controller
{
    public function __invoke(Request $request, HorseAccess $access, ToValidate $toValidate)
    {
        $user = $request->user();
        // Cheval choisi (?horse=, puces en haut de page), sinon le cheval actif, sinon tous les chevaux.
        $horse = $request->integer('horse') ? $access->accessibleHorses($user)->firstWhere('id', $request->integer('horse')) : null;
        $horse ??= $request->query('horse') === null ? $toValidate->scopeFor($user) : null;

        return view('todo.index', ['items' => $toValidate->items($user, $horse), 'horse' => $horse]);
    }
}

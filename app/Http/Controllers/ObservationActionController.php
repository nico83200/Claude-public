<?php

namespace App\Http\Controllers;

use App\Models\HealthObservation;
use App\Services\AttentionPoints;
use Illuminate\Http\Request;

/**
 * Boutons de la relance « Des nouvelles de … ? » (« Ça va mieux », « C'est rentré dans l'ordre »),
 * appelés par le service worker sans ouvrir l'application. Adresse signée, réservée au destinataire connecté.
 */
class ObservationActionController extends Controller
{
    public function __invoke(Request $request, HealthObservation $observation, string $action, AttentionPoints $points)
    {
        abort_unless((int) $request->query('user') === $request->user()->id, 403, 'Cette relance était destinée à une autre personne.');
        $horse = $observation->horse;
        abort_unless($horse && ! $horse->trashed() && $points->canClose($request->user(), $observation), 403);
        $url = route('horses.observations.show', [$horse, $observation]);

        if (! $observation->isOpen()) {
            return $this->reply($request, $horse->shortName().' : ce point d\'attention est déjà clos.', $url);
        }
        if ($action === 'clos') {
            $points->close($observation, $request->user());

            return $this->reply($request, $horse->shortName().' : point d\'attention clos. Bonne nouvelle !', $url);
        }
        $points->addUpdate($observation, $request->user(), 'better', null);

        return $this->reply($request, $horse->shortName().' : « ça va mieux » noté. On vous redemandera des nouvelles.', $url);
    }

    private function reply(Request $request, string $message, string $url)
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message, 'url' => $url])
            : redirect($url)->with('success', $message);
    }
}

<?php

namespace App\Support;

use App\Models\Horse;
use App\Services\HorseAccess;
use Illuminate\Support\Facades\Route;

/**
 * Puces « Tous · Cheval A · Cheval B… » en haut des pages quand on a plusieurs chevaux :
 * un toucher choisit le cheval actif et rouvre la même page pour ce cheval (ou pour tous).
 */
final class HorseSwitch
{
    /** Pages de liste : le cheval passe en paramètre (?horse= ; ?cheval= pour les onglets de la page Santé). */
    public const QUERY_ROUTES = ['budget.index' => 'horse', 'sessions.index' => 'horse', 'calendar.index' => 'horse', 'health.index' => 'cheval'];

    /** Pages d'un cheval : même page pour l'autre cheval ; « Tous » mène à la page commune correspondante. */
    public const HORSE_ROUTES = [
        'horses.show' => ['horses.index'], 'horses.care.index' => ['health.index'], 'horses.logs.index' => ['health.index', ['onglet' => 'suivi']],
        'horses.weight' => ['health.index', ['onglet' => 'poids']], 'horses.feeding' => ['health.index', ['onglet' => 'alimentation']],
        'horses.documents.index' => ['horses.index'], 'horses.professionals' => ['professionals.index'], 'horses.pedigree' => ['horses.index'],
    ];

    /** Données des puces pour la page en cours, ou null si elles n'ont pas lieu d'être. */
    public static function forCurrentPage(): ?array
    {
        $user = auth()->user();
        $route = Route::currentRouteName();
        if (! $user || ! $route || (! isset(self::QUERY_ROUTES[$route]) && ! isset(self::HORSE_ROUTES[$route]))) {
            return null;
        }
        // Santé › Alimentation, Suivi, Poids : un cheval est requis, la page a son propre choix (sans « Tous »).
        if ($route === 'health.index' && ! in_array(request()->query('onglet'), [null, '', 'sante'], true)) {
            return null;
        }
        $access = app(HorseAccess::class);
        if (! $access->hasSeveralHorses($user)) {
            return null;
        }
        $horses = $access->accessibleHorses($user)->whereNull('archived_at')->sortBy(fn (Horse $h) => mb_strtolower($h->shortName()))->values();

        return ['route' => $route, 'horses' => $horses, 'current' => self::currentHorseId($route, $access->focusHorse($user)?->id)];
    }

    private static function currentHorseId(string $route, ?int $focus): ?int
    {
        if (isset(self::HORSE_ROUTES[$route])) {
            $horse = request()->route('horse');

            return (int) ($horse instanceof Horse ? $horse->id : $horse);
        }
        $value = request()->query(self::QUERY_ROUTES[$route]);
        if ($route === 'health.index' && $value === null) {
            return $focus; // Santé : le dossier du cheval actif
        }

        return ctype_digit((string) $value) ? (int) $value : null;
    }

    /** Adresse de la même page pour un autre cheval (null : tous les chevaux), autres paramètres conservés. */
    public static function url(string $route, ?Horse $horse, array $query = []): ?string
    {
        if (isset(self::QUERY_ROUTES[$route])) {
            $key = self::QUERY_ROUTES[$route];
            unset($query['horse'], $query['cheval'], $query['page']);

            return route($route, $query + ($horse ? [$key => $horse->id] : []));
        }
        if (isset(self::HORSE_ROUTES[$route])) {
            [$all, $allQuery] = self::HORSE_ROUTES[$route] + [1 => []];

            return $horse ? route($route, $horse) : route($all, $allQuery);
        }

        return null;
    }
}

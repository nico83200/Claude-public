<?php

namespace App\Http\Controllers;

use App\Services\WeeklyDigest;
use Illuminate\Http\Request;

/** « Ma semaine » : le récapitulatif hebdomadaire, consultable à tout moment. */
class DigestController extends Controller
{
    public function __invoke(Request $request, WeeklyDigest $digests)
    {
        [$user, $digest] = $this->digest($request, $digests);
        $eligible = $digests->introEligible($user, $digest);
        $intro = $eligible ? $digests->cachedPageIntro($user, $digest) : null;

        return view('digest.show', [
            'digest' => $digest, 'empty' => $digests->isEmpty($digest), 'enabled' => $user->profile?->weekly_digest ?? true,
            // Introduction IA : affichée tout de suite si la semaine n'a pas changé, sinon rédigée après l'affichage de la page.
            'intro' => $intro, 'introPending' => $eligible && $intro === null,
        ]);
    }

    /** Introduction IA de la semaine (appelée par la page quand le contenu a changé depuis la dernière rédaction). */
    public function intro(Request $request, WeeklyDigest $digests)
    {
        [$user, $digest] = $this->digest($request, $digests);

        return response()->json(['text' => $digests->pageIntro($user, $digest)]);
    }

    private function digest(Request $request, WeeklyDigest $digests): array
    {
        $user = $request->user()->loadMissing(['profile', 'memberships.role', 'memberships.organization']);
        // En semaine, la période commence aujourd'hui ; le dimanche, elle couvre la semaine suivante.
        return [$user, $digests->for($user, now()->isSunday() ? now() : now()->subDay())];
    }
}

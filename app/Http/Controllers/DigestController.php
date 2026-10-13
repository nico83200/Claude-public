<?php

namespace App\Http\Controllers;

use App\Services\WeeklyDigest;
use Illuminate\Http\Request;

/** « Ma semaine » : le récapitulatif hebdomadaire, consultable à tout moment. */
class DigestController extends Controller
{
    public function __invoke(Request $request, WeeklyDigest $digests)
    {
        $user = $request->user()->loadMissing(['profile', 'memberships.role']);
        // En semaine, la période commence aujourd'hui ; le dimanche, elle couvre la semaine suivante.
        $digest = $digests->for($user, now()->isSunday() ? now() : now()->subDay());

        return view('digest.show', ['digest' => $digest, 'empty' => $digests->isEmpty($digest), 'enabled' => $user->profile?->weekly_digest ?? true]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\AcquisitionTracker;
use Illuminate\Http\Request;

/**
 * Manifeste de l'application installable. Construit pour chaque visiteur : après le scan d'un QR code
 * (code promo, source), l'application installée s'ouvre sur l'inscription avec le code et la source,
 * car elle ne partage pas toujours la session du navigateur (iPhone notamment).
 */
class ManifestController extends Controller
{
    public function __invoke(Request $request)
    {
        $manifest = json_decode((string) file_get_contents(resource_path('manifest.json')), true);
        if (! $request->user()) {
            $code = $request->session()->get('signup_code');
            $resume = AcquisitionTracker::resumeToken($request->session()->get('acquisition_visit'));
            $parrain = $request->session()->get('referral_code');
            if ($code || $resume || $parrain) {
                $manifest['start_url'] = '/register?'.http_build_query(array_filter(['code' => $code, 'suite' => $resume, 'parrain' => $parrain, 'source' => 'pwa']));
            }
        }

        return response()->json($manifest, 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'no-cache, private'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

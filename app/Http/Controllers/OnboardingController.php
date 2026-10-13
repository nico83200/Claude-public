<?php

namespace App\Http\Controllers;

use App\Models\UserProfile;
use Illuminate\Http\Request;

/** Démarrage guidé : application installée (détectée par le téléphone) et masquage de la liste. */
class OnboardingController extends Controller
{
    public function installed(Request $request)
    {
        $profile = UserProfile::firstOrCreate(['user_id' => $request->user()->id]);
        if (! $profile->app_installed_at) {
            $profile->forceFill(['app_installed_at' => now()])->save();
        }

        return response()->json(['ok' => true]);
    }

    public function dismiss(Request $request)
    {
        UserProfile::firstOrCreate(['user_id' => $request->user()->id])->forceFill(['onboarding_dismissed_at' => now()])->save();

        return back()->with('success', 'Démarrage masqué. Vous retrouverez chaque réglage dans le menu.');
    }
}

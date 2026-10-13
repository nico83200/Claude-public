<?php

namespace App\Http\Controllers;

use App\Models\UserProfile;
use Illuminate\Http\Request;

/** Démarrage guidé : masquage de la liste. */
class OnboardingController extends Controller
{
    public function dismiss(Request $request)
    {
        UserProfile::firstOrCreate(['user_id' => $request->user()->id])->forceFill(['onboarding_dismissed_at' => now()])->save();

        return back()->with('success', 'Démarrage masqué. Vous retrouverez chaque réglage dans le menu.');
    }
}

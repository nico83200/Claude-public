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

    /** « Ne plus afficher » la proposition de fiche d'urgence ; le message dit où la retrouver. */
    public function dismissEmergency(Request $request)
    {
        UserProfile::firstOrCreate(['user_id' => $request->user()->id])->forceFill(['emergency_cta_dismissed_at' => now()])->save();

        return back()->with('success', 'C\'est noté. Si vous changez d\'avis, la fiche d\'urgence se trouve dans le dossier de chaque cheval : menu ⋯ (en haut à droite) › « Fiche d\'urgence (QR code) ».');
    }
}

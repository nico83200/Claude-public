<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use App\Services\LegalInfo;

class PublicController extends Controller
{
    public function home()
    {
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }
        // Visiteur non connecté : le site vitrine présente l'application et mène à la connexion.
        // L'application installée ne passe pas par ici (elle s'ouvre sur /dashboard?source=pwa).
        if (filled(config('equine.site_url')) && request()->query('source') !== 'pwa') {
            return redirect()->away((string) config('equine.site_url'));
        }

        return view('public.home', ['plans' => $this->plans()]);
    }

    public function pricing()
    {
        return view('public.pricing', ['plans' => $this->plans()]);
    }

    public function privacy(LegalInfo $legal)
    {
        return view('public.privacy', ['legal' => $legal, 'support' => $legal->support()]);
    }

    public function terms(LegalInfo $legal)
    {
        return view('public.terms', ['legal' => $legal, 'support' => $legal->support()]);
    }

    private function plans()
    {
        return SubscriptionPlan::where('is_active', true)->where('is_public', true)->whereNull('archived_at')->orderBy('sort_order')->get();
    }
}

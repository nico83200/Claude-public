<?php

namespace App\Http\Controllers;

use App\Models\ApplicationSetting;
use App\Models\SubscriptionPlan;

class PublicController extends Controller
{
    public function home()
    {
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }

        return view('public.home', ['plans' => $this->plans()]);
    }

    public function pricing()
    {
        return view('public.pricing', ['plans' => $this->plans()]);
    }

    public function privacy()
    {
        return view('public.privacy', ['support' => ApplicationSetting::get('support_email', config('mail.from.address'))]);
    }

    public function terms()
    {
        return view('public.terms', ['support' => ApplicationSetting::get('support_email', config('mail.from.address'))]);
    }

    private function plans()
    {
        return SubscriptionPlan::where('is_active', true)->where('is_public', true)->whereNull('archived_at')->orderBy('sort_order')->get();
    }
}

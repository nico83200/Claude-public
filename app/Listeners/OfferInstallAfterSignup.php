<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Registered;

/** Compte créé (e-mail, Google ou Apple) : l'installation de l'application sera proposée sur téléphone et tablette. */
class OfferInstallAfterSignup
{
    public function handle(Registered $event): void
    {
        if (request()->hasSession()) {
            request()->session()->put('offer_install', true);
        }
    }
}

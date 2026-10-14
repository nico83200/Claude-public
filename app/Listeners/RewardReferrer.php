<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Auth\Events\Verified;

/** Filleul qui confirme son adresse e-mail : le parrain reçoit ses jours offerts. */
class RewardReferrer
{
    public function handle(Verified $event): void
    {
        if ($event->user instanceof User) {
            app(ReferralService::class)->reward($event->user);
        }
    }
}

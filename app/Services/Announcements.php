<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\License;
use App\Models\Organization;
use App\Models\PushSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Support\Collection;

/**
 * Messages du super-administrateur : à tous les utilisateurs, ou selon l'offre de leurs espaces
 * (un espace sans offre payante active compte dans l'offre gratuite par défaut).
 * Envoi en notification dans l'application et sur le téléphone (si activé), et par e-mail si demandé
 * et accepté par l'utilisateur.
 */
class Announcements
{
    private array $planOf = [];

    public function __construct(private Entitlements $entitlements) {}

    /** @param  int[]|null  $planIds  null = tout le monde */
    public function recipients(?array $planIds): Collection
    {
        $query = User::whereNull('suspended_at')->whereNotNull('email_verified_at');
        if (! $planIds) {
            return $query->get();
        }
        $free = SubscriptionPlan::where('is_default_free', true)->value('id');

        return $query->with('memberships')->get()->filter(function (User $user) use ($planIds, $free) {
            return $user->memberships->contains(fn ($m) => in_array($this->planFor($m->organization_id, $free), $planIds, true));
        })->values();
    }

    private function planFor(int $orgId, ?int $free): ?int
    {
        if (! array_key_exists($orgId, $this->planOf)) {
            $org = Organization::find($orgId);
            $license = $org ? $this->entitlements->currentLicense($org) : null;
            $this->planOf[$orgId] = $license && in_array($license->status, License::WRITABLE, true) && (! $license->ends_at || $license->ends_at->isFuture() || $license->status === 'grace')
                ? (int) $license->subscription_plan_id : $free;
        }

        return $this->planOf[$orgId];
    }

    public function send(Announcement $announcement): int
    {
        $users = $this->recipients($announcement->plan_ids);
        $start = now()->subSecond();
        foreach ($users as $user) {
            $user->notify(new AppNotification('announcement', $announcement->title, $announcement->body, $announcement->url, $announcement->with_email));
        }
        $announcement->update([
            'recipients_count' => $users->count(),
            // Téléphones et ordinateurs effectivement atteints par la notification push.
            'devices_reached' => PushSubscription::whereIn('user_id', $users->pluck('id'))->where('last_success_at', '>=', $start)->count(),
        ]);

        return $users->count();
    }
}

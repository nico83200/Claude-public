<?php

namespace App\Services;

use App\Models\CareRecord;
use App\Models\HorseAccessGrant;
use App\Models\Invitation;
use App\Models\OrganizationMember;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\Perm;
use Illuminate\Support\Collection;

/**
 * Démarrage guidé : quelques étapes qui se cochent d'elles-mêmes au fil des actions.
 * Une étape impossible avec les droits de l'utilisateur (cheval partagé en lecture…) n'est pas proposée.
 */
class Onboarding
{
    public function __construct(private HorseAccess $access) {}

    /**
     * @return array<int, array{key: string, title: string, text: string, done: bool, url: string|null, action: string|null}>|null
     *         null quand tout est fait ou que l'utilisateur a masqué le démarrage
     */
    public function steps(User $user, Collection $horses): ?array
    {
        $user->loadMissing('profile');
        if ($user->profile?->onboarding_dismissed_at) {
            return null;
        }
        $first = $horses->first();
        $healthEdit = $horses->filter(fn ($h) => $this->access->can($user, $h, Perm::HEALTH_EDIT));
        $manage = $horses->filter(fn ($h) => $this->access->can($user, $h, Perm::HORSE_MANAGE));
        $keys = fn ($ids) => CareRecord::whereIn('horse_id', $ids ?: [0])->join('care_categories', 'care_categories.id', '=', 'care_records.care_category_id')
            ->whereIn('care_categories.key', ['deworming', 'farrier'])->distinct()->pluck('care_categories.key')->all();

        $steps = [[
            'key' => 'horse', 'title' => 'Ajouter votre cheval', 'text' => 'Sa fiche réunit santé, séances, agenda et budget.',
            'done' => $horses->isNotEmpty(), 'url' => route('horses.create'), 'action' => null,
        ]];
        if ($horses->isEmpty() || $healthEdit->isNotEmpty()) {
            $found = $keys($healthEdit->pluck('id')->all());
            $target = $healthEdit->first() ?? $first;
            $steps[] = [
                'key' => 'health', 'title' => 'Noter le dernier vermifuge et le dernier parage',
                'text' => 'Les prochains rappels se calculent tout seuls'.(count($found) === 1 ? ' — encore '.(in_array('deworming', $found, true) ? 'le parage.' : 'le vermifuge.') : '.'),
                'done' => count($found) === 2, 'url' => $target ? route('horses.care.create', $target) : null, 'action' => null,
            ];
        }
        $steps[] = [
            'key' => 'notifications', 'title' => 'Activer les notifications', 'text' => 'Rendez-vous, soins à prévoir et récapitulatif du dimanche sur votre téléphone.',
            'done' => PushSubscription::where('user_id', $user->id)->exists(), 'url' => route('settings.notifications'), 'action' => null,
        ];
        if ($horses->isEmpty() || $manage->isNotEmpty()) {
            $shared = Invitation::where('invited_by', $user->id)->exists()
                || HorseAccessGrant::whereIn('horse_id', $manage->pluck('id')->all() ?: [0])->where('user_id', '!=', $user->id)->exists()
                || OrganizationMember::whereIn('organization_id', $user->memberships->pluck('organization_id'))->where('user_id', '!=', $user->id)->exists();
            $steps[] = [
                'key' => 'share', 'title' => 'Inviter votre cavalier ou votre pension', 'text' => 'Chacun note ses séances et ses soins, tout est au même endroit.',
                'done' => $shared, 'url' => $first ? route('horses.sharing', $first) : route('sharing.index'), 'action' => null,
            ];
        }
        $steps[] = [
            'key' => 'install', 'title' => 'Installer l\'application sur votre téléphone', 'text' => 'Une icône sur l\'écran d\'accueil, comme une vraie application.',
            'done' => (bool) $user->profile?->app_installed_at, 'url' => null, 'action' => 'install',
        ];

        return collect($steps)->every('done') ? null : $steps;
    }
}

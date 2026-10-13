<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\CareRecord;
use App\Models\PushSubscription;
use App\Models\RidingSession;
use App\Models\User;
use App\Support\Perm;
use Illuminate\Support\Collection;

/**
 * Démarrage guidé : quelques étapes utiles à tous, qui se cochent d'elles-mêmes au fil des actions.
 * Une étape impossible avec les droits de l'utilisateur (cheval partagé en lecture…) n'est pas proposée.
 */
class Onboarding
{
    public function __construct(private HorseAccess $access) {}

    /**
     * @return array<int, array{key: string, title: string, text: string, done: bool, url: string|null, button: string}>|null
     *         null quand tout est fait ou que l'utilisateur a masqué le démarrage
     */
    public function steps(User $user, Collection $horses): ?array
    {
        $user->loadMissing('profile');
        if ($user->profile?->onboarding_dismissed_at) {
            return null;
        }
        $healthEdit = $horses->filter(fn ($h) => $this->access->can($user, $h, Perm::HEALTH_EDIT));
        $planning = $horses->filter(fn ($h) => $this->access->can($user, $h, Perm::CALENDAR_EDIT) || $this->access->can($user, $h, Perm::SESSIONS_CREATE));

        $steps = [[
            'key' => 'horse', 'title' => 'Ajouter votre cheval', 'text' => 'Sa fiche réunit santé, séances, agenda et budget.',
            'done' => $horses->isNotEmpty(), 'url' => route('horses.create'), 'button' => 'Ajouter',
        ]];
        if ($horses->isEmpty() || $healthEdit->isNotEmpty()) {
            $target = $healthEdit->first();
            $steps[] = [
                'key' => 'care', 'title' => 'Noter votre premier soin', 'text' => 'Vétérinaire, maréchal, vermifuge… les prochains rappels se calculent tout seuls.',
                'done' => CareRecord::whereIn('horse_id', $healthEdit->pluck('id')->all() ?: [0])->exists(),
                'url' => $target ? route('horses.care.create', $target) : null, 'button' => 'Noter',
            ];
        }
        if ($horses->isEmpty() || $planning->isNotEmpty()) {
            $ids = $planning->pluck('id')->all() ?: [0];
            $steps[] = [
                'key' => 'agenda', 'title' => 'Programmer une séance ou un rendez-vous', 'text' => 'Dans l\'agenda, avec un rappel avant et le bilan après.',
                'done' => CalendarEvent::whereIn('horse_id', $ids)->where('created_by', $user->id)
                    // Événements créés automatiquement (soin, traitement, pesée…) exclus : seulement ce que l'utilisateur a programmé.
                    ->whereNull('care_record_id')->whereNull('treatment_id')->whereNull('source_type')->exists()
                    || RidingSession::whereIn('horse_id', $ids)->where('created_by', $user->id)->exists(),
                'url' => route('calendar.index'), 'button' => 'Programmer',
            ];
        }
        $steps[] = [
            'key' => 'notifications', 'title' => 'Activer les notifications', 'text' => 'Rendez-vous, soins à prévoir et récapitulatif du dimanche sur votre téléphone.',
            'done' => PushSubscription::where('user_id', $user->id)->exists(), 'url' => route('settings.notifications'), 'button' => 'Activer',
        ];
        $steps[] = [
            'key' => 'profile', 'title' => 'Compléter votre profil', 'text' => 'Votre niveau (galop) pour des exercices adaptés, et votre téléphone pour la fiche d\'urgence.',
            'done' => filled($user->profile?->riding_level) && filled($user->profile?->phone), 'url' => route('settings.profile'), 'button' => 'Compléter',
        ];

        return collect($steps)->every('done') ? null : $steps;
    }
}

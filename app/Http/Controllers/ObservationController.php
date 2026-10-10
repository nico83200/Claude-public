<?php

namespace App\Http\Controllers;

use App\Models\HealthObservation;
use App\Models\Horse;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\HorseAccess;
use App\Support\Perm;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Signalements / observations (accessible aux cavaliers autorisés à commenter). */
class ObservationController extends Controller
{
    public function store(Request $request, Horse $horse, HorseAccess $access)
    {
        $this->authorizeHorse($horse, Perm::COMMENTS_CREATE);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'severity' => ['required', Rule::in(array_keys(HealthObservation::SEVERITIES))],
        ]);
        $obs = HealthObservation::create($data + ['horse_id' => $horse->id, 'author_id' => $request->user()->id, 'observed_at' => now()]);

        if ($obs->severity !== 'info') {
            self::notifyManagers($horse, $request->user(), $access, 'Observation : '.$horse->shortName(), HealthObservation::SEVERITIES[$obs->severity].' signalée par '.$request->user()->name.'.');
        }

        return back()->with('success', 'Observation enregistrée.');
    }

    public function resolve(Horse $horse, HealthObservation $observation)
    {
        $this->authorizeHorse($horse, Perm::HEALTH_EDIT);
        $observation->update(['resolved_at' => now()]);

        return back()->with('success', 'Observation marquée comme traitée.');
    }

    /** Prévient les personnes qui gèrent la santé du cheval (sans divulguer le contenu à d'autres). */
    public static function notifyManagers(Horse $horse, $author, HorseAccess $access, string $title, string $body): void
    {
        $candidates = $horse->organization->users()->get()
            ->merge(User::whereIn('id', $horse->ownerships()->whereNotNull('user_id')->pluck('user_id'))->get())
            ->unique('id')->reject(fn ($u) => $u->id === $author->id);
        foreach ($candidates as $user) {
            if (in_array(Perm::HEALTH_VIEW, $access->permissions($user, $horse), true)) {
                $user->notify(new AppNotification('observation', $title, $body, route('horses.care.index', $horse)));
            }
        }
    }
}

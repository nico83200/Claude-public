<?php

namespace App\Services;

use App\Http\Controllers\ObservationController;
use App\Models\DailyLog;
use App\Models\HealthObservation;
use App\Models\HealthObservationPhoto;
use App\Models\HealthObservationUpdate;
use App\Models\Horse;
use App\Models\Professional;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Support\Perm;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Points d'attention : ce qu'on remarque d'inhabituel sur un cheval (saignement, boiterie, plaie…),
 * suivi comme un petit dossier : nouvelles successives, photos, vétérinaire à portée de main,
 * relance « Des nouvelles ? » tant que ce n'est pas clos.
 */
class AttentionPoints
{
    /** Relance après ce nombre d'heures, selon l'attention demandée. */
    public const FOLLOW_UP_HOURS = ['alert' => 6, 'watch' => 24, 'info' => 72];

    /** Sans réponse après autant de relances, on n'insiste plus (le dossier reste ouvert). */
    public const MAX_FOLLOW_UPS = 4;

    public const MAX_PHOTOS = 6;

    public function __construct(private HorseAccess $access, private ImageProcessor $images) {}

    /**
     * Prochaine relance : jamais la nuit (21 h – 8 h), reportée au matin à 8 h 30.
     */
    public static function nextFollowUp(string $severity, ?Carbon $from = null): Carbon
    {
        $hours = self::FOLLOW_UP_HOURS[$severity] ?? 24;
        // En jours entiers, l'heure du lendemain reste la même (même au changement d'heure).
        $at = $hours % 24 === 0 ? ($from ?? now())->copy()->addDays(intdiv($hours, 24)) : ($from ?? now())->copy()->addHours($hours);
        if ($at->hour >= 21) {
            $at = $at->copy()->addDay()->setTime(8, 30);
        } elseif ($at->hour < 8) {
            $at = $at->copy()->setTime(8, 30);
        }

        return $at;
    }

    /** @param UploadedFile[] $photos */
    public function open(Horse $horse, User $author, array $data, array $photos = []): HealthObservation
    {
        $point = DB::transaction(function () use ($horse, $author, $data, $photos) {
            $point = HealthObservation::create([
                'horse_id' => $horse->id,
                'author_id' => $author->id,
                'observed_at' => now(),
                'severity' => $data['severity'],
                'title' => $data['title'] ?? null,
                'body' => $data['body'],
                'follow_up_at' => self::nextFollowUp($data['severity']),
            ]);
            $this->storePhotos($point, $author, $photos);
            $this->noteInDailyLog($point, $author, 'anomalies', ($point->title ? $point->title.' : ' : '').Str::limit($point->body, 300), $point->severity === 'info' ? null : 'average');

            return $point;
        });
        ObservationController::notifyManagers($horse, $author, $this->access, 'Point d\'attention : '.$horse->shortName(),
            $author->name.' a noté : '.$point->label().'. Le dossier est ouvert dans l\'appli, avec le contact du vétérinaire.', route('horses.observations.show', [$horse, $point]));

        return $point;
    }

    /** @param UploadedFile[] $photos */
    public function addUpdate(HealthObservation $point, User $author, ?string $trend, ?string $body, array $photos = []): HealthObservationUpdate
    {
        $update = DB::transaction(function () use ($point, $author, $trend, $body, $photos) {
            $update = $point->updates()->create(['author_id' => $author->id, 'trend' => $trend, 'body' => $body]);
            $this->storePhotos($point, $author, $photos, $update);
            // Une nouvelle relance la conversation : prochaine question plus tard, compteur remis à zéro.
            $point->update(['follow_up_at' => self::nextFollowUp($trend === 'worse' ? 'alert' : $point->severity), 'follow_up_count' => 0]);
            $news = collect([$trend ? mb_strtolower(HealthObservation::TRENDS[$trend]) : null, $body ? Str::limit($body, 300) : null])->filter()->implode(' – ');
            $this->noteInDailyLog($point, $author, 'anomalies', $point->label().($news ? ' : '.$news : ' : nouvelle photo'), $trend === 'worse' ? 'average' : null);

            return $update;
        });
        $what = $trend ? HealthObservation::TRENDS[$trend] : 'Nouvelle note';
        ObservationController::notifyManagers($point->horse, $author, $this->access, $point->horse->shortName().' : '.$point->label(),
            $what.($body ? ' – '.Str::limit($body, 120) : '').' ('.$author->name.').', route('horses.observations.show', [$point->horse, $point]));

        return $update;
    }

    public function close(HealthObservation $point, User $by, ?string $note = null): void
    {
        $point->update(['resolved_at' => now(), 'resolved_by' => $by->id, 'resolution_note' => $note ?: null, 'follow_up_at' => null]);
        $this->noteInDailyLog($point, $by, 'observations', $point->label().' : rentré dans l\'ordre'.($note ? ' – '.Str::limit($note, 300) : ''));
        ObservationController::notifyManagers($point->horse, $by, $this->access, $point->horse->shortName().' : point d\'attention clos',
            $point->label().' : rentré dans l\'ordre selon '.$by->name.'.', route('horses.observations.show', [$point->horse, $point]));
    }

    /**
     * Suivi du jour du cheval complété par le point d'attention : créé s'il n'existe pas encore,
     * sinon la ligne est ajoutée (sans doublon) et l'état général « bon » passe à « moyen » si besoin
     * (jamais remonté, jamais « mauvais » d'office : c'est à la personne d'en juger).
     */
    public function noteInDailyLog(HealthObservation $point, User $by, string $field, string $line, ?string $state = null): DailyLog
    {
        $log = DailyLog::where('horse_id', $point->horse_id)->whereBetween('logged_at', [today(), today()->endOfDay()])
            ->orderByRaw('health_observation_id = ? desc', [$point->id])->latest('logged_at')->first();
        if (! $log) {
            return DailyLog::create(['horse_id' => $point->horse_id, 'author_id' => $by->id, 'logged_at' => now(),
                'general_state' => $state, $field => $line, 'health_observation_id' => $point->id]);
        }
        $current = (string) $log->{$field};
        $log->{$field} = str_contains($current, $line) ? $current : trim($current."\n".$line);
        if ($state === 'average' && in_array($log->general_state, [null, 'good'], true)) {
            $log->general_state = 'average';
        }
        $log->health_observation_id = $point->id;
        $log->save();

        return $log;
    }

    public function reopen(HealthObservation $point): void
    {
        $point->update(['resolved_at' => null, 'resolved_by' => null, 'follow_up_at' => self::nextFollowUp($point->severity), 'follow_up_count' => 0]);
    }

    /** @param UploadedFile[] $photos */
    private function storePhotos(HealthObservation $point, User $by, array $photos, ?HealthObservationUpdate $update = null): void
    {
        foreach (array_slice($photos, 0, self::MAX_PHOTOS) as $file) {
            $stored = $this->images->store($file, 'horses/'.$point->horse_id.'/points-attention');
            HealthObservationPhoto::create([
                'health_observation_id' => $point->id,
                'health_observation_update_id' => $update?->id,
                'uploaded_by' => $by->id,
                'path' => $stored['path'],
                'thumb_path' => $stored['thumb'],
                'size_bytes' => $stored['size'],
            ]);
        }
    }

    /**
     * Vétérinaire à joindre : celui du cheval (le principal d'abord), sinon le premier vétérinaire des intervenants.
     *
     * @return array{professional: Professional, source: string}|null
     */
    public function vetFor(Horse $horse): ?array
    {
        $own = $horse->professionals()->where('kind', 'veterinarian')->whereNull('archived_at')
            ->orderByDesc('horse_professionals.is_primary')->orderBy('last_name')->first();
        if ($own) {
            return ['professional' => $own, 'source' => 'horse'];
        }
        $first = Professional::where('organization_id', $horse->organization_id)->where('kind', 'veterinarian')->whereNull('archived_at')
            ->orderBy('last_name')->orderBy('first_name')->first();

        return $first ? ['professional' => $first, 'source' => 'list'] : null;
    }

    /** Qui peut lire le dossier : la santé du cheval, ou la personne qui l'a ouvert. */
    public function canView(User $user, HealthObservation $point): bool
    {
        return $this->access->can($user, $point->horse, Perm::HEALTH_VIEW)
            || ($point->author_id === $user->id && $this->access->can($user, $point->horse, Perm::HORSE_VIEW));
    }

    /** Qui peut clore ou rouvrir : la personne qui l'a ouvert, ou qui gère la santé du cheval. */
    public function canClose(User $user, HealthObservation $point): bool
    {
        return $this->access->can($user, $point->horse, Perm::HEALTH_EDIT)
            || ($point->author_id === $user->id && $this->access->can($user, $point->horse, Perm::COMMENTS_CREATE));
    }

    /** Boutons de la relance sur le téléphone (adresses signées, réservées au destinataire). */
    public function notificationActions(HealthObservation $point, User $user): array
    {
        $params = ['observation' => $point->id, 'user' => $user->id];

        return [
            ['action' => 'better', 'title' => 'Ça va mieux', 'url' => URL::temporarySignedRoute('observations.quick', now()->addDays(7), $params + ['action' => 'mieux'])],
            ['action' => 'close', 'title' => 'C\'est rentré dans l\'ordre', 'url' => URL::temporarySignedRoute('observations.quick', now()->addDays(7), $params + ['action' => 'clos'])],
        ];
    }

    /** Relances dues : « Des nouvelles de … ? » à la personne qui a ouvert le dossier. */
    public function sendFollowUps(): int
    {
        $sent = 0;
        $due = HealthObservation::with(['horse', 'author'])->whereNull('resolved_at')->whereNotNull('follow_up_at')->where('follow_up_at', '<=', now())->get();
        foreach ($due as $point) {
            $horse = $point->horse;
            if (! $horse || $horse->trashed() || $horse->archived_at) {
                $point->update(['follow_up_at' => null]);

                continue;
            }
            $recipients = collect([$point->author])->filter(fn ($u) => $u && $this->canView($u, $point));
            if ($recipients->isEmpty()) {
                // Personne qui a ouvert le dossier partie : on demande à qui gère la santé du cheval.
                $recipients = $horse->organization->users()->get()->filter(fn ($u) => $this->access->can($u, $horse, Perm::HEALTH_EDIT));
            }
            $count = $point->follow_up_count + 1;
            foreach ($recipients as $user) {
                $user->notify(new AppNotification('attention_follow_up', 'Des nouvelles de '.$horse->shortName().' ?',
                    $point->label().' : comment ça évolue ? Donnez des nouvelles en un geste, ou clôturez si tout est rentré dans l\'ordre.',
                    route('horses.observations.show', [$horse, $point]), actions: $this->canClose($user, $point) ? $this->notificationActions($point, $user) : []));
                $sent++;
            }
            $point->update([
                'follow_up_count' => $count,
                'follow_up_at' => $count >= self::MAX_FOLLOW_UPS ? null : self::nextFollowUp($point->severity === 'alert' ? 'watch' : $point->severity),
            ]);
        }

        return $sent;
    }
}

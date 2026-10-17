<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Statut affiché d'une séance ou d'un rendez-vous, d'après l'heure et la durée :
 * prévu (à venir), en cours (entre le début et la fin), à valider (fin passée sans validation).
 * Un statut enregistré « réalisé / terminée » ou « annulé » l'emporte toujours.
 */
final class TimeStatus
{
    /** Séance démarrée en mode séance : reste « en cours » jusqu'à une heure après la fin prévue (départ en retard, séance plus longue). */
    public const LIVE_GRACE_MINUTES = 60;

    /** Statuts qui évoluent avec l'heure (mis à jour sur la page ouverte, voir time-status.js). */
    public const MOVING = ['planned', 'confirmed', 'ongoing'];

    public const COLORS = ['planned' => 'brand', 'confirmed' => 'brand', 'ongoing' => 'blue', 'to_validate' => 'amber', 'done' => 'green', 'cancelled' => 'slate'];

    private function __construct(public readonly string $key, public readonly string $label, public readonly string $color,
        public readonly Carbon $start, public readonly Carbon $end, public readonly bool $live, private readonly bool $feminine) {}

    /**
     * @param  string  $stored  statut enregistré (planned, confirmed, in_progress, done, completed, cancelled)
     * @param  bool  $feminine  « séance » (Prévue, Terminée) ou « rendez-vous » (Prévu, Réalisé)
     */
    public static function resolve(string $stored, Carbon $start, Carbon $end, bool $feminine = false, ?Carbon $now = null): self
    {
        $now ??= now();
        $live = $stored === 'in_progress';
        $key = match (true) {
            $stored === 'cancelled' => 'cancelled',
            in_array($stored, ['done', 'completed'], true) => 'done',
            $live => $now->lt($end->copy()->addMinutes(self::LIVE_GRACE_MINUTES)) ? 'ongoing' : 'to_validate',
            $now->lt($start) => $stored === 'confirmed' ? 'confirmed' : 'planned',
            $now->lt($end) => 'ongoing',
            default => 'to_validate',
        };

        return new self($key, self::labels($feminine)[$key], self::COLORS[$key], $start, $end, $live, $feminine);
    }

    public static function labels(bool $feminine): array
    {
        $e = $feminine ? 'e' : '';

        return ['planned' => 'Prévu'.$e, 'confirmed' => 'Confirmé'.$e, 'ongoing' => 'En cours', 'to_validate' => 'À valider',
            'done' => $feminine ? 'Terminée' : 'Réalisé', 'cancelled' => 'Annulé'.$e];
    }

    public function is(string ...$keys): bool
    {
        return in_array($this->key, $keys, true);
    }

    /** Données du badge pour la mise à jour sur la page ouverte (null si le statut ne bouge plus). */
    public function clientData(): ?array
    {
        if (! $this->is(...self::MOVING)) {
            return null;
        }
        $labels = self::labels($this->feminine);

        return [
            'start' => $this->start->getTimestampMs(), 'end' => $this->end->getTimestampMs(),
            'grace' => $this->live ? self::LIVE_GRACE_MINUTES * 60000 : 0, 'live' => $this->live, 'confirmed' => $this->key === 'confirmed',
            'states' => collect(['planned', 'confirmed', 'ongoing', 'to_validate'])->mapWithKeys(fn ($k) => [$k => [$labels[$k], Badge::classes(self::COLORS[$k])]])->all(),
        ];
    }
}

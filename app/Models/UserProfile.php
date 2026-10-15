<?php

namespace App\Models;

use App\Casts\PhoneNumber;
use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['phone' => PhoneNumber::class, 'weekly_digest' => 'boolean', 'weekly_digest_sent_on' => 'date', 'app_installed_at' => 'datetime', 'onboarding_dismissed_at' => 'datetime', 'emergency_cta_dismissed_at' => 'datetime'];

    /** Niveaux proposés (galops de la FFE). */
    public const GALOPS = [1 => 'Galop 1', 2 => 'Galop 2', 3 => 'Galop 3', 4 => 'Galop 4', 5 => 'Galop 5', 6 => 'Galop 6', 7 => 'Galop 7'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Galop lu dans le niveau saisi (« Galop 4 », « G4 », « 4 »…), ou null. */
    public function galop(): ?int
    {
        return self::parseGalop($this->riding_level);
    }

    public static function parseGalop(?string $level): ?int
    {
        if (! $level || ! preg_match('/^\s*(?:galop|g)?\s*(\d{1,2})\b/iu', $level, $m)) {
            return null;
        }
        $n = (int) $m[1];

        return $n >= 1 && $n <= 7 ? $n : null;
    }

    /** Niveau d'exercice correspondant : galops 1 à 3 débutant, 4 et 5 intermédiaire, 6 et plus confirmé (expert). */
    public static function exerciseLevel(?int $galop): ?string
    {
        return match (true) {
            $galop === null => null,
            $galop <= 3 => 'beginner',
            $galop <= 5 => 'intermediate',
            default => 'advanced',
        };
    }
}

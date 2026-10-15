<?php

namespace App\Models;

use App\Models\Concerns\HasClientUuid;
use App\Models\Concerns\Versioned;
use Illuminate\Database\Eloquent\Model;

class Exercise extends Model
{
    use HasClientUuid, Versioned;

    protected $table = 'exercise_library';

    protected $guarded = ['id', 'uuid', 'scope', 'user_id', 'organization_id', 'version', 'seed_key', 'media_path', 'media_type', 'media_size'];

    /** Valeurs par défaut (identiques à la base) disponibles dès la création. */
    protected $attributes = ['version' => 1, 'level' => 'all'];

    protected $casts = ['steps' => 'array', 'archived_at' => 'datetime'];

    public const LEVELS = ['all' => 'Tous niveaux', 'beginner' => 'Débutant', 'intermediate' => 'Intermédiaire', 'advanced' => 'Confirmé'];

    public const SCOPES = ['default' => 'Bibliothèque par défaut', 'personal' => 'Personnel', 'organization' => 'Partagé dans l\'espace'];

    /** Démonstration : formats acceptés (extension => type MIME) et taille maximale. */
    public const MEDIA_MIMES = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'mp4' => 'video/mp4', 'm4v' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime'];

    public const MEDIA_MAX_MB = ['image' => 15, 'video' => 200];

    /** Champs figés dans la séance au moment de l'ajout. */
    public const SNAPSHOT_FIELDS = ['name', 'description', 'discipline', 'objective', 'level', 'equipment', 'instructions', 'steps', 'duration_minutes', 'repetitions', 'vigilance', 'success_criteria'];

    public function category()
    {
        return $this->belongsTo(ExerciseCategory::class, 'exercise_category_id');
    }

    public function tags()
    {
        return $this->belongsToMany(ExerciseTag::class, 'exercise_tag_assignments', 'exercise_id', 'exercise_tag_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /** Exercices visibles par un utilisateur : par défaut + personnels + partagés dans ses espaces. */
    public function scopeVisibleTo($q, User $user)
    {
        $orgIds = $user->relationLoaded('memberships') ? $user->memberships->pluck('organization_id') : $user->memberships()->pluck('organization_id');

        return $q->where(function ($w) use ($user, $orgIds) {
            $w->where('scope', 'default')
                ->orWhere(fn ($p) => $p->where('scope', 'personal')->where('user_id', $user->id))
                ->orWhere(fn ($o) => $o->where('scope', 'organization')->whereIn('organization_id', $orgIds));
        });
    }

    public static function mediaKind(string $extension): ?string
    {
        $mime = self::MEDIA_MIMES[strtolower($extension)] ?? null;

        return $mime ? strtok($mime, '/') : null;
    }

    public function mediaSrc(): ?string
    {
        return $this->media_path ? route('exercises.media', $this).'?v='.substr(md5($this->media_path), 0, 8) : null;
    }

    public function snapshot(): array
    {
        return array_merge($this->only(self::SNAPSHOT_FIELDS), [
            'category' => $this->category?->name,
            'version' => $this->version,
        ]);
    }
}

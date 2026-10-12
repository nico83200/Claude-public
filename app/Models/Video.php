<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Vidéo affichée automatiquement (tutoriel de première connexion ou nouveautés). */
class Video extends Model
{
    use SoftDeletes;

    protected $guarded = ['id', 'created_by', 'source'];

    protected $casts = ['published_at' => 'datetime'];

    public const DEVICES = ['all' => 'Tous les appareils', 'mobile' => 'Téléphone et tablette', 'desktop' => 'Ordinateur'];

    public const KINDS = ['onboarding' => 'Première connexion (nouveaux comptes)', 'announcement' => 'Nouveautés (comptes existants, sur la page d\'accueil)'];

    /** Fichiers d'une vidéo : type => [colonne, extensions acceptées, taille max (Mo)]. */
    public const FILES = [
        'video' => ['video_path', ['mp4', 'webm', 'mov', 'm4v'], 500],
        'audio' => ['audio_path', ['mp3', 'm4a', 'aac', 'wav', 'ogg', 'opus', 'webm'], 100],
        'captions' => ['captions_path', ['vtt', 'srt'], 1],
        'poster' => ['poster_path', ['jpg', 'jpeg', 'png', 'webp'], 5],
    ];

    public function views()
    {
        return $this->hasMany(VideoView::class);
    }

    public function isBuiltin(): bool
    {
        return $this->source === 'builtin';
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->isPast() && $this->video_path !== null;
    }

    /** Fichier livré avec l'application (public/videos) plutôt qu'envoyé par l'administration (stockage privé). */
    public static function isPublicFile(?string $path): bool
    {
        return $path !== null && str_starts_with($path, 'videos/') && is_file(public_path($path));
    }

    /** Version WebM livrée à côté du MP4 (navigateurs sans H.264, ex. Chromium libre). */
    public function alternateUrl(): ?string
    {
        if (! self::isPublicFile($this->video_path) || ! str_ends_with($this->video_path, '.mp4')) {
            return null;
        }
        $webm = substr($this->video_path, 0, -4).'.webm';

        return is_file(public_path($webm)) ? asset($webm).'?v='.filemtime(public_path($webm)) : null;
    }

    public function fileUrl(string $kind): ?string
    {
        $path = $this->{self::FILES[$kind][0] ?? 'missing'} ?? null;
        if (! $path) {
            return null;
        }

        return self::isPublicFile($path) ? asset($path).'?v='.filemtime(public_path($path)) : route('videos.file', [$this, $kind]).'?v='.$this->updated_at?->timestamp;
    }
}

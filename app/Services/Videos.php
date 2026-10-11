<?php

namespace App\Services;

use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Collection;

class Videos
{
    /**
     * Vidéos à montrer automatiquement à cet utilisateur (une fois chacune) :
     * - tutoriel de première connexion : comptes créés après sa publication ;
     * - nouveautés : comptes qui existaient déjà à sa publication (un nouveau compte a le tutoriel à jour).
     * Tutoriels d'abord, puis nouveautés de la plus ancienne à la plus récente.
     *
     * @return Collection<int, Video>
     */
    public function pendingFor(User $user): Collection
    {
        return Video::query()->whereNotNull('video_path')->whereNotNull('published_at')->where('published_at', '<=', now())
            ->whereNotIn('id', $user->videoViews()->select('video_id'))
            ->where(fn ($q) => $q
                ->where(fn ($w) => $w->where('kind', 'onboarding')->where('published_at', '<=', $user->created_at))
                ->orWhere(fn ($w) => $w->where('kind', 'announcement')->where('published_at', '>', $user->created_at)))
            ->orderByRaw("CASE kind WHEN 'onboarding' THEN 0 ELSE 1 END")->orderBy('published_at')
            ->get();
    }

    /** Vidéos consultables à tout moment (page « Tutoriels et nouveautés »). */
    public function library(): Collection
    {
        return Video::query()->whereNotNull('video_path')->whereNotNull('published_at')->where('published_at', '<=', now())
            ->orderByRaw("CASE kind WHEN 'onboarding' THEN 0 ELSE 1 END")->orderByDesc('published_at')->get();
    }
}

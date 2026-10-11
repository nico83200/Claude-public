<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Models\VideoView;
use App\Services\Videos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Tutoriels et nouveautés : lecture, fichiers (avec reprise de lecture), vues. */
class VideoController extends Controller
{
    public function index(Videos $videos, Request $request)
    {
        return view('videos.index', [
            'videos' => $videos->library(),
            'seen' => $request->user()->videoViews()->pluck('completed', 'video_id'),
        ]);
    }

    /** Données transmises au lecteur. */
    public static function payload(Video $video): array
    {
        return [
            'id' => $video->id, 'title' => $video->title, 'description' => $video->description, 'kind' => $video->kind,
            'video' => $video->fileUrl('video'), 'videoAlt' => $video->alternateUrl(), 'audio' => $video->fileUrl('audio'), 'captions' => $video->fileUrl('captions'), 'poster' => $video->fileUrl('poster'),
            'seenUrl' => route('videos.seen', $video),
        ];
    }

    /** Fichier d'une vidéo envoyée par l'administration. Les en-têtes Range sont gérés (lecture et avance rapide sur iPhone). */
    public function file(Request $request, Video $video, string $kind)
    {
        abort_unless(isset(Video::FILES[$kind]), 404);
        abort_unless($video->isPublished() || $request->user()->is_super_admin, 404);
        $path = $video->{Video::FILES[$kind][0]};
        abort_unless($path, 404);
        if (Video::isPublicFile($path)) {
            return redirect(asset($path));
        }
        abort_unless(Storage::disk('local')->exists($path), 404);
        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'vtt' => 'text/vtt', 'mp4', 'm4v' => 'video/mp4', 'webm' => $kind === 'audio' ? 'audio/webm' : 'video/webm', 'mov' => 'video/quicktime',
            'mp3' => 'audio/mpeg', 'm4a', 'aac' => 'audio/mp4', 'wav' => 'audio/wav', 'ogg', 'opus' => 'audio/ogg',
            'jpg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', default => 'application/octet-stream',
        };

        return response()->file(Storage::disk('local')->path($path), ['Content-Type' => $mime, 'Cache-Control' => 'private, max-age=86400']);
    }

    /** Vidéo vue (fermée ou regardée jusqu'au bout) : elle ne s'affiche plus automatiquement. */
    public function seen(Request $request, Video $video)
    {
        $view = VideoView::firstOrNew(['video_id' => $video->id, 'user_id' => $request->user()->id]);
        $view->seen_at ??= now();
        $view->completed = $view->completed || $request->boolean('completed');
        $view->save();

        return response()->json(['ok' => true]);
    }
}

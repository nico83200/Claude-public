<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Video;
use App\Services\Audit;
use App\Support\UploadLimits;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Vidéos affichées automatiquement : tutoriel de première connexion et nouveautés.
 * Fichiers envoyés par morceaux (limites d'envoi des hébergeurs), stockés hors du dossier public.
 */
class VideoController extends Controller
{
    private const CHUNK_MAX_KB = 4096;

    public function index()
    {
        return view('admin.videos.index', [
            'videos' => Video::withCount(['views', 'views as completed_count' => fn ($q) => $q->where('completed', true)])->orderByDesc('created_at')->get(),
            'users' => User::count(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $video = new Video($data);
        $video->created_by = $request->user()->id;
        $video->save();
        Audit::log('admin.video_created', $video, ['title' => $video->title]);

        return redirect()->route('admin.videos.edit', $video)->with('success', 'Vidéo créée : ajoutez le fichier vidéo, puis publiez-la.');
    }

    public function edit(Video $video)
    {
        return view('admin.videos.edit', ['video' => $video->loadCount('views'), 'chunkBytes' => UploadLimits::chunkBytes(self::CHUNK_MAX_KB)]);
    }

    public function update(Request $request, Video $video)
    {
        $data = $this->validated($request);
        $video->fill($data)->save();

        return back()->with('success', 'Vidéo enregistrée.');
    }

    public function publish(Request $request, Video $video)
    {
        if ($video->published_at) {
            $video->update(['published_at' => null]);
            Audit::log('admin.video_unpublished', $video);

            return back()->with('success', 'Vidéo retirée : elle n\'est plus affichée.');
        }
        abort_unless($video->video_path, 422, 'Ajoutez d\'abord le fichier vidéo.');
        $video->update(['published_at' => now()]);
        Audit::log('admin.video_published', $video, ['kind' => $video->kind]);

        return back()->with('success', $video->kind === 'onboarding'
            ? 'Vidéo publiée : elle sera proposée à la première connexion des nouveaux comptes.'
            : 'Vidéo publiée : chaque utilisateur la verra à sa prochaine connexion.');
    }

    /** Envoi d'un fichier par morceaux ; le dernier morceau le met en place. */
    public function upload(Request $request, Video $video, string $kind)
    {
        abort_unless(isset(Video::FILES[$kind]), 404);
        abort_if($video->isBuiltin() && $kind === 'video', 422, 'La vidéo livrée avec l\'application ne peut pas être remplacée : créez une nouvelle vidéo.');
        [$column, $extensions, $maxMb] = Video::FILES[$kind];
        $data = $request->validate([
            'upload_id' => ['required', 'uuid'],
            'index' => ['required', 'integer', 'min:0', 'max:999'],
            'total' => ['required', 'integer', 'min:1', 'max:1000'],
            'name' => ['required', 'string', 'max:150'],
            'chunk' => ['required', 'file', 'max:'.self::CHUNK_MAX_KB],
        ]);
        $ext = strtolower(pathinfo($data['name'], PATHINFO_EXTENSION));
        if (! in_array($ext, $extensions, true)) {
            return response()->json(['message' => 'Format non accepté. Formats possibles : '.implode(', ', $extensions).'.'], 422);
        }
        $disk = Storage::disk('local');
        $part = 'video-uploads/'.$data['upload_id'].'.part';
        $expected = $disk->exists($part.'.count') ? (int) $disk->get($part.'.count') : 0;
        if ((int) $data['index'] !== $expected) {
            return response()->json(['message' => 'Envoi désynchronisé : recommencez.'], 422);
        }
        $disk->makeDirectory('video-uploads');
        file_put_contents($disk->path($part), file_get_contents($request->file('chunk')->getRealPath()), FILE_APPEND | LOCK_EX);
        $disk->put($part.'.count', (string) ($expected + 1));
        if (filesize($disk->path($part)) > $maxMb * 1048576) {
            $disk->delete([$part, $part.'.count']);

            return response()->json(['message' => 'Fichier trop volumineux (maximum '.$maxMb.' Mo).'], 422);
        }
        if ($expected + 1 < $data['total']) {
            return response()->json(['received' => $expected + 1]);
        }

        $disk->delete($part.'.count');
        $target = 'videos/'.$video->id.'/'.$kind.'-'.Str::random(8).'.'.($ext === 'srt' ? 'vtt' : $ext);
        $disk->makeDirectory('videos/'.$video->id);
        if ($ext === 'srt') {
            $disk->put($target, self::srtToVtt((string) $disk->get($part)));
            $disk->delete($part);
        } else {
            $disk->move($part, $target);
        }
        if ($kind === 'captions' && ! str_starts_with(ltrim((string) $disk->get($target), "\xEF\xBB\xBF"), 'WEBVTT')) {
            $disk->delete($target);

            return response()->json(['message' => 'Ce fichier de sous-titres n\'est pas au format WebVTT ou SRT.'], 422);
        }
        $old = $video->{$column};
        $video->{$column} = $target;
        $video->save();
        if ($old && $old !== $target && ! Video::isPublicFile($old)) {
            $disk->delete($old);
        }
        Audit::log('admin.video_file_uploaded', $video, ['kind' => $kind]);

        return response()->json(['done' => true, 'redirect' => route('admin.videos.edit', $video)]);
    }

    public function removeFile(Video $video, string $kind)
    {
        abort_unless(isset(Video::FILES[$kind]) && $kind !== 'video', 404);
        $column = Video::FILES[$kind][0];
        if ($video->{$column} && ! Video::isPublicFile($video->{$column})) {
            Storage::disk('local')->delete($video->{$column});
        }
        $video->update([$column => null]);

        return back()->with('success', 'Fichier retiré.');
    }

    public function destroy(Video $video)
    {
        if (! $video->isBuiltin()) {
            Storage::disk('local')->deleteDirectory('videos/'.$video->id);
        }
        $video->delete();
        Audit::log('admin.video_deleted', $video, ['title' => $video->title]);

        return redirect()->route('admin.videos.index')->with('success', 'Vidéo supprimée.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'kind' => ['required', 'in:'.implode(',', array_keys(Video::KINDS))],
            'device' => ['nullable', 'in:'.implode(',', array_keys(Video::DEVICES))],
        ]) + ['device' => 'all'];
    }

    public static function srtToVtt(string $srt): string
    {
        $srt = str_replace(["\r\n", "\r"], "\n", ltrim($srt, "\xEF\xBB\xBF"));
        $srt = preg_replace('/(\d{2}:\d{2}:\d{2}),(\d{3})/', '$1.$2', $srt);

        return "WEBVTT\n\n".trim($srt)."\n";
    }
}

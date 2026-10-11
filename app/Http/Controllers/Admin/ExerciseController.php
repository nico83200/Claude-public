<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ExerciseController as LibraryController;
use App\Models\Exercise;
use App\Models\ExerciseCategory;
use App\Services\Audit;
use App\Support\UploadLimits;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Bibliothèque d'exercices par défaut (visible par tous) : le super-administrateur la complète,
 * la corrige et ajoute à chaque exercice une image ou une vidéo de démonstration.
 * Les séances déjà enregistrées gardent leur copie figée de l'exercice.
 */
class ExerciseController extends Controller
{
    private const CHUNK_MAX_KB = 4096;

    public function index(Request $request)
    {
        $query = Exercise::where('scope', 'default')->with('category');
        if ($q = trim((string) $request->query('q'))) {
            $query->where(fn ($w) => $w->where('name', 'like', "%$q%")->orWhere('objective', 'like', "%$q%"));
        }
        if ($c = $request->integer('categorie')) {
            $query->where('exercise_category_id', $c);
        }
        match ($request->query('media')) {
            'avec' => $query->whereNotNull('media_path'),
            'sans' => $query->whereNull('media_path'),
            default => null,
        };
        $request->boolean('archives') ? $query->whereNotNull('archived_at') : $query->whereNull('archived_at');
        $all = Exercise::where('scope', 'default')->whereNull('archived_at');

        return view('admin.exercises.index', [
            'exercises' => $query->orderBy('name')->paginate(40)->withQueryString(),
            'categories' => ExerciseCategory::orderBy('sort_order')->pluck('name', 'id'),
            'counts' => ['total' => (clone $all)->count(), 'media' => (clone $all)->whereNotNull('media_path')->count()],
        ]);
    }

    public function create()
    {
        return view('admin.exercises.form', ['exercise' => new Exercise(['level' => 'all']), 'categories' => ExerciseCategory::orderBy('sort_order')->pluck('name', 'id'), 'chunkBytes' => 0]);
    }

    public function store(Request $request)
    {
        $data = LibraryController::validated($request);
        $exercise = new Exercise($data['fields']);
        $exercise->scope = 'default';
        $exercise->save();
        LibraryController::syncTags($exercise, $data['tags']);
        Audit::log('admin.exercise_created', $exercise, ['name' => $exercise->name]);

        return redirect()->route('admin.exercises.edit', $exercise)->with('success', 'Exercice ajouté à la bibliothèque : vous pouvez maintenant joindre une image ou une vidéo.');
    }

    public function edit(Exercise $exercise)
    {
        abort_unless($exercise->scope === 'default', 404);

        return view('admin.exercises.form', [
            'exercise' => $exercise->load('tags'),
            'categories' => ExerciseCategory::orderBy('sort_order')->pluck('name', 'id'),
            'chunkBytes' => UploadLimits::chunkBytes(self::CHUNK_MAX_KB),
        ]);
    }

    public function update(Request $request, Exercise $exercise)
    {
        abort_unless($exercise->scope === 'default', 404);
        $data = LibraryController::validated($request);
        $exercise->update($data['fields']);
        LibraryController::syncTags($exercise, $data['tags']);
        Audit::log('admin.exercise_updated', $exercise, ['name' => $exercise->name]);

        return back()->with('success', 'Exercice enregistré. Les séances déjà planifiées gardent la version utilisée.');
    }

    public function archive(Exercise $exercise)
    {
        abort_unless($exercise->scope === 'default', 404);
        $exercise->update(['archived_at' => $exercise->archived_at ? null : now()]);
        Audit::log($exercise->archived_at ? 'admin.exercise_archived' : 'admin.exercise_restored', $exercise, ['name' => $exercise->name]);

        return back()->with('success', $exercise->archived_at ? 'Exercice retiré de la bibliothèque (les séances qui l\'utilisent ne changent pas).' : 'Exercice remis dans la bibliothèque.');
    }

    /** Image ou vidéo envoyée par morceaux (limites d'envoi des hébergeurs) ; le dernier morceau la met en place. */
    public function upload(Request $request, Exercise $exercise)
    {
        abort_unless($exercise->scope === 'default', 404);
        $data = $request->validate([
            'upload_id' => ['required', 'uuid'],
            'index' => ['required', 'integer', 'min:0', 'max:999'],
            'total' => ['required', 'integer', 'min:1', 'max:1000'],
            'name' => ['required', 'string', 'max:150'],
            'chunk' => ['required', 'file', 'max:'.self::CHUNK_MAX_KB],
        ]);
        $ext = strtolower(pathinfo($data['name'], PATHINFO_EXTENSION));
        $kind = Exercise::mediaKind($ext);
        if (! $kind) {
            return response()->json(['message' => 'Format non accepté. Formats possibles : '.implode(', ', array_keys(Exercise::MEDIA_MIMES)).'.'], 422);
        }
        $disk = Storage::disk('local');
        $part = 'exercise-uploads/'.$data['upload_id'].'.part';
        $expected = $disk->exists($part.'.count') ? (int) $disk->get($part.'.count') : 0;
        if ((int) $data['index'] !== $expected) {
            return response()->json(['message' => 'Envoi désynchronisé : recommencez.'], 422);
        }
        $disk->makeDirectory('exercise-uploads');
        file_put_contents($disk->path($part), file_get_contents($request->file('chunk')->getRealPath()), FILE_APPEND | LOCK_EX);
        $disk->put($part.'.count', (string) ($expected + 1));
        $max = Exercise::MEDIA_MAX_MB[$kind];
        if (filesize($disk->path($part)) > $max * 1048576) {
            $disk->delete([$part, $part.'.count']);

            return response()->json(['message' => 'Fichier trop volumineux (maximum '.$max.' Mo pour '.($kind === 'image' ? 'une image' : 'une vidéo').').'], 422);
        }
        if ($expected + 1 < $data['total']) {
            return response()->json(['received' => $expected + 1]);
        }

        $disk->delete($part.'.count');
        if (! $this->looksLike($disk->path($part), $kind)) {
            $disk->delete($part);

            return response()->json(['message' => 'Ce fichier n\'est pas une image ou une vidéo lisible.'], 422);
        }
        $target = 'exercise-media/'.$exercise->id.'/'.$kind.'-'.Str::random(8).'.'.$ext;
        $disk->makeDirectory('exercise-media/'.$exercise->id);
        $disk->move($part, $target);
        $old = $exercise->media_path;
        // Sans changer la version : la démonstration ne modifie pas le contenu figé dans les séances.
        $exercise->forceFill(['media_path' => $target, 'media_type' => $kind, 'media_size' => $disk->size($target)])->saveQuietly();
        if ($old && $old !== $target) {
            $disk->delete($old);
        }
        Audit::log('admin.exercise_media_uploaded', $exercise, ['type' => $kind]);

        return response()->json(['done' => true, 'redirect' => route('admin.exercises.edit', $exercise)]);
    }

    public function removeMedia(Exercise $exercise)
    {
        abort_unless($exercise->scope === 'default', 404);
        if ($exercise->media_path) {
            Storage::disk('local')->delete($exercise->media_path);
        }
        $exercise->forceFill(['media_path' => null, 'media_type' => null, 'media_size' => null])->saveQuietly();

        return back()->with('success', 'Démonstration retirée.');
    }

    /** Vérifie la signature du fichier (pas seulement l'extension). */
    private function looksLike(string $path, string $kind): bool
    {
        $head = (string) file_get_contents($path, false, null, 0, 16);
        if ($kind === 'image') {
            return @getimagesize($path) !== false;
        }

        return substr($head, 4, 4) === 'ftyp' || str_starts_with($head, "\x1A\x45\xDF\xA3");
    }
}

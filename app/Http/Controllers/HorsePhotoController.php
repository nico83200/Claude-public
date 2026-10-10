<?php

namespace App\Http\Controllers;

use App\Models\Horse;
use App\Models\HorsePhoto;
use App\Services\ImageProcessor;
use App\Support\Perm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HorsePhotoController extends Controller
{
    public function store(Request $request, Horse $horse, ImageProcessor $images)
    {
        $this->authorizeHorse($horse, Perm::HORSE_EDIT);
        $request->validate(['photo' => ['required', 'image', 'max:'.config('equine.uploads.max_kb')], 'caption' => ['nullable', 'string', 'max:150']]);
        if (! $this->entitlements()->canStore($horse->organization, $request->file('photo')->getSize())) {
            return back()->with('error', 'Espace de stockage de l\'offre insuffisant.');
        }
        $stored = $images->store($request->file('photo'), 'horses/'.$horse->id.'/photos');
        $photo = HorsePhoto::create(['horse_id' => $horse->id, 'uploaded_by' => $request->user()->id, 'path' => $stored['path'], 'thumb_path' => $stored['thumb'], 'size_bytes' => $stored['size'], 'caption' => $request->input('caption')]);
        if (! $horse->main_photo_path) {
            $horse->update(['main_photo_path' => (string) $photo->id]);
        }

        return back()->with('success', 'Photo ajoutée.');
    }

    /** Diffusion contrôlée : aucune URL publique directe vers les fichiers. */
    public function show(Request $request, Horse $horse, HorsePhoto $photo)
    {
        $this->authorizeHorse($horse, Perm::HORSE_VIEW);
        $path = $request->boolean('thumb') && $photo->thumb_path ? $photo->thumb_path : $photo->path;
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    public function makeMain(Horse $horse, HorsePhoto $photo)
    {
        $this->authorizeHorse($horse, Perm::HORSE_EDIT);
        $horse->update(['main_photo_path' => (string) $photo->id]);

        return back()->with('success', 'Photo principale modifiée.');
    }

    public function destroy(Horse $horse, HorsePhoto $photo)
    {
        $this->authorizeHorse($horse, Perm::HORSE_EDIT);
        Storage::disk('local')->delete(array_filter([$photo->path, $photo->thumb_path]));
        if ($horse->main_photo_path === (string) $photo->id) {
            $horse->update(['main_photo_path' => (string) ($horse->photos()->where('id', '!=', $photo->id)->value('id') ?? '') ?: null]);
        }
        $photo->delete();

        return back()->with('success', 'Photo supprimée.');
    }
}

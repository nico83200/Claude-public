<?php

namespace App\Http\Controllers;

use App\Models\Horse;
use App\Models\HorseDocument;
use App\Services\Audit;
use App\Support\Perm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class HorseDocumentController extends Controller
{
    public function index(Horse $horse)
    {
        $this->authorizeHorse($horse, Perm::DOCUMENTS_VIEW);
        $perms = $this->horsePerms($horse);
        $docs = $horse->documents()->with('uploader')->get()
            ->filter(fn ($d) => ! $d->is_sensitive || in_array(Perm::HEALTH_VIEW, $perms, true));

        return view('horses.documents', ['horse' => $horse, 'documents' => $docs, 'canUpload' => $this->horseCan($horse, Perm::DOCUMENTS_CREATE)]);
    }

    public function store(Request $request, Horse $horse)
    {
        $this->authorizeHorse($horse, Perm::DOCUMENTS_CREATE);
        $this->requireFeature($horse->organization, 'documents', 'Stockage de documents');
        $data = $request->validate([
            'file' => ['required', 'file', 'max:'.config('equine.uploads.max_kb'), 'mimes:'.implode(',', config('equine.uploads.mimes'))],
            'title' => ['nullable', 'string', 'max:150'],
            'category' => ['required', Rule::in(array_keys(HorseDocument::CATEGORIES))],
            'is_sensitive' => ['nullable', 'boolean'],
            'care_record_id' => ['nullable', 'integer'],
        ]);
        $file = $request->file('file');
        if (! $this->entitlements()->canStore($horse->organization, $file->getSize())) {
            return back()->with('error', 'Espace de stockage de l\'offre insuffisant.');
        }
        $this->storeFor($horse, $file, $data, $request->user()->id, isset($data['care_record_id']) ? $horse->cares()->find($data['care_record_id']) : null);

        return back()->with('success', 'Document ajouté.');
    }

    public static function storeFor(Horse $horse, $file, array $data, int $userId, $attachable = null): HorseDocument
    {
        $path = $file->storeAs('horses/'.$horse->id.'/documents', Str::uuid().'.'.$file->extension(), 'local');

        return HorseDocument::create([
            'horse_id' => $horse->id, 'uploaded_by' => $userId,
            'attachable_type' => $attachable?->getMorphClass(), 'attachable_id' => $attachable?->id,
            'category' => $data['category'] ?? 'other',
            'title' => $data['title'] ?? $file->getClientOriginalName(),
            'path' => $path, 'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'mime' => $file->getMimeType() ?? 'application/octet-stream', 'size_bytes' => $file->getSize(),
            'is_sensitive' => (bool) ($data['is_sensitive'] ?? true),
        ]);
    }

    public function download(Request $request, Horse $horse, HorseDocument $document)
    {
        $this->authorizeHorse($horse, Perm::DOCUMENTS_VIEW);
        if ($document->is_sensitive) {
            $this->authorizeHorse($horse, Perm::HEALTH_VIEW);
        }
        abort_unless(Storage::disk('local')->exists($document->path), 404);
        Audit::log('document.downloaded', $document, [], $horse->organization_id);

        return Storage::disk('local')->download($document->path, $document->original_name, ['Cache-Control' => 'private, no-store']);
    }

    public function destroy(Request $request, Horse $horse, HorseDocument $document)
    {
        $this->authorizeHorse($horse, Perm::DOCUMENTS_CREATE);
        abort_unless($document->uploaded_by === $request->user()->id || $this->horseCan($horse, Perm::HORSE_MANAGE), 403);
        $document->delete();
        Audit::log('document.deleted', $document, ['title' => $document->title], $horse->organization_id);

        return back()->with('success', 'Document supprimé.');
    }
}

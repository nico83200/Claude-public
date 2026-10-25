<?php

namespace App\Http\Controllers;

use App\Models\HealthObservation;
use App\Models\HealthObservationPhoto;
use App\Models\Horse;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\AttentionPoints;
use App\Services\HorseAccess;
use App\Support\Perm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Points d'attention (anciennement « observations ») : ouverts par les cavaliers autorisés à commenter, suivis jusqu'à leur clôture. */
class ObservationController extends Controller
{
    public function __construct(private AttentionPoints $points) {}

    public function create(Horse $horse)
    {
        $this->authorizeHorse($horse, Perm::COMMENTS_CREATE);

        return view('observations.create', ['horse' => $horse, 'vet' => $this->points->vetFor($horse)]);
    }

    public function store(Request $request, Horse $horse)
    {
        $this->authorizeHorse($horse, Perm::COMMENTS_CREATE);
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:5000'],
            'severity' => ['required', Rule::in(array_keys(HealthObservation::SEVERITIES))],
        ] + $this->photoRules());
        if ($error = $this->storageError($request, $horse)) {
            return $error;
        }
        $point = $this->points->open($horse, $request->user(), $data, $request->file('photos', []));

        return redirect()->route('horses.observations.show', [$horse, $point])
            ->with('success', 'C\'est noté, le suivi est ouvert. Vous serez relancé pour donner des nouvelles.');
    }

    public function show(Request $request, Horse $horse, HealthObservation $observation)
    {
        abort_unless($this->points->canView($request->user(), $observation), 403);
        $observation->load(['author', 'resolver', 'updates.author', 'updates.photos', 'photos']);

        return view('observations.show', [
            'horse' => $horse,
            'point' => $observation,
            'vet' => $this->points->vetFor($horse),
            'canUpdate' => $observation->isOpen() && $this->horseCan($horse, Perm::COMMENTS_CREATE),
            'canClose' => $this->points->canClose($request->user(), $observation),
            'others' => $horse->observations()->whereKeyNot($observation->id)->latest('observed_at')->limit(5)->get(),
        ]);
    }

    /** Une nouvelle : évolution en un geste, un mot, des photos. */
    public function addUpdate(Request $request, Horse $horse, HealthObservation $observation)
    {
        $this->authorizeHorse($horse, Perm::COMMENTS_CREATE);
        abort_unless($observation->isOpen(), 404);
        $data = $request->validate([
            'trend' => ['nullable', Rule::in(array_keys(HealthObservation::TRENDS))],
            'body' => ['nullable', 'string', 'max:5000', 'required_without_all:trend,photos'],
        ] + $this->photoRules(), ['body.required_without_all' => 'Indiquez l\'évolution, un mot ou une photo.']);
        if ($error = $this->storageError($request, $horse)) {
            return $error;
        }
        $this->points->addUpdate($observation, $request->user(), $data['trend'] ?? null, $data['body'] ?? null, $request->file('photos', []));
        $message = ($data['trend'] ?? null) === 'worse'
            ? 'Nouvelle enregistrée. Si vous êtes inquiet, appelez le vétérinaire : il saura vous dire quoi faire.'
            : 'Nouvelle enregistrée.';

        return back()->with('success', $message);
    }

    public function resolve(Request $request, Horse $horse, HealthObservation $observation)
    {
        abort_unless($this->points->canClose($request->user(), $observation), 403);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);
        if ($observation->isOpen()) {
            $this->points->close($observation, $request->user(), $data['note'] ?? null);
        }

        return back()->with('success', 'Point d\'attention clos. Il reste dans l\'historique de '.$horse->shortName().'.');
    }

    public function reopen(Request $request, Horse $horse, HealthObservation $observation)
    {
        abort_unless($this->points->canClose($request->user(), $observation), 403);
        $this->points->reopen($observation);

        return back()->with('success', 'Point d\'attention rouvert.');
    }

    /** Photos servies uniquement aux personnes qui peuvent lire le dossier. */
    public function photo(Request $request, Horse $horse, HealthObservation $observation, HealthObservationPhoto $photo)
    {
        abort_unless($this->points->canView($request->user(), $observation), 403);
        $path = $request->boolean('thumb') && $photo->thumb_path ? $photo->thumb_path : $photo->path;
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    private function photoRules(): array
    {
        return [
            'photos' => ['nullable', 'array', 'max:'.AttentionPoints::MAX_PHOTOS],
            'photos.*' => ['image', 'max:'.config('equine.uploads.max_kb')],
        ];
    }

    private function storageError(Request $request, Horse $horse)
    {
        $bytes = collect($request->file('photos', []))->sum(fn ($f) => $f->getSize());
        if ($bytes && ! $this->entitlements()->canStore($horse->organization, $bytes)) {
            return back()->withInput()->with('error', 'Espace de stockage de l\'offre insuffisant pour les photos.')->with('upsell', ['organization' => $horse->organization_id, 'key' => 'storage_mb']);
        }

        return null;
    }

    /** Prévient les personnes qui gèrent la santé du cheval (sans divulguer le contenu à d'autres). */
    public static function notifyManagers(Horse $horse, $author, HorseAccess $access, string $title, string $body, ?string $url = null): void
    {
        $candidates = $horse->organization->users()->get()
            ->merge(User::whereIn('id', $horse->ownerships()->whereNotNull('user_id')->pluck('user_id'))->get())
            ->unique('id')->reject(fn ($u) => $u->id === $author->id);
        foreach ($candidates as $user) {
            if (in_array(Perm::HEALTH_VIEW, $access->permissions($user, $horse), true)) {
                $user->notify(new AppNotification('observation', $title, $body, $url ?? route('horses.care.index', $horse)));
            }
        }
    }
}

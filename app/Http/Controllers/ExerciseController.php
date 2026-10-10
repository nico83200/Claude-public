<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Models\ExerciseCategory;
use App\Models\ExerciseTag;
use App\Models\Horse;
use App\Support\Perm;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Bibliothèque : exercices par défaut (lecture seule, duplicables),
 * personnels, et partagés dans un espace.
 */
class ExerciseController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Exercise::visibleTo($user)->with(['category', 'tags']);
        if ($q = trim((string) $request->query('q'))) {
            $query->where(fn ($w) => $w->where('name', 'like', "%$q%")->orWhere('objective', 'like', "%$q%")->orWhereHas('tags', fn ($t) => $t->where('name', 'like', "%$q%")));
        }
        foreach (['discipline', 'level', 'scope'] as $f) {
            if ($v = $request->query($f)) {
                $query->where($f, $v);
            }
        }
        if ($c = $request->integer('category')) {
            $query->where('exercise_category_id', $c);
        }
        if ($d = $request->integer('max_minutes')) {
            $query->where('duration_minutes', '<=', $d);
        }
        if ($o = $request->query('objective')) {
            $query->where('objective', 'like', "%$o%");
        }
        $request->boolean('archived') ? $query->whereNotNull('archived_at') : $query->whereNull('archived_at');

        return view('exercises.index', [
            'exercises' => $query->orderBy('name')->paginate(30)->withQueryString(),
            'categories' => ExerciseCategory::orderBy('sort_order')->pluck('name', 'id'),
        ]);
    }

    public function show(Request $request, Exercise $exercise)
    {
        $this->authorizeVisible($request, $exercise);

        return view('exercises.show', ['exercise' => $exercise->load(['category', 'tags', 'author']), 'canEdit' => $this->canEdit($request, $exercise)]);
    }

    public function create(Request $request)
    {
        return view('exercises.form', ['exercise' => new Exercise(['level' => 'all']), 'categories' => ExerciseCategory::orderBy('sort_order')->pluck('name', 'id'), 'canShare' => $this->canShare($request)]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $exercise = new Exercise($data['fields']);
        $this->applyScope($request, $exercise, $data['share']);
        $exercise->save();
        $this->syncTags($exercise, $data['tags']);

        return redirect()->route('exercises.show', $exercise)->with('success', 'Exercice créé.');
    }

    public function edit(Request $request, Exercise $exercise)
    {
        abort_unless($this->canEdit($request, $exercise), 403, 'Les exercices par défaut ne sont pas modifiables : dupliquez-les pour les adapter.');

        return view('exercises.form', ['exercise' => $exercise->load('tags'), 'categories' => ExerciseCategory::orderBy('sort_order')->pluck('name', 'id'), 'canShare' => $this->canShare($request)]);
    }

    public function update(Request $request, Exercise $exercise)
    {
        abort_unless($this->canEdit($request, $exercise), 403);
        $data = $this->validated($request);
        // Les séances existantes conservent leur copie figée (session_exercises.snapshot) :
        // la version est incrémentée, rien n'est réécrit rétroactivement.
        $exercise->update($data['fields']);
        $this->syncTags($exercise, $data['tags']);

        return redirect()->route('exercises.show', $exercise)->with('success', 'Exercice mis à jour. Les séances passées ne sont pas modifiées.');
    }

    public function duplicate(Request $request, Exercise $exercise)
    {
        $this->authorizeVisible($request, $exercise);
        $copy = $exercise->replicate(['uuid', 'scope', 'user_id', 'organization_id', 'version', 'archived_at']);
        $copy->name = $exercise->name.' (copie)';
        $copy->scope = 'personal';
        $copy->user_id = $request->user()->id;
        $copy->duplicated_from_id = $exercise->id;
        $copy->version = 1;
        $copy->save();
        $copy->tags()->sync($exercise->tags()->pluck('exercise_tags.id'));

        return redirect()->route('exercises.edit', $copy)->with('success', 'Copie créée dans vos exercices personnels.');
    }

    public function archive(Request $request, Exercise $exercise)
    {
        abort_unless($this->canEdit($request, $exercise), 403);
        $exercise->update(['archived_at' => $exercise->archived_at ? null : now()]);

        return back()->with('success', $exercise->archived_at ? 'Exercice archivé.' : 'Exercice réactivé.');
    }

    private function authorizeVisible(Request $request, Exercise $exercise): void
    {
        abort_unless(Exercise::visibleTo($request->user())->whereKey($exercise->id)->exists(), 404);
    }

    private function canEdit(Request $request, Exercise $exercise): bool
    {
        return match ($exercise->scope) {
            'personal' => $exercise->user_id === $request->user()->id,
            'organization' => $request->user()->canInOrg($exercise->organization_id, Perm::EXERCISES_MANAGE),
            default => false,
        };
    }

    private function canShare(Request $request): bool
    {
        $org = $request->attributes->get('organization');

        return $org && ! $org->isPersonal() && $request->user()->canInOrg($org, Perm::EXERCISES_MANAGE);
    }

    private function applyScope(Request $request, Exercise $exercise, bool $share): void
    {
        if ($share && $this->canShare($request)) {
            $exercise->scope = 'organization';
            $exercise->organization_id = $this->currentOrganization()->id;
            $exercise->user_id = $request->user()->id;
        } else {
            $exercise->scope = 'personal';
            $exercise->user_id = $request->user()->id;
        }
    }

    private function syncTags(Exercise $exercise, array $tags): void
    {
        $exercise->tags()->sync(collect($tags)->map(fn ($t) => ExerciseTag::firstOrCreate(['name' => mb_strtolower($t)])->id));
    }

    private function validated(Request $request): array
    {
        $fields = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'discipline' => ['nullable', Rule::in([...array_keys(Horse::DISCIPLINES), 'flat', 'all'])],
            'exercise_category_id' => ['nullable', 'exists:exercise_categories,id'],
            'objective' => ['nullable', 'string', 'max:255'],
            'level' => ['required', Rule::in(array_keys(Exercise::LEVELS))],
            'equipment' => ['nullable', 'string', 'max:2000'],
            'prerequisites' => ['nullable', 'string', 'max:2000'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:300'],
            'repetitions' => ['nullable', 'integer', 'min:1', 'max:500'],
            'common_mistakes' => ['nullable', 'string', 'max:2000'],
            'vigilance' => ['nullable', 'string', 'max:2000'],
            'success_criteria' => ['nullable', 'string', 'max:2000'],
            'media_url' => ['nullable', 'url:https', 'max:1000'],
        ]);
        $extra = $request->validate(['steps_text' => ['nullable', 'string', 'max:5000'], 'tags_text' => ['nullable', 'string', 'max:500']]);
        $fields['steps'] = collect(preg_split('/\r?\n/', (string) ($extra['steps_text'] ?? '')))->map(fn ($s) => trim($s))->filter()->values()->all() ?: null;
        $tags = collect(explode(',', (string) ($extra['tags_text'] ?? '')))->map(fn ($t) => trim($t))->filter()->take(10)->all();

        return ['fields' => $fields, 'tags' => $tags, 'share' => $request->boolean('share')];
    }
}

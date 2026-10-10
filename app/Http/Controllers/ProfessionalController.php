<?php

namespace App\Http\Controllers;

use App\Models\Horse;
use App\Models\Professional;
use App\Services\HorseAccess;
use App\Support\Perm;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Répertoire des intervenants, propre à chaque espace (personnel ou écurie). */
class ProfessionalController extends Controller
{
    public function index(Request $request)
    {
        $org = $this->currentOrganization();
        $query = Professional::where('organization_id', $org->id)->withCount('horses');
        if ($q = trim((string) $request->query('q'))) {
            $query->where(fn ($w) => $w->where('last_name', 'like', "%$q%")->orWhere('first_name', 'like', "%$q%")->orWhere('company', 'like', "%$q%"));
        }
        if ($kind = $request->query('kind')) {
            $query->where('kind', $kind);
        }
        $request->boolean('archived') ? $query->whereNotNull('archived_at') : $query->whereNull('archived_at');

        return view('professionals.index', [
            'professionals' => $query->orderBy('last_name')->paginate(30)->withQueryString(),
            'canManage' => $request->user()->canInOrg($org, Perm::PROFESSIONALS_MANAGE),
            'org' => $org,
        ]);
    }

    public function create()
    {
        $this->authorizeOrg($this->currentOrganization(), Perm::PROFESSIONALS_MANAGE);

        return view('professionals.form', ['professional' => new Professional(['kind' => 'veterinarian'])]);
    }

    public function store(Request $request)
    {
        $org = $this->currentOrganization();
        $this->authorizeOrg($org, Perm::PROFESSIONALS_MANAGE);
        $data = $this->validated($request);

        // Anti-doublon : même nom et même téléphone ou email dans l'espace.
        $duplicate = Professional::where('organization_id', $org->id)->where('last_name', $data['last_name'])
            ->where(fn ($q) => $q->when($data['phone'] ?? null, fn ($w, $p) => $w->orWhere('phone', $p))->when($data['email'] ?? null, fn ($w, $e) => $w->orWhere('email', $e)))
            ->when(empty($data['phone']) && empty($data['email']), fn ($q) => $q->where('first_name', $data['first_name'] ?? null))
            ->first();
        if ($duplicate && ! $request->boolean('force')) {
            return back()->withInput()->with('warning', 'Une fiche similaire existe déjà : '.$duplicate->fullName().'. Cochez « Créer quand même » si c\'est une autre personne.')->with('duplicate_id', $duplicate->id);
        }

        $professional = new Professional($data);
        $professional->organization_id = $org->id;
        $professional->save();

        return redirect()->route('professionals.show', $professional)->with('success', 'Intervenant ajouté.');
    }

    public function show(Request $request, Professional $professional, HorseAccess $access)
    {
        $this->authorizeView($request, $professional);
        $user = $request->user();
        $horses = $professional->horses()->get()->filter(fn ($h) => $access->can($user, $h, Perm::HORSE_VIEW));
        $healthIds = $access->horseIdsWith($user, Perm::HEALTH_VIEW);

        return view('professionals.show', [
            'professional' => $professional,
            'horses' => $horses,
            'interventions' => $professional->careRecords()->with(['horse', 'category'])->whereIn('horse_id', $healthIds)->limit(50)->get(),
            'canManage' => $user->canInOrg($professional->organization_id, Perm::PROFESSIONALS_MANAGE),
        ]);
    }

    public function edit(Request $request, Professional $professional)
    {
        $this->authorizeOrg($professional->organization, Perm::PROFESSIONALS_MANAGE);

        return view('professionals.form', ['professional' => $professional]);
    }

    public function update(Request $request, Professional $professional)
    {
        $this->authorizeOrg($professional->organization, Perm::PROFESSIONALS_MANAGE);
        $professional->update($this->validated($request));

        return redirect()->route('professionals.show', $professional)->with('success', 'Fiche mise à jour.');
    }

    public function archive(Professional $professional)
    {
        $this->authorizeOrg($professional->organization, Perm::PROFESSIONALS_MANAGE);
        $professional->update(['archived_at' => $professional->archived_at ? null : now()]);

        return back()->with('success', $professional->archived_at ? 'Intervenant archivé (historique conservé).' : 'Intervenant réactivé.');
    }

    public function forHorse(Horse $horse)
    {
        $this->authorizeHorse($horse, Perm::HORSE_VIEW);

        return view('professionals.horse', [
            'horse' => $horse,
            'attached' => $horse->professionals()->orderBy('last_name')->get(),
            'available' => Professional::where('organization_id', $horse->organization_id)->whereNull('archived_at')->whereNotIn('id', $horse->professionals()->pluck('professionals.id'))->orderBy('last_name')->get(),
            'canEdit' => $this->horseCan($horse, Perm::HORSE_EDIT),
        ]);
    }

    public function attach(Request $request, Horse $horse)
    {
        $this->authorizeHorse($horse, Perm::HORSE_EDIT);
        $data = $request->validate(['professional_id' => ['required', Rule::exists('professionals', 'id')->where('organization_id', $horse->organization_id)], 'is_primary' => ['nullable', 'boolean']]);
        $horse->professionals()->syncWithoutDetaching([$data['professional_id'] => ['is_primary' => (bool) ($data['is_primary'] ?? false)]]);

        return back()->with('success', 'Intervenant associé.');
    }

    public function detach(Horse $horse, Professional $professional)
    {
        $this->authorizeHorse($horse, Perm::HORSE_EDIT);
        $horse->professionals()->detach($professional->id);

        return back()->with('success', 'Intervenant retiré de la fiche.');
    }

    private function authorizeView(Request $request, Professional $professional): void
    {
        abort_unless($request->user()->memberships->contains('organization_id', $professional->organization_id), 404);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'company' => ['nullable', 'string', 'max:150'],
            'kind' => ['required', Rule::in(array_keys(Professional::KINDS))],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'usual_rate' => ['nullable', 'numeric', 'min:0', 'max:999999'],
        ]);
    }
}

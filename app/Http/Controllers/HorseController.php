<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\Horse;
use App\Models\HorseBreed;
use App\Models\Organization;
use App\Services\Audit;
use App\Services\HorseAccess;
use App\Services\HorseService;
use App\Support\Perm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HorseController extends Controller
{
    public function __construct(private HorseService $horses) {}

    public function index(Request $request, HorseAccess $access)
    {
        $user = $request->user();
        $query = $access->accessibleQuery($user)->with(['breed', 'organization', 'identifiers']);
        if ($q = trim((string) $request->query('q'))) {
            $query->where(fn ($w) => $w->where('official_name', 'like', "%{$q}%")->orWhere('usual_name', 'like', "%{$q}%")
                ->orWhereHas('identifiers', fn ($i) => $i->where('value', 'like', "%{$q}%")));
        }
        if ($org = $request->integer('organization')) {
            $query->where('organization_id', $org);
        }
        $request->boolean('archived') ? $query->whereNotNull('archived_at') : $query->whereNull('archived_at');

        $offline = \DB::table('offline_horse_selections')->where('user_id', $user->id)->pluck('horse_id')->all();

        return view('horses.index', [
            'horses' => $query->orderBy('official_name')->paginate(24)->withQueryString(),
            'offline' => $offline,
            'canCreate' => $user->canInOrg($this->currentOrganization(), Perm::HORSES_CREATE),
        ]);
    }

    public function create(Request $request)
    {
        $org = $this->currentOrganization();
        $this->authorizeOrg($org, Perm::HORSES_CREATE);

        return view('horses.form', [
            'horse' => new Horse(['sex' => 'unknown']),
            'breeds' => HorseBreed::orderBy('name')->pluck('name', 'id'),
            'org' => $org,
            'limitReached' => ! $this->entitlements()->withinLimit($org, 'max_horses', $org->horses()->whereNull('archived_at')->count()),
        ]);
    }

    public function store(Request $request)
    {
        $org = $this->currentOrganization();
        $this->authorizeOrg($org, Perm::HORSES_CREATE);
        $data = $request->validate(HorseService::rules(), HorseService::messages());
        $horse = $this->horses->create($org, $request->user(), $data, $request->boolean('i_am_owner', $org->isPersonal()));

        return redirect()->route('horses.show', $horse)->with('success', 'Cheval ajouté.');
    }

    public function show(Request $request, Horse $horse)
    {
        $this->authorizeHorse($horse, Perm::HORSE_VIEW);
        $perms = $this->horsePerms($horse);
        $has = fn ($p) => in_array($p, $perms, true);
        $horse->load(['breed', 'identifiers', 'organization', 'ownerships.user', 'pedigree.related', 'photos', 'locations', 'origin',
            'assignments' => fn ($q) => $q->whereIn('status', ['pending', 'active'])->with('organization')]);

        return view('horses.show', [
            'horse' => $horse,
            'perms' => $perms,
            'sessions' => $has(Perm::SESSIONS_VIEW) ? $horse->sessions()->with('rider')->limit(5)->get() : collect(),
            'care' => $has(Perm::HEALTH_VIEW) ? $horse->careRecords()->with(['category', 'professional'])->limit(5)->get() : collect(),
            'treatments' => $has(Perm::TREATMENTS_VIEW) ? $horse->treatments()->current()->get() : collect(),
            'events' => $has(Perm::CALENDAR_VIEW) ? $horse->events()->where('starts_at', '>=', now()->startOfDay())->where('status', '!=', 'cancelled')->orderBy('starts_at')->limit(5)->get() : collect(),
            'observations' => $horse->observations()->with('author')->whereNull('resolved_at')->limit(5)->get(),
            'logs' => $horse->dailyLogs()->with('author')->limit(3)->get(),
            'sources' => $has(Perm::HORSE_EDIT) ? $horse->externalSources()->where('validation', 'pending')->get() : collect(),
            'isOffline' => \DB::table('offline_horse_selections')->where('user_id', $request->user()->id)->where('horse_id', $horse->id)->exists(),
            'transferTargets' => $has(Perm::HORSE_MANAGE) ? $request->user()->memberships->filter(fn ($m) => $m->organization_id !== $horse->organization_id && in_array(Perm::HORSES_CREATE, $m->role->permissionKeys(), true))->map->organization : collect(),
        ]);
    }

    public function edit(Horse $horse)
    {
        $this->authorizeHorse($horse, Perm::HORSE_EDIT);

        return view('horses.form', [
            'horse' => $horse->load('identifiers'),
            'breeds' => HorseBreed::orderBy('name')->pluck('name', 'id'),
            'org' => $horse->organization,
            'limitReached' => false,
        ]);
    }

    public function update(Request $request, Horse $horse)
    {
        $this->authorizeHorse($horse, Perm::HORSE_EDIT);
        $data = $request->validate(HorseService::rules(), HorseService::messages());
        $this->horses->update($horse, $data);

        return redirect()->route('horses.show', $horse)->with('success', 'Fiche enregistrée.');
    }

    public function archive(Horse $horse)
    {
        $this->authorizeHorse($horse, Perm::HORSE_MANAGE);
        $horse->update(['archived_at' => now()]);
        Audit::log('horse.archived', $horse, [], $horse->organization_id);

        return redirect()->route('horses.show', $horse)->with('success', 'Cheval archivé : son dossier est conservé en lecture seule et ne compte plus dans la limite de l\'offre.');
    }

    public function unarchive(Horse $horse)
    {
        $this->authorizeHorse($horse, Perm::HORSE_MANAGE);
        $this->horses->assertCanAdd($horse->organization);
        $horse->update(['archived_at' => null]);
        Audit::log('horse.unarchived', $horse, [], $horse->organization_id);

        return back()->with('success', 'Cheval restauré.');
    }

    public function destroy(Request $request, Horse $horse)
    {
        $this->authorizeHorse($horse, Perm::HORSE_MANAGE);
        $request->validate(['confirm_name' => ['required', 'in:'.$horse->official_name]], ['confirm_name.in' => 'Le nom saisi ne correspond pas.']);
        $horse->delete(); // suppression logique : restaurable par l'administration pendant la durée de conservation
        Audit::log('horse.deleted', $horse, ['name' => $horse->official_name], $horse->organization_id);

        return redirect()->route('horses.index')->with('success', 'Dossier supprimé.');
    }

    /** Change l'espace gestionnaire (ex. passage de l'espace personnel à son écurie). */
    public function transfer(Request $request, Horse $horse)
    {
        $this->authorizeHorse($horse, Perm::HORSE_MANAGE);
        $target = Organization::findOrFail($request->integer('organization_id'));
        $this->authorizeOrg($target, Perm::HORSES_CREATE);
        $this->horses->assertCanAdd($target);

        DB::transaction(function () use ($horse, $target) {
            $from = $horse->organization_id;
            $horse->organization_id = $target->id;
            $horse->save();
            // Les événements du calendrier suivent le cheval.
            CalendarEvent::where('horse_id', $horse->id)->update(['organization_id' => $target->id]);
            Audit::log('horse.transferred', $horse, ['from' => $from, 'to' => $target->id], $target->id);
        });

        return redirect()->route('horses.show', $horse)->with('success', 'Le cheval est désormais géré par '.$target->name.'.');
    }

    public function toggleOffline(Request $request, Horse $horse)
    {
        $this->authorizeHorse($horse, Perm::HORSE_VIEW);
        $user = $request->user();
        $table = \DB::table('offline_horse_selections');
        $exists = (clone $table)->where('user_id', $user->id)->where('horse_id', $horse->id)->exists();
        if ($exists) {
            (clone $table)->where('user_id', $user->id)->where('horse_id', $horse->id)->delete();

            return back()->with('success', 'Le cheval ne sera plus disponible hors ligne (données locales purgées à la prochaine synchronisation).');
        }
        if ((clone $table)->where('user_id', $user->id)->count() >= config('equine.offline.max_horses')) {
            return back()->with('error', 'Vous avez atteint le nombre maximal de chevaux disponibles hors ligne ('.config('equine.offline.max_horses').').');
        }
        $table->insert(['user_id' => $user->id, 'horse_id' => $horse->id, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Le cheval sera disponible hors ligne après la prochaine synchronisation (ouvrez le mode écurie).');
    }
}

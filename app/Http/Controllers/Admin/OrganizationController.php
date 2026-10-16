<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Horse;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Audit;
use App\Services\Entitlements;
use App\Support\Fr;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    public function index(Request $request)
    {
        $orgs = Organization::with('owner')->withCount(['horses', 'members'])
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('slug', 'like', "%$s%")))
            ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
            ->when($request->query('filter') === 'suspended', fn ($q) => $q->whereNotNull('suspended_at'))
            ->latest()->paginate(40)->withQueryString();

        return view('admin.organizations.index', ['organizations' => $orgs, 'duplicates' => $this->duplicateStables()]);
    }

    /**
     * Comptes qui gèrent plusieurs écuries actives (créées avant la règle « une écurie par compte »).
     *
     * @return Collection<int, array{owner: User, stables: Collection}>
     */
    private function duplicateStables()
    {
        $owners = Organization::where('type', 'stable')->whereNull('suspended_at')->whereNotNull('owner_id')
            ->groupBy('owner_id')->havingRaw('count(*) > 1')->pluck('owner_id');

        return User::whereIn('id', $owners)->orderBy('name')->get()->map(fn (User $owner) => [
            'owner' => $owner,
            'stables' => Organization::where('owner_id', $owner->id)->where('type', 'stable')->whereNull('suspended_at')
                ->withCount(['horses', 'members'])->orderBy('created_at')->get()
                ->each(fn (Organization $o) => $o->setAttribute('paid', $o->subscriptions()->whereIn('stripe_status', ['active', 'trialing', 'past_due'])->exists())),
        ]);
    }

    /** Garde l'écurie choisie ; les autres écuries du compte sont suspendues (lecture seule, données conservées, réversible). */
    public function keepStable(Request $request, User $user)
    {
        $data = $request->validate(['keep_id' => ['required', 'integer']]);
        $keep = Organization::where('owner_id', $user->id)->where('type', 'stable')->whereKey($data['keep_id'])->firstOrFail();
        $others = Organization::where('owner_id', $user->id)->where('type', 'stable')->whereNull('suspended_at')->whereKeyNot($keep->id)->get();
        foreach ($others as $org) {
            $org->forceFill(['suspended_at' => now(), 'suspension_reason' => 'Un compte ne peut gérer qu\'une écurie : « '.$keep->name.' » a été conservée.'])->save();
            Audit::log('admin.organization_suspended', $org, ['reason' => 'duplicate_stable', 'kept' => $keep->id], $org->id);
        }
        if ($keep->isSuspended()) {
            $keep->forceFill(['suspended_at' => null, 'suspension_reason' => null])->save();
        }
        if ($others->contains('id', $user->current_organization_id)) {
            $user->forceFill(['current_organization_id' => $keep->id])->save();
        }

        return back()->with('success', '« '.$keep->name.' » est conservée pour '.$user->name.' ; '.$others->count().' autre(s) écurie(s) suspendue(s) (données conservées, réactivables).');
    }

    public function show(Organization $organization, Entitlements $entitlements)
    {
        Audit::log('admin.organization_viewed', $organization, [], $organization->id);

        return view('admin.organizations.show', [
            'org' => $organization->load(['owner', 'members.user', 'members.role']),
            'ent' => $entitlements->for($organization),
            'licenses' => $organization->licenses()->with(['plan', 'grantor', 'subscription', 'promoCode'])->latest()->get(),
            'subscriptions' => $organization->subscriptions()->with('plan')->latest()->get(),
            'payments' => $organization->payments()->latest()->limit(30)->get(),
            'plans' => SubscriptionPlan::whereNull('archived_at')->orderBy('sort_order')->pluck('name', 'id'),
            'horseCount' => $organization->horses()->count(),
        ]);
    }

    public function suspend(Request $request, Organization $organization)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $organization->forceFill(['suspended_at' => now(), 'suspension_reason' => $data['reason']])->save();
        Audit::log('admin.organization_suspended', $organization, ['reason' => $data['reason']], $organization->id);

        return back()->with('success', 'Espace suspendu : données en lecture seule.');
    }

    /** Pourquoi l'espace ne peut pas être supprimé, ou null. */
    public static function deletionBlocker(Organization $organization): ?string
    {
        return match (true) {
            $organization->isPersonal() && $organization->owner()->exists() => 'C\'est l\'espace personnel d\'un compte : il disparaît avec la suppression du compte (RGPD), pas séparément.',
            $organization->subscriptions()->whereIn('stripe_status', ['active', 'trialing', 'past_due'])->exists() => 'Un abonnement payant est en cours : résiliez-le d\'abord (fiche Stripe ou page Abonnement de l\'espace).',
            default => null,
        };
    }

    /**
     * Suppression d'un espace : ses chevaux sont supprimés (purge définitive au bout de 30 jours, comme pour
     * toute suppression), les membres retirés, les chevaux confiés par d'autres espaces leur sont rendus.
     */
    public function destroy(Request $request, Organization $organization)
    {
        $request->validate(['confirm_name' => ['required', 'string', Rule::in([$organization->name])]], [
            'confirm_name.in' => 'Recopiez exactement le nom de l\'espace pour confirmer.',
        ]);
        if ($reason = self::deletionBlocker($organization)) {
            return back()->with('error', $reason);
        }
        $name = $organization->name;
        $horses = 0;
        DB::transaction(function () use ($organization, &$horses) {
            $horses = Horse::where('organization_id', $organization->id)->count();
            Horse::where('organization_id', $organization->id)->get()->each->delete();
            DB::table('horse_organization_assignments')->where('organization_id', $organization->id)->whereIn('status', ['pending', 'active'])
                ->update(['status' => 'ended', 'ends_at' => now(), 'updated_at' => now()]);
            foreach (User::where('current_organization_id', $organization->id)->get() as $user) {
                $user->forceFill(['current_organization_id' => $user->personalOrganization()?->id])->save();
            }
            OrganizationMember::where('organization_id', $organization->id)->delete();
            $organization->delete();
        });
        Audit::log('admin.organization_deleted', $organization, ['name' => $name, 'horses' => $horses]);

        return redirect()->route('admin.organizations.index')->with('success', 'Espace « '.$name.' » supprimé ('.Fr::count($horses, 'cheval', 'chevaux').', effacés définitivement dans 30 jours).');
    }

    public function reactivate(Organization $organization)
    {
        $organization->forceFill(['suspended_at' => null, 'suspension_reason' => null])->save();
        Audit::log('admin.organization_reactivated', $organization, [], $organization->id);

        return back()->with('success', 'Espace réactivé.');
    }
}

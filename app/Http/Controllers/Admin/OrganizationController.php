<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Audit;
use App\Services\Entitlements;
use Illuminate\Http\Request;

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
     * @return \Illuminate\Support\Collection<int, array{owner: User, stables: \Illuminate\Support\Collection}>
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

    public function reactivate(Organization $organization)
    {
        $organization->forceFill(['suspended_at' => null, 'suspension_reason' => null])->save();
        Audit::log('admin.organization_reactivated', $organization, [], $organization->id);

        return back()->with('success', 'Espace réactivé.');
    }
}

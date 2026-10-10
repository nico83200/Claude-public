<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\SubscriptionPlan;
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

        return view('admin.organizations.index', ['organizations' => $orgs]);
    }

    public function show(Organization $organization, Entitlements $entitlements)
    {
        Audit::log('admin.organization_viewed', $organization, [], $organization->id);

        return view('admin.organizations.show', [
            'org' => $organization->load(['owner', 'members.user', 'members.role']),
            'ent' => $entitlements->for($organization),
            'licenses' => $organization->licenses()->with(['plan', 'grantor', 'subscription'])->latest()->get(),
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

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\Organization;
use App\Models\SubscriptionPlan;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Licences attribuées manuellement (partenariat, geste commercial, client
 * sur devis…). Aucune écriture de paiement n'est simulée.
 */
class LicenseController extends Controller
{
    public function store(Request $request, Organization $organization)
    {
        $data = $this->validated($request);
        $license = License::create($data + [
            'organization_id' => $organization->id, 'source' => 'manual', 'granted_by' => $request->user()->id,
        ]);
        Audit::log('admin.license_granted', $license, $data, $organization->id);

        return back()->with('success', 'Licence manuelle attribuée.');
    }

    public function update(Request $request, License $license)
    {
        if ($license->source === 'stripe') {
            // Une licence Stripe suit l'abonnement ; l'administrateur peut seulement la suspendre / bloquer / rétablir.
            $data = $request->validate(['status' => ['required', Rule::in(['active', 'suspended', 'blocked', 'grace', 'cancel_scheduled', 'expired'])], 'notes' => ['nullable', 'string', 'max:2000']]);
        } else {
            $data = $this->validated($request);
        }
        $before = $license->only(array_keys($data));
        $license->update($data);
        Audit::log('admin.license_updated', $license, ['before' => $before, 'after' => $data], $license->organization_id);

        return back()->with('success', 'Licence mise à jour.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'subscription_plan_id' => ['required', 'exists:subscription_plans,id'],
            'status' => ['required', Rule::in(array_keys(License::STATUSES))],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'notes' => ['required', 'string', 'max:2000'],
            'limit_overrides' => ['array'],
            'limit_overrides.*' => ['nullable', 'integer', 'min:0'],
            'feature_overrides' => ['array'],
            'feature_overrides.*' => ['nullable', 'in:0,1'],
        ], ['notes.required' => 'Indiquez le motif de l\'attribution (traçabilité).']);
        $data['limit_overrides'] = array_filter(array_intersect_key($data['limit_overrides'] ?? [], array_flip(['max_horses', 'max_members', 'max_invitations', 'storage_mb'])), fn ($v) => $v !== null && $v !== '') ?: null;
        $data['feature_overrides'] = collect($data['feature_overrides'] ?? [])->filter(fn ($v) => $v !== null && $v !== '')->only(array_keys(SubscriptionPlan::FEATURES))->map(fn ($v) => (bool) $v)->all() ?: null;

        return $data;
    }
}

<?php

namespace App\Services;

use App\Models\License;
use App\Models\Organization;
use App\Models\SubscriptionPlan;

/**
 * Calcule les droits effectifs d'une organisation à partir de sa licence.
 *
 * Abonnement Stripe ≠ licence : seule la licence (mise à jour par les webhooks
 * vérifiés ou par un administrateur) détermine les fonctionnalités et limites.
 */
class Entitlements
{
    /** @var array<int, array> */
    private array $cache = [];

    public function for(Organization $organization): array
    {
        return $this->cache[$organization->id] ??= $this->compute($organization);
    }

    public function flush(?Organization $organization = null): void
    {
        if ($organization) {
            unset($this->cache[$organization->id]);
        } else {
            $this->cache = [];
        }
    }

    private function compute(Organization $organization): array
    {
        $license = $this->currentLicense($organization);
        $plan = $license?->plan;
        $status = $license?->status;
        $source = $license?->source;

        if (! $license) {
            $plan = SubscriptionPlan::where('is_default_free', true)->where('is_active', true)->first();
            $status = $plan ? 'active' : 'none';
            $source = $plan ? 'free' : null;
        }

        $limits = [
            'max_horses' => $plan?->max_horses,
            'max_members' => $plan?->max_members,
            'max_invitations' => $plan?->max_invitations,
            'storage_mb' => $plan?->storage_mb,
            'history_months' => $plan?->history_months,
        ];
        foreach (($license?->limit_overrides ?? []) as $key => $value) {
            if (array_key_exists($key, $limits)) {
                $limits[$key] = $value === null || $value === '' ? null : (int) $value;
            }
        }

        $features = $plan?->features ?? [];
        foreach (($license?->feature_overrides ?? []) as $feature => $enabled) {
            $features = $enabled ? array_values(array_unique([...$features, $feature])) : array_values(array_diff($features, [$feature]));
        }

        $writable = in_array($status, License::WRITABLE, true) && ! $organization->isSuspended();

        $reason = null;
        if ($organization->isSuspended()) {
            $reason = 'Cet espace est suspendu par l\'administration. Les données restent consultables et exportables.';
        } elseif (! $writable) {
            $reason = match ($status) {
                'expired' => 'L\'abonnement a expiré : les données sont en lecture seule et restent exportables.',
                'suspended', 'blocked' => 'La licence est suspendue : les données sont en lecture seule.',
                'pending' => 'Le paiement est en attente de confirmation.',
                default => 'Aucune licence active pour cet espace.',
            };
        }

        return [
            'license' => $license,
            'plan' => $plan,
            'status' => $status,
            'source' => $source,
            'limits' => $limits,
            'features' => $features,
            'writable' => $writable,
            'read_only_reason' => $reason,
        ];
    }

    public function currentLicense(Organization $organization): ?License
    {
        $licenses = License::with('plan')->where('organization_id', $organization->id)
            ->whereNotIn('status', ['pending'])
            ->orderByDesc('id')->get();

        // Une licence active (ou en grâce / résiliation programmée) prime sur tout le reste.
        $active = $licenses->first(fn (License $l) => in_array($l->status, License::WRITABLE, true)
            && (! $l->ends_at || $l->ends_at->isFuture() || $l->status === 'grace'));

        return $active ?? $licenses->first();
    }

    public function writable(Organization $organization): bool
    {
        return $this->for($organization)['writable'];
    }

    public function hasFeature(Organization $organization, string $feature): bool
    {
        return in_array($feature, $this->for($organization)['features'], true);
    }

    public function limit(Organization $organization, string $key): ?int
    {
        return $this->for($organization)['limits'][$key] ?? null;
    }

    /** Vérifie qu'une limite n'est pas atteinte ; null = illimité. */
    public function withinLimit(Organization $organization, string $key, int $current): bool
    {
        $limit = $this->limit($organization, $key);

        return $limit === null || $current < $limit;
    }

    public function storageUsedBytes(Organization $organization): int
    {
        $horseIds = $organization->horses()->withTrashed()->pluck('id');

        return (int) \DB::table('horse_documents')->whereIn('horse_id', $horseIds)->whereNull('deleted_at')->sum('size_bytes')
            + (int) \DB::table('horse_photos')->whereIn('horse_id', $horseIds)->sum('size_bytes')
            + (int) \DB::table('expense_documents')->join('expenses', 'expenses.id', '=', 'expense_documents.expense_id')->where('expenses.organization_id', $organization->id)->sum('expense_documents.size_bytes');
    }

    public function canStore(Organization $organization, int $bytes): bool
    {
        $limitMb = $this->limit($organization, 'storage_mb');

        return $limitMb === null || $this->storageUsedBytes($organization) + $bytes <= $limitMb * 1024 * 1024;
    }
}

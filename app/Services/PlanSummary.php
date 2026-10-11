<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Résumé de l'offre en cours d'un espace, pour l'administration :
 * nom de l'offre, nature (payante, essai, offerte, gratuite), montant et échéance.
 */
class PlanSummary
{
    public const KINDS = [
        'paid' => ['Payante', 'green'],
        'trial' => ['Essai gratuit', 'blue'],
        'offered' => ['Offerte', 'purple'],
        'free' => ['Gratuite', 'slate'],
        'grace' => ['Paiement en retard', 'amber'],
        'ended' => ['Terminée', 'red'],
    ];

    public function __construct(private Entitlements $entitlements) {}

    /**
     * @return array{organization: Organization, plan: ?string, kind: string, kind_label: string, color: string,
     *               price: ?string, deadline_label: string, deadline: ?Carbon}
     */
    public function forOrganization(Organization $org): array
    {
        $ent = $this->entitlements->for($org);
        $license = $ent['license'];
        $source = $ent['source'];
        $status = $ent['status'];
        $sub = $source === 'stripe' && $license?->subscription_id ? Subscription::find($license->subscription_id) : null;

        $kind = match (true) {
            ! $ent['plan'] || in_array($status, ['expired', 'suspended', 'blocked', 'none'], true) => 'ended',
            $source === 'free' => 'free',
            in_array($source, ['manual', 'code', 'trial'], true) => 'offered',
            $status === 'grace' => 'grace',
            $sub?->stripe_status === 'trialing' => 'trial',
            default => 'paid',
        };

        [$deadlineLabel, $deadline] = match ($kind) {
            'free' => ['Sans échéance', null],
            'offered' => $license?->ends_at ? ['Jusqu\'au', $license->ends_at] : ['Sans échéance', null],
            'trial' => [$sub->cancel_at_period_end ? 'Essai, se termine le' : 'Fin d\'essai le', $sub->trial_ends_at ?? $sub->current_period_end],
            'grace' => ['Grâce jusqu\'au', $license?->grace_ends_at],
            'ended' => ['Terminée le', $license?->ends_at],
            default => [$sub?->cancel_at_period_end || $status === 'cancel_scheduled' ? 'Se termine le' : 'Renouvellement le', $sub?->current_period_end ?? $license?->ends_at],
        };

        $price = null;
        if ($sub && $sub->unit_amount !== null) {
            $price = number_format((float) $sub->unit_amount, 2, ',', ' ').' '.($sub->currency === 'EUR' || ! $sub->currency ? '€' : $sub->currency).' / '.($sub->interval === 'year' ? 'an' : 'mois');
        }

        return [
            'organization' => $org,
            'plan' => $ent['plan']?->name,
            'kind' => $kind,
            'kind_label' => self::KINDS[$kind][0],
            'color' => self::KINDS[$kind][1],
            'price' => $price,
            'deadline_label' => $deadlineLabel,
            'deadline' => $deadline,
        ];
    }

    /** Offres des espaces dont l'utilisateur est propriétaire (espace personnel d'abord). */
    public function forUser(User $user): array
    {
        return $user->memberships->pluck('organization')->filter(fn ($o) => $o && $o->owner_id === $user->id)
            ->sortBy(fn ($o) => $o->isPersonal() ? 0 : 1)->values()
            ->map(fn ($o) => $this->forOrganization($o))->all();
    }
}

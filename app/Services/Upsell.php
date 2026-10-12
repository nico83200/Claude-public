<?php

namespace App\Services;

use App\Models\License;
use App\Models\Organization;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\Perm;

/**
 * Propositions de passer à une offre supérieure, affichées là où l'offre actuelle bloque
 * (fonction non incluse, limite atteinte). Rien n'est débloqué ici : le bouton mène à la page
 * Abonnement, et seul un paiement confirmé par Stripe change l'offre.
 */
class Upsell
{
    /** Argumentaire par fonction ou limite : [titre, texte]. */
    public const PITCHES = [
        'budget' => ['Suivez ce que coûte votre cheval', 'Pension, maréchal, soins : dépenses par catégorie, dépenses récurrentes avec suivi des paiements et coût par mois.'],
        'documents' => ['Tous ses papiers au même endroit', 'Carnet de vaccination, factures, radios, assurance : rangés dans son dossier et consultables partout, même au box.'],
        'exports' => ['Exportez en PDF et CSV', 'Fiche du cheval, carnet de santé, séances et dépenses : à imprimer, à envoyer au vétérinaire ou à un acheteur, ou à ouvrir dans un tableur.'],
        'assistant' => ['Dictez au lieu de taper', '« Cours demain à 16 h avec Léo » : l\'assistant vocal crée la séance, le soin, la dépense ou le rendez-vous pour vous.'],
        'calendar_sync' => ['Votre agenda Jack&Cie dans votre téléphone', 'Rendez-vous, soins, séances et traitements apparaissent automatiquement dans Google Agenda ou le Calendrier d\'Apple, à côté de vos autres rendez-vous.'],
        'stable' => ['Gérez votre écurie à plusieurs', 'Membres, rôles, chevaux en pension : chacun accède à ce dont il a besoin.'],
        'max_horses' => ['Un cheval de plus ?', 'Suivez tous vos chevaux dans la même application, avec leurs soins, séances et rappels.'],
        'max_invitations' => ['Partagez avec toute l\'équipe', 'Demi-pensionnaire, moniteur, vétérinaire : invitez davantage de personnes sur vos chevaux.'],
        'max_members' => ['Ajoutez des membres', 'Faites travailler plusieurs personnes dans le même espace, chacune avec son rôle.'],
        'storage_mb' => ['Plus d\'espace pour vos fichiers', 'Photos, documents et justificatifs : jusqu\'à plusieurs gigaoctets de stockage.'],
        'assistant_monthly' => ['Encore plus de consignes vocales', 'Les offres supérieures incluent davantage de consignes chaque mois.'],
    ];

    /** Fonctions réservées aux offres qui les incluent. */
    public const GATED = ['budget', 'documents', 'assistant', 'exports', 'stable', 'sharing', 'calendar_sync'];

    public function __construct(private Entitlements $entitlements) {}

    /**
     * Proposition pour une fonction absente ou une limite atteinte, ou null si rien à proposer.
     *
     * @return array{key: string, title: string, text: string, plan: SubscriptionPlan, trial_days: int, can_upgrade: bool, url: string, cta: string}|null
     */
    public function for(Organization $org, ?User $user, string $key): ?array
    {
        $isLimit = ! array_key_exists($key, SubscriptionPlan::FEATURES);
        if (! $isLimit && $this->entitlements->hasFeature($org, $key)) {
            return null;
        }
        $plan = $this->planUnlocking($org, $key, $isLimit);

        return $plan ? $this->offer($org, $user, $key, $plan) : null;
    }

    /**
     * Proposition générale pour un espace en offre gratuite (tableau de bord, menu) :
     * réservée aux personnes qui gèrent l'abonnement, null sinon.
     */
    public function upgrade(Organization $org, ?User $user): ?array
    {
        if (! $this->onFreePlan($org) || ! $user?->canInOrg($org, Perm::BILLING_MANAGE)) {
            return null;
        }
        $plan = $this->planUnlocking($org, null, false);
        if (! $plan) {
            return null;
        }
        $offer = $this->offer($org, $user, 'upgrade', $plan);
        $names = ['assistant' => 'l\'assistant vocal', 'budget' => 'le suivi des dépenses', 'documents' => 'le stockage de documents', 'exports' => 'les exports PDF et CSV', 'calendar_sync' => 'la synchronisation de l\'agenda avec Google et Apple'];
        $items = array_map(fn ($f) => $names[$f], $this->missingFeatures($org));
        $currentHorses = $this->entitlements->limit($org, 'max_horses');
        if (! $plan->max_horses || ($currentHorses !== null && $plan->max_horses > $currentHorses)) {
            $items[] = $plan->max_horses ? 'jusqu\'à '.$plan->max_horses.' chevaux' : 'des chevaux en illimité';
        }
        $offer['title'] = 'Débloquez tout Jack&Cie';
        $offer['text'] = ucfirst(implode(', ', array_slice($items, 0, -1)).(count($items) > 1 ? ' et ' : '').end($items)).' avec l\'offre '.$plan->name.'.';
        $offer['url'] = route('billing.index').'#offres';
        $offer['features'] = $this->missingFeatures($org);

        return $offer;
    }

    private function offer(Organization $org, ?User $user, string $key, SubscriptionPlan $plan): array
    {
        $trial = $this->trialDays($org, $plan);
        $canUpgrade = $user && $user->canInOrg($org, Perm::BILLING_MANAGE);
        [$title, $text] = self::PITCHES[$key] ?? [SubscriptionPlan::FEATURES[$key] ?? 'Offre supérieure', ''];

        return [
            'key' => $key,
            'title' => $title,
            'text' => $text,
            'plan' => $plan,
            'trial_days' => $trial,
            'can_upgrade' => $canUpgrade,
            'url' => route('billing.index', ['fonction' => $key]).'#offres',
            'features' => [],
            'cta' => $trial ? 'Essayer '.$trial.' jours gratuitement' : 'Passer à l\'offre '.$plan->name,
            'note' => $canUpgrade
                ? 'Inclus dans l\'offre '.$plan->name.($plan->price_monthly ? ' (dès '.number_format((float) $plan->price_monthly, 2, ',', ' ').' € / mois)' : '').'.'
                : 'Inclus dans l\'offre '.$plan->name.' : le responsable de l\'espace '.$org->name.' peut changer d\'offre.',
        ];
    }

    /** Fonctions de l'offre la plus proche qui manquent à l'espace (pour le tableau de bord). */
    public function missingFeatures(Organization $org): array
    {
        $plan = $this->planUnlocking($org, null, false);

        return $plan ? array_values(array_diff(array_intersect($plan->features ?? [], ['assistant', 'budget', 'documents', 'exports', 'calendar_sync']), $this->entitlements->for($org)['features'])) : [];
    }

    /** Offre gratuite par défaut (Découverte) ou aucune licence payante. */
    public function onFreePlan(Organization $org): bool
    {
        return in_array($this->entitlements->for($org)['source'], ['free', null], true);
    }

    private function planUnlocking(Organization $org, ?string $key, bool $isLimit): ?SubscriptionPlan
    {
        $audiences = $org->isPersonal() ? ['individual', 'any'] : ['stable', 'any', 'individual'];
        $current = $this->entitlements->for($org);
        $currentLimit = $isLimit ? ($current['limits'][$key] ?? null) : null;

        return SubscriptionPlan::purchasable()->whereIn('audience', $audiences)->orderBy('sort_order')->orderBy('price_monthly')->get()
            ->first(function (SubscriptionPlan $p) use ($key, $isLimit, $currentLimit, $current) {
                if ($current['plan'] && $p->id === $current['plan']->id) {
                    return false;
                }
                if ($key === null) {
                    return true;
                }
                if (! $isLimit) {
                    return in_array($key, $p->features ?? [], true);
                }
                $limit = $p->{$key};

                return $currentLimit !== null && ($limit === null || $limit > $currentLimit);
            });
    }

    private function trialDays(Organization $org, SubscriptionPlan $plan): int
    {
        if ($plan->trial_days <= 0 || License::where('organization_id', $org->id)->where('source', 'stripe')->exists()) {
            return 0;
        }

        return (int) $plan->trial_days;
    }
}

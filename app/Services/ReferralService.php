<?php

namespace App\Services;

use App\Models\License;
use App\Models\Referral;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Parrainage : chaque utilisateur a un lien personnel. Quand un filleul s'inscrit par ce lien
 * et confirme son adresse e-mail, le parrain reçoit 15 jours de l'offre Propriétaire.
 *
 * Les jours ne remplacent jamais un abonnement en cours : si le parrain a déjà une offre active
 * (payante ou offerte), ils sont mis en réserve et utilisables plus tard depuis la page Parrainage.
 */
class ReferralService
{
    public const DAYS = 15;

    public const PLAN = 'particulier';

    public const COOKIE = 'jc_parrain';

    /** Récompenses au plus par parrain sur 12 mois glissants (6 mois offerts). */
    public const MAX_PER_YEAR = 12;

    public function __construct(private Entitlements $entitlements) {}

    public static function plan(): ?SubscriptionPlan
    {
        return SubscriptionPlan::where('slug', self::PLAN)->first();
    }

    public function code(User $user): string
    {
        if (! $user->referral_code) {
            do {
                $code = strtoupper(Str::random(8));
                $code = strtr($code, ['0' => 'X', 'O' => 'Y', 'I' => 'Z', '1' => 'W', 'L' => 'V']);
            } while (User::where('referral_code', $code)->exists());
            $user->forceFill(['referral_code' => $code])->save();
        }

        return $user->referral_code;
    }

    public function link(User $user): string
    {
        return route('referral.join', $this->code($user));
    }

    public static function normalize(?string $code): ?string
    {
        $code = strtoupper(trim((string) $code));

        return preg_match('/^[A-Z0-9]{6,16}$/', $code) ? $code : null;
    }

    /** Nouveau compte arrivé par un lien de parrainage. */
    public function attach(User $referee, ?string $code): ?Referral
    {
        $code = self::normalize($code);
        if (! $code || $referee->referred_by_id || Referral::where('referee_id', $referee->id)->exists()) {
            return null;
        }
        $referrer = User::where('referral_code', $code)->first();
        if (! $referrer || $referrer->id === $referee->id || $referrer->suspended_at || strcasecmp($referrer->email, $referee->email) === 0) {
            return null;
        }
        $referee->forceFill(['referred_by_id' => $referrer->id])->save();
        $referral = Referral::create(['referrer_id' => $referrer->id, 'referee_id' => $referee->id, 'status' => 'pending']);
        Audit::log('referral.signed_up', $referral, ['referrer' => $referrer->id], null);
        if ($referee->hasVerifiedEmail()) {
            $this->reward($referee);
        }

        return $referral;
    }

    /** Filleul confirmé (adresse e-mail vérifiée) : jours offerts au parrain, une seule fois. */
    public function reward(User $referee): ?Referral
    {
        return DB::transaction(function () use ($referee) {
            $referral = Referral::lockForUpdate()->where('referee_id', $referee->id)->where('status', 'pending')->first();
            if (! $referral || ! $referee->hasVerifiedEmail()) {
                return null;
            }
            $referrer = $referral->referrer;
            $org = $referrer?->personalOrganization();
            $plan = self::plan();
            if (! $org || ! $plan) {
                return null;
            }
            $recent = Referral::where('referrer_id', $referrer->id)->whereIn('status', ['rewarded', 'banked', 'used'])->where('rewarded_at', '>=', now()->subYear())->count();
            if ($recent >= self::MAX_PER_YEAR) {
                $referral->update(['status' => 'capped', 'rewarded_at' => now()]);

                return $referral;
            }

            $current = $this->entitlements->currentLicense($org);
            $active = $current && in_array($current->status, License::WRITABLE, true) && (! $current->ends_at || $current->ends_at->isFuture());
            if ($active && $current->source === 'referral') {
                $current->update(['ends_at' => $current->ends_at->copy()->addDays(self::DAYS)]);
                $referral->update(['status' => 'rewarded', 'days' => self::DAYS, 'license_id' => $current->id, 'rewarded_at' => now()]);
                $text = self::DAYS.' jours de plus : votre offre '.$plan->name.' offerte court jusqu\'au '.$current->ends_at->format('d/m/Y').'.';
            } elseif (! $active) {
                $license = $this->grant($org->id, $plan, self::DAYS, 'Parrainage de '.$referee->name);
                $referral->update(['status' => 'rewarded', 'days' => self::DAYS, 'license_id' => $license->id, 'rewarded_at' => now()]);
                $text = 'Profitez de l\'offre '.$plan->name.' gratuitement jusqu\'au '.$license->ends_at->format('d/m/Y').'.';
            } else {
                $referral->update(['status' => 'banked', 'days' => self::DAYS, 'rewarded_at' => now()]);
                $text = 'Votre abonnement est déjà actif : ces jours sont mis en réserve dans Parrainage, utilisables quand vous le souhaitez.';
            }
            $this->entitlements->flush($org);
            Audit::log('referral.rewarded', $referral, ['days' => self::DAYS, 'status' => $referral->status], $org->id);
            $referrer->notify(new AppNotification('referral', $referee->name.' a rejoint Jack&Cie grâce à vous !', self::DAYS.' jours de l\'offre '.$plan->name.' vous sont offerts. '.$text, route('referral.index')));

            return $referral;
        });
    }

    /** Jours en réserve (parrain qui avait déjà une offre active au moment de la récompense). */
    public function bankedDays(User $user): int
    {
        return (int) Referral::where('referrer_id', $user->id)->where('status', 'banked')->sum('days');
    }

    /** Utilise les jours en réserve : seulement quand aucune offre n'est active sur l'espace personnel. */
    public function useBanked(User $user): ?License
    {
        return DB::transaction(function () use ($user) {
            $org = $user->personalOrganization();
            $plan = self::plan();
            $rows = Referral::lockForUpdate()->where('referrer_id', $user->id)->where('status', 'banked')->get();
            $days = (int) $rows->sum('days');
            if (! $org || ! $plan || $days === 0 || $this->hasActiveOffer($user)) {
                return null;
            }
            $license = $this->grant($org->id, $plan, $days, 'Parrainage : '.$rows->count().' filleul(s) en réserve');
            Referral::whereIn('id', $rows->pluck('id'))->update(['status' => 'used', 'license_id' => $license->id]);
            $this->entitlements->flush($org);
            Audit::log('referral.banked_used', $license, ['days' => $days], $org->id);

            return $license;
        });
    }

    public function hasActiveOffer(User $user): bool
    {
        $org = $user->personalOrganization();
        $current = $org ? $this->entitlements->currentLicense($org) : null;

        return $current && in_array($current->status, License::WRITABLE, true) && (! $current->ends_at || $current->ends_at->isFuture());
    }

    private function grant(int $orgId, SubscriptionPlan $plan, int $days, string $notes): License
    {
        return License::create([
            'organization_id' => $orgId, 'subscription_plan_id' => $plan->id, 'source' => 'referral', 'status' => 'active',
            'starts_at' => now(), 'ends_at' => now()->addDays($days), 'notes' => $notes,
        ]);
    }
}

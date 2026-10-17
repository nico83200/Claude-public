<?php

namespace App\Services;

use App\Models\HalfLease;
use App\Models\Horse;
use App\Models\HorseAccessGrant;
use App\Models\HorseDocument;
use App\Models\Invitation;
use App\Models\RidingDay;
use App\Models\RidingDayChange;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Support\Fr;
use App\Support\Perm;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Demi-pensions : le propriétaire propose (invitation par e-mail, ou dans l'appli si la personne a déjà un compte),
 * l'accès « Demi-pension » s'ouvre à son accord, ses jours habituels s'appliquent à partir du début,
 * et tout s'arrête à la date de fin (préavis), l'historique restant.
 */
class HalfLeases
{
    public function __construct(private InvitationService $invitations) {}

    /** Jours habituels déjà réservés par une autre demi-pension du cheval : jour (1 = lundi) => nom. */
    public function takenDays(Horse $horse, ?HalfLease $except = null): array
    {
        $taken = [];
        foreach (HalfLease::open()->with('user')->where('horse_id', $horse->id)->when($except, fn ($q) => $q->whereKeyNot($except->id))->get() as $lease) {
            foreach ($lease->weekdays ?? [] as $day) {
                $taken[(int) $day] = $lease->riderName();
            }
        }

        return $taken;
    }

    /** @return array{0: HalfLease, 1: string} fiche + jeton de l'invitation (lien à envoyer aussi par message) */
    public function propose(Horse $horse, User $owner, array $data): array
    {
        $email = strtolower(trim($data['email']));
        if (HalfLease::open()->where('horse_id', $horse->id)->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'Une demi-pension est déjà proposée ou en cours pour cette adresse sur ce cheval.']);
        }
        $this->checkDays($horse, $data['weekdays'] ?? []);

        return DB::transaction(function () use ($horse, $owner, $data, $email) {
            $lease = new HalfLease($this->fields($data) + ['email' => $email]);
            $lease->horse_id = $horse->id;
            $lease->created_by = $owner->id;
            $lease->status = 'proposed';
            $lease->save();
            [$invitation, $token] = $this->invitations->inviteHalfLease($lease->setRelation('horse', $horse), $owner);
            $lease->update(['invitation_id' => $invitation->id]);
            Audit::log('half_lease.proposed', $lease, ['email' => $email], $horse->organization_id);

            return [$lease, $token];
        });
    }

    /** Proposition acceptée (InvitationService::accept) : fiche en cours, jours habituels dès le début. */
    public function accepted(HalfLease $lease, User $user, HorseAccessGrant $grant): void
    {
        $lease->update(['user_id' => $user->id, 'grant_id' => $grant->id, 'status' => 'active', 'accepted_at' => now(), 'answered_at' => now()]);
        $this->applyDays($lease);
        app(HalfLeaseFees::class)->sync($lease);
        $horse = $lease->horse;
        Audit::log('half_lease.accepted', $lease, ['user' => $user->email], $horse->organization_id);
        $lease->creator?->notify(new AppNotification('half_lease', 'Demi-pension acceptée : '.$horse->shortName(),
            $user->name.' a accepté la demi-pension sur '.$horse->shortName().($lease->starts_on->isFuture() ? ', à partir du '.Fr::day($lease->starts_on) : '').'.',
            route('half-leases.show', $lease)));
    }

    /** Proposition refusée par la personne (l'invitation est déjà close). */
    public function declined(Invitation $invitation): void
    {
        $lease = HalfLease::with(['horse', 'creator'])->where('invitation_id', $invitation->id)->where('status', 'proposed')->first();
        if (! $lease) {
            return;
        }
        $lease->update(['status' => 'declined', 'answered_at' => now()]);
        $lease->creator?->notify(new AppNotification('half_lease', 'Demi-pension refusée : '.$lease->horse->shortName(),
            $lease->name.' a refusé la demi-pension sur '.$lease->horse->shortName().'.', route('half-leases.show', $lease)));
    }

    /** Proposition retirée par le propriétaire avant la réponse. */
    public function cancel(HalfLease $lease): void
    {
        abort_unless($lease->status === 'proposed', 422);
        $lease->invitation?->update(['revoked_at' => now()]);
        $lease->update(['status' => 'cancelled', 'answered_at' => now()]);
    }

    /** Invitation annulée depuis la page Partage : la proposition l'est aussi. */
    public function invitationRevoked(Invitation $invitation): void
    {
        HalfLease::where('invitation_id', $invitation->id)->where('status', 'proposed')->update(['status' => 'cancelled', 'answered_at' => now()]);
    }

    /** Conditions modifiées par le propriétaire : jours, accès et invitation suivent ; la personne est prévenue. */
    public function update(HalfLease $lease, array $data, User $by): void
    {
        $this->checkDays($lease->horse, $data['weekdays'] ?? [], $lease);
        DB::transaction(function () use ($lease, $data) {
            $lease->update($this->fields($data));
            $permissions = $this->permissions($lease);
            if ($lease->status === 'proposed') {
                $lease->invitation?->update(['permissions' => $permissions, 'access_expires_at' => $lease->ends_on?->copy()->addDay()]);
            }
            if ($lease->status === 'active') {
                $lease->grant?->update(['permissions' => $permissions, 'expires_at' => $lease->ends_on?->copy()->addDay()]);
                // Jours retirés libérés ; début repoussé : ses jours attendront la nouvelle date.
                if ($lease->days_applied_at) {
                    $keep = $lease->starts_on->isFuture() ? [] : array_map('intval', $lease->weekdays ?? []);
                    RidingDay::where('horse_id', $lease->horse_id)->where('user_id', $lease->user_id)->whereNotIn('weekday', $keep)->delete();
                    $lease->forceFill(['days_applied_at' => null])->save();
                }
                $this->applyDays($lease);
            }
            app(HalfLeaseFees::class)->sync($lease);
        });
        if ($lease->status === 'active' && $lease->user && $lease->user_id !== $by->id) {
            $lease->user->notify(new AppNotification('half_lease', 'Demi-pension modifiée : '.$lease->horse->shortName(),
                $by->name.' a mis à jour la demi-pension sur '.$lease->horse->shortName().' : '.$this->summary($lease).'.', route('half-leases.show', $lease)));
        }
    }

    /** Semaine type changée sur la page Jours de monte : les jours habituels des demi-pensions commencées suivent. */
    public function syncFromWeek(Horse $horse): void
    {
        foreach (HalfLease::where('horse_id', $horse->id)->where('status', 'active')->whereNotNull('days_applied_at')->whereNotNull('user_id')->get() as $lease) {
            $days = RidingDay::where('horse_id', $horse->id)->where('user_id', $lease->user_id)->orderBy('weekday')->pluck('weekday')->map(fn ($d) => (int) $d)->all();
            if ($days !== array_map('intval', $lease->weekdays ?? [])) {
                $lease->update(['weekdays' => $days]);
            }
        }
    }

    /** Fin décidée par le propriétaire ou la demi-pension : accès jusqu'au soir du dernier jour, l'autre partie est prévenue. */
    public function end(HalfLease $lease, Carbon $lastDay, User $by): void
    {
        abort_unless($lease->status === 'active', 422);
        $lastDay = $lastDay->copy()->startOfDay();
        $lease->update(['ends_on' => $lastDay->toDateString()]);
        $lease->grant?->update(['expires_at' => $lastDay->copy()->addDay()]);
        app(HalfLeaseFees::class)->sync($lease);
        Audit::log('half_lease.ended', $lease, ['ends_on' => $lastDay->toDateString()], $lease->horse->organization_id);
        $other = $by->id === $lease->user_id ? $lease->creator : $lease->user;
        $other?->notify(new AppNotification('half_lease', 'Fin de la demi-pension : '.$lease->horse->shortName(),
            $by->name.' met fin à la demi-pension sur '.$lease->horse->shortName().' : dernier jour le '.$lastDay->translatedFormat('l').' '.Fr::day($lastDay).'.',
            route('half-leases.show', $lease)));
    }

    /** Accès retiré (« Révoquer », « Quitter ») : la demi-pension se termine aujourd'hui. */
    public function grantRevoked(HorseAccessGrant $grant): void
    {
        foreach (HalfLease::where('grant_id', $grant->id)->where('status', 'active')->get() as $lease) {
            $lease->update(['status' => 'ended', 'ends_on' => ($lease->ends_on && $lease->ends_on->lt(today()) ? $lease->ends_on : today())->toDateString()]);
            $this->releaseDays($lease);
            app(HalfLeaseFees::class)->sync($lease);
        }
    }

    /**
     * Tâche quotidienne : jours habituels appliqués au début, rappel de fin d'essai (2 jours avant), clôture après le dernier jour.
     * Retourne le nombre de notifications.
     */
    public function daily(): int
    {
        foreach (HalfLease::where('status', 'active')->whereNull('days_applied_at')->where('starts_on', '<=', today())->get() as $lease) {
            $this->applyDays($lease);
        }
        $sent = 0;
        $trials = HalfLease::with(['horse', 'user', 'creator'])->where('status', 'active')->whereNull('trial_reminded_at')
            ->whereBetween('trial_ends_on', [today()->toDateString(), today()->addDays(2)->toDateString()])->get();
        foreach ($trials as $lease) {
            foreach (collect([$lease->user, $lease->creator])->filter()->unique('id') as $person) {
                $person->notify(new AppNotification('half_lease', 'Fin de la période d\'essai : '.$lease->horse->shortName(),
                    'La période d\'essai de la demi-pension de '.$lease->riderName().' sur '.$lease->horse->shortName().' se termine le '.$lease->trial_ends_on->translatedFormat('l').' '.Fr::day($lease->trial_ends_on, false)
                    .'. Rien à faire pour continuer ; sinon, « Mettre fin » sur la fiche.', route('half-leases.show', $lease)));
                $sent++;
            }
            $lease->update(['trial_reminded_at' => now()]);
        }
        foreach (HalfLease::where('status', 'active')->whereNotNull('ends_on')->where('ends_on', '<', today()->toDateString())->get() as $lease) {
            $lease->update(['status' => 'ended']);
            $this->releaseDays($lease);
        }

        return $sent + $this->documentReminders();
    }

    /** Assurance, licence… : rappel aux deux 30 jours avant la fin de validité, puis une fois expirée. */
    public function documentReminders(): int
    {
        $sent = 0;
        $docs = HorseDocument::where('attachable_type', HalfLease::class)->whereNotNull('valid_until')
            ->where(fn ($q) => $q->where(fn ($w) => $w->whereNull('expiry_warned_at')->whereBetween('valid_until', [today()->toDateString(), today()->addDays(30)->toDateString()]))
                ->orWhere(fn ($w) => $w->whereNull('expired_notified_at')->where('valid_until', '<', today()->toDateString())))->get();
        foreach ($docs as $doc) {
            $lease = HalfLease::with(['horse', 'user', 'creator'])->find($doc->attachable_id);
            $expired = $doc->valid_until->lt(today());
            $doc->forceFill($expired ? ['expired_notified_at' => now(), 'expiry_warned_at' => $doc->expiry_warned_at ?? now()] : ['expiry_warned_at' => now()])->save();
            if (! $lease || $lease->status !== 'active') {
                continue;
            }
            $kind = HorseDocument::HALF_LEASE_CATEGORIES[$doc->category] ?? 'Document';
            foreach (collect([$lease->user, $lease->creator])->filter()->unique('id') as $person) {
                $person->notify(new AppNotification('half_lease', ($expired ? $kind.' expirée' : $kind.' à renouveler').' : '.$lease->riderName(),
                    '« '.$doc->title.' » '.($expired ? 'n\'est plus valable depuis le ' : 'est valable jusqu\'au ').Fr::day($doc->valid_until).' (demi-pension sur '.$lease->horse->shortName().'). Déposez la nouvelle version sur la fiche.',
                    route('half-leases.show', $lease)));
                $sent++;
            }
        }

        return $sent;
    }

    /** « 180,00 € par mois, payable le 5 · mardi et jeudi · du 1er novembre 2026 au 30 juin 2027 » */
    public function summary(HalfLease $lease): string
    {
        return implode(' · ', array_filter([
            $lease->priceLabel(),
            $lease->weekdays ? $lease->daysLabel() : null,
            $lease->ends_on ? 'du '.Fr::day($lease->starts_on).' au '.Fr::day($lease->ends_on) : 'à partir du '.Fr::day($lease->starts_on),
        ]));
    }

    /** Droits de l'accès : préréglage Demi-pension par défaut, jamais le budget du cheval. */
    public function permissions(HalfLease $lease): array
    {
        return array_values(array_unique([Perm::HORSE_VIEW, ...array_intersect($lease->permissions ?? Perm::halfLeasePreset(), Perm::shareableKeys())]));
    }

    /** Jours habituels de la demi-pension inscrits dans la semaine type, une fois la demi-pension commencée. */
    private function applyDays(HalfLease $lease): void
    {
        if ($lease->status !== 'active' || $lease->days_applied_at || ! $lease->user_id || $lease->starts_on->isFuture()) {
            return;
        }
        foreach (array_map('intval', $lease->weekdays ?? []) as $day) {
            RidingDay::updateOrCreate(['horse_id' => $lease->horse_id, 'weekday' => $day], ['user_id' => $lease->user_id]);
        }
        $lease->forceFill(['days_applied_at' => now()])->save();
    }

    /** Fin : ses jours habituels et les jours qu'on lui a laissés après la fin redeviennent libres. */
    private function releaseDays(HalfLease $lease): void
    {
        if (! $lease->user_id) {
            return;
        }
        RidingDay::where('horse_id', $lease->horse_id)->where('user_id', $lease->user_id)->delete();
        RidingDayChange::where('horse_id', $lease->horse_id)->where('user_id', $lease->user_id)->where('date', '>', $lease->ends_on ?? today())->delete();
    }

    private function checkDays(Horse $horse, array $days, ?HalfLease $except = null): void
    {
        $taken = array_intersect_key($this->takenDays($horse, $except), array_flip(array_map('intval', $days)));
        if ($taken) {
            $day = array_key_first($taken);
            throw ValidationException::withMessages(['weekdays' => 'Le '.mb_strtolower(RidingDay::WEEKDAYS[$day]).' est déjà le jour de '.$taken[$day].'.']);
        }
    }

    private function fields(array $data): array
    {
        $days = array_values(array_unique(array_map('intval', $data['weekdays'] ?? [])));
        sort($days);

        return [
            'name' => trim($data['name']), 'phone' => $data['phone'] ?? null,
            'starts_on' => $data['starts_on'], 'trial_ends_on' => $data['trial_ends_on'] ?? null, 'ends_on' => $data['ends_on'] ?? null,
            'notice_days' => $data['notice_days'] ?? null, 'weekdays' => $days,
            'monthly_amount' => isset($data['monthly_amount']) && $data['monthly_amount'] !== '' ? $data['monthly_amount'] : null,
            'payment_day' => $data['payment_day'] ?? null, 'first_month' => ($data['first_month'] ?? 'full') === 'prorata' ? 'prorata' : 'full', 'notes' => $data['notes'] ?? null,
            'permissions' => array_values(array_intersect($data['permissions'] ?? Perm::halfLeasePreset(), Perm::shareableKeys())),
        ];
    }
}

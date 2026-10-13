<?php

namespace App\Services;

use App\Models\CareRecord;
use App\Models\Organization;
use App\Models\Professional;
use App\Models\User;
use App\Support\Perm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Carnet des intervenants d'un espace : recherche d'une fiche existante (email, téléphone
 * ou nom complet) et création ou mise à jour, notamment depuis une fiche contact importée.
 */
class ProfessionalDirectory
{
    /** @var array<int, array<int, int>> cache par utilisateur, le temps d'une requête */
    private array $sharedIds = [];

    /**
     * Intervenants des chevaux partagés avec l'utilisateur, hors de ses propres espaces :
     * ceux associés au cheval (fiche consultable) et ceux de ses soins (accès santé).
     *
     * @return array<int, int>
     */
    public function sharedIds(User $user): array
    {
        if (isset($this->sharedIds[$user->id])) {
            return $this->sharedIds[$user->id];
        }
        $access = app(HorseAccess::class);
        $own = $user->memberships->pluck('organization_id');
        $viewIds = $access->horseIdsWith($user, Perm::HORSE_VIEW);
        $healthIds = $access->horseIdsWith($user, Perm::HEALTH_VIEW);
        $ids = DB::table('horse_professionals')->whereIn('horse_id', $viewIds ?: [0])->pluck('professional_id')
            ->merge(CareRecord::whereIn('horse_id', $healthIds ?: [0])->whereNotNull('professional_id')->pluck('professional_id'))
            ->unique()->values();

        return $this->sharedIds[$user->id] = Professional::whereIn('id', $ids->all() ?: [0])->whereNotIn('organization_id', $own)->pluck('id')->all();
    }

    /** Intervenants visibles : ceux de ses espaces et ceux des chevaux partagés avec lui. */
    public function visibleQuery(User $user): Builder
    {
        $own = $user->memberships->pluck('organization_id');
        $shared = $this->sharedIds($user);

        return Professional::query()->where(fn ($q) => $q->whereIn('organization_id', $own)->orWhereIn('id', $shared ?: [0]));
    }

    public function isShared(User $user, Professional $professional): bool
    {
        return in_array($professional->id, $this->sharedIds($user), true);
    }

    /** Fiche existante de l'espace correspondant à ces coordonnées, ou null. */
    public function match(Organization $org, array $data): ?Professional
    {
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $phone = self::normalizePhone($data['phone'] ?? null);
        $name = mb_strtolower(trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')));

        $candidates = Professional::where('organization_id', $org->id)->get();

        return $candidates->first(fn (Professional $p) => $email !== '' && strtolower((string) $p->email) === $email)
            ?? $candidates->first(fn (Professional $p) => $phone !== null && self::normalizePhone($p->phone) === $phone)
            ?? $candidates->first(fn (Professional $p) => $name !== '' && mb_strtolower($p->fullName()) === $name && (! $email || ! $p->email) && (! $phone || ! $p->phone));
    }

    /**
     * Crée la fiche, ou met à jour celle qui correspond (champs renseignés uniquement :
     * une information absente de la fiche importée n'efface jamais l'existante).
     *
     * @return array{0: Professional, 1: bool} [fiche, créée ?]
     */
    public function upsert(Organization $org, array $data, ?Professional $existing = null): array
    {
        $existing ??= $this->match($org, $data);
        $values = array_filter($data, fn ($v) => $v !== null && $v !== '');
        if ($existing) {
            if ($existing->archived_at) {
                $values['archived_at'] = null; // de nouveau utilisée : la fiche revient dans les listes
            }
            $existing->update($values);

            return [$existing->fresh(), false];
        }
        $professional = new Professional($values);
        $professional->organization_id = $org->id;
        $professional->save();

        return [$professional, true];
    }

    /** « 06 12 34 56 78 », « +33 6 12 34 56 78 » et « 0033612345678 » donnent la même valeur. */
    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '' || strlen($digits) < 6) {
            return null;
        }
        if (str_starts_with($digits, '0033')) {
            $digits = '0'.substr($digits, 4);
        } elseif (str_starts_with($digits, '33') && strlen($digits) === 11) {
            $digits = '0'.substr($digits, 2);
        }

        return $digits;
    }
}

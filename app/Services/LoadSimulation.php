<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\CareCategory;
use App\Models\CareRecord;
use App\Models\DailyLog;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\HorseAccessGrant;
use App\Models\Invitation;
use App\Models\RidingSession;
use App\Models\SimulationBatch;
use App\Models\User;
use App\Models\UserProfile;
use App\Support\Perm;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Simulation de charge depuis l'administration : inscriptions, chevaux et activité de comptes de test,
 * créés par petites étapes (hébergement mutualisé : pas de requête longue), puis supprimés en totalité.
 *
 * Garde-fous : adresses en « @simulation.invalid » (domaine réservé, jamais distribuable, et e-mails bloqués à l'envoi),
 * aucune licence ni abonnement, aucun appel à l'IA, espaces et chevaux marqués « démo » (hors indicateurs).
 */
class LoadSimulation
{
    public const DOMAIN = 'simulation.invalid';

    /** Comptes de test au plus, tous lots confondus. */
    public const MAX_TOTAL = 10000;

    public const MAX_PER_BATCH = 2000;

    /** Une étape s'arrête d'elle-même au-delà de ce temps (limite d'exécution PHP de l'hébergeur). */
    public const STEP_SECONDS = 8;

    public const LIMITS = ['accounts' => [1, self::MAX_PER_BATCH], 'horses' => [1, 3], 'events' => [0, 40], 'sessions' => [0, 40],
        'cares' => [0, 20], 'expenses' => [0, 30], 'logs' => [0, 60], 'past_days' => [0, 365], 'future_days' => [0, 180], 'chunk' => [1, 50], 'shares' => [0, 30]];

    public const DEFAULTS = ['accounts' => 50, 'horses' => 1, 'events' => 8, 'sessions' => 10, 'cares' => 4, 'expenses' => 6, 'logs' => 14,
        'past_days' => 60, 'future_days' => 30, 'chunk' => 10, 'shares' => 0];

    private const FIRST = ['Camille', 'Léa', 'Manon', 'Chloé', 'Inès', 'Jade', 'Louise', 'Emma', 'Lucas', 'Hugo', 'Arthur', 'Jules', 'Clara', 'Zoé', 'Lina', 'Nina', 'Paul', 'Théo', 'Alice', 'Margaux'];

    private const LAST = ['Martin', 'Bernard', 'Dubois', 'Thomas', 'Robert', 'Richard', 'Petit', 'Durand', 'Leroy', 'Moreau', 'Simon', 'Laurent', 'Lefebvre', 'Michel', 'Garcia', 'David', 'Bertrand', 'Roux', 'Vincent', 'Fournier'];

    private const HORSES = ['Tornade', 'Éclair', 'Quartz', 'Ulysse', 'Vanille', 'Caramel', 'Iris', 'Saphir', 'Opale', 'Rubis', 'Galopin', 'Pépite', 'Nuage', 'Hermès', 'Lune', 'Jasmin'];

    /**
     * Partages proposés au compte qui lance la simulation (un cheval de test par partage, profils en alternance) :
     * pour voir l'app avec plusieurs chevaux et des droits différents.
     */
    public static function shareProfiles(): array
    {
        return [
            ['label' => 'Demi-pension', 'perms' => Perm::halfLeasePreset()],
            ['label' => 'Pension (écurie)', 'perms' => Perm::boardingPreset()],
            ['label' => 'Lecture seule', 'perms' => array_values(array_filter(Perm::horseKeys(), fn ($k) => Perm::isRead($k)))],
            ['label' => 'Vétérinaire', 'perms' => [Perm::HORSE_VIEW, Perm::HEALTH_VIEW, Perm::HEALTH_EDIT, Perm::TREATMENTS_VIEW, Perm::DOCUMENTS_VIEW, Perm::CALENDAR_VIEW]],
            ['label' => 'Cavalier', 'perms' => [Perm::HORSE_VIEW, Perm::SESSIONS_VIEW, Perm::SESSIONS_CREATE, Perm::SESSIONS_EDIT_OWN, Perm::CALENDAR_VIEW, Perm::DAILYLOG_CREATE, Perm::COMMENTS_CREATE]],
            ['label' => 'Tous les droits', 'perms' => Perm::horseKeys()],
            ['label' => 'Fiche seule, 30 jours', 'perms' => [Perm::HORSE_VIEW], 'days' => 30],
        ];
    }

    public function __construct(private OrganizationService $organizations, private HorseService $horses) {}

    public static function isSimulated(?User $user): bool
    {
        return $user && ($user->simulation_batch_id !== null || str_ends_with(strtolower((string) $user->email), '@'.self::DOMAIN));
    }

    /** Paramètres bornés (valeurs par défaut pour les champs absents). */
    public static function normalize(array $input): array
    {
        $out = [];
        foreach (self::LIMITS as $key => [$min, $max]) {
            $out[$key] = max($min, min($max, (int) ($input[$key] ?? self::DEFAULTS[$key])));
        }

        return $out;
    }

    public function start(User $admin, array $input): SimulationBatch
    {
        $params = self::normalize($input);
        if (SimulationBatch::where('status', 'running')->exists()) {
            throw ValidationException::withMessages(['accounts' => 'Une simulation est déjà en cours : arrêtez-la ou attendez sa fin.']);
        }
        $existing = User::whereNotNull('simulation_batch_id')->count();
        if ($existing + $params['accounts'] > self::MAX_TOTAL) {
            throw ValidationException::withMessages(['accounts' => 'Au plus '.self::MAX_TOTAL.' comptes de test en tout ('.$existing.' existent déjà) : supprimez d\'anciennes simulations.']);
        }
        $params['shares'] = min($params['shares'], $params['accounts'] * $params['horses']);
        $params['shares_accept'] = (bool) ($input['shares_accept'] ?? false);
        $params['share_to'] = $admin->id;
        // Un seul mot de passe aléatoire (inconnu) par lot : le hachage de chaque compte coûterait plusieurs secondes.
        $params['password_hash'] = Hash::make(Str::random(48));
        $batch = SimulationBatch::create(['created_by' => $admin->id, 'status' => 'running', 'params' => $params, 'counts' => []]);
        Audit::log('simulation.started', $batch, ['accounts' => $params['accounts']]);

        return $batch;
    }

    /** Crée le paquet suivant de comptes (avec chevaux et activité) ; renvoie l'avancement. */
    public function step(SimulationBatch $batch): array
    {
        if ($batch->status !== 'running') {
            return $this->progress($batch);
        }
        $p = $batch->params;
        $t0 = microtime(true);
        $counts = $batch->counts ?? [];
        $made = 0;
        while ($made < $p['chunk'] && $batch->accounts_done < $p['accounts'] && microtime(true) - $t0 < self::STEP_SECONDS) {
            DB::transaction(function () use ($batch, &$counts) {
                $this->account($batch, $batch->accounts_done + 1, $counts);
            });
            $batch->accounts_done++;
            $made++;
        }
        $ms = (int) round((microtime(true) - $t0) * 1000);
        $batch->forceFill([
            'counts' => $counts, 'elapsed_ms' => $batch->elapsed_ms + $ms,
            'status' => $batch->accounts_done >= $p['accounts'] ? 'done' : 'running',
            'finished_at' => $batch->accounts_done >= $p['accounts'] ? now() : null,
        ])->save();

        return $this->progress($batch) + ['step_ms' => $ms, 'step_accounts' => $made];
    }

    public function stop(SimulationBatch $batch): void
    {
        if ($batch->status === 'running') {
            $batch->update(['status' => 'stopped']);
        }
    }

    public function resume(SimulationBatch $batch): void
    {
        if ($batch->status === 'stopped' && ! SimulationBatch::where('status', 'running')->exists()) {
            $batch->update(['status' => 'running']);
        }
    }

    /**
     * Supprime une partie des comptes du lot et tout ce qui leur appartient (espaces, chevaux et leurs données en cascade).
     * Seuls les comptes du lot, en « @simulation.invalid », et les espaces dont ils sont propriétaires sont touchés.
     */
    public function purgeStep(SimulationBatch $batch, int $limit = 25): array
    {
        if (! in_array($batch->status, ['purging', 'purged'], true)) {
            $batch->update(['status' => 'purging']);
        }
        $t0 = microtime(true);
        $users = User::withTrashed()->where('simulation_batch_id', $batch->id)->where('email', 'like', '%@'.self::DOMAIN)->limit($limit)->pluck('id');
        if ($users->isNotEmpty()) {
            DB::transaction(function () use ($users) {
                $orgs = DB::table('organizations')->whereIn('owner_id', $users)->pluck('id');
                $horses = DB::table('horses')->whereIn('organization_id', $orgs)->pluck('id');
                DB::table('audit_logs')->whereIn('organization_id', $orgs)->orWhereIn('user_id', $users)->delete();
                DB::table('notifications')->where('notifiable_type', (new User)->getMorphClass())->whereIn('notifiable_id', $users)->delete();
                // Suppression en base (sans corbeille) : les données des chevaux et des espaces partent en cascade.
                DB::table('calendar_events')->whereIn('horse_id', $horses)->orWhereIn('organization_id', $orgs)->delete();
                DB::table('horses')->whereIn('id', $horses)->delete();
                DB::table('organizations')->whereIn('id', $orgs)->delete();
                DB::table('users')->whereIn('id', $users)->delete();
            });
        }
        $left = User::withTrashed()->where('simulation_batch_id', $batch->id)->count();
        if ($left === 0) {
            $batch->update(['status' => 'purged', 'purged_at' => now()]);
            Audit::log('simulation.purged', $batch, ['accounts' => $batch->accounts_done]);
        }

        return ['deleted' => $users->count(), 'left' => $left, 'status' => $batch->status, 'step_ms' => (int) round((microtime(true) - $t0) * 1000)];
    }

    public function progress(SimulationBatch $batch): array
    {
        return ['id' => $batch->id, 'status' => $batch->status, 'label' => SimulationBatch::STATUSES[$batch->status] ?? $batch->status,
            'done' => $batch->accounts_done, 'total' => $batch->accounts(), 'counts' => $batch->counts ?? [], 'elapsed_ms' => $batch->elapsed_ms];
    }

    /** Une inscription complète : compte, espace personnel, chevaux (dans la limite de l'offre) et leur activité. */
    private function account(SimulationBatch $batch, int $n, array &$counts): void
    {
        $p = $batch->params;
        $first = self::FIRST[array_rand(self::FIRST)];
        $last = self::LAST[array_rand(self::LAST)];
        $user = new User(['name' => $first.' '.mb_strtoupper($last), 'email' => 'sim'.$batch->id.'-'.$n.'-'.Str::lower(Str::random(5)).'@'.self::DOMAIN, 'terms_accepted_at' => now()]);
        $user->forceFill(['password' => $p['password_hash'], 'email_verified_at' => now(), 'simulation_batch_id' => $batch->id]);
        $user->saveQuietly();
        UserProfile::create(['user_id' => $user->id]);
        $org = $this->organizations->createPersonal($user);
        $org->forceFill(['is_demo' => true])->save();
        $this->bump($counts, 'accounts');

        for ($h = 0; $h < $p['horses']; $h++) {
            try {
                $horse = $this->horses->create($org, $user, ['official_name' => mb_strtoupper(self::HORSES[array_rand(self::HORSES)]).' SIM '.$batch->id.'-'.$n.'-'.($h + 1), 'sex' => ['female', 'gelding', 'male'][random_int(0, 2)]], true);
            } catch (ValidationException) {
                $this->bump($counts, 'horses_refused'); // limite de chevaux de l'offre gratuite

                continue;
            }
            $horse->forceFill(['is_demo' => true])->save();
            $this->bump($counts, 'horses');
            $this->activity($horse, $user, $p, $counts);
            if (($counts['shares'] ?? 0) < ($p['shares'] ?? 0)) {
                $this->share($horse, $user, $p, $counts);
            }
        }
    }

    private function activity($horse, User $user, array $p, array &$counts): void
    {
        $date = fn () => now()->addMinutes(random_int(-$p['past_days'] * 1440, max(1, $p['future_days']) * 1440))->setSecond(0)->roundMinutes(15);
        $types = array_values(array_diff(array_keys(CalendarEvent::TYPES), ['session', 'treatment']));
        for ($i = 0; $i < $p['events']; $i++) {
            $at = $date();
            $event = new CalendarEvent(['horse_id' => $horse->id, 'type' => $types[array_rand($types)], 'title' => 'Simulation '.($i + 1), 'starts_at' => $at,
                'duration_minutes' => [30, 45, 60, 90][random_int(0, 3)], 'status' => $at->isPast() ? ['done', 'planned', 'cancelled'][random_int(0, 2)] : ['planned', 'confirmed'][random_int(0, 1)]]);
            $event->forceFill(['uuid' => (string) Str::uuid(), 'organization_id' => $horse->organization_id, 'created_by' => $user->id])->save();
            $this->bump($counts, 'events');
        }
        $sessionTypes = array_keys(RidingSession::TYPES);
        for ($i = 0; $i < $p['sessions']; $i++) {
            $at = $date();
            RidingSession::forceCreate(['horse_id' => $horse->id, 'created_by' => $user->id, 'rider_id' => $user->id, 'scheduled_at' => $at,
                'planned_minutes' => [30, 45, 60][random_int(0, 2)], 'session_type' => $sessionTypes[array_rand($sessionTypes)],
                'status' => $at->isPast() ? (random_int(0, 3) ? 'completed' : 'planned') : 'planned', 'objective' => 'Séance simulée']);
            $this->bump($counts, 'sessions');
        }
        $categories = CareCategory::pluck('id')->all();
        for ($i = 0; $i < $p['cares'] && $categories; $i++) {
            CareRecord::forceCreate(['horse_id' => $horse->id, 'care_category_id' => $categories[array_rand($categories)], 'author_id' => $user->id,
                'performed_at' => now()->subDays(random_int(0, max(1, $p['past_days']))), 'reason' => 'Soin simulé', 'cost' => random_int(30, 250)]);
            $this->bump($counts, 'cares');
        }
        $expenseCategories = ExpenseCategory::pluck('id')->all();
        for ($i = 0; $i < $p['expenses'] && $expenseCategories; $i++) {
            $spent = now()->subDays(random_int(0, max(1, $p['past_days'])));
            Expense::forceCreate(['organization_id' => $horse->organization_id, 'author_id' => $user->id, 'horse_id' => $horse->id,
                'expense_category_id' => $expenseCategories[array_rand($expenseCategories)], 'amount' => random_int(10, 400) + random_int(0, 99) / 100,
                'spent_on' => $spent->toDateString(), 'paid_on' => $spent->toDateString()]);
            $this->bump($counts, 'expenses');
        }
        for ($i = 0; $i < $p['logs']; $i++) {
            DailyLog::create(['horse_id' => $horse->id, 'author_id' => $user->id, 'logged_at' => Carbon::today()->subDays($i)->setTime(random_int(7, 20), 0),
                'appetite' => ['good', 'good', 'reduced'][random_int(0, 2)], 'general_state' => ['good', 'good', 'average'][random_int(0, 2)]]);
            $this->bump($counts, 'logs');
        }
    }

    /**
     * Partage d'un cheval de test vers le compte qui lance la simulation : invitation en attente (à accepter depuis l'accueil)
     * ou accès accordé d'office. Créé directement (les comptes de test sont sur l'offre gratuite, sans partage) et sans
     * e-mail ni notification ; les droits reçus sont ensuite appliqués normalement.
     */
    private function share($horse, User $owner, array $p, array &$counts): void
    {
        $to = User::find($p['share_to'] ?? null);
        if (! $to) {
            return;
        }
        $profiles = self::shareProfiles();
        $profile = $profiles[($counts['shares'] ?? 0) % count($profiles)];
        $expires = isset($profile['days']) ? now()->addDays($profile['days']) : null;
        $invitation = Invitation::create([
            'type' => 'horse_access', 'email' => strtolower($to->email), 'horse_id' => $horse->id, 'organization_id' => $horse->organization_id,
            'permissions' => array_values(array_unique([Perm::HORSE_VIEW, ...$profile['perms']])), 'access_expires_at' => $expires,
            'invited_by' => $owner->id, 'token_hash' => hash('sha256', Str::random(48)), 'expires_at' => now()->addDays(14),
        ]);
        if (! empty($p['shares_accept'])) {
            HorseAccessGrant::create(['horse_id' => $horse->id, 'user_id' => $to->id, 'permissions' => $invitation->permissions, 'expires_at' => $expires,
                'granted_by' => $owner->id, 'invitation_id' => $invitation->id, 'label' => $profile['label'].' (simulation)']);
            $invitation->update(['accepted_at' => now(), 'accepted_by' => $to->id]);
        }
        // Nom d'usage parlant : on voit tout de suite quels droits porte chaque cheval partagé.
        $horse->forceFill(['usual_name' => Str::before($horse->official_name, ' SIM').' · '.$profile['label']])->saveQuietly();
        $this->bump($counts, 'shares');
    }

    private function bump(array &$counts, string $key): void
    {
        $counts[$key] = ($counts[$key] ?? 0) + 1;
    }
}

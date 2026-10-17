<?php

namespace App\Services;

use App\Models\Horse;
use App\Models\RidingSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Throwable;

/**
 * Simulation de navigation par rendu interne : dans une requête du super-admin, le serveur génère de vraies pages
 * de l'application (routes, middlewares, contrôleurs, vues) comme les verrait un compte de test, et mesure chacune
 * (durée, requêtes en base, taille). Aucune porte d'entrée nouvelle : rien n'est accessible depuis l'extérieur.
 *
 * Garde-fous : comptes de test uniquement, pages en lecture (GET) choisies par le serveur, session en mémoire
 * (rien d'enregistré), l'administrateur retrouve sa session et son identité après chaque page.
 */
class NavigationSimulation
{
    /** Une étape s'arrête d'elle-même au-delà de ce temps (limite d'exécution de l'hébergeur). */
    public const STEP_SECONDS = 6;

    public const MAX_PAGES = 25;

    /** Requêtes en base depuis le début de l'étape (écouteur posé une fois). */
    private int $queries = 0;

    private bool $listening = false;

    /** Rend jusqu'à $pages pages au hasard parmi les comptes de test ; renvoie la mesure de chacune. */
    public function step(int $pages): array
    {
        $users = User::whereNotNull('simulation_batch_id')->where('email', 'like', '%@'.LoadSimulation::DOMAIN)->inRandomOrder()->limit(10)->get();
        if ($users->isEmpty()) {
            return [];
        }
        if (! $this->listening) {
            DB::listen(fn () => $this->queries++);
            $this->listening = true;
        }
        $t0 = microtime(true);
        $visits = [];
        for ($i = 0; $i < min($pages, self::MAX_PAGES) && microtime(true) - $t0 < self::STEP_SECONDS; $i++) {
            $user = $users->random();
            $plan = $this->pages($user);
            $label = array_rand($plan);
            $visits[] = ['label' => $label] + $this->render($user, $plan[$label]);
        }

        return $visits;
    }

    /** Pages qu'un utilisateur ouvre le plus, pour ce compte de test. */
    public function pages(User $user): array
    {
        $horse = Horse::whereIn('organization_id', DB::table('organizations')->where('owner_id', $user->id)->select('id'))->first();
        $session = $horse ? RidingSession::where('horse_id', $horse->id)->inRandomOrder()->value('id') : null;

        return array_filter([
            'Accueil' => route('dashboard', [], false),
            'Mes chevaux' => route('horses.index', [], false),
            'Fiche du cheval' => $horse ? route('horses.show', $horse, false) : null,
            'Agenda' => route('calendar.index', [], false),
            'Séances' => route('sessions.index', [], false),
            'Séance' => $session ? route('sessions.show', $session, false) : null,
            'Santé' => route('health.index', [], false),
            'Carnet de santé' => $horse ? route('horses.care.index', $horse, false) : null,
            'Suivi quotidien' => $horse ? route('horses.logs.index', $horse, false) : null,
            'Budget' => route('budget.index', [], false),
            'Ma semaine' => route('digest.show', [], false),
            'Exercices' => route('exercises.index', [], false),
        ]);
    }

    /** Génère une page comme le compte de test, puis rétablit la requête et l'identité de l'administrateur. */
    public function render(User $user, string $path): array
    {
        abort_unless(LoadSimulation::isSimulated($user) && $user->simulation_batch_id !== null, 403);
        $original = app('request');
        $admin = Auth::guard('web')->user();
        $driver = config('session.driver');
        $before = $this->queries;

        $sub = Request::create($path, 'GET', [], [], [], ['HTTP_ACCEPT' => 'text/html'] + array_intersect_key($original->server->all(), array_flip(['HTTP_HOST', 'SERVER_NAME', 'SERVER_PORT', 'HTTPS'])));
        $t0 = microtime(true);
        try {
            // Session en mémoire : la page simulée n'écrit aucune session en base.
            config(['session.driver' => 'array']);
            app()->instance('request', $sub);
            Facade::clearResolvedInstance('request');
            // Compte rechargé à chaque page, comme après une vraie connexion (relations usuelles préchargées).
            $user = User::with(['profile', 'memberships.organization', 'memberships.role'])->findOrFail($user->id);
            Auth::guard('web')->setUser($user);
            app(HorseAccess::class)->flush();
            app(Entitlements::class)->flush();
            app(ToValidate::class)->flush();
            app(CompetitionVaccines::class)->flush();
            $response = app('router')->dispatch($sub);
            $status = $response->getStatusCode();
            $bytes = strlen((string) $response->getContent());
            $error = isset($response->exception) && $response->exception ? class_basename($response->exception).' : '.mb_substr($response->exception->getMessage(), 0, 300) : null;
        } catch (Throwable $e) {
            report($e);
            $status = 500;
            $bytes = 0;
            $error = class_basename($e).' : '.mb_substr($e->getMessage(), 0, 300);
        } finally {
            $ms = round((microtime(true) - $t0) * 1000, 1);
            $queries = $this->queries - $before;
            config(['session.driver' => $driver]);
            app()->instance('request', $original);
            Facade::clearResolvedInstance('request');
            $admin ? Auth::guard('web')->setUser($admin) : Auth::guard('web')->forgetUser();
            app(HorseAccess::class)->flush();
            app(Entitlements::class)->flush();
            app(ToValidate::class)->flush();
            app(CompetitionVaccines::class)->flush();
        }

        return ['path' => $path, 'status' => $status, 'ms' => $ms, 'queries' => $queries, 'kb' => round($bytes / 1024, 1)] + ($status >= 500 && $error ? ['error' => $error] : []);
    }
}

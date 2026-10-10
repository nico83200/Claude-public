<?php

namespace App\Http\Controllers;

use App\Models\ApplicationSetting;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\Audit;
use App\Services\EnvEditor;
use App\Services\OrganizationService;
use App\Support\Installation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use PDO;
use Throwable;

/**
 * Installeur web du premier lancement :
 *  1. prérequis serveur  2. base MySQL  3. application  4. super-administrateur  5. terminé.
 * Accessible uniquement tant que storage/app/installed.json n'existe pas.
 */
class InstallController extends Controller
{
    public function __construct(private EnvEditor $env) {}

    public function welcome(Request $request)
    {
        return view('install.welcome', [
            'requirements' => Installation::requirements(),
            'ok' => Installation::requirementsMet(),
            'needsKey' => filled(config('equine.install_key')) && ! $request->session()->get('install.unlocked'),
        ]);
    }

    public function unlock(Request $request)
    {
        $key = (string) config('equine.install_key');
        if ($key === '' || hash_equals($key, (string) $request->input('install_key'))) {
            $request->session()->put('install.unlocked', true);

            return redirect()->route('install.database');
        }

        return back()->withErrors(['install_key' => 'Clé d\'installation incorrecte.']);
    }

    public function database(Request $request)
    {
        $this->guard($request);

        return view('install.database', [
            'defaults' => [
                'host' => $this->env->get('DB_HOST') ?: '127.0.0.1', 'port' => $this->env->get('DB_PORT') ?: '3306',
                'database' => $this->env->get('DB_DATABASE') ?: 'jackcie', 'username' => $this->env->get('DB_USERNAME') ?: '',
            ],
            'envWritable' => $this->env->writable(),
        ]);
    }

    public function saveDatabase(Request $request)
    {
        $this->guard($request);
        $data = $request->validate([
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'database' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'create_database' => ['nullable', 'boolean'],
        ], ['database.regex' => 'Lettres, chiffres, tirets et soulignés uniquement.']);

        // 1. Connexion au serveur (sans base) pour un diagnostic précis
        try {
            $pdo = new PDO("mysql:host={$data['host']};port={$data['port']};charset=utf8mb4", $data['username'], $data['password'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['host' => 'Connexion au serveur MySQL impossible : '.$this->explain($e)]);
        }
        $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
        $isMaria = str_contains(strtolower($version), 'mariadb');
        if (version_compare(preg_replace('/[^0-9.].*$/', '', $version), $isMaria ? '10.6.0' : '8.0.0', '<')) {
            return back()->withInput()->withErrors(['host' => "Version non prise en charge ($version) : MySQL 8.0+ ou MariaDB 10.6+ requis."]);
        }

        // 2. Base de données : création éventuelle, puis vérification d'accès
        $exists = (bool) $pdo->query('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '.$pdo->quote($data['database']))->fetchColumn();
        if (! $exists) {
            if (! $request->boolean('create_database')) {
                return back()->withInput()->withErrors(['database' => 'Cette base n\'existe pas. Créez-la chez votre hébergeur ou cochez « Créer la base ».']);
            }
            try {
                $pdo->exec('CREATE DATABASE `'.$data['database'].'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            } catch (Throwable $e) {
                return back()->withInput()->withErrors(['database' => 'Création de la base refusée (droits insuffisants) : créez-la depuis l\'interface de votre hébergeur.']);
            }
        }

        // 3. Configuration de l'application sur cette base
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.host' => $data['host'], 'database.connections.mysql.port' => $data['port'],
            'database.connections.mysql.database' => $data['database'], 'database.connections.mysql.username' => $data['username'],
            'database.connections.mysql.password' => $data['password'] ?? '',
        ]);
        DB::purge('mysql');

        try {
            DB::connection('mysql')->getPdo();
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['database' => 'Accès à la base refusé : '.$this->explain($e)]);
        }

        // Une installation existante n'est jamais écrasée.
        $existingUsers = 0;
        try {
            $existingUsers = DB::connection('mysql')->getSchemaBuilder()->hasTable('users') ? DB::connection('mysql')->table('users')->count() : 0;
        } catch (Throwable) {
        }

        $envValues = ['DB_CONNECTION' => 'mysql', 'DB_HOST' => $data['host'], 'DB_PORT' => (string) $data['port'], 'DB_DATABASE' => $data['database'], 'DB_USERNAME' => $data['username'], 'DB_PASSWORD' => $data['password'] ?? ''];
        if (! $this->saveEnv($envValues)) {
            $request->session()->put('install.env_manual', array_merge($request->session()->get('install.env_manual', []), $envValues));
        }
        $request->session()->put('install.db', $envValues);

        // 4. Création des tables et des données de référence
        @set_time_limit(300);
        try {
            Artisan::call('migrate', ['--force' => true, '--database' => 'mysql']);
            Artisan::call('db:seed', ['--force' => true, '--class' => 'Database\\Seeders\\DatabaseSeeder']);
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->withErrors(['database' => 'Échec de la création des tables : '.$this->explain($e)]);
        }

        $request->session()->put('install.step', 'application');

        return redirect()->route('install.application')->with('success', 'Base de données prête ('.$version.').'.($existingUsers ? " Base existante détectée ($existingUsers compte(s)) : aucune donnée n'a été supprimée." : ''));
    }

    public function application(Request $request)
    {
        $this->guard($request, 'application');

        return view('install.application', ['url' => $request->getSchemeAndHttpHost()]);
    }

    public function saveApplication(Request $request)
    {
        $this->guard($request, 'application');
        $data = $request->validate([
            'app_name' => ['required', 'string', 'max:60'],
            'app_url' => ['required', 'url', 'max:255'],
            'support_email' => ['nullable', 'email'],
            'environment' => ['required', 'in:production,local'],
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['nullable', 'integer'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_from' => ['nullable', 'email', 'required_with:mail_host'],
            'stripe_key' => ['nullable', 'string', 'starts_with:pk_'],
            'stripe_secret' => ['nullable', 'string', 'starts_with:sk_,rk_'],
            'stripe_webhook_secret' => ['nullable', 'string', 'starts_with:whsec_'],
        ]);
        $this->useSessionDatabase($request);

        $https = str_starts_with($data['app_url'], 'https://');
        $values = [
            'APP_NAME' => $data['app_name'], 'APP_URL' => rtrim($data['app_url'], '/'),
            'APP_ENV' => $data['environment'], 'APP_DEBUG' => false,
            'SESSION_SECURE_COOKIE' => $https, 'SESSION_DRIVER' => 'database', 'CACHE_STORE' => 'database', 'QUEUE_CONNECTION' => 'database',
            'LOG_CHANNEL' => 'daily', 'LOG_LEVEL' => 'warning',
        ];
        if (filled($data['mail_host'] ?? null)) {
            $values += ['MAIL_MAILER' => 'smtp', 'MAIL_HOST' => $data['mail_host'], 'MAIL_PORT' => (string) ($data['mail_port'] ?: 587), 'MAIL_USERNAME' => $data['mail_username'] ?? '', 'MAIL_PASSWORD' => $data['mail_password'] ?? '', 'MAIL_FROM_ADDRESS' => $data['mail_from']];
        }
        foreach (['stripe_key' => 'STRIPE_KEY', 'stripe_secret' => 'STRIPE_SECRET', 'stripe_webhook_secret' => 'STRIPE_WEBHOOK_SECRET'] as $field => $key) {
            if (filled($data[$field] ?? null)) {
                $values[$key] = $data[$field];
            }
        }
        if (! $this->saveEnv($values)) {
            $request->session()->put('install.env_manual', array_merge($request->session()->get('install.env_manual', []), $values));
        }

        ApplicationSetting::put('app_name', $data['app_name']);
        if (filled($data['support_email'] ?? null)) {
            ApplicationSetting::put('support_email', $data['support_email']);
        }
        $request->session()->put('install.step', 'admin');

        return redirect()->route('install.admin');
    }

    public function admin(Request $request)
    {
        $this->guard($request, 'admin');

        return view('install.admin');
    }

    public function saveAdmin(Request $request, OrganizationService $organizations)
    {
        $this->guard($request, 'admin');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
        ], ['password.min' => 'Au moins 12 caractères, avec majuscules, minuscules, chiffres et symboles.']);
        $this->useSessionDatabase($request);

        $user = DB::transaction(function () use ($data, $organizations) {
            $email = strtolower($data['email']);
            $user = User::where('email', $email)->first();
            if ($user) {
                $user->forceFill(['name' => $data['name'], 'password' => $data['password']])->save();
            } else {
                $user = User::create(['name' => $data['name'], 'email' => $email, 'password' => $data['password'], 'terms_accepted_at' => now()]);
                UserProfile::create(['user_id' => $user->id]);
            }
            $user->forceFill(['is_super_admin' => true, 'email_verified_at' => $user->email_verified_at ?? now()])->save();
            if (! $user->personalOrganization()) {
                $organizations->createPersonal($user);
            }

            return $user;
        });
        Audit::log('admin.super_admin_created', $user, ['via' => 'installer']);

        $manual = $request->session()->get('install.env_manual', []);
        if ($manual && ! $this->env->get('APP_KEY')) {
            $manual = ['APP_KEY' => config('app.key')] + $manual;
        }
        // Le pilote de session change après l'installation : le récapitulatif passe par un jeton à usage unique.
        $token = Str::random(40);
        Cache::store('file')->put('install-done:'.$token, ['email' => $user->email, 'manual_env' => $manual], now()->addMinutes(30));

        Installation::markInstalled(['by' => $user->email]);
        $request->session()->forget(['install.step', 'install.db', 'install.unlocked', 'install.env_manual']);
        try {
            Artisan::call('config:clear');
        } catch (Throwable) {
        }

        return redirect()->to(URL::temporarySignedRoute('install.done', now()->addMinutes(30), ['t' => $token]));
    }

    /** Page finale : affichée une seule fois, juste après le verrouillage. */
    public function done(Request $request)
    {
        $info = Cache::store('file')->get('install-done:'.$request->query('t'));
        abort_unless(is_array($info), 404);

        return view('install.done', [
            'email' => $info['email'],
            'manualEnv' => $info['manual_env'],
            'cron' => '* * * * * cd '.base_path().' && php artisan schedule:run >> /dev/null 2>&1',
            'webhook' => rtrim(config('app.url'), '/').'/stripe/webhook',
        ]);
    }

    // ------------------------------------------------------------------

    private function guard(Request $request, ?string $step = null): void
    {
        if (filled(config('equine.install_key')) && ! $request->session()->get('install.unlocked')) {
            abort(redirect()->route('install'));
        }
        if ($step === 'application' && ! in_array($request->session()->get('install.step'), ['application', 'admin'], true)) {
            abort(redirect()->route('install.database'));
        }
        if ($step === 'admin' && $request->session()->get('install.step') !== 'admin') {
            abort(redirect()->route('install.database'));
        }
    }

    /** Le .env vient d'être écrit mais n'est rechargé qu'à la requête suivante ; on réapplique les paramètres de session. */
    private function useSessionDatabase(Request $request): void
    {
        $db = $request->session()->get('install.db');
        if ($db) {
            config([
                'database.default' => 'mysql',
                'database.connections.mysql.host' => $db['DB_HOST'], 'database.connections.mysql.port' => $db['DB_PORT'],
                'database.connections.mysql.database' => $db['DB_DATABASE'], 'database.connections.mysql.username' => $db['DB_USERNAME'],
                'database.connections.mysql.password' => $db['DB_PASSWORD'],
            ]);
            DB::purge('mysql');
        }
    }

    private function saveEnv(array $values): bool
    {
        try {
            if (! $this->env->writable()) {
                return false;
            }
            $this->env->set($values);

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    private function explain(Throwable $e): string
    {
        $m = $e->getMessage();

        return match (true) {
            str_contains($m, 'Access denied') => 'identifiant ou mot de passe refusé.',
            str_contains($m, 'Unknown database') => 'base inconnue.',
            str_contains($m, 'getaddrinfo') || str_contains($m, 'Name or service not known') => 'hôte introuvable.',
            str_contains($m, 'Connection refused') || str_contains($m, 'timed out') => 'serveur injoignable (hôte ou port incorrect).',
            default => mb_substr(preg_replace('/\(Connection:.*$/s', '', $m), 0, 200),
        };
    }
}

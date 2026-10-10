<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Installation;
use Dotenv\Dotenv;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;

/**
 * Installeur web sur une base MySQL vierge (equine_install_test), avec un .env
 * et un fichier verrou temporaires : n'affecte ni la base de tests ni le .env du projet.
 */
class InstallerTest extends BaseTestCase
{
    private string $envDir;

    private string $db = 'equine_install_test';

    private ?string $savedLock = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->envDir = sys_get_temp_dir().'/jackcie-install-'.uniqid();
        File::ensureDirectoryExists($this->envDir);
        File::copy(base_path('.env.example'), $this->envDir.'/.env');
        $this->app->useEnvironmentPath($this->envDir);
        config(['equine.installed' => false]);
        // Le verrou réel du poste est sauvegardé puis restauré.
        $this->savedLock = File::exists(Installation::lockPath()) ? File::get(Installation::lockPath()) : null;
        File::delete(Installation::lockPath());
        $this->dropDatabase();
    }

    protected function tearDown(): void
    {
        $this->dropDatabase();
        File::deleteDirectory($this->envDir);
        File::delete(Installation::lockPath());
        if ($this->savedLock !== null) {
            File::put(Installation::lockPath(), $this->savedLock);
        }
        parent::tearDown();
    }

    private function dropDatabase(): void
    {
        $pdo = new \PDO('mysql:host=127.0.0.1;port=3306', 'equine', 'secret');
        $pdo->exec("DROP DATABASE IF EXISTS `{$this->db}`");
    }

    private function dbForm(array $over = []): array
    {
        return array_merge(['host' => '127.0.0.1', 'port' => 3306, 'database' => $this->db, 'username' => 'equine', 'password' => 'secret', 'create_database' => '1'], $over);
    }

    public function test_every_page_redirects_to_installer_before_installation(): void
    {
        $this->get('/')->assertRedirect('/install');
        $this->get('/login')->assertRedirect('/install');
        $this->get('/install')->assertOk()->assertSee('Vérification du serveur')->assertSee('pdo_mysql');
    }

    public function test_full_installation_flow_then_installer_is_locked(): void
    {
        $this->get('/install/application')->assertRedirect('/install/base-de-donnees');

        $this->post('/install/base-de-donnees', $this->dbForm())->assertRedirect('/install/application');
        $this->assertSame($this->db, $this->envValue('DB_DATABASE'));
        $this->assertTrue(DB::connection('mysql')->getSchemaBuilder()->hasTable('horses'));
        $this->assertGreaterThan(0, DB::connection('mysql')->table('subscription_plans')->count());

        $this->post('/install/application', ['app_name' => 'Jack&Cie', 'app_url' => 'https://app.jackcie.fr', 'environment' => 'production', 'support_email' => 'aide@jackcie.fr'])->assertRedirect('/install/administrateur');
        $this->assertSame('Jack&Cie', $this->envValue('APP_NAME'));
        $this->assertSame('https://app.jackcie.fr', $this->envValue('APP_URL'));
        $this->assertSame('true', $this->envValue('SESSION_SECURE_COOKIE'));

        $this->post('/install/administrateur', ['name' => 'Admin', 'email' => 'Admin@JackCie.fr', 'password' => 'faible', 'password_confirmation' => 'faible'])->assertSessionHasErrors('password');
        $res = $this->post('/install/administrateur', ['name' => 'Admin', 'email' => 'Admin@JackCie.fr', 'password' => 'Tr3s-Solide!Mdp', 'password_confirmation' => 'Tr3s-Solide!Mdp']);
        $res->assertRedirect();
        $this->assertStringContainsString('/install/termine', $res->headers->get('Location'));

        $admin = User::where('email', 'admin@jackcie.fr')->firstOrFail();
        $this->assertTrue($admin->is_super_admin);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertNotNull($admin->personalOrganization());
        $this->assertFileExists(Installation::lockPath());

        // Le récapitulatif s'affiche une fois via le lien signé ; l'installeur est ensuite fermé.
        config(['equine.installed' => null]);
        $this->get($res->headers->get('Location'))->assertOk()->assertSee('Installation terminée')->assertSee('schedule:run');
        $this->get('/install')->assertNotFound();
        $this->get('/install/base-de-donnees')->assertNotFound();
        $this->post('/install/administrateur', ['name' => 'Pirate', 'email' => 'pirate@x.fr', 'password' => 'Tr3s-Solide!Mdp', 'password_confirmation' => 'Tr3s-Solide!Mdp'])->assertNotFound();
        $this->get(URL::temporarySignedRoute('install.done', now()->addMinute(), ['t' => 'inconnu']))->assertNotFound();
        $this->assertSame(1, User::where('is_super_admin', true)->count());
    }

    public function test_wrong_credentials_and_missing_database_are_reported(): void
    {
        $this->post('/install/base-de-donnees', $this->dbForm(['password' => 'mauvais']))->assertSessionHasErrors(['host']);
        $this->post('/install/base-de-donnees', $this->dbForm(['create_database' => null]))->assertSessionHasErrors(['database']);
        $this->post('/install/base-de-donnees', $this->dbForm(['database' => 'pas;valide']))->assertSessionHasErrors(['database']);
        $this->assertFileDoesNotExist(Installation::lockPath());
    }

    public function test_install_key_protects_the_installer_when_configured(): void
    {
        config(['equine.install_key' => 'cle-secrete']);
        $this->get('/install')->assertSee('Clé d\'installation');
        $this->get('/install/base-de-donnees')->assertRedirect('/install');
        $this->post('/install/deverrouiller', ['install_key' => 'faux'])->assertSessionHasErrors('install_key');
        $this->post('/install/deverrouiller', ['install_key' => 'cle-secrete'])->assertRedirect('/install/base-de-donnees');
        $this->get('/install/base-de-donnees')->assertOk();
    }

    public function test_existing_database_is_preserved(): void
    {
        $this->post('/install/base-de-donnees', $this->dbForm())->assertRedirect();
        DB::connection('mysql')->table('application_settings')->insert(['key' => 'garde', 'value' => '"oui"', 'created_at' => now(), 'updated_at' => now()]);
        // Relancer l'étape base de données ne supprime rien.
        $this->post('/install/base-de-donnees', $this->dbForm())->assertRedirect('/install/application');
        $this->assertSame(1, DB::connection('mysql')->table('application_settings')->where('key', 'garde')->count());
    }

    private function envValue(string $key): ?string
    {
        return Dotenv::parse(File::get($this->envDir.'/.env'))[$key] ?? null;
    }
}

<?php

namespace Tests\Feature;

use App\Models\AppUpdate;
use App\Models\User;
use App\Services\Updates\Updater;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use ZipArchive;

class UpdateTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir().'/jc-update-'.Str::random(8);
        mkdir($this->tmp.'/target/app', 0755, true);
        mkdir($this->tmp.'/target/storage/app', 0755, true);
        mkdir($this->tmp.'/work', 0755, true);
        file_put_contents($this->tmp.'/target/VERSION', "2.1.0\n");
        file_put_contents($this->tmp.'/target/.env', 'APP_KEY=secret-du-site');
        file_put_contents($this->tmp.'/target/storage/app/photo.jpg', 'photo du cheval');
        file_put_contents($this->tmp.'/target/app/Ancien.php', '<?php // ancien');
    }

    protected function tearDown(): void
    {
        if (app()->isDownForMaintenance()) {
            Artisan::call('up');
        }
        exec('rm -rf '.escapeshellarg($this->tmp));
        parent::tearDown();
    }

    private function updater(): Updater
    {
        return new Updater($this->tmp.'/target', $this->tmp.'/work');
    }

    /** Paquet de distribution minimal ; $files remplace / complète le contenu. */
    private function package(string $version, array $files = [], string $root = 'jackcie/'): string
    {
        $path = $this->tmp.'/jackcie-'.$version.'-'.Str::random(4).'.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $base = ['VERSION' => $version, 'artisan' => '#!/usr/bin/env php', 'composer.json' => '{}', 'vendor/autoload.php' => '<?php', 'app/Support/Installation.php' => '<?php',
            'public/index.php' => '<?php', 'bootstrap/app.php' => '<?php'];
        foreach (array_merge($base, $files) as $name => $content) {
            if ($content !== null) {
                $zip->addFromString(str_starts_with($name, '/') || str_starts_with($name, '..') ? $name : $root.$name, $content);
            }
        }
        $zip->close();

        return $path;
    }

    public function test_package_inspection_rejects_unsafe_or_incomplete_archives(): void
    {
        $u = $this->updater();
        $this->assertSame('2.2.0', $u->inspect($this->package('2.2.0'))['version']);
        foreach ([
            $this->package('2.2.0', ['../evil.php' => 'x']),
            $this->package('2.2.0', ['app/../../evil.php' => 'x']),
            $this->package('2.2.0', ['vendor/autoload.php' => null]),
            $this->package('2.2.0', [], 'autre/'),
            $this->package('version-x'),
        ] as $bad) {
            try {
                $u->inspect($bad);
                $this->fail('Paquet accepté à tort : '.$bad);
            } catch (\RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->expectException(\RuntimeException::class);
        file_put_contents($this->tmp.'/pas-un-zip.zip', 'texte');
        $u->inspect($this->tmp.'/pas-un-zip.zip');
    }

    public function test_apply_replaces_code_but_never_env_or_storage(): void
    {
        $pkg = $this->package('2.2.0', ['.env' => 'APP_KEY=ecrase', 'storage/app/photo.jpg' => 'remplacée', 'app/Nouveau.php' => '<?php // nouveau', 'app/Ancien.php' => '<?php // mis à jour']);
        $update = AppUpdate::create(['status' => 'verified', 'package_path' => $pkg, 'package_size' => filesize($pkg)]);
        $u = $this->updater();
        $u->apply($update, ['maintenance' => false, 'backup_db' => false]);
        $this->assertSame('migrating', $update->fresh()->status);
        $u->finalize($update->fresh(), migrate: false);

        $t = $this->tmp.'/target';
        $this->assertSame('done', $update->fresh()->status);
        $this->assertSame('2.2.0', trim(file_get_contents($t.'/VERSION')));
        $this->assertSame('<?php // nouveau', file_get_contents($t.'/app/Nouveau.php'));
        $this->assertSame('<?php // mis à jour', file_get_contents($t.'/app/Ancien.php'));
        $this->assertSame('APP_KEY=secret-du-site', file_get_contents($t.'/.env'));
        $this->assertSame('photo du cheval', file_get_contents($t.'/storage/app/photo.jpg'));
        $this->assertSame('2.1.0', $update->fresh()->from_version);

        // Sauvegarde du code : sans .env ni storage
        $zip = new ZipArchive;
        $zip->open($update->fresh()->backup_code_path);
        $this->assertSame('<?php // ancien', $zip->getFromName('jackcie/app/Ancien.php'));
        $this->assertFalse($zip->getFromName('jackcie/.env'));
        $this->assertFalse($zip->getFromName('jackcie/storage/app/photo.jpg'));
        $zip->close();

        // Restauration manuelle
        $u->restore($update->fresh());
        $this->assertSame('2.1.0', trim(file_get_contents($t.'/VERSION')));
        $this->assertSame('<?php // ancien', file_get_contents($t.'/app/Ancien.php'));
        $this->assertSame('restored', $update->fresh()->status);
    }

    public function test_failed_copy_restores_previous_code(): void
    {
        mkdir($this->tmp.'/target/app/Conflit.php'); // un dossier là où le paquet a un fichier
        $pkg = $this->package('2.2.0', ['app/Ancien.php' => '<?php // mis à jour', 'app/Conflit.php' => '<?php']);
        $update = AppUpdate::create(['status' => 'verified', 'package_path' => $pkg, 'package_size' => filesize($pkg)]);
        try {
            $this->updater()->apply($update, ['maintenance' => false, 'backup_db' => false]);
            $this->fail('Échec attendu');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('restauré', $e->getMessage());
        }
        $this->assertSame('failed', $update->fresh()->status);
        $this->assertSame('2.1.0', trim(file_get_contents($this->tmp.'/target/VERSION')));
        $this->assertSame('<?php // ancien', file_get_contents($this->tmp.'/target/app/Ancien.php'));
    }

    public function test_database_backup_contains_schema_and_data(): void
    {
        $path = $this->updater()->backupDatabase($this->tmp.'/work/base.sql.gz');
        $sql = gzdecode(file_get_contents($path));
        $this->assertStringContainsString('CREATE TABLE `users`', $sql);
        $this->assertStringContainsString('INSERT INTO `subscription_plans`', $sql);
    }

    private function admin(): User
    {
        $admin = $this->user();
        $admin->forceFill(['is_super_admin' => true])->save();

        return $admin;
    }

    private function sendInChunks(User $admin, string $zip, int $chunk = 700): TestResponse
    {
        $data = file_get_contents($zip);
        $parts = str_split($data, $chunk);
        $id = (string) Str::uuid();
        foreach ($parts as $i => $part) {
            $res = $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post('/admin/mises-a-jour/envoi', [
                'upload_id' => $id, 'index' => $i, 'total' => count($parts), 'name' => 'jackcie-x.zip',
                'chunk' => UploadedFile::fake()->createWithContent('part', $part),
            ], ['Accept' => 'application/json'])->assertOk();
        }

        return $res;
    }

    public function test_admin_uploads_in_chunks_then_installs_in_two_phases(): void
    {
        $this->app->instance(Updater::class, $this->updater());
        $admin = $this->admin();
        $this->actingAs($this->user())->withSession(['auth.password_confirmed_at' => time()])->get('/admin/mises-a-jour')->assertNotFound();
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->get('/admin/mises-a-jour')->assertOk()->assertSee('Version installée : 2.1.0');

        // Version plus ancienne refusée
        $this->sendInChunks($admin, $this->package('2.0.0'))->assertJson(['done' => true]);
        $this->assertSame('rejected', AppUpdate::latest('id')->first()->status);

        $pkg = $this->package('2.2.0', ['app/Nouveau.php' => '<?php // nouveau']);
        $this->sendInChunks($admin, $pkg)->assertJson(['done' => true]);
        $update = AppUpdate::latest('id')->first();
        $this->assertSame('verified', $update->status);
        $this->assertSame(hash_file('sha256', $pkg), $update->package_sha256);
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->get('/admin/mises-a-jour/'.$update->id)->assertOk()->assertSee('Installer la version 2.2.0');

        $res = $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post('/admin/mises-a-jour/'.$update->id.'/installer', ['confirm' => '1', 'backup_db' => '1'])->assertRedirect();
        $this->assertSame('migrating', $update->fresh()->status);
        $this->assertTrue(app()->isDownForMaintenance());
        $this->assertNotNull($res->getCookie('laravel_maintenance', false));

        // Phase 2 (lien signé, cookie de contournement de la maintenance)
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->withUnencryptedCookie('laravel_maintenance', $res->getCookie('laravel_maintenance', false)->getValue()) // valeur brute, telle que reçue par le navigateur
            ->get($res->headers->get('Location'))->assertRedirect('/admin/mises-a-jour/'.$update->id);
        $this->assertSame('done', $update->fresh()->status);
        $this->assertFalse(app()->isDownForMaintenance());
        $this->assertFileExists($update->fresh()->backup_db_path);
        $this->assertSame('<?php // nouveau', file_get_contents($this->tmp.'/target/app/Nouveau.php'));
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->get('/admin/mises-a-jour/'.$update->id.'/sauvegarde/base')->assertOk();
    }

    public function test_scheduler_finishes_an_interrupted_update(): void
    {
        $pkg = $this->package('2.2.0');
        $update = AppUpdate::create(['status' => 'verified', 'package_path' => $pkg, 'package_size' => filesize($pkg)]);
        $this->app->instance(Updater::class, $this->updater());
        $this->updater()->apply($update, ['maintenance' => false, 'backup_db' => false]);
        $this->assertSame('migrating', $update->fresh()->status);
        Artisan::call('app:finalize-update');
        $this->assertSame('migrating', $update->fresh()->status); // délai de 2 minutes
        $this->travel(3)->minutes();
        Artisan::call('app:finalize-update');
        $this->assertSame('done', $update->fresh()->status);
    }

    public function test_maintenance_page_resumes_an_interrupted_update_without_cron(): void
    {
        $pkg = $this->package('2.2.0');
        $update = AppUpdate::create(['status' => 'verified', 'package_path' => $pkg, 'package_size' => filesize($pkg)]);
        $this->app->instance(Updater::class, $this->updater());
        $this->updater()->apply($update, ['maintenance' => false, 'backup_db' => false]);
        Artisan::call('down', ['--secret' => 'secret-test']);
        try {
            $this->get('/')->assertStatus(503)->assertSee('/maintenance/reprise', false);
            $this->getJson('/maintenance/reprise')->assertOk()->assertJson(['resumed' => false, 'down' => true]); // délai de 2 minutes
            $this->travel(3)->minutes();
            $this->getJson('/maintenance/reprise')->assertOk()->assertJson(['resumed' => true, 'down' => false]);
            $this->assertSame('done', $update->fresh()->status);
            $this->assertFalse(app()->isDownForMaintenance());
        } finally {
            if (app()->isDownForMaintenance()) {
                Artisan::call('up');
            }
        }
    }
}

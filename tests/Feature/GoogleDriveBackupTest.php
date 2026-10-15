<?php

namespace Tests\Feature;

use App\Models\ApplicationSetting;
use App\Models\BackupRun;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\Backups\BackupCipher;
use App\Services\Backups\BackupService;
use App\Services\Backups\GoogleDrive;
use App\Services\NotificationDiagnostics;
use App\Services\SystemMonitor;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Sauvegarde chiffrée sur Google Drive. Les appels à Google sont simulés (Http::fake) avec les réponses
 * documentées de l'API Drive v3 ; l'essai réel se fait avec le compte Google de l'éditeur.
 */
class GoogleDriveBackupTest extends TestCase
{
    /** @var array<int, array{method: string, url: string, body: string, headers: array}> */
    private array $calls = [];

    /** @var array<string, string> contenu reçu par « Google Drive », par nom de fichier */
    private array $uploaded = [];

    private array $driveFiles = [];

    private bool $failUpload = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google.client_id' => 'client-id.apps.googleusercontent.com', 'services.google.client_secret' => 'secret-google']);
        $pending = [];
        Http::fake(function (Request $request) use (&$pending) {
            $url = $request->url();
            $this->calls[] = ['method' => $request->method(), 'url' => $url, 'body' => $request->body(), 'headers' => $request->headers()];
            if (str_starts_with($url, 'https://oauth2.googleapis.com/token')) {
                parse_str($request->body(), $form);

                return Http::response(($form['grant_type'] ?? '') === 'authorization_code'
                    ? ['access_token' => 'acc-1', 'expires_in' => 3599, 'refresh_token' => 'refresh-xyz', 'scope' => 'openid https://www.googleapis.com/auth/drive.file https://www.googleapis.com/auth/userinfo.email', 'token_type' => 'Bearer']
                    : ['access_token' => 'acc-2', 'expires_in' => 3599, 'token_type' => 'Bearer']);
            }
            if (str_starts_with($url, 'https://oauth2.googleapis.com/revoke')) {
                return Http::response('', 200);
            }
            if (str_starts_with($url, 'https://openidconnect.googleapis.com/v1/userinfo')) {
                return Http::response(['email' => 'sauvegardes@exemple.fr']);
            }
            if ($request->method() === 'POST' && str_starts_with($url, 'https://www.googleapis.com/drive/v3/files')) {
                return Http::response(['id' => 'folder-1']);
            }
            if ($request->method() === 'GET' && str_starts_with($url, 'https://www.googleapis.com/drive/v3/files/folder-1')) {
                return Http::response(['id' => 'folder-1', 'trashed' => false]);
            }
            if ($request->method() === 'GET' && str_starts_with($url, 'https://www.googleapis.com/drive/v3/files')) {
                return Http::response(['files' => $this->driveFiles]);
            }
            if ($request->method() === 'DELETE') {
                return Http::response('', 204);
            }
            if ($request->method() === 'POST' && str_contains($url, 'uploadType=resumable')) {
                if ($this->failUpload) {
                    return Http::response(['error' => ['message' => 'The user\'s Drive storage quota has been exceeded.']], 403);
                }
                $id = 'up-'.count($pending);
                $pending[$id] = ['name' => json_decode($request->body(), true)['name'], 'data' => ''];

                return Http::response('', 200, ['Location' => 'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&upload_id='.$id]);
            }
            if ($request->method() === 'PUT' && str_contains($url, 'upload_id=')) {
                parse_str(parse_url($url, PHP_URL_QUERY), $q);
                $pending[$q['upload_id']]['data'] .= $request->body();
                preg_match('#bytes (\d+)-(\d+)/(\d+)#', $request->header('Content-Range')[0] ?? '', $m);
                if ((int) $m[2] + 1 < (int) $m[3]) {
                    return Http::response('', 308);
                }
                $this->uploaded[$pending[$q['upload_id']]['name']] = $pending[$q['upload_id']]['data'];
                $this->driveFiles[] = ['id' => 'drive-'.$q['upload_id'], 'name' => $pending[$q['upload_id']]['name'], 'createdTime' => now()->toIso8601String(), 'size' => (string) strlen($pending[$q['upload_id']]['data'])];

                return Http::response(['id' => 'drive-'.$q['upload_id']]);
            }

            return Http::response(['error' => ['message' => 'Appel inattendu : '.$request->method().' '.$url]], 500);
        });
    }

    private function admin(): User
    {
        $admin = $this->user();
        $admin->forceFill(['is_super_admin' => true])->save();

        return $admin;
    }

    private function connect(User $admin): string
    {
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post('/admin/sauvegardes/google')->assertRedirect();
        $state = session('backup_oauth_state');

        return $state;
    }

    public function test_the_cipher_round_trips_large_files_and_detects_tampering(): void
    {
        $cipher = new BackupCipher;
        $key = BackupCipher::newKey();
        $dir = sys_get_temp_dir().'/jcbk-'.uniqid();
        mkdir($dir);
        file_put_contents($dir.'/plain', random_bytes(2_500_000));
        $cipher->encryptFile($dir.'/plain', $dir.'/enc', $key);
        $this->assertStringStartsWith("JCBK1\n", file_get_contents($dir.'/enc'));
        $this->assertStringNotContainsString(substr(file_get_contents($dir.'/plain'), 1000, 64), file_get_contents($dir.'/enc'));

        $cipher->decryptFile($dir.'/enc', $dir.'/back', $key);
        $this->assertSame(md5_file($dir.'/plain'), md5_file($dir.'/back'));

        // Mauvaise clé, fichier modifié ou tronqué : refusé, aucun fichier partiel laissé.
        foreach ([[BackupCipher::newKey(), null], [$key, 'tamper'], [$key, 'truncate']] as [$k, $damage]) {
            $copy = file_get_contents($dir.'/enc');
            if ($damage === 'tamper') {
                $copy[2000] = chr(ord($copy[2000]) ^ 1);
            }
            file_put_contents($dir.'/bad', $damage === 'truncate' ? substr($copy, 0, 1_500_000) : $copy);
            try {
                $cipher->decryptFile($dir.'/bad', $dir.'/out', $k);
                $this->fail('Déchiffrement accepté à tort ('.($damage ?? 'mauvaise clé').').');
            } catch (\RuntimeException) {
                $this->assertFileDoesNotExist($dir.'/out');
            }
        }
        array_map('unlink', glob($dir.'/*'));
        rmdir($dir);
    }

    public function test_super_admin_connects_drive_and_gets_a_restoration_key(): void
    {
        $admin = $this->admin();
        $this->actingAs($this->user())->get('/admin/sauvegardes')->assertNotFound();
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->get('/admin/sauvegardes')->assertOk()->assertSee('data-connect-drive', false);

        $redirect = $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post('/admin/sauvegardes/google')->headers->get('Location');
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $redirect);
        parse_str(parse_url($redirect, PHP_URL_QUERY), $q);
        $this->assertSame(['https://www.googleapis.com/auth/drive.file openid email', 'offline', route('admin.backups.callback')], [$q['scope'], $q['access_type'], $q['redirect_uri']]);

        // Retour avec un mauvais « state » : refusé.
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time(), 'backup_oauth_state' => 'abc'])
            ->get('/admin/sauvegardes/google/retour?state=autre&code=c')->assertRedirect()->assertSessionHas('error');
        $this->assertNull(ApplicationSetting::get('backup.google_refresh_token'));

        $page = $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time(), 'backup_oauth_state' => $q['state']])
            ->get('/admin/sauvegardes/google/retour?state='.$q['state'].'&code=code-google')->assertRedirect(route('admin.backups.index'));
        $page->assertSessionHas('success', 'Google Drive connecté (sauvegardes@exemple.fr).')->assertSessionHas('show_key', true);
        // Jeton et clé stockés chiffrés, jamais en clair en base.
        $raw = DB::table('application_settings')->whereIn('key', ['backup.google_refresh_token', 'backup.key'])->pluck('value')->implode(' ');
        $this->assertStringNotContainsString('refresh-xyz', $raw);
        $key = app(BackupService::class)->key();
        $this->assertStringStartsWith('jcbk_', $key);
        $this->assertStringNotContainsString($key, $raw);
        $this->assertSame('folder-1', ApplicationSetting::get('backup.folder_id'));

        // La clé s'affiche juste après la connexion, puis seulement à la demande.
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->get('/admin/sauvegardes')->assertOk()->assertSee($key)->assertSee('data-backup-key', false);
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->get('/admin/sauvegardes')->assertOk()->assertDontSee($key)->assertSee('sauvegardes@exemple.fr');
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post('/admin/sauvegardes/cle')->assertSessionHas('show_key', true);
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time(), 'show_key' => true])->get('/admin/sauvegardes')->assertSee($key);
    }

    public function test_nightly_backup_is_encrypted_uploaded_and_old_ones_pruned(): void
    {
        $admin = $this->admin();
        $state = $this->connect($admin);
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time(), 'backup_oauth_state' => $state])->get('/admin/sauvegardes/google/retour?state='.$state.'&code=c');
        $key = app(BackupService::class)->key();
        $this->driveFiles = [
            ['id' => 'old-base', 'name' => 'jackcie-base-2026-08-01-0300.sql.gz.jcbk', 'createdTime' => now()->subDays(45)->toIso8601String(), 'size' => '10'],
            ['id' => 'recent-base', 'name' => 'jackcie-base-x.sql.gz.jcbk', 'createdTime' => now()->subDays(3)->toIso8601String(), 'size' => '10'],
            ['id' => 'mine', 'name' => 'mes-notes.txt', 'createdTime' => now()->subDays(90)->toIso8601String(), 'size' => '10'],
        ];
        foreach (range(1, 6) as $i) {
            $this->driveFiles[] = ['id' => 'files-'.$i, 'name' => 'jackcie-fichiers-'.$i.'.zip.jcbk', 'createdTime' => now()->subWeeks($i)->toIso8601String(), 'size' => '10'];
        }

        // Avant 3 h : rien ; à partir de 3 h : sauvegarde de la nuit (base + fichiers, jamais sauvegardés).
        $this->travelTo(now()->startOfDay()->setTime(2, 30));
        $this->artisan('backup:run', ['--auto' => true])->assertSuccessful();
        $this->assertSame(0, BackupRun::count());
        $this->travelTo(now()->setTime(3, 5));
        $this->artisan('backup:run', ['--auto' => true])->assertSuccessful();

        $runs = BackupRun::orderBy('id')->get();
        $this->assertSame([['database', 'done'], ['files', 'done']], $runs->map(fn ($r) => [$r->kind, $r->status])->all());
        $this->assertSame(['drive-up-0', 'drive-up-1'], $runs->pluck('drive_file_id')->all());
        // Ce que reçoit Google est chiffré ; avec la clé, on retrouve la base et les fichiers.
        $base = $this->uploaded[$runs[0]->file_name];
        $this->assertStringStartsWith("JCBK1\n", $base);
        $this->assertStringNotContainsString('INSERT INTO', $base);
        $tmp = tempnam(sys_get_temp_dir(), 'jcbk');
        file_put_contents($tmp, $base);
        $this->artisan('backup:decrypt', ['fichier' => $tmp, '--key' => $key, '--output' => $tmp.'.sql.gz'])->assertSuccessful();
        $this->assertStringContainsString('-- Sauvegarde Jack&Cie', gzdecode(file_get_contents($tmp.'.sql.gz')));
        $this->assertStringContainsString('INSERT INTO `users`', gzdecode(file_get_contents($tmp.'.sql.gz')));
        @unlink($tmp);
        @unlink($tmp.'.sql.gz');
        $this->assertDirectoryDoesNotExist(storage_path('app/backups-tmp'));

        // Rétention : base de plus de 30 jours et archives de fichiers au-delà des 5 dernières ; le reste du dossier n'est pas touché.
        $deleted = collect($this->calls)->where('method', 'DELETE')->map(fn ($c) => basename(parse_url($c['url'], PHP_URL_PATH)))->sort()->values()->all();
        $this->assertSame(['files-5', 'files-6', 'old-base'], $deleted);

        // Même nuit : pas de seconde sauvegarde ; demande depuis l'administration : partie dans la minute.
        $this->artisan('backup:run', ['--auto' => true])->assertSuccessful();
        $this->assertSame(2, BackupRun::count());
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post('/admin/sauvegardes/lancer')->assertSessionHas('success');
        $this->artisan('backup:run', ['--auto' => true])->assertSuccessful();
        $this->assertSame(3, BackupRun::count());
        $this->assertSame('database', BackupRun::latest('id')->first()->kind);
        $this->assertNull(app(BackupService::class)->pendingRequest());
    }

    public function test_a_failed_backup_alerts_super_admins_and_the_monitor_warns_after_48_hours(): void
    {
        $admin = $this->admin();
        $state = $this->connect($admin);
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time(), 'backup_oauth_state' => $state])->get('/admin/sauvegardes/google/retour?state='.$state.'&code=c');
        Notification::fake();
        $this->failUpload = true;

        $this->artisan('backup:run')->assertFailed();
        $run = BackupRun::sole();
        $this->assertSame('failed', $run->status);
        $this->assertStringContainsString('quota', $run->error);
        Notification::assertSentTo($admin, AppNotification::class, fn ($n) => $n->kind === 'system_alert' && str_contains($n->body, 'quota'));
        $this->assertDirectoryDoesNotExist(storage_path('app/backups-tmp'));
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->get('/admin/sauvegardes')->assertSee('data-backup-run="failed"', false);

        // 48 heures sans sauvegarde réussie : alerte de la surveillance.
        ApplicationSetting::put('monitor_enabled', true);
        $this->travel(49)->hours();
        NotificationDiagnostics::beat();
        $this->assertContains('backup', app(SystemMonitor::class)->check());
    }

    public function test_large_files_are_sent_in_several_resumable_chunks(): void
    {
        $admin = $this->admin();
        $state = $this->connect($admin);
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time(), 'backup_oauth_state' => $state])->get('/admin/sauvegardes/google/retour?state='.$state.'&code=c');
        $drive = app(GoogleDrive::class);
        $drive->chunkSize = 262144;
        $file = tempnam(sys_get_temp_dir(), 'jcbk');
        file_put_contents($file, random_bytes(700_000));

        $this->assertSame('drive-up-0', $drive->upload($file, 'gros.jcbk', 'folder-1'));
        $ranges = collect($this->calls)->where('method', 'PUT')->map(fn ($c) => $c['headers']['Content-Range'][0])->values()->all();
        $this->assertSame(['bytes 0-262143/700000', 'bytes 262144-524287/700000', 'bytes 524288-699999/700000'], $ranges);
        $this->assertSame(md5_file($file), md5($this->uploaded['gros.jcbk']));
        @unlink($file);
    }

    public function test_disconnecting_revokes_access_and_stops_backups(): void
    {
        $admin = $this->admin();
        $state = $this->connect($admin);
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time(), 'backup_oauth_state' => $state])->get('/admin/sauvegardes/google/retour?state='.$state.'&code=c');
        $this->assertTrue(app(BackupService::class)->ready());
        // Mentionné dans la liste des sous-traitants.
        $this->get('/confidentialite')->assertSee('Google Drive');

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->delete('/admin/sauvegardes/google')->assertSessionHas('success');
        $this->assertTrue(collect($this->calls)->contains(fn ($c) => str_starts_with($c['url'], 'https://oauth2.googleapis.com/revoke')));
        $this->assertFalse(app(BackupService::class)->ready());
        $this->artisan('backup:run', ['--auto' => true])->assertSuccessful();
        $this->assertSame(0, BackupRun::count());
        // La clé de restauration est conservée : les sauvegardes déjà envoyées restent lisibles.
        $this->assertNotNull(app(BackupService::class)->key());
    }
}

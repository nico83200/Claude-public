<?php

namespace App\Services\Backups;

use App\Models\ApplicationSetting;
use App\Models\BackupRun;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\Updates\Updater;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Sauvegarde automatique hors de l'hébergement, sur Google Drive, chiffrée avec la clé de restauration :
 *  - la base de données chaque nuit (30 jours conservés) ;
 *  - les photos et documents (storage/app/private) chaque semaine (5 dernières conservées).
 * Lancée par le planificateur (backup:run --auto) ou à la demande depuis Administration › Sauvegardes.
 * En cas d'échec, les super-administrateurs sont prévenus (notification et e-mail).
 */
class BackupService
{
    public const KEEP_DATABASE_DAYS = 30;

    public const KEEP_FILES = 5;

    /** Heure à partir de laquelle la sauvegarde de la nuit est lancée (rattrapée plus tard si le cron a manqué). */
    public const HOUR = 3;

    public function __construct(private GoogleDrive $drive, private BackupCipher $cipher, private Updater $updater) {}

    public function key(): ?string
    {
        $stored = ApplicationSetting::get('backup.key');

        return $stored ? rescue(fn () => Crypt::decryptString($stored), null, false) : null;
    }

    /** Clé de restauration créée une fois ; la changer rendrait les anciennes sauvegardes illisibles. */
    public function ensureKey(): string
    {
        if ($key = $this->key()) {
            return $key;
        }
        $key = BackupCipher::newKey();
        ApplicationSetting::put('backup.key', Crypt::encryptString($key));

        return $key;
    }

    public function ready(): bool
    {
        return $this->drive->clientConfigured() && $this->drive->connected() && $this->key() !== null;
    }

    /** Demande depuis l'administration : traitée au prochain passage du cron (dans la minute). */
    public function request(User $user, bool $files): void
    {
        ApplicationSetting::put('backup.requested', ['by' => $user->id, 'files' => $files, 'at' => now()->toIso8601String()]);
    }

    public function pendingRequest(): ?array
    {
        return ApplicationSetting::get('backup.requested');
    }

    /** Passage du planificateur : sauvegarde demandée, ou sauvegarde de la nuit pas encore faite. */
    public function runIfDue(): ?array
    {
        if (! $this->ready()) {
            return null;
        }
        if ($request = $this->pendingRequest()) {
            ApplicationSetting::put('backup.requested', null);

            return $this->run((bool) ($request['files'] ?? false), $request['by'] ?? null);
        }
        $last = BackupRun::where('kind', 'database')->latest('started_at')->value('started_at');
        $due = now()->hour >= self::HOUR && (! $last || Carbon::parse($last)->lt(now()->startOfDay()->setTime(self::HOUR, 0)));

        return $due ? $this->run($this->filesDue(), null) : null;
    }

    public function filesDue(): bool
    {
        $last = BackupRun::where('kind', 'files')->where('status', 'done')->latest('finished_at')->value('finished_at');

        return ! $last || Carbon::parse($last)->lt(now()->subDays(7)->addHours(2));
    }

    /** @return array{database: BackupRun, files: BackupRun|null} */
    public function run(bool $withFiles, ?int $requestedBy = null): array
    {
        $key = $this->key() ?? throw new RuntimeException('Clé de restauration absente.');
        $dir = storage_path('app/backups-tmp');
        File::ensureDirectoryExists($dir);
        $stamp = now()->format('Y-m-d-Hi');
        $startedAt = now()->subSecond();
        $results = ['database' => null, 'files' => null];
        try {
            $folder = $this->drive->folderId();
            $results['database'] = $this->send('database', 'jackcie-base-'.$stamp.'.sql.gz.jcbk', $folder, $key, $requestedBy,
                fn (string $plain) => $this->updater->backupDatabase($plain), $dir.'/base-'.$stamp.'.sql.gz');
            if ($withFiles) {
                $results['files'] = $this->send('files', 'jackcie-fichiers-'.$stamp.'.zip.jcbk', $folder, $key, $requestedBy,
                    fn (string $plain) => $this->zipPrivateFiles($plain), $dir.'/fichiers-'.$stamp.'.zip');
            }
            $this->prune($folder);
        } catch (Throwable $e) {
            // Échec avant toute archive (dossier Drive, connexion) : tracé aussi dans l'historique.
            $results['database'] ??= BackupRun::where('kind', 'database')->where('started_at', '>=', $startedAt)->latest('id')->first()
                ?? BackupRun::create(['kind' => 'database', 'status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000), 'requested_by' => $requestedBy, 'started_at' => now(), 'finished_at' => now()]);
            $this->alert($e->getMessage());
        } finally {
            File::deleteDirectory($dir);
        }

        return $results;
    }

    /** Une archive : création en clair dans le dossier temporaire, chiffrement, envoi, suppression locale. */
    private function send(string $kind, string $name, string $folder, string $key, ?int $requestedBy, callable $build, string $plain): BackupRun
    {
        $run = BackupRun::create(['kind' => $kind, 'status' => 'running', 'file_name' => $name, 'requested_by' => $requestedBy, 'started_at' => now()]);
        try {
            $build($plain);
            $encrypted = $plain.'.jcbk';
            $this->cipher->encryptFile($plain, $encrypted, $key);
            @unlink($plain);
            $id = $this->drive->upload($encrypted, $name, $folder);
            $run->update(['status' => 'done', 'size' => filesize($encrypted), 'drive_file_id' => $id, 'finished_at' => now()]);
            @unlink($encrypted);

            return $run;
        } catch (Throwable $e) {
            $run->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000), 'finished_at' => now()]);

            throw $e;
        }
    }

    /** Photos, documents et justificatifs (storage/app/private), sans les fichiers temporaires. */
    public function zipPrivateFiles(string $target): string
    {
        $root = storage_path('app/private');
        $zip = new ZipArchive;
        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Archive des fichiers impossible à créer.');
        }
        $zip->addFromString('LISEZMOI.txt', 'Fichiers privés de Jack&Cie (storage/app/private) du '.now()->format('d/m/Y H:i').".\nÀ décompresser dans storage/app/private de l'application.\n");
        if (is_dir($root)) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($it as $file) {
                $relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($root))), '/');
                if ($file->isFile() && ! str_starts_with($relative, 'tmp/')) {
                    $zip->addFile($file->getPathname(), $relative);
                    // Photos et PDF déjà compressés : stockés tels quels (archive plus rapide).
                    $zip->setCompressionName($relative, preg_match('/\.(jpe?g|png|webp|heic|pdf|mp4|zip|gz)$/i', $relative) ? ZipArchive::CM_STORE : ZipArchive::CM_DEFLATE);
                }
            }
        }
        if (! $zip->close()) {
            throw new RuntimeException('Archive des fichiers incomplète.');
        }

        return $target;
    }

    /** Rétention : 30 jours de bases, les 5 dernières archives de fichiers ; les autres fichiers du dossier ne sont pas touchés. */
    private function prune(string $folder): void
    {
        $files = collect($this->drive->files($folder));
        $limit = now()->subDays(self::KEEP_DATABASE_DAYS);
        $old = $files->filter(fn ($f) => str_starts_with($f['name'], 'jackcie-base-') && $f['createdTime'] && Carbon::parse($f['createdTime'])->lt($limit))
            ->merge($files->filter(fn ($f) => str_starts_with($f['name'], 'jackcie-fichiers-'))->sortByDesc('createdTime')->slice(self::KEEP_FILES));
        foreach ($old as $f) {
            $this->drive->delete($f['id']);
        }
    }

    private function alert(string $error): void
    {
        foreach (User::where('is_super_admin', true)->whereNull('suspended_at')->get() as $admin) {
            $admin->notifyNow(new AppNotification('system_alert', 'Jack&Cie · Sauvegarde Google Drive en échec',
                'La sauvegarde automatique n\'a pas pu être envoyée sur Google Drive : '.mb_substr($error, 0, 400)
                ."\n\nVérifiez la connexion dans Administration › Sauvegardes, puis relancez-la.", route('admin.backups.index')));
        }
    }
}

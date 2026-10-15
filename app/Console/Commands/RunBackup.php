<?php

namespace App\Console\Commands;

use App\Services\Backups\BackupService;
use Illuminate\Console\Command;

/** Sauvegarde chiffrée sur Google Drive (voir App\Services\Backups\BackupService et docs/SAUVEGARDE.md). */
class RunBackup extends Command
{
    protected $signature = 'backup:run {--auto : Seulement si une sauvegarde est due ou demandée (planificateur)} {--files : Inclure les photos et documents}';

    protected $description = 'Sauvegarde chiffrée de la base (et des fichiers) sur Google Drive';

    public function handle(BackupService $backups): int
    {
        if (! $backups->ready()) {
            $this->line('Sauvegarde Google Drive non configurée (Administration › Sauvegardes).');

            return self::SUCCESS;
        }
        $results = $this->option('auto') ? $backups->runIfDue() : $backups->run((bool) $this->option('files') || $backups->filesDue());
        if ($results === null) {
            return self::SUCCESS;
        }
        foreach (array_filter($results) as $kind => $run) {
            $this->line($kind.' : '.$run->status.($run->error ? ' – '.$run->error : ' ('.$run->file_name.')'));
        }

        return collect($results)->filter()->contains(fn ($run) => $run->status === 'failed') ? self::FAILURE : self::SUCCESS;
    }
}

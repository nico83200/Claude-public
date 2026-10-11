<?php

namespace App\Console\Commands;

use App\Models\AppUpdate;
use App\Services\Updates\Updater;
use Illuminate\Console\Command;
use Throwable;

/**
 * Filet de sécurité : termine une mise à jour dont la seconde phase (migrations,
 * remise en ligne) n'a pas été déclenchée par le navigateur (page fermée, coupure…).
 * Lancé chaque minute par le planificateur, y compris pendant la maintenance.
 */
class FinalizeUpdate extends Command
{
    protected $signature = 'app:finalize-update {--now : Sans attendre le délai de 2 minutes}';

    protected $description = 'Termine une mise à jour de l\'application restée en attente';

    public function handle(Updater $updater): int
    {
        $pending = AppUpdate::where('status', 'migrating')
            ->when(! $this->option('now'), fn ($q) => $q->where('updated_at', '<', now()->subMinutes(2)))->get();
        foreach ($pending as $update) {
            try {
                $updater->finalize($update);
                $this->info('Mise à jour '.$update->to_version.' terminée.');
            } catch (Throwable $e) {
                $this->error($e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Support\Installation;
use Illuminate\Console\Command;

/** Ferme l'installeur web pour une installation réalisée en ligne de commande. */
class MarkInstalled extends Command
{
    protected $signature = 'app:mark-installed';

    protected $description = 'Marque l\'application comme installée (ferme l\'installeur web)';

    public function handle(): int
    {
        Installation::markInstalled(['by' => 'cli']);
        $this->info('Installeur web fermé ('.Installation::lockPath().').');

        return self::SUCCESS;
    }
}

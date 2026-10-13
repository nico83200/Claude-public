<?php

namespace App\Console\Commands;

use App\Services\SystemMonitor;
use Illuminate\Console\Command;

/** Surveillance : cron arrêté ou erreurs en hausse → alerte aux super-administrateurs. */
class MonitorCheck extends Command
{
    protected $signature = 'monitor:check';

    protected $description = 'Vérifie le cron et le nombre d\'erreurs, alerte les super-administrateurs si besoin';

    public function handle(SystemMonitor $monitor): int
    {
        $sent = $monitor->check();
        $this->info($sent ? 'Alertes : '.implode(', ', $sent) : 'Rien à signaler.');

        return self::SUCCESS;
    }
}

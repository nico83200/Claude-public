<?php

namespace App\Console\Commands;

use App\Services\AttentionPoints;
use Illuminate\Console\Command;

/** Relances « Des nouvelles de … ? » pour les points d'attention encore ouverts (jamais la nuit). */
class SendAttentionFollowUps extends Command
{
    protected $signature = 'attention:follow-up';

    protected $description = 'Demande des nouvelles des points d\'attention ouverts';

    public function handle(AttentionPoints $points): int
    {
        $this->info($points->sendFollowUps().' relance(s) envoyée(s).');

        return self::SUCCESS;
    }
}

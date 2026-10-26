<?php

namespace App\Console\Commands;

use App\Models\DailyLog;
use App\Models\HealthObservation;
use App\Services\AttentionPoints;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Reprise, à lancer une fois après la mise à jour 2.52 : chaque observation signalée avant que les points d'attention
 * complètent le suivi du jour est recopiée dans le suivi du jour où elle a été signalée (créé s'il manque, complété
 * sinon, sans doublon), comme une nouvelle saisie le ferait. Relancer la commande ne recopie rien deux fois.
 */
class BackfillAttentionDailyLogs extends Command
{
    protected $signature = 'attention:backfill-daily-logs {--dry-run : Affiche ce qui serait fait sans rien enregistrer}';

    protected $description = 'Recopie les observations passées dans le suivi du jour où elles ont été signalées';

    public function handle(AttentionPoints $points): int
    {
        $dry = (bool) $this->option('dry-run');
        $created = $completed = $skipped = 0;

        HealthObservation::with('horse')->whereHas('horse')->orderBy('observed_at')->orderBy('id')
            ->chunk(200, function ($chunk) use ($points, $dry, &$created, &$completed, &$skipped) {
                foreach ($chunk as $point) {
                    $line = ($point->title ? $point->title.' : ' : '').Str::limit($point->body, 300);
                    $day = $point->observed_at;
                    $existing = DailyLog::where('horse_id', $point->horse_id)
                        ->whereBetween('logged_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])->get();
                    if ($existing->contains(fn ($log) => $log->health_observation_id === $point->id || str_contains((string) $log->anomalies, $line))) {
                        $skipped++;

                        continue;
                    }
                    $existing->isEmpty() ? $created++ : $completed++;
                    if ($dry) {
                        $this->line(($existing->isEmpty() ? 'Créé    ' : 'Complété').' · '.$point->horse->shortName().' · '.$day->format('d/m/Y').' · '.Str::limit($line, 70));

                        continue;
                    }
                    $points->noteInDailyLog($point, null, 'anomalies', $line, $point->severity === 'info' ? null : 'average', $day->copy());
                }
            });

        $this->info(($dry ? 'Simulation : ' : '').$created.' suivi(s) du jour créé(s), '.$completed.' complété(s), '.$skipped.' observation(s) déjà reprise(s).');

        return self::SUCCESS;
    }
}

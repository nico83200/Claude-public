<?php

namespace App\Console\Commands;

use App\Models\DailyLog;
use App\Models\HealthObservation;
use App\Services\AttentionPoints;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Reprise des observations passées dans le suivi du jour, comme si elles avaient été saisies avec la 2.52 :
 * l'ouverture le jour où elle a été signalée, chaque nouvelle le jour où elle a été donnée, la clôture le jour
 * où elle a eu lieu (suivi créé s'il manque, complété sinon). Une ligne déjà présente ce jour-là n'est pas recopiée :
 * relancer la commande ne fait rien deux fois.
 */
class BackfillAttentionDailyLogs extends Command
{
    protected $signature = 'attention:backfill-daily-logs {--dry-run : Affiche ce qui serait fait sans rien enregistrer}';

    protected $description = 'Recopie les observations passées (ouverture, nouvelles, clôture) dans le suivi du jour correspondant';

    public function handle(AttentionPoints $points): int
    {
        $dry = (bool) $this->option('dry-run');
        $count = ['created' => 0, 'completed' => 0, 'skipped' => 0];

        HealthObservation::with(['horse', 'updates'])->whereHas('horse')->orderBy('observed_at')->orderBy('id')
            ->chunk(200, function ($chunk) use ($points, $dry, &$count) {
                foreach ($chunk as $point) {
                    $entries = [['at' => $point->observed_at, 'field' => 'anomalies', 'line' => AttentionPoints::openLine($point), 'state' => $point->severity === 'info' ? null : 'average']];
                    foreach ($point->updates as $u) {
                        $entries[] = ['at' => $u->created_at, 'field' => 'anomalies', 'line' => AttentionPoints::updateLine($point, $u->trend, $u->body), 'state' => $u->trend === 'worse' ? 'average' : null];
                    }
                    if ($point->resolved_at) {
                        $entries[] = ['at' => $point->resolved_at, 'field' => 'observations', 'line' => AttentionPoints::closeLine($point, $point->resolution_note), 'state' => null];
                    }
                    foreach ($entries as $e) {
                        $count[$this->apply($points, $point, $e, $dry)]++;
                    }
                }
            });

        $this->info(($dry ? 'Simulation : ' : '').$count['created'].' suivi(s) du jour créé(s), '.$count['completed'].' complété(s), '
            .$count['skipped'].' ligne(s) déjà présente(s).');

        return self::SUCCESS;
    }

    /** @param array{at: Carbon, field: string, line: string, state: ?string} $e */
    private function apply(AttentionPoints $points, HealthObservation $point, array $e, bool $dry): string
    {
        $at = $e['at']->copy();
        $logs = DailyLog::where('horse_id', $point->horse_id)->whereBetween('logged_at', [$at->copy()->startOfDay(), $at->copy()->endOfDay()])->get();
        if ($logs->contains(fn ($log) => str_contains((string) $log->{$e['field']}, $e['line']))) {
            return 'skipped';
        }
        $result = $logs->isEmpty() ? 'created' : 'completed';
        if ($dry) {
            $this->line(($result === 'created' ? 'Créé    ' : 'Complété').' · '.$point->horse->shortName().' · '.$at->format('d/m/Y').' · '.Str::limit($e['line'], 70));
        } else {
            $points->noteInDailyLog($point, null, $e['field'], $e['line'], $e['state'], $at);
        }

        return $result;
    }
}

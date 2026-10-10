<?php

namespace App\Console\Commands;

use App\Models\Horse;
use App\Models\HorseDocument;
use App\Models\SyncOperation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Politique de conservation : purge définitive des éléments supprimés
 * logiquement depuis plus de N jours, et des journaux techniques anciens.
 */
class PurgeData extends Command
{
    protected $signature = 'data:purge {--days=30 : Délai après suppression logique} {--dry-run}';

    protected $description = 'Purge définitive des données supprimées au-delà du délai de conservation';

    public function handle(): int
    {
        $limit = now()->subDays((int) $this->option('days'));
        $horses = Horse::onlyTrashed()->where('deleted_at', '<', $limit)->get();
        $docs = HorseDocument::onlyTrashed()->where('deleted_at', '<', $limit)->get();
        $this->info("Chevaux à purger : {$horses->count()} · Documents : {$docs->count()}");
        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }
        foreach ($docs as $doc) {
            Storage::disk('local')->delete($doc->path);
            $doc->forceDelete();
        }
        foreach ($horses as $horse) {
            Storage::disk('local')->deleteDirectory('horses/'.$horse->id);
            $horse->forceDelete(); // cascade SQL sur les tables liées
        }
        SyncOperation::where('created_at', '<', now()->subDays(180))->delete();
        DB::table('audit_logs')->where('created_at', '<', now()->subYears(3))->delete();
        DB::table('notifications')->whereNotNull('read_at')->where('created_at', '<', now()->subYear())->delete();

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\Backups\BackupCipher;
use Illuminate\Console\Command;
use RuntimeException;

/** Restauration : déchiffre une sauvegarde téléchargée depuis Google Drive (clé de restauration « jcbk_… »). */
class DecryptBackup extends Command
{
    protected $signature = 'backup:decrypt {fichier : Sauvegarde .jcbk téléchargée} {--key= : Clé de restauration (jcbk_…)} {--output= : Fichier déchiffré (par défaut : même nom sans .jcbk)}';

    protected $description = 'Déchiffre une sauvegarde Jack&Cie (.jcbk) avec la clé de restauration';

    public function handle(BackupCipher $cipher): int
    {
        $in = (string) $this->argument('fichier');
        $out = $this->option('output') ?: preg_replace('/\.jcbk$/', '', $in);
        if ($out === $in) {
            $out = $in.'.dechiffre';
        }
        $key = $this->option('key') ?: $this->secret('Clé de restauration (jcbk_…)');
        try {
            $cipher->decryptFile($in, $out, (string) $key);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info('Sauvegarde déchiffrée : '.$out);
        $this->line(str_ends_with($out, '.sql.gz') ? 'Base : gunzip < '.basename($out).' | mysql -u UTILISATEUR -p BASE (voir docs/SAUVEGARDE.md).' : 'Fichiers : décompresser dans storage/app/private.');

        return self::SUCCESS;
    }
}

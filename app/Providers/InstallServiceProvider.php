<?php

namespace App\Providers;

use App\Services\EnvEditor;
use App\Support\Installation;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

/**
 * Avant installation : l'application doit pouvoir démarrer sans base de
 * données ni fichier .env. On crée le .env et la clé de chiffrement si besoin,
 * et on bascule sessions / cache / file d'attente sur des pilotes sans base.
 */
class InstallServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (Installation::isInstalled() || $this->app->runningUnitTests()) {
            return;
        }

        if (! config('app.key')) {
            $key = EnvEditor::generateKey();
            try {
                $env = new EnvEditor;
                $env->ensureExists();
                $env->set(['APP_KEY' => $key]);
            } catch (\Throwable) {
                // .env non modifiable : clé stable propre à cette copie (le contenu à copier sera affiché à la fin).
                $key = 'base64:'.base64_encode(hash('sha256', base_path().'|'.php_uname('n').'|'.@filemtime(base_path('artisan')), true));
            }
            config(['app.key' => $key]);
        }

        File::ensureDirectoryExists(storage_path('framework/sessions'));
        File::ensureDirectoryExists(storage_path('framework/cache/data'));
        config([
            'session.driver' => 'file',
            'session.encrypt' => false,
            'session.secure' => false,
            'cache.default' => 'file',
            'queue.default' => 'sync',
            'app.debug' => false,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

/**
 * Coquille de l'application « écurie » : page autonome rendue à partir
 * d'IndexedDB, mise en cache par le Service Worker pour l'usage hors ligne.
 */
class OfflineController extends Controller
{
    public function app()
    {
        return response()->view('offline.app')->header('Cache-Control', 'no-cache');
    }
}

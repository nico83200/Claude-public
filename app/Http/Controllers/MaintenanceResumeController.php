<?php

namespace App\Http\Controllers;

use App\Models\AppUpdate;
use App\Services\Updates\Updater;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Reprise automatique d'une mise à jour restée en maintenance (page fermée, délai dépassé, cron absent) :
 * appelée par la page « Service indisponible », elle termine l'installation déjà copiée
 * (migrations puis remise en ligne). Sans effet s'il n'y a rien à reprendre.
 */
class MaintenanceResumeController extends Controller
{
    public function __invoke(Updater $updater): JsonResponse
    {
        $pending = AppUpdate::where('status', 'migrating')->where('updated_at', '<', now()->subMinutes(2))->get();
        $resumed = false;
        foreach ($pending as $update) {
            try {
                $updater->finalize($update);
                $resumed = true;
            } catch (Throwable $e) {
                report($e);
            }
        }

        return response()->json(['resumed' => $resumed, 'down' => app()->isDownForMaintenance()]);
    }
}

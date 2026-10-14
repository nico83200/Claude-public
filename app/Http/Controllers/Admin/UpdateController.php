<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppUpdate;
use App\Services\Audit;
use App\Services\Updates\Updater;
use App\Support\UploadLimits;
use Illuminate\Foundation\Http\MaintenanceModeBypassCookie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use RuntimeException;

/**
 * Mises à jour de l'application depuis l'administration : envoi du zip de
 * distribution par morceaux, vérification, installation, restauration.
 */
class UpdateController extends Controller
{
    private const CHUNK_MAX_KB = 4096;

    public function __construct(private Updater $updater) {}

    public function index()
    {
        return view('admin.updates.index', [
            'current' => $this->updater->currentVersion(),
            'updates' => AppUpdate::with('user')->latest()->limit(30)->get(),
            'zipAvailable' => class_exists(\ZipArchive::class),
            'problems' => $this->updater->preflight(30 * 1048576),
            'chunkBytes' => UploadLimits::chunkBytes(self::CHUNK_MAX_KB),
        ]);
    }

    /** Réception d'un morceau du zip ; le dernier déclenche l'assemblage et la vérification. */
    public function upload(Request $request)
    {
        $data = $request->validate([
            'upload_id' => ['required', 'uuid'],
            'index' => ['required', 'integer', 'min:0', 'max:999'],
            'total' => ['required', 'integer', 'min:1', 'max:1000'],
            'name' => ['required', 'string', 'max:120'],
            'chunk' => ['required', 'file', 'max:'.self::CHUNK_MAX_KB],
        ]);
        $dir = $this->updater->workDir();
        $part = $dir.'/envoi-'.$data['upload_id'].'.part';
        // Les morceaux arrivent dans l'ordre (envoi séquentiel) : on vérifie la position attendue.
        $expected = is_file($part.'.count') ? (int) file_get_contents($part.'.count') : 0;
        if ((int) $data['index'] !== $expected) {
            return response()->json(['message' => 'Envoi désynchronisé : recommencez.'], 422);
        }
        file_put_contents($part, file_get_contents($request->file('chunk')->getRealPath()), FILE_APPEND | LOCK_EX);
        file_put_contents($part.'.count', (string) ($expected + 1));
        if ($expected + 1 < $data['total']) {
            return response()->json(['received' => $expected + 1]);
        }

        @unlink($part.'.count');
        $final = $dir.'/paquet-'.$data['upload_id'].'.zip';
        rename($part, $final);
        $update = AppUpdate::create([
            'user_id' => $request->user()->id, 'from_version' => $this->updater->currentVersion(), 'status' => 'uploaded',
            'package_path' => $final, 'package_name' => basename($data['name']), 'package_size' => filesize($final), 'package_sha256' => hash_file('sha256', $final),
        ]);
        try {
            $info = $this->updater->inspect($final);
            $cmp = version_compare($info['version'], $update->from_version);
            if ($cmp < 0) {
                throw new RuntimeException('Ce paquet contient la version '.$info['version'].', plus ancienne que la version installée ('.$update->from_version.').');
            }
            $update->update(['status' => 'verified', 'to_version' => $info['version'],
                'message' => $cmp === 0 ? 'Même version que celle installée : réinstallation.' : $info['files'].' fichiers, '.round($info['size'] / 1048576).' Mo décompressés.']);
        } catch (RuntimeException $e) {
            @unlink($final);
            $update->update(['status' => 'rejected', 'message' => $e->getMessage(), 'package_path' => null]);
        }
        Audit::log('admin.update_uploaded', $update, ['status' => $update->status, 'version' => $update->to_version]);

        return response()->json(['done' => true, 'redirect' => route('admin.updates.show', $update)]);
    }

    public function show(AppUpdate $update)
    {
        return view('admin.updates.show', [
            'update' => $update->load('user'),
            'current' => $this->updater->currentVersion(),
            'problems' => $update->status === 'verified' ? $this->updater->preflight((int) $update->package_size) : [],
        ]);
    }

    public function apply(Request $request, AppUpdate $update)
    {
        // Second envoi (double appui, rechargement, retour arrière) : on renvoie vers le suivi au lieu d'une erreur.
        if (in_array($update->status, ['applying', 'migrating'], true)) {
            return redirect()->route('admin.updates.show', $update)->with('success', 'L\'installation est déjà en cours : suivez son avancement ici.');
        }
        if ($update->status === 'done') {
            return redirect()->route('admin.updates.show', $update)->with('success', 'Jack&Cie '.$update->to_version.' est déjà installée.');
        }
        if ($update->status !== 'verified' || ! $update->package_path || ! is_file($update->package_path)) {
            return redirect()->route('admin.updates.show', $update)->with('error', 'Ce paquet n\'est pas prêt à être installé.');
        }
        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => 'Cochez la case de confirmation.']);
        if ($problems = $this->updater->preflight((int) $update->package_size)) {
            return back()->with('error', implode(' ', $problems));
        }
        Audit::log('admin.update_started', $update, ['to' => $update->to_version]);
        try {
            $this->updater->apply($update, ['backup_db' => $request->boolean('backup_db')]);
        } catch (RuntimeException $e) {
            return redirect()->route('admin.updates.show', $update)->with('error', $e->getMessage());
        }

        // Phase 2 dans une nouvelle requête (nouveau code chargé) ; le cookie laisse passer l'administrateur pendant la maintenance.
        $response = redirect()->to(URL::temporarySignedRoute('admin.updates.finalize', now()->addMinutes(30), $update));
        if ($update->recovery_secret) {
            $response->withCookie(MaintenanceModeBypassCookie::create($update->recovery_secret));
        }

        return $response;
    }

    public function finalize(AppUpdate $update)
    {
        try {
            $this->updater->finalize($update);
        } catch (RuntimeException $e) {
            return redirect()->route('admin.updates.show', $update)->with('error', $e->getMessage());
        }
        Audit::log('admin.update_finished', $update, ['to' => $update->to_version, 'status' => $update->status]);

        return redirect()->route('admin.updates.show', $update)->with('success', 'Jack&Cie '.$update->to_version.' est installée.');
    }

    public function restore(Request $request, AppUpdate $update)
    {
        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => 'Cochez la case de confirmation.']);
        abort_unless(in_array($update->status, ['done', 'failed'], true), 422);
        try {
            $this->updater->restore($update);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        Audit::log('admin.update_restored', $update, ['to' => $update->from_version]);

        return back()->with('success', 'Code de la version '.$update->from_version.' restauré.');
    }

    public function download(AppUpdate $update, string $kind)
    {
        $path = match ($kind) {
            'code' => $update->backup_code_path, 'base' => $update->backup_db_path, default => null
        };
        abort_unless($path && is_file($path) && str_starts_with(realpath($path), realpath($this->updater->workDir())), 404);
        Audit::log('admin.update_backup_downloaded', $update, ['kind' => $kind]);

        return response()->download($path);
    }

    public function destroy(AppUpdate $update)
    {
        abort_unless(in_array($update->status, ['uploaded', 'verified', 'rejected'], true), 422);
        if ($update->package_path) {
            @unlink($update->package_path);
        }
        $update->delete();

        return redirect()->route('admin.updates.index')->with('success', 'Paquet supprimé.');
    }
}

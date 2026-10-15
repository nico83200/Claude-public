<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BackupRun;
use App\Services\Audit;
use App\Services\Backups\BackupService;
use App\Services\Backups\GoogleDrive;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

/** Administration › Sauvegardes : connexion à Google Drive, clé de restauration, historique et lancement manuel. */
class BackupController extends Controller
{
    public function __construct(private GoogleDrive $drive, private BackupService $backups) {}

    public function index()
    {
        return view('admin.backups.index', [
            'clientConfigured' => $this->drive->clientConfigured(),
            'connected' => $this->drive->connected(),
            'email' => $this->drive->email(),
            'hasKey' => $this->backups->key() !== null,
            'key' => session('show_key') ? $this->backups->key() : null,
            'redirectUri' => route('admin.backups.callback'),
            'pending' => $this->backups->pendingRequest(),
            'runs' => BackupRun::latest('started_at')->limit(40)->get(),
            'lastDatabase' => BackupRun::where('kind', 'database')->where('status', 'done')->latest('finished_at')->first(),
            'lastFiles' => BackupRun::where('kind', 'files')->where('status', 'done')->latest('finished_at')->first(),
        ]);
    }

    public function connect(Request $request)
    {
        abort_unless($this->drive->clientConfigured(), 422, 'Renseignez d\'abord l\'identifiant et le secret Google (Administration › Intégrations).');
        $state = Str::random(40);
        $request->session()->put('backup_oauth_state', $state);

        return redirect()->away($this->drive->authorizationUrl(route('admin.backups.callback'), $state));
    }

    public function callback(Request $request)
    {
        $expected = $request->session()->pull('backup_oauth_state');
        if (! $expected || ! hash_equals($expected, (string) $request->query('state'))) {
            return redirect()->route('admin.backups.index')->with('error', 'Connexion à Google expirée : recommencez.');
        }
        if ($request->query('error') || ! $request->query('code')) {
            return redirect()->route('admin.backups.index')->with('warning', 'Connexion à Google Drive annulée.');
        }
        try {
            $email = $this->drive->connect((string) $request->query('code'), route('admin.backups.callback'));
            $this->drive->folderId();
        } catch (RuntimeException $e) {
            return redirect()->route('admin.backups.index')->with('error', $e->getMessage());
        }
        $isNewKey = ! $this->backups->key();
        $this->backups->ensureKey();
        Audit::log('admin.backup_connected', null, ['account' => $email]);

        return redirect()->route('admin.backups.index')->with('success', 'Google Drive connecté ('.$email.').')
            ->with('show_key', $isNewKey);
    }

    public function disconnect()
    {
        $this->drive->disconnect();
        Audit::log('admin.backup_disconnected', null, []);

        return back()->with('success', 'Google Drive déconnecté : plus aucune sauvegarde n\'y sera envoyée. Les sauvegardes déjà envoyées restent dans le Drive.');
    }

    /** Clé de restauration affichée à la demande (une fois par demande, tracée dans le journal). */
    public function revealKey()
    {
        abort_unless($this->backups->key(), 404);
        Audit::log('admin.backup_key_viewed', null, []);

        return back()->with('show_key', true);
    }

    public function run(Request $request)
    {
        abort_unless($this->backups->ready(), 422, 'Connectez d\'abord Google Drive.');
        $this->backups->request($request->user(), $request->boolean('files'));
        Audit::log('admin.backup_requested', null, ['files' => $request->boolean('files')]);

        return back()->with('success', 'Sauvegarde lancée : elle part au prochain passage du cron (dans la minute). Rechargez la page pour suivre son avancement.');
    }
}

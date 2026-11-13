<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Horse;
use App\Services\Audit;
use App\Support\AuditLabels;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class AuditController extends Controller
{
    /** Historique des actions de tous les utilisateurs, avec recherche (action, personne, dates, catégorie, adresse IP). */
    public function index(Request $request)
    {
        $logs = $this->filtered($request)->with(['user', 'organization'])->latest('created_at')->latest('id')->paginate(50)->withQueryString();
        $horseIds = $logs->getCollection()->map(fn ($l) => $l->subject_type === (new Horse)->getMorphClass() ? $l->subject_id : ($l->properties['horse_id'] ?? null))->filter()->unique();

        return view('admin.audit', [
            'logs' => $logs,
            'horses' => Horse::withTrashed()->whereIn('id', $horseIds)->get()->keyBy('id'),
            'categories' => AuditLabels::CATEGORIES,
        ]);
    }

    /** Les mêmes lignes en CSV (20 000 au plus), pour une recherche plus large dans un tableur. */
    public function export(Request $request)
    {
        Audit::log('admin.audit_exported', null, array_filter($request->only(['q', 'user', 'from', 'to', 'category', 'ip'])));
        $rows = $this->filtered($request)->with(['user', 'organization'])->latest('created_at')->latest('id')->limit(20000)->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Date', 'Action', 'Code', 'Utilisateur', 'E-mail', 'Espace', 'Élément', 'Adresse IP'], ';');
            foreach ($rows as $l) {
                fputcsv($out, [$l->created_at->format('d/m/Y H:i:s'), AuditLabels::label($l->action), $l->action, $l->user?->name, $l->user?->email,
                    $l->organization?->name, $l->subject_type ? class_basename($l->subject_type).' #'.$l->subject_id : '', $l->ip_address], ';');
            }
            fclose($out);
        }, 'journal-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filtered(Request $request)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'], 'user' => ['nullable', 'string', 'max:150'], 'ip' => ['nullable', 'string', 'max:45'],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'category' => ['nullable', 'string', 'max:40'],
        ]);
        $query = AuditLog::query();
        if ($q = trim($data['q'] ?? '')) {
            // Recherche dans les libellés lisibles (« séance », « suppression ») comme dans les codes.
            $needle = Str::lower(Str::ascii($q));
            $actions = AuditLog::query()->distinct()->pluck('action')
                ->filter(fn ($a) => str_contains(Str::lower(Str::ascii(AuditLabels::label($a))), $needle) || str_contains(Str::lower($a), $needle));
            $query->where(fn ($w) => $w->whereIn('action', $actions->all())->orWhere('action', 'like', '%'.$q.'%'));
        }
        if ($u = trim($data['user'] ?? '')) {
            $query->whereHas('user', fn ($w) => $w->where('email', 'like', '%'.$u.'%')->orWhere('name', 'like', '%'.$u.'%'));
        }
        if ($c = $data['category'] ?? null) {
            $query->where(fn ($w) => $w->where('action', $c)->orWhere('action', 'like', $c.'.%'));
        }

        return $query
            ->when($data['ip'] ?? null, fn ($w, $ip) => $w->where('ip_address', 'like', $ip.'%'))
            ->when($data['from'] ?? null, fn ($w, $d) => $w->where('created_at', '>=', Carbon::parse($d)->startOfDay()))
            ->when($data['to'] ?? null, fn ($w, $d) => $w->where('created_at', '<=', Carbon::parse($d)->endOfDay()));
    }

    /** Dernières erreurs techniques (journal applicatif), sans les afficher aux utilisateurs. */
    public function errors()
    {
        $files = collect(glob(storage_path('logs/*.log')) ?: [])->sortByDesc(fn ($f) => filemtime($f))->take(3);
        $entries = [];
        foreach ($files as $file) {
            $lines = $this->tail($file, 400);
            foreach ($lines as $line) {
                if (preg_match('/^\[(.+?)\] \w+\.(ERROR|CRITICAL|ALERT|EMERGENCY|WARNING): (.*)$/', $line, $m)) {
                    $entries[] = ['date' => $m[1], 'level' => $m[2], 'message' => mb_substr($m[3], 0, 500), 'file' => basename($file)];
                }
            }
        }

        return view('admin.errors', ['entries' => array_reverse(array_slice($entries, -150))]);
    }

    private function tail(string $file, int $lines): array
    {
        $size = filesize($file);
        $fh = fopen($file, 'r');
        fseek($fh, max(0, $size - 400000));
        $content = stream_get_contents($fh);
        fclose($fh);

        return array_slice(explode("\n", (string) $content), -$lines);
    }
}

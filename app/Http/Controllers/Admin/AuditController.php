<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.audit', [
            'logs' => AuditLog::with(['user', 'organization'])
                ->when($request->query('action'), fn ($q, $a) => $q->where('action', 'like', "$a%"))
                ->when($request->query('user'), fn ($q, $u) => $q->whereHas('user', fn ($w) => $w->where('email', 'like', "%$u%")))
                ->latest('created_at')->paginate(50)->withQueryString(),
        ]);
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

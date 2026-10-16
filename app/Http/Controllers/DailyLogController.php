<?php

namespace App\Http\Controllers;

use App\Models\DailyLog;
use App\Models\Horse;
use App\Services\HorseAccess;
use App\Support\Perm;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DailyLogController extends Controller
{
    public static function rules(): array
    {
        return [
            'logged_at' => ['nullable', 'date'],
            'appetite' => ['nullable', Rule::in(array_keys(DailyLog::APPETITE))],
            'general_state' => ['nullable', Rule::in(array_keys(DailyLog::STATE))],
            'behavior' => ['nullable', 'string', 'max:255'],
            'activity' => ['nullable', 'string', 'max:255'],
            'observations' => ['nullable', 'string', 'max:5000'],
            'anomalies' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function index(Horse $horse)
    {
        $this->authorizeHorse($horse, Perm::HORSE_VIEW);

        return view('horses.logs', [
            'horse' => $horse,
            'logs' => $horse->dailyLogs()->with('author')->paginate(30)->withQueryString(),
            'canCreate' => $this->horseCan($horse, Perm::DAILYLOG_CREATE),
            'perms' => app(HorseAccess::class)->permissions(auth()->user(), $horse),
        ]);
    }

    public function store(Request $request, Horse $horse, HorseAccess $access)
    {
        $this->authorizeHorse($horse, Perm::DAILYLOG_CREATE);
        $data = $request->validate(self::rules());
        $data['logged_at'] ??= now();
        $log = DailyLog::create($data + ['horse_id' => $horse->id, 'author_id' => $request->user()->id]);

        if ($log->anomalies || $log->general_state === 'poor' || $log->appetite === 'none') {
            ObservationController::notifyManagers($horse, $request->user(), $access, 'Suivi quotidien : '.$horse->shortName(), 'Un point d\'attention a été noté dans le suivi quotidien par '.$request->user()->name.'.');
        }

        return back()->with('success', $request->boolean('all_good') ? 'C\'est noté : tout va bien aujourd\'hui.' : 'Suivi quotidien enregistré.');
    }

    public function edit(Horse $horse, DailyLog $log)
    {
        $this->authorizeLog($horse, $log);

        return view('horses.log-edit', ['horse' => $horse, 'log' => $log]);
    }

    public function update(Request $request, Horse $horse, DailyLog $log)
    {
        $this->authorizeLog($horse, $log);
        $data = $request->validate(self::rules());
        $data['logged_at'] ??= $log->logged_at;
        $log->update($data);

        return redirect()->route('horses.logs.index', $horse)->with('success', 'Saisie modifiée.');
    }

    public function destroy(Horse $horse, DailyLog $log)
    {
        $this->authorizeLog($horse, $log);
        $log->delete();

        return redirect()->route('horses.logs.index', $horse)->with('success', 'Saisie supprimée.');
    }

    /** Une saisie se corrige par son auteur, ou par un gestionnaire du cheval. */
    public static function canEdit(DailyLog $log, array $perms): bool
    {
        return in_array(Perm::HORSE_MANAGE, $perms, true)
            || ($log->author_id === auth()->id() && in_array(Perm::DAILYLOG_CREATE, $perms, true));
    }

    private function authorizeLog(Horse $horse, DailyLog $log): void
    {
        abort_unless($log->horse_id === $horse->id, 404);
        $this->authorizeHorse($horse, Perm::HORSE_VIEW);
        abort_unless(self::canEdit($log, app(HorseAccess::class)->permissions(auth()->user(), $horse)), 403);
    }
}

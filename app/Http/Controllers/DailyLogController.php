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
            'logs' => $horse->dailyLogs()->with('author')->paginate(30),
            'canCreate' => $this->horseCan($horse, Perm::DAILYLOG_CREATE),
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

        return back()->with('success', 'Suivi quotidien enregistré.');
    }
}

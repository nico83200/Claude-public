<?php

namespace App\Http\Controllers;

use App\Models\Horse;
use App\Models\RidingDay;
use App\Models\User;
use App\Services\RidingDays;
use App\Support\Perm;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Jours de monte d'un cheval : semaine type (propriétaire, gérant) et échanges ponctuels (la personne du jour). */
class RidingDayController extends Controller
{
    public function __construct(private RidingDays $days) {}

    /** Semaine type et quatre prochaines semaines, avec « Laisser ce jour ». */
    public function show(Request $request, Horse $horse)
    {
        $this->authorizeHorse($horse, Perm::HORSE_VIEW);
        abort_unless($this->horseCan($horse, Perm::SESSIONS_VIEW) || $this->horseCan($horse, Perm::CALENDAR_VIEW), 403);
        $canManage = $this->horseCan($horse, Perm::HORSE_MANAGE);

        return view('riding-days.show', [
            'horse' => $horse,
            'weekly' => $this->days->weekly($horse),
            'candidates' => $this->days->candidates($horse),
            'upcoming' => $this->days->between(collect([$horse]), today(), today()->addDays(27)),
            'canManage' => $canManage,
            'canRide' => $this->days->mayRide($request->user(), $horse),
        ]);
    }

    public function update(Request $request, Horse $horse)
    {
        $this->authorizeHorse($horse, Perm::HORSE_MANAGE);
        $data = $request->validate(['days' => ['nullable', 'array'], 'days.*' => ['nullable', 'integer']]);
        $this->days->setWeekly($horse, collect($data['days'] ?? [])->only(array_keys(RidingDay::WEEKDAYS))->all(), $request->user());

        return back()->with('success', 'Jours de monte enregistrés. Les personnes concernées sont prévenues.');
    }

    /** « Je te laisse samedi » : un jour donné à quelqu'un d'autre, ou laissé libre. */
    public function give(Request $request, Horse $horse)
    {
        $this->authorizeHorse($horse, Perm::HORSE_VIEW);
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d'], 'to' => ['nullable', 'integer']]);
        $to = ! empty($data['to']) ? User::find($data['to']) : null;
        abort_if(! empty($data['to']) && ! $to, 422);
        $this->days->give($horse, Carbon::parse($data['date']), $to, $request->user());
        $when = Carbon::parse($data['date'])->translatedFormat('l j F');

        return back()->with('success', $to ? ucfirst($when).' : '.$to->name.' monte '.$horse->shortName().'.' : ucfirst($when).' laissé libre.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Horse;
use App\Models\RidingDay;
use App\Models\RidingDayRequest;
use App\Models\User;
use App\Services\RidingDays;
use App\Support\Fr;
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

        $user = $request->user();
        $upcoming = $this->days->between(collect([$horse]), today(), today()->addDays(27));
        $pending = RidingDayRequest::pending()->with(['requester', 'holder'])->where('horse_id', $horse->id)->orderBy('date')->get();

        return view('riding-days.show', [
            'horse' => $horse,
            'weekly' => $this->days->weekly($horse),
            'candidates' => $this->days->candidates($horse),
            'upcoming' => $upcoming,
            'canManage' => $canManage,
            'canRide' => $this->days->mayRide($user, $horse),
            // Demandes à traiter (personne du jour, ou propriétaire pour un jour libre) et les siennes en attente.
            'toAnswer' => $pending->filter(fn ($r) => $r->requester_id !== $user->id && (($upcoming[$r->date->toDateString()][$horse->id]['user'] ?? null)?->id === $user->id || $canManage)),
            'myRequests' => $pending->where('requester_id', $user->id),
            // Ses jours à venir : à proposer en échange.
            'myDays' => collect($upcoming)->filter(fn ($byHorse) => ($byHorse[$horse->id]['user'] ?? null)?->id === $user->id)->keys()
                ->mapWithKeys(fn ($d) => [$d => ucfirst(Carbon::parse($d)->translatedFormat('l j F'))]),
            'month' => $this->days->monthCount($horse, today()),
        ]);
    }

    public function update(Request $request, Horse $horse)
    {
        $this->authorizeHorse($horse, Perm::HORSE_MANAGE);
        $data = $request->validate(['days' => ['nullable', 'array'], 'days.*' => ['nullable', 'integer']]);
        $this->days->setWeekly($horse, collect($data['days'] ?? [])->only(array_keys(RidingDay::WEEKDAYS))->all(), $request->user());

        return back()->with('success', 'Jours de monte enregistrés. Les personnes concernées sont prévenues.');
    }

    /** « Puis-je monter samedi ? », éventuellement contre un de ses jours. */
    public function requestDay(Request $request, Horse $horse)
    {
        $this->authorizeHorse($horse, Perm::HORSE_VIEW);
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d'], 'swap_date' => ['nullable', 'date_format:Y-m-d']]);
        $sent = $this->days->request($horse, Carbon::parse($data['date']), $request->user(), ! empty($data['swap_date']) ? Carbon::parse($data['swap_date']) : null);

        return back()->with('success', 'Demande envoyée'.($sent->holder ? ' à '.$sent->holder->name : ' au propriétaire').' : sa réponse vous sera notifiée.');
    }

    public function answer(Request $request, Horse $horse, RidingDayRequest $dayRequest)
    {
        $this->authorizeHorse($horse, Perm::HORSE_VIEW);
        abort_unless($dayRequest->horse_id === $horse->id, 404);
        $accept = $request->input('decision') === 'accept';
        $this->days->answer($dayRequest, $accept, $request->user());

        return back()->with('success', $accept ? ucfirst($dayRequest->date->translatedFormat('l j F')).' : '.$dayRequest->requester->name.' monte '.$horse->shortName().'.' : 'Demande refusée ; la réponse est envoyée à '.$dayRequest->requester->name.'.');
    }

    public function cancelRequest(Request $request, Horse $horse, RidingDayRequest $dayRequest)
    {
        abort_unless($dayRequest->horse_id === $horse->id && $dayRequest->requester_id === $request->user()->id && $dayRequest->status === 'pending', 404);
        $dayRequest->update(['status' => 'cancelled', 'answered_at' => now()]);

        return back()->with('success', 'Demande retirée.');
    }

    /** Absence : ses jours de la période libérés ou laissés à quelqu'un, en une fois. */
    public function absence(Request $request, Horse $horse)
    {
        $this->authorizeHorse($horse, Perm::HORSE_VIEW);
        $data = $request->validate(['from' => ['required', 'date', 'after_or_equal:today'], 'to' => ['required', 'date', 'after_or_equal:from'], 'give_to' => ['nullable', 'integer']]);
        $giveTo = ! empty($data['give_to']) ? User::find($data['give_to']) : null;
        abort_if(! empty($data['give_to']) && ! $giveTo, 422);
        $count = $this->days->absence($horse, $request->user(), Carbon::parse($data['from']), Carbon::parse($data['to']), $giveTo);

        return back()->with('success', $count ? Fr::count($count, 'jour', 'jours').' '.($giveTo ? 'laissé'.($count > 1 ? 's' : '').' à '.$giveTo->name : 'libéré'.($count > 1 ? 's' : '')).' sur la période.' : 'Aucun de vos jours sur cette période.');
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

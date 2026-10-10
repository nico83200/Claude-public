<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\CareRecord;
use App\Models\Exercise;
use App\Models\Expense;
use App\Models\Professional;
use App\Models\RidingSession;
use App\Services\HorseAccess;
use App\Support\Perm;
use Illuminate\Http\Request;

/** Recherche globale, strictement limitée aux données autorisées. */
class SearchController extends Controller
{
    public function __invoke(Request $request, HorseAccess $access)
    {
        $q = trim((string) $request->query('q'));
        $user = $request->user();
        $results = [];

        if (mb_strlen($q) >= 2) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
            $results['Chevaux'] = $access->accessibleQuery($user)->where(fn ($w) => $w->where('official_name', 'like', $like)->orWhere('usual_name', 'like', $like)
                ->orWhereHas('identifiers', fn ($i) => $i->where('value', 'like', $like)))->limit(10)->get()
                ->map(fn ($h) => ['title' => $h->displayName(), 'meta' => $h->isArchived() ? 'Archivé' : null, 'url' => route('horses.show', $h)]);

            $results['Intervenants'] = Professional::whereIn('organization_id', $user->memberships->pluck('organization_id'))
                ->where(fn ($w) => $w->where('last_name', 'like', $like)->orWhere('first_name', 'like', $like)->orWhere('company', 'like', $like))->limit(10)->get()
                ->map(fn ($p) => ['title' => $p->fullName(), 'meta' => $p->kindLabel(), 'url' => route('professionals.show', $p)]);

            $healthIds = $access->horseIdsWith($user, Perm::HEALTH_VIEW);
            $results['Soins'] = CareRecord::with(['horse', 'category'])->whereIn('horse_id', $healthIds)
                ->where(fn ($w) => $w->where('reason', 'like', $like)->orWhere('observations', 'like', $like)->orWhere('care_performed', 'like', $like))->latest('performed_at')->limit(10)->get()
                ->map(fn ($c) => ['title' => ($c->reason ?: $c->category->name).' – '.$c->horse->shortName(), 'meta' => $c->performed_at->format('d/m/Y'), 'url' => route('horses.care.show', [$c->horse_id, $c])]);

            $sessionIds = $access->horseIdsWith($user, Perm::SESSIONS_VIEW);
            $results['Séances'] = RidingSession::with('horse')->whereIn('horse_id', $sessionIds)
                ->where(fn ($w) => $w->where('objective', 'like', $like)->orWhere('notes', 'like', $like)->orWhere('progress', 'like', $like)->orWhere('to_rework', 'like', $like))->latest('scheduled_at')->limit(10)->get()
                ->map(fn ($s) => ['title' => ($s->objective ?: $s->typeLabel()).' – '.$s->horse->shortName(), 'meta' => $s->scheduled_at->format('d/m/Y'), 'url' => route('sessions.show', $s)]);

            $results['Exercices'] = Exercise::visibleTo($user)->whereNull('archived_at')->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('objective', 'like', $like))->limit(10)->get()
                ->map(fn ($e) => ['title' => $e->name, 'meta' => Exercise::SCOPES[$e->scope], 'url' => route('exercises.show', $e)]);

            $calIds = $access->horseIdsWith($user, Perm::CALENDAR_VIEW);
            $results['Rendez-vous'] = CalendarEvent::with('horse')->whereIn('horse_id', $calIds)->where('title', 'like', $like)->latest('starts_at')->limit(10)->get()
                ->map(fn ($e) => ['title' => $e->title, 'meta' => $e->starts_at->format('d/m/Y H:i'), 'url' => route('calendar.show', $e)]);

            $expIds = $access->horseIdsWith($user, Perm::EXPENSES_VIEW);
            $results['Dépenses'] = Expense::with('horse')->whereIn('horse_id', $expIds)->where(fn ($w) => $w->where('supplier', 'like', $like)->orWhere('comment', 'like', $like))->latest('spent_on')->limit(10)->get()
                ->map(fn ($e) => ['title' => ($e->supplier ?: 'Dépense').' – '.number_format((float) $e->amount, 2, ',', ' ').' €', 'meta' => $e->spent_on->format('d/m/Y'), 'url' => route('budget.index', ['horse' => $e->horse_id])]);
        }

        return view('search', ['q' => $q, 'results' => array_filter($results, fn ($r) => $r->isNotEmpty())]);
    }
}

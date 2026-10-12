<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\SubscriptionPlan;
use App\Services\Announcements;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Envoi d'un message ponctuel (information, offre…) à tous les utilisateurs ou selon leur offre. */
class AnnouncementController extends Controller
{
    public function index(Request $request, Announcements $announcements)
    {
        $plans = SubscriptionPlan::orderBy('sort_order')->orderBy('id')->get();
        $selected = array_map('intval', (array) $request->query('plans', []));

        return view('admin.announcements', [
            'plans' => $plans,
            'counts' => ['all' => $announcements->recipients(null)->count()] + $plans->mapWithKeys(fn ($p) => [$p->id => $announcements->recipients([$p->id])->count()])->all(),
            'history' => Announcement::with('sender')->latest()->limit(30)->get(),
            'selected' => $selected,
        ]);
    }

    public function store(Request $request, Announcements $announcements)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:1000'],
            'url' => ['nullable', 'string', 'max:500', 'regex:#^(/|https://)#'],
            'audience' => ['required', Rule::in(['all', 'plans'])],
            'plans' => ['required_if:audience,plans', 'array'],
            'plans.*' => ['integer', 'exists:subscription_plans,id'],
            'with_email' => ['nullable', 'boolean'],
        ], ['url.regex' => 'Le lien doit commencer par / (page de l\'application) ou https://.', 'plans.required_if' => 'Choisissez au moins une offre.']);
        $announcement = Announcement::create([
            'sent_by' => $request->user()->id, 'title' => $data['title'], 'body' => $data['body'],
            'url' => isset($data['url']) && str_starts_with($data['url'], '/') ? url($data['url']) : ($data['url'] ?? null),
            'plan_ids' => $data['audience'] === 'plans' ? array_values(array_map('intval', $data['plans'])) : null,
            'with_email' => (bool) ($data['with_email'] ?? false),
        ]);
        $count = $announcements->send($announcement);
        Audit::log('admin.announcement_sent', $announcement, ['recipients' => $count]);

        return redirect()->route('admin.announcements.index')->with($count ? 'success' : 'warning', $count
            ? 'Message envoyé à '.$count.' utilisateur'.($count > 1 ? 's' : '').'.'
            : 'Aucun utilisateur ne correspond à ces offres : rien n\'a été envoyé.');
    }
}

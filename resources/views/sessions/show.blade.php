<x-layouts.app :title="'Séance · '.$session->horse->shortName()">
    <x-page-header :title="$session->horse->shortName().' · '.$session->typeLabel()" :subtitle="$session->scheduled_at->translatedFormat('l j F Y à H:i').' · '.$session->riderLabel()" :back="route('sessions.index', ['horse' => $session->horse_id])">
        @if ($canEdit && ! $session->isCompleted())
            <a href="{{ route('sessions.live', $session) }}" class="btn-primary"><x-icon name="play" /> Mode séance</a>
            <a href="{{ route('sessions.edit', $session) }}" class="btn-secondary">Préparer</a>
            <a href="{{ route('sessions.debrief', $session) }}" class="btn-secondary">Bilan</a>
        @endif
        @if ($canCreate)<form method="POST" action="{{ route('sessions.duplicate', $session) }}">@csrf<button class="btn-ghost">Dupliquer</button></form>@endif
    </x-page-header>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-section>
                <div class="mb-3 flex flex-wrap gap-1"><x-badge :color="$session->isCompleted() ? 'green' : 'slate'">{{ \App\Models\RidingSession::STATUSES[$session->status] }}</x-badge>@if ($session->discipline)<x-badge>{{ \App\Models\Horse::DISCIPLINES[$session->discipline] ?? $session->discipline }}</x-badge>@endif</div>
                <x-dl :items="['Objectif' => $session->objective, 'Durée prévue' => $session->planned_minutes ? $session->planned_minutes.' min' : null, 'Durée réelle' => $session->actual_minutes ? $session->actual_minutes.' min' : null, 'Lieu' => $session->location, 'État avant la séance' => $session->horse_state_before, 'Précautions' => $session->precautions, 'Notes' => $session->notes]" />
            </x-section>

            <x-section title="Exercices">
                @forelse (\App\Models\RidingSession::PHASES as $phase => $label)
                    @php $items = $session->exercises->where('phase', $phase); @endphp
                    @if ($items->isNotEmpty())
                        <p class="mt-3 mb-1 text-xs font-semibold tracking-wide text-slate-500 uppercase first:mt-0">{{ $label }}</p>
                        @foreach ($items as $item)
                            <div class="flex items-start justify-between gap-3 border-t border-slate-100 py-2 text-sm">
                                <div><p class="font-medium">{{ $item->name }} @if ($item->difficulty)<x-badge color="amber">Difficulté</x-badge>@endif</p>
                                    <p class="text-xs text-slate-500">{{ collect([$item->planned_minutes ? $item->planned_minutes.' min prévues' : null, $item->planned_repetitions ? $item->planned_repetitions.' rép. prévues' : null, $item->actual_minutes ? $item->actual_minutes.' min réelles' : null, $item->done_repetitions !== null ? $item->done_repetitions.' rép. faites' : null])->filter()->implode(' · ') }}</p>
                                    @if ($item->instructions)<p class="text-xs whitespace-pre-line text-slate-600">{{ $item->instructions }}</p>@endif
                                    @if ($item->note)<p class="text-xs text-slate-700">📝 {{ $item->note }}</p>@endif
                                    @if ($item->exercise && $item->exercise_version && $item->exercise->version > $item->exercise_version)<p class="text-[11px] text-slate-400">Exercice modifié depuis dans la bibliothèque : cette séance conserve la version utilisée.</p>@endif
                                </div>
                                <x-badge :color="['done' => 'green', 'skipped' => 'red'][$item->status] ?? 'slate'">{{ ['pending' => 'Non coché', 'done' => 'Fait', 'skipped' => 'Non réalisé'][$item->status] }}</x-badge>
                            </div>
                        @endforeach
                    @endif
                @empty @endforelse
                @if ($session->exercises->isEmpty())<p class="text-sm text-slate-500">Aucun exercice.</p>@endif
            </x-section>

            @if ($session->isCompleted())
            <x-section title="Bilan">
                <div class="mb-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    @foreach (['rider_feeling' => 'Ressenti', 'horse_behavior' => 'Comportement', 'concentration' => 'Concentration', 'availability' => 'Disponibilité'] as $k => $label)
                        <div class="rounded-lg bg-slate-50 p-2 text-center"><p class="text-xs text-slate-500">{{ $label }}</p><p class="text-xl font-bold">{{ $session->$k ?? '—' }}<span class="text-sm font-normal text-slate-400">/5</span></p></div>
                    @endforeach
                </div>
                <x-dl :items="['Progrès' => $session->progress, 'Difficultés' => $session->difficulties, 'À retravailler' => $session->to_rework, 'Anomalies constatées' => $session->anomalies, 'Objectifs pour la prochaine séance' => $session->next_objectives]" />
            </x-section>
            @endif
        </div>

        <div class="space-y-4">
            <x-section title="Commentaires">
                @forelse ($session->comments as $c)<div class="border-t border-slate-100 py-2 text-sm first:border-0"><p class="whitespace-pre-line">{{ $c->body }}</p><p class="text-xs text-slate-500">{{ $c->author?->name }} · {{ $c->created_at->format('d/m H:i') }}</p></div>@empty<p class="text-sm text-slate-500">Aucun commentaire.</p>@endforelse
                @if ($canComment)
                    <form method="POST" action="{{ route('sessions.comments.store', $session) }}" class="mt-2 space-y-2">@csrf<x-textarea name="body" rows="2" required aria-label="Commentaire" /><button class="btn-secondary w-full">Commenter</button></form>
                @endif
            </x-section>
            @if ($previous->isNotEmpty())
            <x-section title="Séances précédentes">
                @foreach ($previous as $p)<a href="{{ route('sessions.show', $p) }}" class="block border-t border-slate-100 py-2 text-sm first:border-0"><span class="font-medium">{{ $p->scheduled_at->format('d/m') }}</span> {{ $p->typeLabel() }}@if ($p->to_rework)<span class="block text-xs text-amber-700">À retravailler : {{ \Illuminate\Support\Str::limit($p->to_rework, 80) }}</span>@endif @if ($p->next_objectives)<span class="block text-xs text-slate-500">Objectif suivant : {{ \Illuminate\Support\Str::limit($p->next_objectives, 80) }}</span>@endif</a>@endforeach
            </x-section>
            @endif
            <x-section title="Modèle">
                <form method="POST" action="{{ route('sessions.template', $session) }}" class="space-y-2">@csrf<x-field name="name" label="Enregistrer comme modèle" placeholder="Nom du modèle" required /><x-checkbox name="share" label="Partager dans l'espace (écurie)" /><button class="btn-secondary w-full">Créer le modèle</button></form>
            </x-section>
            @if ($canEdit)
                <x-confirm-delete :action="route('sessions.destroy', $session)" message="Supprimer cette séance ?" label="Supprimer la séance" />
            @endif
        </div>
    </div>
</x-layouts.app>

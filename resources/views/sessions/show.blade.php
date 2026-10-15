<x-layouts.app :title="'Séance · '.$session->horse->shortName()">
    <div class="mb-6" x-data="{ more: false }">
        <a href="{{ route('sessions.index', ['horse' => $session->horse_id]) }}" class="mb-3 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-800"><x-icon name="back" class="size-4" /> Séances de {{ $session->horse->shortName() }}</a>
        <div class="flex items-start gap-3">
            <div class="min-w-0 flex-1">
                <h1 class="text-[26px] leading-tight font-bold break-words sm:text-[32px]">{{ $session->isLesson() ? $session->title() : $session->typeLabel() }}</h1>
                <p class="mt-1 text-[15px] text-slate-500">{{ ucfirst($session->scheduled_at->translatedFormat('l j F à H:i')) }} · {{ $session->horse->shortName() }} · {{ $session->riderLabel() }}</p>
            </div>
            <div class="relative shrink-0" @keydown.escape.window="more = false">
                <button type="button" class="btn-icon" @click="more = ! more" :aria-expanded="more" aria-haspopup="menu" aria-label="Plus d'actions"><x-icon name="dots" /></button>
                <div x-cloak x-show="more" x-transition.origin.top.right @click.outside="more = false" role="menu" class="absolute right-0 z-40 mt-2 w-60 rounded-2xl border border-slate-100 bg-white p-1.5 shadow-xl">
                    @if ($canCreate)<form method="POST" action="{{ route('sessions.duplicate', $session) }}">@csrf<button class="menu-item" role="menuitem"><x-icon name="plus" class="size-4 text-slate-400" /> Dupliquer la séance</button></form>@endif
                    <button type="button" class="menu-item" role="menuitem" @click="more = false; document.getElementById('save-template').showModal()"><x-icon name="book" class="size-4 text-slate-400" /> Enregistrer comme modèle</button>
                    @if ($canEdit)
                        <div class="my-1 border-t border-slate-100"></div>
                        <x-confirm-delete :action="route('sessions.destroy', $session)" message="Supprimer cette séance ?" class="menu-item text-red-600"><x-icon name="trash" class="size-4" /> Supprimer la séance</x-confirm-delete>
                    @endif
                </div>
            </div>
        </div>
        @if ($canEdit)
            <div class="mt-4 flex flex-wrap gap-2">
                @unless ($session->isCompleted())
                    <a href="{{ route('sessions.debrief', $session) }}" class="btn-primary" data-debrief-link><x-icon name="check" class="size-4" /> Faire le bilan</a>
                    @if ($session->usesExercises())<a href="{{ route('sessions.edit', $session) }}" class="btn-secondary" data-prepare-link><x-icon name="session" class="size-4" /> Préparer</a>@endif
                @endunless
                <a href="{{ route('sessions.details', $session) }}" class="btn-secondary" data-edit-session><x-icon name="pencil" class="size-4" /> Modifier</a>
            </div>
        @endif
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-section>
                <div class="flex flex-wrap gap-1.5"><x-badge :color="$session->isCompleted() ? 'green' : ($session->status === 'in_progress' ? 'amber' : 'slate')">{{ \App\Models\RidingSession::STATUSES[$session->status] }}</x-badge>@if ($session->discipline)<x-badge>{{ \App\Models\Horse::DISCIPLINES[$session->discipline] ?? $session->discipline }}</x-badge>@endif @if ($session->actual_minutes || $session->planned_minutes)<x-badge color="brand">{{ $session->actual_minutes ?: $session->planned_minutes }} min</x-badge>@endif</div>
                @if ($session->objective)<p class="mt-3 text-lg font-medium text-slate-900">{{ $session->objective }}</p>@endif
                <div class="mt-4"></div>
                <x-dl :items="['Durée prévue' => $session->planned_minutes ? $session->planned_minutes.' min' : null, 'Durée réelle' => $session->actual_minutes ? $session->actual_minutes.' min' : null, 'Lieu' => $session->location, 'État avant la séance' => $session->horse_state_before, 'Précautions' => $session->precautions, 'Notes' => $session->notes]" />
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
                                    @if ($item->exercise?->media_path)<a href="{{ route('exercises.show', $item->exercise) }}#demonstration" class="mt-0.5 inline-flex items-center gap-1 text-xs link"><x-icon :name="$item->exercise->media_type === 'video' ? 'play' : 'camera'" class="size-3.5" /> Voir la démonstration</a>@endif
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
                @forelse ($session->comments as $c)<div class="border-t border-slate-100 py-2 text-sm first:border-0"><p class="whitespace-pre-line">{{ $c->body }}</p><p class="text-xs text-slate-500">{{ $c->author?->name }} · {{ $c->created_at->format('d/m H:i') }}</p></div>@empty<p class="text-sm text-slate-500">Aucun commentaire pour le moment.</p>@endforelse
                @if ($canComment)
                    <form method="POST" action="{{ route('sessions.comments.store', $session) }}" class="mt-3 flex items-end gap-2">@csrf<textarea name="body" rows="1" required aria-label="Commentaire" placeholder="Ajouter un commentaire…" class="input min-h-11 flex-1 resize-none" oninput="this.style.height = 'auto'; this.style.height = this.scrollHeight + 'px'"></textarea><button class="btn-primary size-11 shrink-0 p-0" aria-label="Commenter"><x-icon name="send" class="size-5" /></button></form>
                @endif
            </x-section>
            @if ($previous->isNotEmpty())
            <x-section title="Séances précédentes">
                @foreach ($previous as $p)<a href="{{ route('sessions.show', $p) }}" class="block border-t border-slate-100 py-2 text-sm first:border-0"><span class="font-medium">{{ $p->scheduled_at->format('d/m') }}</span> {{ $p->typeLabel() }}@if ($p->to_rework)<span class="block text-xs text-amber-700">À retravailler : {{ \Illuminate\Support\Str::limit($p->to_rework, 80) }}</span>@endif @if ($p->next_objectives)<span class="block text-xs text-slate-500">Objectif suivant : {{ \Illuminate\Support\Str::limit($p->next_objectives, 80) }}</span>@endif</a>@endforeach
            </x-section>
            @endif
        </div>
    </div>

    <dialog id="save-template" class="m-auto w-[calc(100%-2rem)] max-w-md rounded-3xl p-0 backdrop:bg-slate-900/40">
        <form method="POST" action="{{ route('sessions.template', $session) }}" class="space-y-4 p-6">
            @csrf
            <div class="flex items-start justify-between gap-2">
                <div><h2 class="text-lg font-semibold">Enregistrer comme modèle</h2><p class="text-sm text-slate-500">Réutilisez ces exercices pour une prochaine séance.</p></div>
                <button type="button" class="btn-icon border-0" onclick="this.closest('dialog').close()" aria-label="Fermer"><x-icon name="x" /></button>
            </div>
            <x-field name="name" label="Nom du modèle" required />
            <x-checkbox name="share" label="Partager dans l'espace (écurie)" />
            <button class="btn-primary w-full">Créer le modèle</button>
        </form>
    </dialog>
</x-layouts.app>

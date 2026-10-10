<x-layouts.app :title="'Préparer la séance · '.$session->horse->shortName()">
    <x-page-header :title="'Préparer : '.$session->horse->shortName()" :subtitle="$session->scheduled_at->translatedFormat('l j F à H:i')" :back="route('sessions.show', $session)">
        <a href="{{ route('sessions.live', $session) }}" class="btn-primary"><x-icon name="play" /> Démarrer</a>
    </x-page-header>

    <div class="grid gap-4 lg:grid-cols-5">
        <div class="space-y-4 lg:col-span-3">
            @foreach (\App\Models\RidingSession::PHASES as $phase => $label)
                @php $items = $session->exercises->where('phase', $phase)->values(); @endphp
                <x-section :title="$label">
                    @forelse ($items as $i => $item)
                        <div x-data="{ open: false }" class="border-t border-slate-100 py-2 first:border-0">
                            <div class="flex items-center gap-2">
                                <div class="flex flex-col">
                                    <form method="POST" action="{{ route('sessions.exercises.move', [$session, $item]) }}">@csrf<input type="hidden" name="direction" value="up"><button class="px-1 text-slate-400 hover:text-slate-700 disabled:opacity-20" @disabled($i === 0) aria-label="Monter">▲</button></form>
                                    <form method="POST" action="{{ route('sessions.exercises.move', [$session, $item]) }}">@csrf<input type="hidden" name="direction" value="down"><button class="px-1 text-slate-400 hover:text-slate-700 disabled:opacity-20" @disabled($i === $items->count() - 1) aria-label="Descendre">▼</button></form>
                                </div>
                                <button type="button" @click="open = ! open" class="min-w-0 flex-1 text-left text-sm">
                                    <span class="font-medium">{{ $item->name }}</span>
                                    <span class="block text-xs text-slate-500">{{ collect([$item->planned_minutes ? $item->planned_minutes.' min' : null, $item->planned_repetitions ? $item->planned_repetitions.' rép.' : null, $item->instructions ? 'consignes' : null])->filter()->implode(' · ') ?: 'Définir durée, répétitions, consignes' }}</span>
                                </button>
                                <x-confirm-delete :action="route('sessions.exercises.destroy', [$session, $item])" message="Retirer cet exercice ?" class="btn-ghost px-2 text-red-600"><x-icon name="trash" class="size-4" /></x-confirm-delete>
                            </div>
                            <form x-show="open" x-cloak method="POST" action="{{ route('sessions.exercises.update', [$session, $item]) }}" class="mt-2 grid gap-2 rounded-lg bg-slate-50 p-3 sm:grid-cols-3">
                                @csrf @method('PUT')
                                <x-field name="planned_minutes" type="number" label="Durée (min)" :value="$item->planned_minutes" min="0" max="300" />
                                <x-field name="planned_repetitions" type="number" label="Répétitions" :value="$item->planned_repetitions" min="0" max="500" />
                                <x-select name="phase" label="Phase" :options="\App\Models\RidingSession::PHASES" :value="$item->phase" />
                                <x-textarea name="instructions" label="Consignes" :value="$item->instructions" class="sm:col-span-3" />
                                <button class="btn-secondary sm:col-span-3">Enregistrer</button>
                            </form>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Aucun exercice dans cette phase.</p>
                    @endforelse
                    <form method="POST" action="{{ route('sessions.exercises.store', $session) }}" class="mt-2 flex flex-wrap gap-2">
                        @csrf
                        <input type="hidden" name="items[0][phase]" value="{{ $phase }}">
                        <input type="hidden" name="items[0][is_break]" value="1">
                        <input type="hidden" name="items[0][name]" value="Pause">
                        <input type="hidden" name="items[0][planned_minutes]" value="2">
                        <button class="btn-ghost text-sm">+ Pause</button>
                    </form>
                </x-section>
            @endforeach

            <x-section title="Informations de la séance">
                <form method="POST" action="{{ route('sessions.update', $session) }}" class="grid gap-3 sm:grid-cols-2">
                    @csrf @method('PUT')
                    <x-field name="scheduled_at" type="datetime-local" label="Date et heure" :value="$session->scheduled_at->format('Y-m-d\TH:i')" required />
                    <x-select name="session_type" label="Type" :options="\App\Models\RidingSession::TYPES" :value="$session->session_type" required />
                    <x-select name="discipline" label="Discipline" :options="\App\Models\Horse::DISCIPLINES" :value="$session->discipline" placeholder="—" />
                    <x-select name="rider_id" label="Cavalier" :options="$riders" :value="$session->rider_id" placeholder="—" />
                    <x-field name="objective" label="Objectif" :value="$session->objective" class="sm:col-span-2" />
                    <x-field name="planned_minutes" type="number" label="Durée prévue" :value="$session->planned_minutes" />
                    <x-field name="location" label="Lieu" :value="$session->location" />
                    <x-field name="horse_state_before" label="État avant" :value="$session->horse_state_before" class="sm:col-span-2" />
                    <x-textarea name="precautions" label="Précautions" :value="$session->precautions" class="sm:col-span-2" />
                    <x-textarea name="notes" label="Notes" :value="$session->notes" class="sm:col-span-2" />
                    <x-select name="status" label="Statut" :options="['planned' => 'Prévue', 'in_progress' => 'En cours', 'cancelled' => 'Annulée']" :value="$session->status" />
                    <div class="flex items-end"><button class="btn-primary w-full">Enregistrer</button></div>
                </form>
            </x-section>
        </div>

        {{-- Bibliothèque : ajout multiple --}}
        <div class="lg:col-span-2">
            <x-section title="Bibliothèque d'exercices" class="lg:sticky lg:top-20">
                <form method="POST" action="{{ route('sessions.exercises.store', $session) }}" x-data="{ q: '', cat: '', phase: 'main', picked: [] }">
                    @csrf
                    <div class="mb-2 grid grid-cols-2 gap-2">
                        <input x-model="q" placeholder="Rechercher" class="input py-2 text-sm" aria-label="Rechercher un exercice">
                        <select x-model="cat" class="input py-2 text-sm" aria-label="Catégorie"><option value="">Toutes catégories</option>@foreach ($categories as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select>
                    </div>
                    <div class="max-h-[55vh] space-y-1 overflow-y-auto pr-1">
                        @foreach ($library as $ex)
                            <label x-show="(! q || @js(mb_strtolower($ex->name.' '.$ex->objective)).includes(q.toLowerCase())) && (! cat || cat == '{{ $ex->exercise_category_id }}')" class="flex cursor-pointer items-start gap-2 rounded-lg border border-slate-200 p-2 text-sm hover:bg-slate-50">
                                <input type="checkbox" value="{{ $ex->id }}" x-model="picked" class="mt-0.5 rounded">
                                <span><span class="font-medium">{{ $ex->name }}</span><span class="block text-xs text-slate-500">{{ $ex->category?->name }} · {{ \App\Models\Exercise::LEVELS[$ex->level] }}{{ $ex->duration_minutes ? ' · '.$ex->duration_minutes.' min' : '' }}</span></span>
                            </label>
                        @endforeach
                    </div>
                    <template x-for="(id, i) in picked" :key="id"><span><input type="hidden" :name="'items[' + i + '][exercise_id]'" :value="id"><input type="hidden" :name="'items[' + i + '][phase]'" :value="phase"></span></template>
                    <div class="mt-3 flex gap-2">
                        <select x-model="phase" class="input py-2 text-sm" aria-label="Phase">@foreach (\App\Models\RidingSession::PHASES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                        <button class="btn-primary shrink-0" :disabled="! picked.length" x-text="'Ajouter (' + picked.length + ')'"></button>
                    </div>
                </form>
                <form method="POST" action="{{ route('sessions.exercises.store', $session) }}" class="mt-4 space-y-2 border-t border-slate-100 pt-3">
                    @csrf
                    <p class="text-sm font-medium">Exercice libre</p>
                    <input name="items[0][name]" placeholder="Nom de l'exercice" required class="input py-2 text-sm" aria-label="Nom de l'exercice libre">
                    <div class="flex gap-2"><select name="items[0][phase]" class="input py-2 text-sm" aria-label="Phase">@foreach (\App\Models\RidingSession::PHASES as $k => $l)<option value="{{ $k }}" @selected($k === 'main')>{{ $l }}</option>@endforeach</select><button class="btn-secondary">Ajouter</button></div>
                </form>
            </x-section>
        </div>
    </div>
</x-layouts.app>

<x-layouts.app :title="'Préparer la séance · '.$session->horse->shortName()">
    <x-page-header :title="'Préparer : '.$session->horse->shortName()" :subtitle="$session->scheduled_at->translatedFormat('l j F à H:i')" :back="route('sessions.show', $session)">
        <a href="{{ route('sessions.live', $session) }}" class="btn-primary"><x-icon name="play" /> Démarrer</a>
    </x-page-header>

    <div class="grid gap-4 lg:grid-cols-5" x-data="Object.assign(sessionPlanner(@js(route('sessions.exercises.layout', $session))), { pickPhase: 'main', pickPhaseLabel: '', openPicker(phase, label) { this.pickPhase = phase; this.pickPhaseLabel = label; this.$refs.picker.showModal(); } })">
        <div class="space-y-4 lg:col-span-3">
            <p class="flex items-center gap-2 text-sm text-slate-600"><span class="text-gold-600" aria-hidden="true">⠿</span> <span class="lg:hidden">Touchez « + Exercices » dans chaque étape pour choisir les exercices ; glissez-les par la poignée pour les réordonner.</span><span class="hidden lg:inline">Glissez les exercices par la poignée pour les réordonner, les changer de section ou les ajouter depuis la bibliothèque.</span>
                <span x-show="saving" x-cloak class="ml-auto text-xs text-slate-500">Enregistrement…</span></p>
            <p x-show="error" x-cloak x-text="error" class="rounded-lg bg-red-50 p-3 text-sm text-red-800" role="alert"></p>
            @foreach (\App\Models\RidingSession::PHASES as $phase => $label)
                @php $items = $session->exercises->where('phase', $phase)->values(); @endphp
                <x-section :title="$label">
                    <div data-phase="{{ $phase }}" class="drop-zone -mx-2 min-h-14 rounded-xl px-2">
                    @foreach ($items as $i => $item)
                        <div x-data="{ open: false }" data-token="item:{{ $item->id }}" class="border-t border-slate-100 bg-white py-2 first:border-0">
                            <div class="flex items-center gap-2">
                                <button type="button" data-handle class="flex size-9 shrink-0 cursor-grab touch-none items-center justify-center rounded-lg text-slate-400 hover:bg-sand-100 hover:text-slate-700 active:cursor-grabbing" aria-label="Glisser pour déplacer" title="Glisser pour déplacer">⠿</button>
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
                    @endforeach
                        <p class="empty-hint py-3 text-center text-sm text-slate-500">Déposez un exercice ici</p>
                    </div>
                    <form method="POST" action="{{ route('sessions.exercises.store', $session) }}" class="mt-2 flex flex-wrap gap-2">
                        <button type="button" class="btn-secondary text-sm" @click="openPicker(@js($phase), @js($label))" data-open-picker="{{ $phase }}"><x-icon name="plus" class="size-4" /> Exercices</button>
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
                @include('sessions._details-form')
            </x-section>
        </div>

        <form id="galop-form" method="POST" action="{{ route('settings.riding-level') }}" class="hidden">@csrf</form>
        {{-- Fenêtre de choix pour une étape (plein écran sur téléphone) : exercices groupés par catégorie, les plus utiles à l'étape en premier. --}}
        <dialog x-ref="picker" data-exercise-picker class="m-0 h-dvh max-h-none w-full max-w-none p-0 sm:m-auto sm:h-auto sm:max-h-[88vh] sm:w-[calc(100%-2rem)] sm:max-w-2xl sm:rounded-3xl">
            <form method="POST" action="{{ route('sessions.exercises.store', $session) }}" class="flex h-full max-h-[inherit] flex-col" x-data="{ q: '', picked: [], free: '' }" @submit="if (! picked.length && ! free.trim()) $event.preventDefault()">
                @csrf
                <div class="border-b border-slate-100 p-4 pt-[max(1rem,env(safe-area-inset-top))]">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-lg font-semibold">Ajouter à <span x-text="pickPhaseLabel"></span></h2>
                        <button type="button" class="btn-ghost min-h-10 px-2" @click="$refs.picker.close()" aria-label="Fermer"><x-icon name="x" class="size-5" /></button>
                    </div>
                    <input x-model="q" type="search" placeholder="Rechercher un exercice" class="input mt-3" aria-label="Rechercher un exercice">
                </div>
                <div class="flex flex-1 flex-col overflow-y-auto p-4" data-picker-groups>
                    {{-- Pour le cavalier : exercices habituels (historique) et exercices adaptés à son galop. --}}
                    @php $byId = $library->keyBy('id'); $sugRider = $suggestions['rider']; $isMe = $sugRider && $sugRider->id === auth()->id(); @endphp
                    @if ($isMe && ! $suggestions['galop'])
                        <div class="mb-4 rounded-2xl bg-gold-50 p-3 ring-1 ring-gold-200" style="order:-2" data-ask-galop>
                            <p class="text-sm font-medium text-slate-900">Quel est votre niveau ?</p>
                            <p class="text-xs text-slate-600">Pour vous proposer des exercices adaptés. Il sera enregistré dans votre fiche.</p>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @foreach (\App\Models\UserProfile::GALOPS as $g => $gl)
                                    <button type="submit" form="galop-form" name="galop" value="{{ $g }}" class="btn-secondary min-h-9 px-3 text-sm">{{ $gl }}</button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @foreach ($suggestions['phases'] as $sugPhase => $sugItems)
                        @continue(empty($sugItems))
                        <section class="mb-4" style="order:-1" x-show="pickPhase === @js($sugPhase) && ! q" data-suggestions="{{ $sugPhase }}">
                            <h3 class="sticky top-0 z-10 -mx-4 flex items-center gap-1.5 bg-[var(--color-white)] px-4 py-1.5 text-xs font-semibold tracking-wide text-brand-700 uppercase"><x-icon name="sparkles" class="size-3.5" /> {{ $isMe ? 'Pour vous' : 'Pour '.($sugRider?->name ?? 'le cavalier') }}{{ $suggestions['galop'] ? ' · Galop '.$suggestions['galop'] : '' }}</h3>
                            <div class="space-y-1.5">
                                @foreach ($sugItems as $sug)
                                    @php $ex = $byId[$sug['id']]; @endphp
                                    <label class="flex min-h-12 cursor-pointer items-start gap-3 rounded-xl border border-brand-200 bg-brand-50/40 p-3 text-sm has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50">
                                        <input type="checkbox" value="{{ $ex->id }}" x-model="picked" class="mt-0.5 size-5 shrink-0 rounded">
                                        <span class="min-w-0"><span class="font-medium">{{ $ex->name }}</span><span class="block text-xs text-brand-800">{{ $sug['reason'] }}</span><span class="block text-xs text-slate-500">{{ $ex->category?->name }} · {{ \App\Models\Exercise::LEVELS[$ex->level] }}{{ $ex->duration_minutes ? ' · '.$ex->duration_minutes.' min' : '' }}</span></span>
                                    </label>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                    @foreach ($categories as $catId => $catName)
                        @php $inCat = $library->where('exercise_category_id', $catId); @endphp
                        @continue($inCat->isEmpty())
                        <section class="mb-4" :style="'order:' + (@js(collect($phasePriority)->map(fn ($r) => $r[$catId] ?? 999))[pickPhase] ?? 999)" data-category="{{ $catName }}"
                                 x-show="! q || @js($inCat->map(fn ($e) => mb_strtolower($e->name.' '.$e->objective))->implode(' | ')).includes(q.toLowerCase())">
                            <h3 class="sticky top-0 z-10 -mx-4 bg-[var(--color-white)] px-4 py-1.5 text-xs font-semibold tracking-wide text-slate-500 uppercase">{{ $catName }}</h3>
                            <div class="space-y-1.5">
                                @foreach ($inCat as $ex)
                                    <label class="flex min-h-12 cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 text-sm has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50"
                                           x-show="! q || @js(mb_strtolower($ex->name.' '.$ex->objective)).includes(q.toLowerCase())">
                                        <input type="checkbox" value="{{ $ex->id }}" x-model="picked" class="mt-0.5 size-5 shrink-0 rounded">
                                        <span class="min-w-0"><span class="font-medium">{{ $ex->name }}</span><span class="block text-xs text-slate-500">{{ \App\Models\Exercise::LEVELS[$ex->level] }}{{ $ex->duration_minutes ? ' · '.$ex->duration_minutes.' min' : '' }}{{ $ex->objective ? ' · '.\Illuminate\Support\Str::limit($ex->objective, 60) : '' }}</span></span>
                                    </label>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                    <div class="order-last mt-2 rounded-xl bg-slate-50 p-3" style="order: 10000">
                        <label class="label" for="picker-free">Exercice libre (absent de la bibliothèque)</label>
                        <input id="picker-free" x-model="free" maxlength="150" placeholder="Nom de l'exercice" class="input">
                    </div>
                </div>
                <template x-for="(id, i) in picked" :key="id"><span><input type="hidden" :name="'items[' + i + '][exercise_id]'" :value="id"><input type="hidden" :name="'items[' + i + '][phase]'" :value="pickPhase"></span></template>
                <template x-if="free.trim()"><span><input type="hidden" :name="'items[' + picked.length + '][name]'" :value="free.trim()"><input type="hidden" :name="'items[' + picked.length + '][phase]'" :value="pickPhase"></span></template>
                <div class="border-t border-slate-100 p-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
                    <button class="btn-primary w-full" :disabled="! picked.length && ! free.trim()" x-text="(n => n ? 'Ajouter ' + n + ' exercice' + (n > 1 ? 's' : '') : 'Choisissez des exercices')(picked.length + (free.trim() ? 1 : 0))">Choisissez des exercices</button>
                </div>
            </form>
        </dialog>

        {{-- Bibliothèque : ajout multiple et glisser-déposer (ordinateur) --}}
        <div class="hidden lg:col-span-2 lg:block">
            <x-section title="Bibliothèque d'exercices" class="lg:sticky lg:top-20">
                <form method="POST" action="{{ route('sessions.exercises.store', $session) }}" x-data="{ q: '', cat: '', phase: 'main', picked: [] }">
                    @csrf
                    <div class="mb-2 grid grid-cols-2 gap-2">
                        <input x-model="q" placeholder="Rechercher" class="input py-2 text-sm" aria-label="Rechercher un exercice">
                        <select x-model="cat" class="input py-2 text-sm" aria-label="Catégorie"><option value="">Toutes catégories</option>@foreach ($categories as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select>
                    </div>
                    <div class="max-h-[55vh] space-y-1 overflow-y-auto pr-1" data-library>
                        @foreach ($library as $ex)
                            <label data-token="ex:{{ $ex->id }}" x-show="(! q || @js(mb_strtolower($ex->name.' '.$ex->objective)).includes(q.toLowerCase())) && (! cat || cat == '{{ $ex->exercise_category_id }}')" class="flex cursor-pointer items-start gap-2 rounded-lg border border-slate-200 p-2 text-sm hover:bg-slate-50">
                                <span data-handle class="-my-1 -ml-1 flex size-8 shrink-0 cursor-grab touch-none items-center justify-center rounded-lg text-slate-400 hover:bg-sand-100" title="Glisser vers une section" aria-hidden="true">⠿</span>
                                <input type="checkbox" value="{{ $ex->id }}" x-model="picked" class="mt-0.5 rounded">
                                <span class="min-w-0"><span class="font-medium">{{ $ex->name }}</span><span class="block text-xs text-slate-500">{{ $ex->category?->name }} · {{ \App\Models\Exercise::LEVELS[$ex->level] }}{{ $ex->duration_minutes ? ' · '.$ex->duration_minutes.' min' : '' }}</span></span>
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

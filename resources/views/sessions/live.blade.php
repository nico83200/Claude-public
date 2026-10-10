<x-layouts.app :title="'Séance en cours · '.$session->horse->shortName()">
    <div x-data="liveSession(@js(['update' => route('sessions.exercises.update', [$session, '__ID__'])]))" class="mx-auto max-w-xl">
        <div class="mb-3 flex items-center justify-between">
            <div><p class="text-lg font-bold">{{ $session->horse->shortName() }}</p><p class="text-sm text-slate-500">{{ $session->typeLabel() }}{{ $session->objective ? ' · '.$session->objective : '' }}</p></div>
            <a href="{{ route('sessions.show', $session) }}" class="btn-ghost">Quitter</a>
        </div>
        @if ($session->precautions || $session->horse->precautions)<div class="mb-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm"><strong>Précautions :</strong> {{ $session->precautions ?: $session->horse->precautions }}</div>@endif
        <p x-show="error" x-cloak class="mb-3 rounded-xl bg-red-50 p-3 text-sm text-red-800" x-text="error"></p>
        <p class="mb-3 text-xs text-slate-500">Sans réseau à l'écurie ? Utilisez le <a href="{{ route('offline.app') }}" class="link">mode écurie</a> (hors ligne) après avoir rendu le cheval disponible hors ligne.</p>

        @foreach (\App\Models\RidingSession::PHASES as $phase => $label)
            @php $items = $session->exercises->where('phase', $phase); @endphp
            @if ($items->isNotEmpty())
                <p class="mt-4 mb-2 text-sm font-semibold tracking-wide text-slate-500 uppercase">{{ $label }}</p>
                <div class="space-y-3">
                    @foreach ($items as $item)
                        <div class="card p-4" x-data="{ s: @js($item->only(['id', 'status', 'done_repetitions', 'actual_minutes', 'difficulty', 'note'])) }" :class="s.status === 'done' ? 'border-emerald-300 bg-emerald-50' : (s.status === 'skipped' ? 'opacity-70' : '')">
                            <div class="flex items-start justify-between gap-2">
                                <div><p class="text-lg font-semibold">{{ $item->name }}</p><p class="text-sm text-slate-500">{{ collect([$item->planned_minutes ? $item->planned_minutes.' min' : null, $item->planned_repetitions ? $item->planned_repetitions.' répétitions' : null])->filter()->implode(' · ') }}</p>@if ($item->instructions)<p class="mt-1 text-sm whitespace-pre-line">{{ $item->instructions }}</p>@endif</div>
                                <button type="button" @click="save(s, { difficulty: ! s.difficulty })" class="rounded-lg p-2" :class="s.difficulty ? 'bg-amber-100 text-amber-700' : 'text-slate-400'" :aria-pressed="s.difficulty" aria-label="Difficulté"><x-icon name="alert" class="size-6" /></button>
                            </div>
                            <div class="mt-3 grid grid-cols-2 gap-2">
                                <button type="button" @click="save(s, { status: s.status === 'done' ? 'pending' : 'done' })" class="btn big-touch" :class="s.status === 'done' ? 'bg-emerald-600 text-white' : 'border border-emerald-600 text-emerald-700'"><x-icon name="check" /> <span x-text="s.status === 'done' ? 'Fait' : 'Marquer fait'"></span></button>
                                <button type="button" @click="save(s, { status: s.status === 'skipped' ? 'pending' : 'skipped' })" class="btn big-touch border border-slate-300" :class="s.status === 'skipped' && 'bg-slate-200'" x-text="s.status === 'skipped' ? 'Non réalisé' : 'Pas fait'"></button>
                            </div>
                            @unless ($item->is_break)
                            <div class="mt-2 flex items-center gap-2">
                                <button type="button" @click="save(s, { done_repetitions: Math.max(0, (s.done_repetitions ?? 0) - 1) })" class="btn-secondary big-touch w-14 text-xl" aria-label="Moins">−</button>
                                <span class="flex-1 text-center"><span class="text-2xl font-bold" x-text="s.done_repetitions ?? 0"></span> rép.</span>
                                <button type="button" @click="save(s, { done_repetitions: (s.done_repetitions ?? 0) + 1 })" class="btn-secondary big-touch w-14 text-xl" aria-label="Plus">+</button>
                            </div>
                            @endunless
                            <div class="mt-2 flex gap-2 text-sm">
                                <button type="button" class="btn-ghost" @click="let v = prompt('Durée réelle (min)', s.actual_minutes ?? ''); if (v !== null && v !== '') save(s, { actual_minutes: parseInt(v) || 0 })" x-text="s.actual_minutes ? s.actual_minutes + ' min réelles' : 'Durée réelle'"></button>
                                <button type="button" class="btn-ghost" @click="let v = prompt('Observation', s.note ?? ''); if (v !== null) save(s, { note: v.slice(0, 255) })" x-text="s.note ? 'Note : ' + s.note.slice(0, 20) : 'Ajouter une note'"></button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @endforeach
        @if ($session->exercises->isEmpty())<x-empty title="Aucun exercice préparé"><x-slot:actions><a href="{{ route('sessions.edit', $session) }}" class="btn-primary">Préparer la séance</a></x-slot:actions></x-empty>@endif
        <a href="{{ route('sessions.debrief', $session) }}" class="btn-primary big-touch mt-6 w-full">Terminer et faire le bilan</a>
    </div>
    @push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('liveSession', (urls) => ({
                error: null,
                async save(s, changes) {
                    const before = { ...s };
                    Object.assign(s, changes);
                    try {
                        const res = await fetch(urls.update.replace('__ID__', s.id), {
                            method: 'PUT', credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                            body: JSON.stringify(changes),
                        });
                        if (!res.ok) throw new Error((await res.json().catch(() => ({}))).message || 'Erreur ' + res.status);
                        this.error = null;
                    } catch (e) {
                        Object.assign(s, before);
                        this.error = navigator.onLine ? ('Non enregistré : ' + e.message) : 'Hors ligne : la modification n\'a pas été enregistrée. Utilisez le mode écurie pour travailler sans réseau.';
                    }
                },
            }));
        });
    </script>
    @endpush
</x-layouts.app>

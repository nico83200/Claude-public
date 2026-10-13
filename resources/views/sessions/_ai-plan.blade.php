{{-- Plan de séance proposé par l'assistant (Claude) : on choisit les exercices à garder avant de les ajouter. --}}
@php
    $planReady = app(\App\Services\Assistant\AssistantService::class)->serviceReady();
    $planIncluded = app(\App\Services\Entitlements::class)->hasFeature($session->horse->organization, 'assistant');
@endphp
@if ($planReady)
    <div class="card flex items-center gap-4 p-4" x-data="{
            busy: false, error: '', summary: '', items: [], minutes: 0,
            async ask() {
                this.busy = true; this.error = '';
                try {
                    const r = await fetch(@js(route('sessions.plan', $session)), { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content } });
                    const json = await r.json();
                    if (! r.ok) { this.error = json.error || 'Plan indisponible.'; return; }
                    this.summary = json.summary; this.minutes = json.minutes;
                    this.items = json.items.map(i => ({ ...i, keep: true }));
                    this.$refs.plan.showModal();
                } catch (e) { this.error = 'Plan indisponible (connexion).'; } finally { this.busy = false; }
            },
            kept() { return this.items.filter(i => i.keep); },
        }" data-ai-plan>
        <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-brand-50 text-brand-800"><x-icon name="sparkles" class="size-6" /></span>
        <div class="min-w-0 flex-1">
            <p class="font-semibold text-slate-900">Besoin d'idées ?</p>
            <p class="text-sm text-slate-500" x-show="! busy && ! error">L'assistant propose un plan selon l'objectif, la durée, votre niveau et les derniers bilans.</p>
            <p class="text-sm text-brand-800" x-show="busy" x-cloak><span class="thinking-dots"><span>●</span><span>●</span><span>●</span></span> Préparation du plan…</p>
            <p class="text-sm text-red-600" x-show="error" x-text="error" x-cloak></p>
        </div>
        @if ($planIncluded)
            <button type="button" class="btn-primary min-h-11 shrink-0 px-4" @click="ask()" :disabled="busy" data-ai-plan-ask>Proposer un plan</button>
        @else
            <a href="{{ route('billing.index', ['fonction' => 'assistant']) }}#offres" class="btn-secondary min-h-11 shrink-0 px-4">Proposer un plan <x-locked-mark :org="$session->horse->organization" feature="assistant" /></a>
        @endif

        <dialog x-ref="plan" class="m-auto w-[calc(100%-1rem)] max-w-xl rounded-3xl p-0 backdrop:bg-slate-900/50">
            <form method="POST" action="{{ route('sessions.exercises.store', $session) }}" class="flex max-h-[85dvh] flex-col">
                @csrf
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 p-5">
                    <div><h2 class="text-lg font-semibold">Plan proposé</h2><p class="text-sm text-slate-500" x-text="summary"></p></div>
                    <button type="button" class="btn-ghost px-2" @click="$refs.plan.close()" aria-label="Fermer"><x-icon name="x" /></button>
                </div>
                <div class="flex-1 overflow-y-auto p-5">
                    <template x-for="(item, idx) in items" :key="idx">
                        <div>
                            <p class="mt-3 mb-1 text-xs font-semibold tracking-wide text-slate-500 uppercase first:mt-0" x-show="idx === 0 || items[idx - 1].phase !== item.phase" x-text="item.phase_label"></p>
                            <label class="flex items-start gap-3 rounded-2xl px-2 py-2 hover:bg-slate-50">
                                <input type="checkbox" x-model="item.keep" class="mt-1 size-5 rounded">
                                <span class="min-w-0 flex-1">
                                    <span class="block font-medium text-slate-900"><span x-text="item.name"></span> <span class="text-xs font-normal text-slate-500" x-show="item.planned_minutes" x-text="'· ' + item.planned_minutes + ' min'"></span></span>
                                    <span class="block text-sm text-slate-500" x-text="item.instructions"></span>
                                    <span class="text-xs text-amber-700" x-show="! item.in_library">Hors bibliothèque : ajouté comme exercice libre</span>
                                </span>
                            </label>
                        </div>
                    </template>
                    <template x-for="(item, n) in kept()" :key="'h' + n">
                        <span>
                            <input type="hidden" :name="'items[' + n + '][exercise_id]'" :value="item.exercise_id ?? ''">
                            <input type="hidden" :name="'items[' + n + '][name]'" :value="item.exercise_id ? '' : item.name">
                            <input type="hidden" :name="'items[' + n + '][phase]'" :value="item.phase">
                            <input type="hidden" :name="'items[' + n + '][planned_minutes]'" :value="item.planned_minutes ?? ''">
                            <input type="hidden" :name="'items[' + n + '][instructions]'" :value="item.exercise_id ? '' : item.instructions">
                        </span>
                    </template>
                </div>
                <div class="border-t border-slate-100 p-5">
                    <button class="btn-primary w-full" :disabled="! kept().length" x-text="kept().length ? 'Ajouter ' + kept().length + ' exercice' + (kept().length > 1 ? 's' : '') + ' à la séance' : 'Choisissez des exercices'" data-ai-plan-add></button>
                    <p class="mt-2 text-center text-xs text-slate-500">Proposition à adapter : vous restez seul juge de ce qui convient à votre cheval.</p>
                </div>
            </form>
        </dialog>
    </div>
@endif

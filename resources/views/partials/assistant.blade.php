{{-- Assistant vocal : panneau (feuille sur mobile, fenêtre sur ordinateur) et bandeau d'annulation --}}
@php
    $assistantConfig = ['statusUrl' => route('assistant.status'), 'transcribeUrl' => route('assistant.transcribe'), 'commandUrl' => route('assistant.command'), 'historyUrl' => route('assistant.history'), 'horseId' => $contextHorseId];
@endphp
<script type="application/json" id="assistant-config">@json($assistantConfig)</script>
<div x-data="{ get a() { return this.$store.assistant } }" @keydown.escape.window="a.isOpen && a.close()">
    {{-- Panneau --}}
    <div x-cloak x-show="a.isOpen" class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center sm:p-6" role="dialog" aria-modal="true" aria-labelledby="assistant-title">
        <div x-show="a.isOpen" x-transition.opacity class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="a.close()"></div>
        <section x-show="a.isOpen" x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-y-full sm:translate-y-4 sm:opacity-0" x-transition:enter-end="translate-y-0 sm:opacity-100"
                 class="relative flex max-h-[92dvh] w-full flex-col overflow-hidden rounded-t-[2rem] bg-white pb-[env(safe-area-inset-bottom)] shadow-2xl sm:max-w-lg sm:rounded-[2rem]">
            <div class="mx-auto mt-2.5 h-1.5 w-10 rounded-full bg-slate-200 sm:hidden"></div>
            <header class="flex items-center justify-between px-6 pt-4">
                <h2 id="assistant-title" class="flex items-center gap-2 text-[17px] font-semibold"><x-icon name="sparkles" class="size-5 text-brand-600" /> Assistant</h2>
                <div class="flex items-center gap-1">
                    <a href="{{ route('assistant.history') }}" class="btn-ghost px-3 text-sm">Historique</a>
                    <button type="button" class="btn-ghost px-2" @click="a.close()" aria-label="Fermer"><x-icon name="x" /></button>
                </div>
            </header>

            <div class="flex-1 overflow-y-auto px-6 pt-4 pb-6">
                {{-- Indisponible --}}
                <template x-if="a.status && ! a.status.available && ! a.status.upsell">
                    <div class="rounded-2xl bg-slate-50 p-4 text-sm text-slate-700" x-text="a.status.reason"></div>
                </template>
                {{-- Non inclus dans l'offre : proposition d'offre supérieure --}}
                <template x-if="a.status && ! a.status.available && a.status.upsell">
                    <div class="flex flex-col items-center py-2 text-center" data-upsell="assistant">
                        <span class="flex size-20 items-center justify-center rounded-full bg-brand-900 text-gold-200 shadow-[var(--shadow-float)]"><x-icon name="mic" class="size-9" /></span>
                        <p class="mt-4 text-xs font-semibold tracking-wide text-gold-600 uppercase" x-text="'Offre ' + a.status.upsell.plan"></p>
                        <p class="mt-1 text-xl font-semibold text-slate-900" x-text="a.status.upsell.title"></p>
                        <p class="mt-2 text-[15px] text-slate-600" x-text="a.status.upsell.text"></p>
                        <ul class="mt-4 w-full space-y-2 text-left text-sm text-slate-700">
                            <li class="flex gap-2 rounded-2xl bg-slate-50 px-4 py-3"><x-icon name="check" class="size-5 shrink-0 text-brand-600" /> « Le maréchal vient le 2 décembre à 9 h » → ajouté à l'agenda</li>
                            <li class="flex gap-2 rounded-2xl bg-slate-50 px-4 py-3"><x-icon name="check" class="size-5 shrink-0 text-brand-600" /> « Vaccin fait ce matin, 75 € » → soin et dépense notés</li>
                            <li class="flex gap-2 rounded-2xl bg-slate-50 px-4 py-3"><x-icon name="check" class="size-5 shrink-0 text-brand-600" /> « Quand est le prochain maréchal ? » → réponse immédiate</li>
                        </ul>
                        <template x-if="a.status.upsell.url">
                            <div class="mt-5 w-full">
                                <a :href="a.status.upsell.url" class="btn-primary w-full" x-text="a.status.upsell.cta"></a>
                                <p class="mt-2 text-xs text-slate-500" x-text="a.status.upsell.trial ? 'Sans engagement, résiliable à tout moment.' : a.status.upsell.note"></p>
                            </div>
                        </template>
                        <template x-if="! a.status.upsell.url"><p class="mt-4 text-xs text-slate-500" x-text="a.status.upsell.note"></p></template>
                    </div>
                </template>

                <template x-if="! a.status || a.status.available">
                    <div>
                        {{-- Consigne transcrite --}}
                        <template x-if="a.transcript && a.state !== 'idle'">
                            <div class="mb-4 flex justify-end"><p class="max-w-[85%] rounded-3xl rounded-br-lg bg-slate-100 px-4 py-2.5 text-[15px] text-slate-800" x-text="a.transcript"></p></div>
                        </template>

                        {{-- Écoute --}}
                        <div x-show="a.state === 'idle' || a.state === 'listening' || a.state === 'transcribing'" class="flex flex-col items-center py-4 text-center">
                            <button type="button" @click="a.state === 'listening' ? a.stopListening() : a.listen()" :disabled="a.state === 'transcribing' || ! a.canRecord"
                                    class="relative flex size-24 items-center justify-center rounded-full bg-brand-800 text-white shadow-[var(--shadow-float)] transition active:scale-95 disabled:bg-slate-300"
                                    :class="a.state === 'listening' && 'mic-ring'" :aria-label="a.state === 'listening' ? 'Terminer' : 'Parler'">
                                <span class="relative z-10" x-show="a.state !== 'listening'"><x-icon name="mic" class="size-10" /></span>
                                <span class="relative z-10" x-show="a.state === 'listening'" x-cloak><x-icon name="stop" class="size-9" /></span>
                            </button>
                            <p class="mt-5 text-lg font-semibold text-slate-900" x-text="a.state === 'listening' ? 'Je vous écoute…' : (a.state === 'transcribing' ? 'Je transcris…' : (a.canRecord ? 'Touchez et parlez' : 'Écrivez votre consigne'))"></p>
                            <p class="mt-1 text-sm text-slate-500" x-show="a.state === 'listening'">Arrêt automatique quand vous vous taisez · <span x-text="a.elapsed + ' s'"></span></p>
                            <div class="mt-3 flex h-6 items-end gap-1" x-show="a.state === 'listening'" aria-hidden="true">
                                <template x-for="i in 7"><span class="w-1.5 rounded-full bg-brand-500 transition-all duration-100" :style="`height: ${6 + Math.max(0, a.level * 18 - Math.abs(4 - i) * 3)}px`"></span></template>
                            </div>
                            <button type="button" class="btn-ghost mt-2 text-sm" x-show="a.state === 'listening'" @click="a.cancelRecording()">Annuler</button>
                            <template x-if="a.state === 'idle'">
                                <div class="mt-6 w-full text-left">
                                    <p class="mb-2 text-xs font-medium tracking-wide text-slate-400 uppercase">Par exemple</p>
                                    <div class="flex flex-col gap-2">
                                        @foreach (['Le maréchal est prévu le 2 décembre à 9 h', 'Réalise une séance de monte libre en dressage pour travailler la rectitude', 'Le vétérinaire a vacciné Uni ce matin, 75 €', 'Quand est le prochain maréchal ?'] as $ex)
                                            <button type="button" @click="a.send(@js($ex))" class="rounded-2xl bg-slate-50 px-4 py-3 text-left text-[15px] text-slate-700 transition hover:bg-brand-50 hover:text-brand-900">« {{ $ex }} »</button>
                                        @endforeach
                                    </div>
                                </div>
                            </template>
                        </div>

                        {{-- Réflexion --}}
                        <div x-show="a.state === 'thinking'" class="flex items-center gap-3 py-2 text-slate-500">
                            <span class="flex size-9 items-center justify-center rounded-full bg-brand-50 text-brand-700"><x-icon name="sparkles" class="size-5" /></span>
                            <span class="thinking-dots text-2xl leading-none tracking-widest"><span>•</span><span>•</span><span>•</span></span>
                        </div>

                        {{-- Résultat --}}
                        <template x-if="a.state === 'result'">
                            <div>
                                <div class="flex items-start gap-3">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-700"><x-icon name="sparkles" class="size-5" /></span>
                                    <p class="pt-1.5 text-[16px] leading-relaxed text-slate-900" x-text="a.reply"></p>
                                </div>
                                <div class="mt-4 space-y-2" x-show="a.actions.length">
                                    <template x-for="act in a.actions" :key="act.id">
                                        <div class="flex items-center gap-3 rounded-2xl bg-slate-50 p-3" :class="act.undone && 'opacity-50'">
                                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full" :class="act.undone ? 'bg-slate-200 text-slate-500' : 'bg-brand-100 text-brand-800'"><x-icon name="check" class="size-4" /></span>
                                            <a :href="act.url" class="min-w-0 flex-1 text-sm font-medium text-slate-800" :class="act.undone && 'line-through'" x-text="act.summary"></a>
                                            <button type="button" x-show="act.undoable" @click="a.undo(act)" class="btn-ghost min-h-9 px-3 text-xs">Annuler</button>
                                            <span x-show="act.undone" class="text-xs text-slate-500">Annulé</span>
                                        </div>
                                    </template>
                                </div>
                                <div class="mt-4 flex flex-wrap gap-2" x-show="a.question">
                                    <template x-for="opt in (a.question?.options || [])" :key="opt">
                                        <button type="button" @click="a.answer(opt)" class="btn-secondary" x-text="opt"></button>
                                    </template>
                                </div>
                                <p class="mt-3 text-sm text-red-700" x-show="a.error" x-text="a.error"></p>
                            </div>
                        </template>

                        {{-- Erreur --}}
                        <template x-if="a.state === 'error'">
                            <div class="rounded-2xl bg-red-50 p-4 text-sm text-red-800" x-text="a.error"></div>
                        </template>
                    </div>
                </template>
            </div>

            {{-- Saisie (clavier) et relance --}}
            <footer class="border-t border-slate-100 px-4 pt-3 pb-4" x-show="! a.status || a.status.available">
                <form class="flex items-center gap-2" @submit.prevent="a.send(a.draft)">
                    <button type="button" x-show="a.state === 'result' || a.state === 'error'" @click="a.listen()" :disabled="! a.canRecord" class="flex size-11 shrink-0 items-center justify-center rounded-full bg-brand-800 text-white disabled:bg-slate-300" aria-label="Parler"><x-icon name="mic" class="size-5" /></button>
                    <label for="assistant-input" class="sr-only">Écrire une consigne</label>
                    <input id="assistant-input" x-model="a.draft" :placeholder="a.question ? 'Votre réponse…' : 'Écrire une consigne…'" autocomplete="off" class="input rounded-full bg-slate-50 py-2.5" :disabled="a.state === 'thinking' || a.state === 'listening' || a.state === 'transcribing'">
                    <button class="flex size-11 shrink-0 items-center justify-center rounded-full bg-slate-900 text-white disabled:bg-slate-300" :disabled="! a.draft.trim() || a.state === 'thinking'" aria-label="Envoyer"><x-icon name="send" class="size-5" /></button>
                </form>
                <p class="mt-2 px-2 text-xs text-slate-400" x-show="a.status && a.status.remaining !== null && a.status.remaining !== undefined"><span x-text="a.status?.remaining"></span> consigne(s) restante(s) ce mois-ci</p>
            </footer>
        </section>
    </div>

    {{-- Bandeau d'annulation (10 s) --}}
    <div x-cloak x-show="a.toast && ! a.isOpen" x-transition class="fixed inset-x-3 bottom-[calc(5.5rem+env(safe-area-inset-bottom))] z-[70] mx-auto flex max-w-md items-center gap-3 rounded-2xl bg-slate-900 px-4 py-3 text-sm text-white shadow-2xl lg:bottom-6" role="status">
        <x-icon name="check" class="size-5 shrink-0 text-brand-300" />
        <span class="min-w-0 flex-1 truncate" x-text="a.toast?.text"></span>
        <button type="button" @click="a.undoToast()" class="shrink-0 rounded-full bg-white/10 px-3 py-1.5 font-semibold hover:bg-white/20">Annuler <span class="tabular-nums opacity-70" x-text="a.toast?.left"></span></button>
    </div>
</div>

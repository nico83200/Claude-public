<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="app-user" content="{{ auth()->id() }}">
    <meta name="theme-color" content="#2f6347">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icons/icon.svg" type="image/svg+xml">
    <title>Mode écurie · {{ $appName }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body data-offline-shell="1" class="min-h-dvh bg-sand-50">
<div x-data="stableApp" x-cloak class="mx-auto min-h-dvh max-w-2xl pb-24">
    {{-- En-tête : état de connexion et de synchronisation --}}
    <header class="sticky top-0 z-20 bg-brand-800 px-4 pt-[env(safe-area-inset-top)] text-white shadow">
        <div class="flex h-14 items-center gap-3">
            <button x-show="view !== 'home'" @click="back()" class="-ml-2 rounded-lg p-2 hover:bg-white/10" aria-label="Retour">
                <x-icon name="back" class="size-6" />
            </button>
            <div class="min-w-0 flex-1">
                <p class="truncate text-base font-semibold" x-text="view === 'home' ? 'Mode écurie' : (horse?.usual_name || horse?.official_name || 'Mode écurie')"></p>
                <p x-show="ready" class="flex items-center gap-1.5 text-xs text-brand-100">
                    <span class="size-2 rounded-full" :class="!online ? 'bg-slate-300' : (state.error || state.conflicts ? 'bg-red-400' : (state.syncing || state.pending ? 'bg-amber-300' : 'bg-emerald-300'))"></span>
                    <span x-text="statusLabel()"></span>
                    <template x-if="state.pending"><span x-text="'· ' + state.pending + ' en attente'"></span></template>
                </p>
            </div>
            <button @click="syncNow()" :disabled="!online || state.syncing" class="rounded-lg p-2 hover:bg-white/10 disabled:opacity-40" aria-label="Synchroniser">
                <x-icon name="refresh" class="size-6" x-bind:class="state.syncing && 'animate-spin'" />
            </button>
        </div>
    </header>

    <div x-show="error" class="m-4 rounded-xl bg-red-50 p-4 text-sm text-red-800" x-text="error"></div>

    {{-- Verrouillage par code PIN --}}
    <div x-show="ready && locked" class="fixed inset-0 z-50 flex flex-col items-center justify-center bg-brand-900 p-6 text-white">
        <x-icon name="shield" class="mb-4 size-12" />
        <p class="mb-4 text-lg font-semibold">Application verrouillée</p>
        <form @submit.prevent="unlock()" class="w-full max-w-xs space-y-3">
            <input type="password" inputmode="numeric" autocomplete="off" x-model="pinInput" class="input text-center text-2xl tracking-widest text-slate-900" placeholder="Code PIN" aria-label="Code PIN">
            <p x-show="pinError" class="text-center text-sm text-red-200" x-text="pinError"></p>
            <button class="btn big-touch w-full bg-white text-brand-800">Déverrouiller</button>
        </form>
        <button @click="forgetDevice()" class="mt-8 text-sm text-brand-100 underline">Code oublié : effacer les données de l'appareil</button>
    </div>

    <main x-show="ready && !locked" class="space-y-4 p-4">
        {{-- Alertes d'état --}}
        <template x-if="state.authRequired">
            <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                Votre session a expiré. <a href="/login" class="link">Reconnectez-vous</a> pour synchroniser : vos saisies restent sur l'appareil.
            </div>
        </template>
        <template x-if="state.expired">
            <div class="rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-900">
                Les données hors ligne ont dépassé leur durée de validité et ont été retirées de l'appareil. Connectez-vous à Internet pour les recharger. Les saisies non envoyées sont conservées.
            </div>
        </template>
        <template x-if="state.error && !state.authRequired && online">
            <div class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800" x-text="state.error"></div>
        </template>

        {{-- ACCUEIL --}}
        <section x-show="view === 'home'" class="space-y-4">
            <template x-if="!horses.length && !state.expired">
                <div class="card p-5 text-sm text-slate-600">
                    <p class="font-semibold text-slate-800">Aucun cheval disponible hors ligne</p>
                    <p class="mt-1">Depuis la fiche d'un cheval, utilisez « Disponible hors ligne », puis revenez ici avec une connexion pour télécharger ses données.</p>
                    <a href="{{ route('horses.index') }}" class="btn-primary mt-3">Choisir des chevaux</a>
                </div>
            </template>
            <div class="grid gap-3">
                <template x-for="h in horses" :key="h.id">
                    <button @click="openHorse(h)" class="card flex min-h-20 items-center gap-4 p-4 text-left active:bg-slate-50">
                        <div class="flex size-12 shrink-0 items-center justify-center rounded-full bg-brand-100 text-lg font-bold text-brand-800" x-text="(h.usual_name || h.official_name).charAt(0)"></div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold" x-text="h.usual_name || h.official_name"></p>
                            <p class="truncate text-sm text-slate-500" x-text="[h.breed, h.age ? h.age + ' ans' : null, h.organization].filter(Boolean).join(' · ')"></p>
                        </div>
                        <span x-show="!h.writable" class="chip bg-amber-100 text-amber-800">Lecture seule</span>
                        <x-icon name="chevron" class="size-5 text-slate-400" />
                    </button>
                </template>
            </div>

            <button @click="go('pending')" class="card flex w-full items-center justify-between p-4 text-left">
                <span><span class="font-semibold">Synchronisation</span><span class="block text-sm text-slate-500" x-text="'Dernière : ' + fmt(state.lastSync)"></span></span>
                <span class="flex gap-1">
                    <span x-show="state.pending" class="chip bg-amber-100 text-amber-800" x-text="state.pending + ' en attente'"></span>
                    <span x-show="state.rejected" class="chip bg-red-100 text-red-800" x-text="state.rejected + ' refusée(s)'"></span>
                    <span x-show="state.conflicts" class="chip bg-red-100 text-red-800" x-text="state.conflicts + ' conflit(s)'"></span>
                </span>
            </button>

            <div class="card space-y-2 p-4 text-sm">
                <p class="font-semibold">Sécurité de l'appareil</p>
                <p class="text-slate-500">Données locales valables jusqu'au <span x-text="fmt(state.validUntil)"></span>. Elles sont effacées à la déconnexion.</p>
                <div class="flex flex-wrap gap-2">
                    <button x-show="!pinSet" @click="setPin()" class="btn-secondary">Activer un code PIN</button>
                    <button x-show="pinSet" @click="removePin()" class="btn-secondary">Désactiver le code PIN</button>
                    <a href="{{ route('dashboard') }}" class="btn-ghost">Version complète</a>
                </div>
            </div>
        </section>

        {{-- FICHE CHEVAL (essentiel) --}}
        <section x-show="view === 'horse' && horse" class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <button x-show="can('sessions.create')" @click="startNewSession()" class="btn-primary big-touch"><x-icon name="plus" /> Séance</button>
                <button x-show="can('dailylog.create')" @click="startLog()" class="btn-secondary big-touch">Suivi du jour</button>
                <button x-show="can('comments.create')" @click="startObservation()" class="btn-secondary big-touch">Signaler</button>
                <button x-show="can('horse.edit')" @click="startHorseNotes()" class="btn-secondary big-touch">Notes</button>
            </div>
            <template x-if="horse?.precautions || horse?.care_instructions">
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm">
                    <p x-show="horse.precautions" class="font-semibold text-amber-900">Précautions</p>
                    <p x-show="horse.precautions" class="mb-2 whitespace-pre-line" x-text="horse.precautions"></p>
                    <p x-show="horse.care_instructions" class="font-semibold text-amber-900">Consignes de soins</p>
                    <p class="whitespace-pre-line" x-text="horse.care_instructions"></p>
                </div>
            </template>
            <div class="card p-4 text-sm">
                <dl class="grid grid-cols-2 gap-3">
                    <div><dt class="text-xs text-slate-500 uppercase">Race</dt><dd x-text="horse?.breed || '—'"></dd></div>
                    <div><dt class="text-xs text-slate-500 uppercase">Âge</dt><dd x-text="horse?.age ? horse.age + ' ans' : '—'"></dd></div>
                    <div><dt class="text-xs text-slate-500 uppercase">Sexe</dt><dd x-text="horse?.sex"></dd></div>
                    <div><dt class="text-xs text-slate-500 uppercase">Lieu</dt><dd x-text="horse?.current_location || '—'"></dd></div>
                    <div class="col-span-2" x-show="horse?.particularities"><dt class="text-xs text-slate-500 uppercase">Particularités</dt><dd class="whitespace-pre-line" x-text="horse?.particularities"></dd></div>
                </dl>
            </div>
            <template x-if="horseTreatments.length">
                <div class="card p-4"><p class="mb-2 font-semibold">Traitements en cours</p>
                    <template x-for="t in horseTreatments" :key="t.id"><div class="border-t border-slate-100 py-2 text-sm first:border-0"><p class="font-medium" x-text="t.product"></p><p class="text-slate-600" x-text="[t.dosage, t.frequency].filter(Boolean).join(' · ')"></p><p class="text-xs text-slate-500" x-text="'Jusqu\'au ' + (t.ends_on ? fmt(t.ends_on, false) : '—')"></p><p x-show="t.instructions" class="text-sm whitespace-pre-line" x-text="t.instructions"></p></div></template>
                    <p class="mt-2 text-xs text-slate-500">Informations saisies d'après les prescriptions : en cas de doute, contactez le vétérinaire.</p>
                </div>
            </template>
            <template x-if="horseFeeding">
                <div class="card p-4"><p class="mb-2 font-semibold" x-text="'Alimentation — ' + horseFeeding.name"></p>
                    <template x-for="(e, i) in horseFeeding.entries" :key="i"><p class="text-sm"><span class="font-medium" x-text="e.time_of_day ? e.time_of_day.slice(0,5) + ' · ' : ''"></span><span x-text="e.feed"></span> <span class="text-slate-500" x-text="e.quantity ? e.quantity + ' ' + e.unit : ''"></span></p></template>
                    <p x-show="horseFeeding.instructions" class="mt-2 text-sm whitespace-pre-line text-slate-600" x-text="horseFeeding.instructions"></p>
                </div>
            </template>
            <div class="card p-4"><p class="mb-2 font-semibold">Séances</p>
                <template x-if="!horseSessions.length"><p class="text-sm text-slate-500">Aucune séance disponible hors ligne.</p></template>
                <template x-for="s in horseSessions" :key="s.uuid">
                    <button @click="openSession(s)" class="flex w-full items-center justify-between border-t border-slate-100 py-3 text-left first:border-0">
                        <span><span class="block font-medium" x-text="types[s.session_type] + (s.objective ? ' — ' + s.objective : '')"></span><span class="text-xs text-slate-500" x-text="fmt(s.scheduled_at) + ' · ' + s.rider"></span></span>
                        <span class="flex items-center gap-1"><span x-show="isPendingSession(s)" class="chip bg-amber-100 text-amber-800">non synchronisée</span><span class="chip" :class="s.status === 'completed' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700'" x-text="{planned:'Prévue',in_progress:'En cours',completed:'Terminée',cancelled:'Annulée'}[s.status]"></span></span>
                    </button>
                </template>
            </div>
            <template x-if="horseEvents.length">
                <div class="card p-4"><p class="mb-2 font-semibold">Agenda</p>
                    <template x-for="e in horseEvents" :key="e.uuid"><p class="border-t border-slate-100 py-2 text-sm first:border-0"><span class="font-medium" x-text="fmt(e.starts_at)"></span> — <span x-text="e.title"></span></p></template>
                </div>
            </template>
        </section>

        {{-- NOUVELLE SÉANCE --}}
        <form x-show="view === 'new-session'" @submit.prevent="createSession()" class="card space-y-4 p-4">
            <h2 class="text-lg font-semibold">Nouvelle séance</h2>
            <div><label class="label" for="ns-date">Date et heure</label><input id="ns-date" type="datetime-local" x-model="form.scheduled_at" class="input" required></div>
            <div><label class="label" for="ns-type">Type</label><select id="ns-type" x-model="form.session_type" class="input"><template x-for="(l, k) in types" :key="k"><option :value="k" x-text="l"></option></template></select></div>
            <div><label class="label" for="ns-obj">Objectif</label><input id="ns-obj" x-model="form.objective" class="input" maxlength="255"></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label" for="ns-min">Durée prévue (min)</label><input id="ns-min" type="number" min="1" max="600" x-model="form.planned_minutes" class="input"></div>
                <div><label class="label" for="ns-loc">Lieu</label><input id="ns-loc" x-model="form.location" class="input"></div>
            </div>
            <div><label class="label" for="ns-state">État du cheval avant</label><input id="ns-state" x-model="form.horse_state_before" class="input"></div>
            <button class="btn-primary big-touch w-full">Créer et préparer</button>
        </form>

        {{-- SÉANCE : préparation / consultation --}}
        <section x-show="view === 'session' && session" class="space-y-4">
            <div class="card p-4">
                <p class="text-lg font-semibold" x-text="types[session?.session_type] + (session?.objective ? ' — ' + session.objective : '')"></p>
                <p class="text-sm text-slate-500" x-text="fmt(session?.scheduled_at) + ' · ' + session?.rider + (session?.planned_minutes ? ' · ' + session.planned_minutes + ' min prévues' : '')"></p>
                <p x-show="session && isPendingSession(session)" class="mt-2 text-xs text-amber-700">Modifications enregistrées sur l'appareil, en attente de synchronisation.</p>
            </div>
            <template x-if="session?.status !== 'completed' && session?.can_edit && horse?.writable">
                <div class="grid grid-cols-2 gap-3">
                    <button @click="go('live')" class="btn-primary big-touch"><x-icon name="play" /> Mode séance</button>
                    <button @click="startDebrief()" class="btn-secondary big-touch">Bilan</button>
                </div>
            </template>
            <template x-for="(label, phase) in phases" :key="phase">
                <div class="card p-4" x-show="itemsByPhase(phase).length || (session?.status !== 'completed' && session?.can_edit)">
                    <p class="mb-2 text-sm font-semibold tracking-wide text-slate-500 uppercase" x-text="label"></p>
                    <template x-for="item in itemsByPhase(phase)" :key="item.uuid">
                        <div class="flex items-center justify-between border-t border-slate-100 py-2 text-sm first:border-0">
                            <span><span class="font-medium" x-text="item.name"></span><span class="block text-xs text-slate-500" x-text="[item.planned_minutes ? item.planned_minutes + ' min' : null, item.planned_repetitions ? item.planned_repetitions + ' rép.' : null].filter(Boolean).join(' · ')"></span></span>
                            <span class="chip" :class="item.status === 'done' ? 'bg-emerald-100 text-emerald-800' : (item.status === 'skipped' ? 'bg-slate-200 text-slate-700' : 'bg-white text-slate-500')" x-text="{pending:'À faire',done:'Fait',skipped:'Non fait'}[item.status]"></span>
                        </div>
                    </template>
                    <template x-if="session?.status !== 'completed' && session?.can_edit && horse?.writable">
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button @click="form.phase = phase; search = ''; go('library')" class="btn-secondary text-sm"><x-icon name="plus" class="size-4" /> Exercice</button>
                            <button @click="addItem(null, phase, true)" class="btn-ghost text-sm">+ Pause</button>
                        </div>
                    </template>
                </div>
            </template>
            <template x-if="session?.status === 'completed'">
                <div class="card space-y-1 p-4 text-sm">
                    <p class="font-semibold">Bilan</p>
                    <p x-show="session.actual_minutes" x-text="'Durée réelle : ' + session.actual_minutes + ' min'"></p>
                    <p x-show="session.progress"><span class="font-medium">Progrès : </span><span x-text="session.progress"></span></p>
                    <p x-show="session.to_rework"><span class="font-medium">À retravailler : </span><span x-text="session.to_rework"></span></p>
                    <p x-show="session.anomalies" class="text-red-700"><span class="font-medium">Anomalies : </span><span x-text="session.anomalies"></span></p>
                    <p x-show="session.next_objectives"><span class="font-medium">Prochaine fois : </span><span x-text="session.next_objectives"></span></p>
                </div>
            </template>
            <div class="card p-4">
                <p class="mb-2 font-semibold">Commentaires</p>
                <template x-for="c in session?.comments ?? []" :key="c.uuid"><div class="border-t border-slate-100 py-2 text-sm first:border-0"><p class="whitespace-pre-line" x-text="c.body"></p><p class="text-xs text-slate-500" x-text="c.author + ' · ' + fmt(c.created_at)"></p></div></template>
                <form x-show="can('comments.create')" @submit.prevent="addComment()" class="mt-2 flex gap-2"><input x-model="form.comment" class="input" placeholder="Ajouter un commentaire" maxlength="5000"><button class="btn-primary">OK</button></form>
            </div>
        </section>

        {{-- BIBLIOTHÈQUE (ajout d'exercices) --}}
        <section x-show="view === 'library'" class="space-y-3">
            <div class="flex gap-2"><input x-model="search" class="input" placeholder="Rechercher un exercice" aria-label="Rechercher un exercice"><button @click="go('session')" class="btn-primary">Terminé</button></div>
            <button @click="addItem(null, form.phase)" class="card w-full p-3 text-left text-sm font-medium">+ Exercice libre (sans modèle)</button>
            <template x-for="e in filteredExercises()" :key="e.id">
                <button @click="addItem(e, form.phase)" class="card flex min-h-14 w-full items-center justify-between p-3 text-left">
                    <span><span class="font-medium" x-text="e.name"></span><span class="block text-xs text-slate-500" x-text="[e.category, e.duration_minutes ? e.duration_minutes + ' min' : null].filter(Boolean).join(' · ')"></span></span>
                    <x-icon name="plus" class="size-5 text-brand-600" />
                </button>
            </template>
        </section>

        {{-- MODE SÉANCE --}}
        <section x-show="view === 'live' && session" class="space-y-3">
            <template x-for="(label, phase) in phases" :key="phase">
                <div x-show="itemsByPhase(phase).length" class="space-y-3">
                    <p class="pt-2 text-sm font-semibold tracking-wide text-slate-500 uppercase" x-text="label"></p>
                    <template x-for="item in itemsByPhase(phase)" :key="item.uuid">
                        <div class="card p-4" :class="item.status === 'done' ? 'border-emerald-300 bg-emerald-50' : (item.status === 'skipped' ? 'opacity-70' : '')">
                            <div class="flex items-start justify-between gap-2">
                                <div><p class="text-lg font-semibold" x-text="item.name"></p>
                                    <p class="text-sm text-slate-500" x-text="[item.planned_minutes ? item.planned_minutes + ' min prévues' : null, item.planned_repetitions ? item.planned_repetitions + ' répétitions' : null].filter(Boolean).join(' · ')"></p>
                                    <p x-show="item.instructions" class="mt-1 text-sm whitespace-pre-line text-slate-700" x-text="item.instructions"></p></div>
                                <button @click="toggleDifficulty(item)" class="rounded-lg p-2" :class="item.difficulty ? 'bg-amber-100 text-amber-700' : 'text-slate-400'" :aria-pressed="item.difficulty" aria-label="Marquer une difficulté"><x-icon name="alert" class="size-6" /></button>
                            </div>
                            <div class="mt-3 grid grid-cols-2 gap-2">
                                <button @click="mark(item, item.status === 'done' ? 'pending' : 'done')" class="btn big-touch" :class="item.status === 'done' ? 'bg-emerald-600 text-white' : 'border border-emerald-600 text-emerald-700'"><x-icon name="check" /> <span x-text="item.status === 'done' ? 'Fait' : 'Marquer fait'"></span></button>
                                <button @click="mark(item, item.status === 'skipped' ? 'pending' : 'skipped')" class="btn big-touch border border-slate-300" :class="item.status === 'skipped' && 'bg-slate-200'"><span x-text="item.status === 'skipped' ? 'Non réalisé' : 'Pas fait'"></span></button>
                            </div>
                            <div x-show="!item.is_break" class="mt-2 flex items-center gap-2">
                                <button @click="reps(item, -1)" class="btn-secondary big-touch w-14 text-xl" aria-label="Moins une répétition">−</button>
                                <span class="flex-1 text-center text-sm"><span class="text-2xl font-bold" x-text="item.done_repetitions ?? 0"></span> rép.</span>
                                <button @click="reps(item, 1)" class="btn-secondary big-touch w-14 text-xl" aria-label="Plus une répétition">+</button>
                            </div>
                            <div class="mt-2 flex gap-2 text-sm">
                                <button @click="minutesItem(item)" class="btn-ghost" x-text="item.actual_minutes ? item.actual_minutes + ' min réelles' : 'Durée réelle'"></button>
                                <button @click="noteItem(item)" class="btn-ghost" x-text="item.note ? 'Note : ' + item.note.slice(0, 20) : 'Ajouter une note'"></button>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
            <p x-show="!(session?.items ?? []).length" class="card p-4 text-sm text-slate-500">Aucun exercice préparé. Revenez à la séance pour en ajouter.</p>
            <button @click="startDebrief()" class="btn-primary big-touch w-full">Terminer et faire le bilan</button>
        </section>

        {{-- BILAN --}}
        <form x-show="view === 'debrief'" @submit.prevent="saveDebrief()" class="card space-y-4 p-4">
            <h2 class="text-lg font-semibold">Bilan de séance</h2>
            <div><label class="label" for="db-min">Durée réelle (min)</label><input id="db-min" type="number" min="0" max="600" x-model="form.actual_minutes" class="input"></div>
            <template x-for="[key, label] in [['rider_feeling','Ressenti du cavalier'],['horse_behavior','Comportement du cheval'],['concentration','Concentration'],['availability','Disponibilité']]" :key="key">
                <fieldset><legend class="label" x-text="label"></legend>
                    <div class="grid grid-cols-5 gap-1">
                        <template x-for="n in [1,2,3,4,5]" :key="n"><button type="button" @click="form[key] = n" class="btn big-touch border" :class="form[key] == n ? 'border-brand-600 bg-brand-600 text-white' : 'border-slate-300 bg-white'" x-text="n" :aria-pressed="form[key] == n"></button></template>
                    </div>
                </fieldset>
            </template>
            <template x-for="[key, label] in [['progress','Progrès'],['difficulties','Difficultés'],['to_rework','Points à retravailler'],['anomalies','Anomalies constatées'],['next_objectives','Objectifs pour la prochaine séance']]" :key="key">
                <div><label class="label" :for="'db-' + key" x-text="label"></label><textarea :id="'db-' + key" x-model="form[key]" rows="2" class="input"></textarea></div>
            </template>
            <button class="btn-primary big-touch w-full">Enregistrer le bilan</button>
        </form>

        {{-- SUIVI QUOTIDIEN --}}
        <form x-show="view === 'log'" @submit.prevent="saveLog()" class="card space-y-4 p-4">
            <h2 class="text-lg font-semibold">Suivi du jour</h2>
            <fieldset><legend class="label">Appétit</legend><div class="grid grid-cols-3 gap-2"><template x-for="[k, l] in [['good','Bon'],['reduced','Diminué'],['none','Absent']]" :key="k"><button type="button" @click="form.appetite = k" class="btn big-touch border" :class="form.appetite === k ? 'border-brand-600 bg-brand-600 text-white' : 'border-slate-300'" x-text="l"></button></template></div></fieldset>
            <fieldset><legend class="label">État général</legend><div class="grid grid-cols-3 gap-2"><template x-for="[k, l] in [['good','Bon'],['average','Moyen'],['poor','Mauvais']]" :key="k"><button type="button" @click="form.general_state = k" class="btn big-touch border" :class="form.general_state === k ? 'border-brand-600 bg-brand-600 text-white' : 'border-slate-300'" x-text="l"></button></template></div></fieldset>
            <div><label class="label" for="lg-beh">Comportement</label><input id="lg-beh" x-model="form.behavior" class="input" maxlength="255"></div>
            <div><label class="label" for="lg-act">Activité</label><input id="lg-act" x-model="form.activity" class="input" maxlength="255" placeholder="Paddock, marcheur, repos…"></div>
            <div><label class="label" for="lg-obs">Observations</label><textarea id="lg-obs" x-model="form.observations" rows="2" class="input"></textarea></div>
            <div><label class="label" for="lg-ano">Anomalies</label><textarea id="lg-ano" x-model="form.anomalies" rows="2" class="input"></textarea></div>
            <button class="btn-primary big-touch w-full">Enregistrer</button>
        </form>

        {{-- OBSERVATION --}}
        <form x-show="view === 'observation'" @submit.prevent="saveObservation()" class="card space-y-4 p-4">
            <h2 class="text-lg font-semibold">Signaler une observation</h2>
            <fieldset><legend class="label">Niveau</legend><div class="grid grid-cols-3 gap-2"><template x-for="[k, l] in [['info','Info'],['watch','À surveiller'],['alert','Alerte']]" :key="k"><button type="button" @click="form.severity = k" class="btn big-touch border" :class="form.severity === k ? (k === 'alert' ? 'border-red-600 bg-red-600 text-white' : 'border-brand-600 bg-brand-600 text-white') : 'border-slate-300'" x-text="l"></button></template></div></fieldset>
            <div><label class="label" for="ob-body">Description</label><textarea id="ob-body" x-model="form.body" rows="4" class="input" required></textarea></div>
            <p class="text-xs text-slate-500">En cas d'urgence, contactez directement le vétérinaire.</p>
            <button class="btn-primary big-touch w-full">Enregistrer</button>
        </form>

        {{-- NOTES DU CHEVAL --}}
        <form x-show="view === 'horse-notes'" @submit.prevent="saveHorseNotes()" class="card space-y-4 p-4">
            <h2 class="text-lg font-semibold">Notes du cheval</h2>
            <div><label class="label" for="hn-part">Particularités</label><textarea id="hn-part" x-model="form.particularities" rows="3" class="input"></textarea></div>
            <div><label class="label" for="hn-notes">Observations générales</label><textarea id="hn-notes" x-model="form.general_notes" rows="3" class="input"></textarea></div>
            <div><label class="label" for="hn-loc">Lieu actuel</label><input id="hn-loc" x-model="form.current_location" class="input"></div>
            <p class="text-xs text-slate-500">Si quelqu'un modifie ces champs en même temps, un conflit vous sera présenté au lieu d'écraser sa saisie.</p>
            <button class="btn-primary big-touch w-full">Enregistrer</button>
        </form>

        {{-- SYNCHRONISATION : opérations en attente, refusées, conflits --}}
        <section x-show="view === 'pending'" class="space-y-4">
            <div class="card p-4 text-sm">
                <p class="font-semibold" x-text="statusLabel()"></p>
                <p class="text-slate-500" x-text="'Dernière synchronisation réussie : ' + fmt(state.lastSync)"></p>
                <button @click="syncNow()" :disabled="!online || state.syncing" class="btn-primary mt-3 w-full">Relancer la synchronisation</button>
            </div>
            <div class="card p-4"><p class="mb-2 font-semibold" x-text="'En attente (' + pending.length + ')'"></p>
                <template x-if="!pending.length"><p class="text-sm text-slate-500">Tout est synchronisé.</p></template>
                <template x-for="op in pending" :key="op.op_uuid"><div class="border-t border-slate-100 py-2 text-sm first:border-0"><p class="font-medium" x-text="describeOp(op)"></p><p class="text-xs text-slate-500" x-text="fmt(op.created_at) + (op.last_error ? ' · ' + op.last_error : '')"></p></div></template>
            </div>
            <div class="card p-4" x-show="rejected.length"><p class="mb-2 font-semibold text-red-700">Saisies refusées par le serveur</p>
                <p class="mb-2 text-xs text-slate-500">Elles n'ont pas été enregistrées (droits retirés, abonnement expiré, données invalides). Leur contenu reste consultable ici pour être ressaisi.</p>
                <template x-for="op in rejected" :key="op.op_uuid"><details class="border-t border-slate-100 py-2 text-sm"><summary class="cursor-pointer"><span class="font-medium" x-text="describeOp(op)"></span> — <span class="text-red-700" x-text="op.last_error"></span></summary><pre class="mt-2 overflow-x-auto rounded bg-slate-50 p-2 text-xs" x-text="JSON.stringify(op.payload, null, 2)"></pre><button @click="dismissRejected(op)" class="btn-ghost mt-1 text-red-600">Supprimer</button></details></template>
            </div>
            <div class="card p-4"><div class="flex items-center justify-between"><p class="font-semibold" x-text="'Conflits (' + state.conflicts + ')'"></p><button @click="loadConflicts()" class="btn-ghost text-sm">Afficher</button></div>
                <template x-for="c in conflicts" :key="c.id">
                    <div class="mt-3 rounded-lg border border-red-200 p-3 text-sm">
                        <p class="font-medium" x-text="{session:'Séance', session_exercise:'Exercice de séance', horse:'Fiche du cheval'}[c.entity] ?? c.entity"></p>
                        <table class="mt-2 w-full text-xs"><thead><tr><th class="text-left">Champ</th><th class="text-left">Votre saisie</th><th class="text-left">Version serveur</th></tr></thead>
                            <tbody><template x-for="(v, k) in c.local_values" :key="k"><tr class="border-t"><td class="py-1 pr-2 font-medium" x-text="k"></td><td class="py-1 pr-2" x-text="v ?? '—'"></td><td class="py-1" x-text="c.server_values[k] ?? '—'"></td></tr></template></tbody></table>
                        <div class="mt-2 grid grid-cols-2 gap-2"><button @click="resolveConflict(c, 'local')" class="btn-secondary">Garder ma saisie</button><button @click="resolveConflict(c, 'server')" class="btn-secondary">Garder le serveur</button></div>
                    </div>
                </template>
                <p x-show="conflictsLoaded && !conflicts.length" class="mt-2 text-sm text-slate-500">Aucun conflit à résoudre.</p>
            </div>
        </section>
    </main>

    <div x-show="toast" x-transition class="fixed inset-x-4 bottom-6 z-40 mx-auto max-w-sm rounded-xl bg-slate-900 px-4 py-3 text-center text-sm text-white shadow-lg" role="status" x-text="toast"></div>
</div>
<noscript><p style="padding:1rem">JavaScript est nécessaire pour le mode écurie.</p></noscript>
</body>
</html>

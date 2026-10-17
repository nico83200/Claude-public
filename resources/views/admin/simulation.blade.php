@php
    $fields = [
        'accounts' => ['Inscriptions', 'comptes de test'],
        'horses' => ['Chevaux par compte', 'dans la limite de l\'offre gratuite'],
        'events' => ['Rendez-vous par cheval', 'vétérinaire, maréchal, concours…'],
        'sessions' => ['Séances par cheval', 'avec leur événement d\'agenda'],
        'cares' => ['Soins par cheval', 'carnet de santé'],
        'expenses' => ['Dépenses par cheval', 'budget'],
        'logs' => ['Suivis quotidiens par cheval', 'un par jour'],
        'past_days' => ['Jours passés', 'étalement dans le passé'],
        'future_days' => ['Jours à venir', 'étalement dans le futur'],
        'chunk' => ['Comptes par étape', 'une requête par étape'],
    ];
    $countLabels = ['accounts' => 'comptes', 'horses' => 'chevaux', 'horses_refused' => 'chevaux refusés (offre)', 'events' => 'rendez-vous', 'sessions' => 'séances', 'cares' => 'soins', 'expenses' => 'dépenses', 'logs' => 'suivis'];
    $batchData = $batches->map(fn ($b) => ['id' => $b->id, 'status' => $b->status, 'label' => \App\Models\SimulationBatch::STATUSES[$b->status] ?? $b->status,
        'done' => $b->accounts_done, 'total' => $b->accounts(), 'counts' => $b->counts ?? [], 'date' => $b->created_at->format('d/m/Y H:i'), 'seconds' => round($b->elapsed_ms / 1000)])->values();
    $activeData = $active ? app(\App\Services\LoadSimulation::class)->progress($active) : null;
@endphp
<x-layouts.app title="Simulation · Admin">
    <x-page-header title="Simulation de charge" subtitle="Créer en masse des comptes de test avec leurs chevaux et leur activité, suivre le serveur en direct, puis tout supprimer" />
    @include('admin._nav')

    {{-- État du serveur, relevé en continu --}}
    <x-section title="État du serveur en direct" data-server-monitor>
        <div x-data="serverMonitor({ url: @js(route('admin.simulation.status')) })">
            <div class="mb-3 flex flex-wrap items-center gap-2 text-sm">
                <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full" :class="error ? 'bg-red-500' : (paused ? 'bg-slate-400' : 'animate-pulse bg-emerald-500')"></span>
                    <span x-text="error ? error : (paused ? 'En pause' : 'Relevé toutes les ' + every + ' s')"></span></span>
                <select x-model.number="every" class="input w-auto py-1 text-sm" aria-label="Fréquence"><option value="2">2 s</option><option value="3">3 s</option><option value="5">5 s</option><option value="10">10 s</option></select>
                <button type="button" class="btn-secondary py-1.5" @click="toggle()" x-text="paused ? 'Reprendre' : 'Pause'"></button>
                <span class="text-xs text-slate-500" x-show="snap" x-text="snap ? 'Serveur ' + (snap.system.hostname || '') + ' · PHP ' + snap.php.version : ''"></span>
            </div>
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4" x-show="snap" x-cloak>
                @php $tile = 'rounded-2xl bg-slate-50 p-3 ring-1 ring-slate-200/70'; @endphp
                <div class="{{ $tile }}" data-metric="response">
                    <p class="text-xs text-slate-500">Temps de réponse</p>
                    <p class="text-xl font-semibold" :class="snap?.response_ms > 1500 ? 'text-red-700' : (snap?.response_ms > 600 ? 'text-amber-700' : 'text-slate-900')" x-text="snap?.response_ms + ' ms'"></p>
                    <svg viewBox="0 0 100 30" class="mt-1 h-8 w-full" preserveAspectRatio="none" aria-hidden="true"><path :d="path('response')" fill="none" stroke="currentColor" stroke-width="1.5" class="text-brand-600" vector-effect="non-scaling-stroke" /></svg>
                </div>
                <div class="{{ $tile }}" data-metric="load">
                    <p class="text-xs text-slate-500">Charge processeur (1 / 5 / 15 min)</p>
                    <p class="text-xl font-semibold" x-text="snap?.system.load ? snap.system.load.join(' · ') : 'non lisible'"></p>
                    <p class="text-xs text-slate-500" x-show="snap?.system.cpus" x-text="snap?.system.cpus + ' cœurs visibles'"></p>
                    <svg viewBox="0 0 100 30" class="mt-1 h-8 w-full" preserveAspectRatio="none" aria-hidden="true"><path :d="path('load')" fill="none" stroke="currentColor" stroke-width="1.5" class="text-amber-600" vector-effect="non-scaling-stroke" /></svg>
                </div>
                <div class="{{ $tile }}" data-metric="db">
                    <p class="text-xs text-slate-500">Base de données</p>
                    <p class="text-xl font-semibold" x-text="snap?.database.ok ? (snap.qps ?? '…') + ' req/s' : 'injoignable'"></p>
                    <p class="text-xs text-slate-500" x-show="snap?.database.ok" x-text="'ping ' + snap?.database.ping_ms + ' ms' + (snap?.database.status ? ' · ' + snap.database.status.Threads_connected + ' connexions' + ' · ' + snap.database.status.Slow_queries + ' lentes' : '')"></p>
                    <svg viewBox="0 0 100 30" class="mt-1 h-8 w-full" preserveAspectRatio="none" aria-hidden="true"><path :d="path('qps')" fill="none" stroke="currentColor" stroke-width="1.5" class="text-sky-600" vector-effect="non-scaling-stroke" /></svg>
                </div>
                <div class="{{ $tile }}" data-metric="memory">
                    <p class="text-xs text-slate-500">Mémoire du serveur</p>
                    <p class="text-xl font-semibold" x-text="snap?.system.memory ? (snap.system.memory.available_mb / 1024).toFixed(1) + ' Go libres' : 'non lisible'"></p>
                    <p class="text-xs text-slate-500" x-show="snap?.system.memory" x-text="'sur ' + (snap?.system.memory?.total_mb / 1024).toFixed(1) + ' Go'"></p>
                    <p class="text-xs text-slate-500" x-text="'PHP : ' + snap?.php.peak_mb + ' Mo (limite ' + snap?.php.memory_limit + ')'"></p>
                </div>
                <div class="{{ $tile }}" data-metric="disk">
                    <p class="text-xs text-slate-500">Disque</p>
                    <p class="text-xl font-semibold" x-text="snap?.disk ? snap.disk.free_gb + ' Go libres' : 'non lisible'"></p>
                    <p class="text-xs text-slate-500" x-text="'Base : ' + (snap?.database.size_mb ?? '?') + ' Mo'"></p>
                </div>
                <div class="{{ $tile }}" data-metric="queue">
                    <p class="text-xs text-slate-500">File d'attente</p>
                    <p class="text-xl font-semibold" x-text="(snap?.app.queue_pending ?? '?') + ' en attente'"></p>
                    <p class="text-xs" :class="snap?.app.queue_failed ? 'text-red-700' : 'text-slate-500'" x-text="(snap?.app.queue_failed ?? '?') + ' en échec'"></p>
                </div>
                <div class="{{ $tile }}" data-metric="health">
                    <p class="text-xs text-slate-500">Cron et erreurs</p>
                    <p class="text-xl font-semibold" :class="snap?.app.errors_last_hour ? 'text-red-700' : 'text-slate-900'" x-text="(snap?.app.errors_last_hour ?? '?') + ' erreur(s) / h'"></p>
                    <p class="text-xs" :class="snap?.app.cron_silent_minutes === null || snap?.app.cron_silent_minutes > 5 ? 'text-red-700' : 'text-slate-500'"
                       x-text="snap?.app.cron_silent_minutes === null ? 'cron jamais vu' : 'cron il y a ' + snap?.app.cron_silent_minutes + ' min'"></p>
                </div>
                <div class="{{ $tile }}" data-metric="accounts">
                    <p class="text-xs text-slate-500">Comptes</p>
                    <p class="text-xl font-semibold" x-text="snap?.app.users + ' réels'"></p>
                    <p class="text-xs text-slate-500" x-text="snap?.app.simulated_users + ' de test'"></p>
                </div>
            </div>
            <details class="mt-3 text-sm" x-show="snap?.database.tables?.length" x-cloak>
                <summary class="cursor-pointer text-slate-600">Tables les plus volumineuses</summary>
                <table class="mt-2 w-full text-left text-xs"><tbody>
                    <template x-for="t in snap?.database.tables || []" :key="t.name"><tr class="border-t border-slate-100"><td class="py-1" x-text="t.name"></td><td x-text="t.rows.toLocaleString('fr-FR') + ' lignes'"></td><td x-text="t.mb + ' Mo'"></td></tr></template>
                </tbody></table>
            </details>
            <p class="mt-3 text-xs text-slate-500">Mesures lues par l'application sur le serveur ; sur un hébergement mutualisé Hostinger, certaines peuvent être indisponibles ou concerner la machine entière. Les limites de votre offre (processeur, processus simultanés, inodes) se consultent dans hPanel › Utilisation des ressources.</p>
        </div>
    </x-section>

    <div class="mt-4" x-data="loadSimulation({
            storeUrl: @js(route('admin.simulation.store')),
            stepUrl: @js(route('admin.simulation.step', '__ID__')),
            stopUrl: @js(route('admin.simulation.stop', '__ID__')),
            resumeUrl: @js(route('admin.simulation.resume', '__ID__')),
            purgeUrl: @js(route('admin.simulation.purge', '__ID__')),
            active: @js($activeData),
            batches: @js($batchData),
        })" x-init="form = @js($defaults)">
        <p x-show="error" x-cloak class="mb-3 rounded-2xl bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-100" x-text="error" data-simulation-error></p>

        <div class="grid gap-4 lg:grid-cols-2">
            <x-section title="Lancer une simulation" data-simulation-form>
                <form @submit.prevent="start()" class="grid grid-cols-2 gap-3">
                    @foreach ($fields as $key => [$label, $help])
                        <label class="block text-sm">
                            <span class="font-medium text-slate-700">{{ $label }}</span>
                            <input type="number" class="input mt-1" name="{{ $key }}" min="{{ $limits[$key][0] }}" max="{{ $limits[$key][1] }}" x-model.number="form.{{ $key }}" required>
                            <span class="text-xs text-slate-500">{{ $help }} ({{ $limits[$key][0] }} à {{ $limits[$key][1] }})</span>
                        </label>
                    @endforeach
                    <p class="col-span-2 text-sm text-slate-600">Environ <strong x-text="estimate().toLocaleString('fr-FR')"></strong> lignes créées en base.</p>
                    <button class="btn-primary col-span-2" :disabled="running || purging" data-simulation-start><x-icon name="play" class="size-4" /> Lancer la simulation</button>
                </form>
                <ul class="mt-4 space-y-1 text-xs text-slate-500">
                    <li>• Comptes en « @simulation.invalid » : adresse non distribuable, aucun e-mail n'est envoyé, aucune notification sur téléphone, aucun appel à l'IA.</li>
                    <li>• Aucun abonnement ni licence : les comptes de test sont sur l'offre gratuite, comme une vraie inscription.</li>
                    <li>• Exclus des indicateurs et statistiques (espaces et chevaux marqués « démo »).</li>
                    <li>• Création par étapes de quelques secondes : le site reste disponible pour les vrais utilisateurs ; au plus {{ number_format(\App\Services\LoadSimulation::MAX_TOTAL, 0, ',', ' ') }} comptes de test en tout.</li>
                </ul>
            </x-section>

            <x-section title="Simulation en cours" data-simulation-progress>
                <template x-if="run">
                    <div>
                        <div class="flex items-center justify-between text-sm"><span x-text="'Simulation n° ' + run.id + ' · ' + run.label"></span><span class="font-semibold" x-text="run.done + ' / ' + run.total + ' comptes'"></span></div>
                        <div class="mt-2 h-3 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-brand-700 transition-all" :style="'width:' + (run.total ? Math.round(run.done / run.total * 100) : 0) + '%'"></div></div>
                        <div class="mt-3 flex flex-wrap gap-1.5 text-xs">
                            @foreach ($countLabels as $key => $label)
                                <span class="chip bg-slate-100 text-slate-700" x-show="run.counts?.{{ $key }}" x-text="(run.counts?.{{ $key }} || 0).toLocaleString('fr-FR') + ' {{ $label }}'"></span>
                            @endforeach
                        </div>
                        <p class="mt-2 text-xs text-slate-500" x-show="avgStep()" x-text="'≈ ' + avgStep() + ' ms par inscription complète (moyenne des dernières étapes)'"></p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button type="button" class="btn-secondary" x-show="run.status === 'running'" @click="stop()" data-simulation-stop>Arrêter</button>
                            <button type="button" class="btn-secondary" x-show="run.status === 'stopped'" @click="resume(run.id)">Reprendre</button>
                        </div>
                    </div>
                </template>
                <p x-show="! run" class="text-sm text-slate-500">Aucune simulation en cours.</p>
                <p x-show="purging" x-cloak class="mt-3 text-sm text-red-700" x-text="'Suppression de la simulation n° ' + purging + '… ' + (purgeLeft ?? '') + ' compte(s) restant(s)'"></p>
            </x-section>
        </div>

        <x-section title="Simulations" class="mt-4" data-simulation-batches>
            <div class="mb-3 flex justify-end">
                <button type="button" class="btn-ghost text-red-600" @click="purgeAll()" :disabled="purging" x-show="batches.some((b) => b.status !== 'purged')" data-simulation-purge-all>Supprimer toutes les données de test</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs text-slate-500"><tr><th class="py-2">N°</th><th>Date</th><th>Statut</th><th>Comptes</th><th>Créé</th><th></th></tr></thead>
                    <tbody>
                        <template x-for="b in batches" :key="b.id">
                            <tr class="border-t border-slate-100" :data-batch="b.id">
                                <td class="py-2" x-text="b.id"></td>
                                <td x-text="b.date"></td>
                                <td x-text="b.label"></td>
                                <td x-text="b.done + ' / ' + b.total"></td>
                                <td class="text-xs text-slate-500" x-text="Object.entries(b.counts || {}).map(([k, v]) => v + ' ' + ({{ json_encode($countLabels) }}[k] || k)).join(' · ')"></td>
                                <td class="text-right">
                                    <button type="button" class="btn-ghost text-red-600" x-show="b.status !== 'purged' && b.status !== 'running'" :disabled="purging" @click="purge(b.id)">Supprimer</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <p x-show="! batches.length" class="text-sm text-slate-500">Aucune simulation pour l'instant.</p>
            </div>
        </x-section>
    </div>
</x-layouts.app>

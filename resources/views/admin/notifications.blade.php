<x-layouts.app title="Notifications · Admin">
    <x-page-header title="Notifications" subtitle="Vérifier que les rappels et messages arrivent bien sur les téléphones" />
    @include('admin._nav')
    @php $ok = fn ($v) => $v ? 'bg-emerald-50 text-emerald-800 ring-emerald-100' : 'bg-red-50 text-red-800 ring-red-100'; @endphp
    <div class="grid gap-4 lg:grid-cols-2">
        <x-section title="Tâches planifiées (cron)" data-diag-scheduler>
            <p class="rounded-2xl px-4 py-3 text-sm ring-1 {{ $ok($scheduler['cron_ok']) }}">
                @if ($scheduler['cron_ok'])
                    Le cron du serveur tourne (dernier passage {{ $scheduler['cron_last']->diffForHumans() }}).
                @else
                    Le cron du serveur ne tourne pas{{ $scheduler['cron_last'] ? ' (dernier passage '.$scheduler['cron_last']->format('d/m/Y H:i').')' : '' }} : les rappels (quotidien, rendez-vous, pesée…) ne partent qu'à la visite suivante d'un utilisateur{{ $scheduler['fallback_last'] ? ', dernier rattrapage '.$scheduler['fallback_last']->diffForHumans() : '' }}.
                @endif
            </p>
            @unless ($scheduler['cron_ok'])
                <p class="mt-3 text-sm text-slate-600">À ajouter une fois dans les tâches cron de l'hébergement (cPanel › Tâches cron, chaque minute) :</p>
                <pre class="mt-2 overflow-x-auto rounded-xl bg-slate-900 p-3 text-xs text-white">{{ $scheduler['cron_line'] }}</pre>
                <p class="mt-3 text-sm text-slate-600">Si l'hébergeur demande plutôt le chemin d'un fichier PHP (ex. OVH), indiquez ce fichier, à lancer toutes les minutes :</p>
                <pre class="mt-2 overflow-x-auto rounded-xl bg-slate-900 p-3 text-xs text-white" data-cron-file>{{ $scheduler['cron_file'] }}</pre>
            @endunless
            <div class="mt-3 text-sm text-slate-600" data-cron-runs>
                <p class="font-medium text-slate-700">Derniers passages de cron.php</p>
                @forelse ($scheduler['cron_php_runs'] as $run)
                    <p class="text-xs {{ ($run['status'] ?? '') === 'ok' ? 'text-slate-600' : 'text-red-600' }}">{{ \Illuminate\Support\Carbon::parse($run['at'])->format('d/m H:i:s') }} · PHP {{ $run['php'] ?? '?' }} ({{ $run['sapi'] ?? '?' }}) · {{ $run['status'] ?? '' }}</p>
                @empty
                    <p class="text-xs text-slate-500">Aucun passage enregistré : l'hébergeur n'a pas encore lancé cron.php (vérifiez le chemin et la fréquence de la tâche). La ligne de commande ci-dessus n'écrit pas dans ce journal.</p>
                @endforelse
            </div>
            <p class="mt-3 text-sm text-slate-600">File d'attente : {{ $queue['pending'] ?? '?' }} en attente{{ $queue['oldest'] ? ' (la plus ancienne depuis '.$queue['oldest']->diffForHumans().')' : '' }}, {{ $queue['failed'] ?? '?' }} en échec.</p>
        </x-section>
        <x-section title="Notifications sur les appareils" data-diag-push>
            <p class="rounded-2xl px-4 py-3 text-sm ring-1 {{ $ok($vapidOk) }}">{{ $vapidOk ? 'Clés d\'envoi (VAPID) prêtes.' : 'Clés d\'envoi (VAPID) indisponibles.' }}</p>
            <p class="mt-3 text-sm text-slate-600">{{ $totals['devices'] }} appareil(s) abonné(s) pour {{ $totals['users'] }} utilisateur(s) · {{ $totals['failing'] }} en erreur.</p>
            @foreach ($pushErrors as $sub)
                <p class="mt-2 border-t border-slate-100 pt-2 text-xs text-slate-600"><strong>{{ $sub->user?->email }}</strong> · {{ $sub->last_failure_at?->format('d/m H:i') }} · {{ $sub->last_error }}</p>
            @endforeach
        </x-section>
    </div>

    <x-section title="Vérifier un utilisateur" class="mt-4" data-diag-user>
        <form method="GET" class="mb-4 flex gap-2"><input type="email" name="email" value="{{ request('email', $target?->email) }}" class="input" placeholder="adresse e-mail" aria-label="E-mail"><button class="btn-secondary shrink-0">Vérifier</button></form>
        @if (! $target)
            <p class="text-sm text-red-600">Aucun compte avec cette adresse.</p>
        @else
            <p class="text-sm font-medium text-slate-900">{{ $target->name }} · {{ $target->email }}</p>
            <p class="mt-2 text-sm text-slate-600"><strong>Rappel quotidien :</strong> {{ $daily['message'] }}</p>
            <div class="mt-3 space-y-2">
                @forelse ($targetDevices as $d)
                    <div class="rounded-2xl bg-slate-50 px-4 py-3 text-sm">
                        <p class="font-medium text-slate-800">{{ str_contains($d->endpoint, 'apple.com') ? 'iPhone / iPad (Apple)' : (str_contains($d->endpoint, 'google') ? 'Android / Chrome (Google)' : (str_contains($d->endpoint, 'mozilla') ? 'Firefox' : 'Autre')) }} · abonné le {{ $d->created_at->format('d/m/Y') }}</p>
                        <p class="text-xs text-slate-500">{{ $d->device }}</p>
                        <p class="text-xs {{ $d->last_error ? 'text-red-600' : 'text-slate-600' }}">Accepté par {{ str_contains($d->endpoint, 'apple.com') ? 'Apple' : 'le service push' }} : {{ $d->last_success_at?->format('d/m/Y H:i:s') ?? 'jamais' }} · Reçu et affiché par l'appareil : {{ $d->last_received_at?->format('d/m/Y H:i:s') ?? 'jamais' }}{{ $d->last_display_error ? ' (erreur d\'affichage : '.$d->last_display_error.')' : '' }}{{ $d->last_error ? ' · Dernière erreur ('.$d->last_failure_at?->format('d/m H:i').') : '.$d->last_error : '' }}</p>
                    </div>
                @empty
                    <p class="rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-900">Aucun appareil abonné : les notifications restent dans la cloche de l'application. Sur le téléphone, ouvrir l'application installée (écran d'accueil) › Paramètres › Notifications › Activer.</p>
                @endforelse
            </div>
            <form method="POST" action="{{ route('admin.notifications.test') }}" class="mt-3">@csrf<input type="hidden" name="user_id" value="{{ $target->id }}"><button class="btn-primary"><x-icon name="bell" class="size-4" /> Envoyer un essai sur ses appareils</button></form>
            @if ($lastNotifications->isNotEmpty())
                <p class="mt-4 text-xs font-semibold text-slate-500 uppercase">Dernières notifications dans l'application</p>
                @foreach ($lastNotifications as $n)
                    <p class="border-t border-slate-100 py-1.5 text-xs text-slate-600 first-of-type:border-0">{{ $n->created_at->format('d/m H:i') }} · {{ $n->data['title'] ?? '' }}</p>
                @endforeach
            @endif
        @endif
    </x-section>
</x-layouts.app>

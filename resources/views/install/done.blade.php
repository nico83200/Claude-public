<x-layouts.install :step="5" title="Installation terminée">
    <p class="mb-5 text-sm text-slate-600">Jack&amp;Cie est prêt. L'installeur est maintenant fermé définitivement.</p>
    @if ($manualEnv)
        <div class="mb-5 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            <p class="mb-2 font-semibold">Action requise : le fichier .env n'a pas pu être écrit.</p>
            <p class="mb-2">Ajoutez ces lignes au fichier <code>.env</code> à la racine de l'application (via FTP ou le gestionnaire de fichiers) :</p>
            <pre class="overflow-x-auto rounded-lg bg-white p-3 text-xs select-all">@foreach ($manualEnv as $k => $v){{ $k }}={{ is_bool($v) ? ($v ? 'true' : 'false') : (preg_match('/[\s#"\'$&=;]/', (string) $v) ? "'".$v."'" : $v) }}
@endforeach</pre>
        </div>
    @endif
    <h2 class="mb-2 text-base">Dernières étapes</h2>
    <ol class="mb-6 list-decimal space-y-3 pl-5 text-sm text-slate-700">
        <li>Ajoutez la <strong>tâche planifiée</strong> (rappels, licences, emails) dans le gestionnaire cron de votre hébergeur :
            <pre class="mt-1 overflow-x-auto rounded-lg bg-sand-100 p-2 text-xs select-all">{{ $cron }}</pre></li>
        <li>Connectez-vous avec <strong>{{ $email }}</strong>, puis activez la <strong>double authentification</strong> (Paramètres › Sécurité) : elle est exigée pour l'administration.</li>
        <li>Si vous utilisez Stripe, déclarez le webhook :
            <pre class="mt-1 overflow-x-auto rounded-lg bg-sand-100 p-2 text-xs select-all">{{ $webhook }}</pre></li>
        <li>Complétez les informations légales dans Administration › Paramètres.</li>
    </ol>
    <a href="{{ route('login') }}" class="btn-primary w-full">Se connecter</a>
</x-layouts.install>

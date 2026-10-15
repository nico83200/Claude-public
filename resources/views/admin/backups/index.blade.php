@php
    $size = fn ($b) => $b >= 1048576 ? number_format($b / 1048576, 1, ',', ' ').' Mo' : number_format(max(1, $b / 1024), 0, ',', ' ').' Ko';
    $badge = ['done' => 'green', 'failed' => 'red', 'running' => 'amber'];
@endphp
<x-layouts.app title="Sauvegardes · Admin">
    <x-page-header title="Sauvegardes" subtitle="Copie chiffrée de la base et des fichiers sur Google Drive, hors de l'hébergement" />
    @include('admin._nav')

    @if ($key)
        <div class="mb-6 rounded-3xl bg-amber-50 p-5 ring-1 ring-amber-200" data-backup-key>
            <p class="font-semibold text-amber-900">Clé de restauration : à conserver hors de l'application</p>
            <p class="mt-1 text-sm text-amber-900">Sans elle, les sauvegardes sont illisibles (elles sont chiffrées). Gardez-la dans un gestionnaire de mots de passe ou imprimée en lieu sûr — pas sur ce serveur.</p>
            <div class="mt-3 flex flex-wrap items-center gap-2" x-data="{ copied: false }">
                <code class="rounded-xl bg-white px-3 py-2 text-sm break-all select-all">{{ $key }}</code>
                <button type="button" class="btn-secondary min-h-10" @click="navigator.clipboard?.writeText(@js($key)); copied = true" x-text="copied ? 'Copiée' : 'Copier'">Copier</button>
            </div>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-5">
        <x-section title="Google Drive" class="lg:col-span-3">
            @if (! $clientConfigured)
                <p class="text-sm text-slate-600">Renseignez d'abord l'identifiant et le secret du client Google dans <a href="{{ route('admin.integrations.edit') }}" class="link">Intégrations</a> (le même client que pour la connexion avec Google).</p>
            @elseif (! $connected)
                <p class="text-sm text-slate-600">Connectez le compte Google qui recevra les sauvegardes. Jack&Cie ne pourra voir que les fichiers qu'il crée lui-même dans un dossier « Jack&amp;Cie – sauvegardes ».</p>
                <form method="POST" action="{{ route('admin.backups.connect') }}" class="mt-4">@csrf<button class="btn-primary" data-connect-drive>Connecter Google Drive</button></form>
            @else
                <p class="text-sm text-slate-600">Compte connecté : <strong>{{ $email ?: 'Google' }}</strong></p>
                <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                    <div><dt class="text-slate-500">Dernière sauvegarde de la base</dt><dd class="font-medium">{{ $lastDatabase ? $lastDatabase->finished_at->format('d/m/Y à H:i').' · '.$size($lastDatabase->size) : 'Aucune' }}</dd></div>
                    <div><dt class="text-slate-500">Dernière sauvegarde des fichiers</dt><dd class="font-medium">{{ $lastFiles ? $lastFiles->finished_at->format('d/m/Y à H:i').' · '.$size($lastFiles->size) : 'Aucune' }}</dd></div>
                </dl>
                @if ($pending)
                    <p class="mt-3 rounded-2xl bg-sky-50 p-3 text-sm text-sky-900" data-backup-pending>Sauvegarde demandée : elle part au prochain passage du cron (dans la minute).</p>
                @endif
                <div class="mt-4 flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('admin.backups.run') }}">@csrf<button class="btn-primary" data-run-backup>Sauvegarder la base maintenant</button></form>
                    <form method="POST" action="{{ route('admin.backups.run') }}">@csrf<input type="hidden" name="files" value="1"><button class="btn-secondary">Base, photos et documents</button></form>
                    @if ($hasKey && ! $key)<form method="POST" action="{{ route('admin.backups.key') }}">@csrf<button class="btn-ghost">Afficher la clé de restauration</button></form>@endif
                    <form method="POST" action="{{ route('admin.backups.disconnect') }}" data-confirm="Déconnecter Google Drive ? Plus aucune sauvegarde n'y sera envoyée (celles déjà envoyées restent dans le Drive)." data-confirm-ok="Déconnecter">@csrf @method('DELETE')<button class="btn-ghost text-red-600">Déconnecter</button></form>
                </div>
            @endif
        </x-section>
        <x-section title="Fonctionnement" class="lg:col-span-2">
            <ul class="space-y-2 text-sm text-slate-600">
                <li>• Chaque nuit vers 3 h : la base de données (30 jours conservés).</li>
                <li>• Chaque semaine : photos, documents et justificatifs (5 dernières archives conservées).</li>
                <li>• Fichiers chiffrés avant l'envoi : Google ne peut pas les lire. La clé de restauration est indispensable pour les relire.</li>
                <li>• En cas d'échec, les super-administrateurs sont prévenus par notification et e-mail.</li>
                <li>• Restauration : <code>php artisan backup:decrypt</code>, voir <code>docs/SAUVEGARDE.md</code>.</li>
                <li>• Adresse de retour à déclarer dans Google Cloud (client OAuth) :<br><code class="break-all text-xs">{{ $redirectUri }}</code></li>
            </ul>
        </x-section>
    </div>

    <h2 class="mt-8 mb-3 text-[17px] font-semibold">Historique</h2>
    @if ($runs->isEmpty())
        <p class="text-sm text-slate-500">Aucune sauvegarde pour l'instant.</p>
    @else
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Date</th><th>Contenu</th><th>État</th><th class="text-right">Taille</th><th>Détail</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @foreach ($runs as $r)
                <tr data-backup-run="{{ $r->status }}">
                    <td class="text-sm whitespace-nowrap">{{ $r->started_at?->format('d/m/Y H:i') }}</td>
                    <td class="text-sm">{{ \App\Models\BackupRun::KINDS[$r->kind] ?? $r->kind }}</td>
                    <td><x-badge :color="$badge[$r->status] ?? 'slate'">{{ \App\Models\BackupRun::STATUSES[$r->status] ?? $r->status }}</x-badge></td>
                    <td class="text-right text-sm whitespace-nowrap">{{ $r->size ? $size($r->size) : '—' }}</td>
                    <td class="text-xs text-slate-500">{{ $r->error ?: $r->file_name }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    @endif
</x-layouts.app>

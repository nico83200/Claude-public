<x-layouts.app :title="'Mise à jour '.($update->to_version ?? '').' · Admin'">
    <x-page-header :title="'Version '.($update->to_version ?? 'inconnue')" :subtitle="\App\Models\AppUpdate::STATUSES[$update->status]" :back="route('admin.updates.index')" />
    @include('admin._nav')
    <div class="grid gap-4 lg:grid-cols-5">
        <div class="space-y-4 lg:col-span-3">
            <x-section title="Paquet">
                <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-sm">
                    <dt class="text-slate-500">Fichier</dt><dd>{{ $update->package_name }}</dd>
                    <dt class="text-slate-500">Taille</dt><dd>{{ number_format($update->package_size / 1048576, 1, ',', ' ') }} Mo</dd>
                    <dt class="text-slate-500">SHA-256</dt><dd class="font-mono text-xs break-all">{{ $update->package_sha256 }}</dd>
                    <dt class="text-slate-500">Versions</dt><dd>{{ $update->from_version }} → <strong>{{ $update->to_version ?? '?' }}</strong> (installée actuellement : {{ $current }})</dd>
                    <dt class="text-slate-500">Envoyé</dt><dd>{{ $update->created_at->format('d/m/Y H:i') }} par {{ $update->user?->name }}</dd>
                </dl>
                @if ($update->message)<p class="mt-3 rounded-2xl {{ in_array($update->status, ['rejected', 'failed'], true) ? 'bg-red-50 text-red-800' : 'bg-slate-50 text-slate-700' }} p-3 text-sm">{{ $update->message }}</p>@endif
            </x-section>

            @if ($update->status === 'verified')
                <x-section title="Installer">
                    @if ($problems)
                        <div class="rounded-2xl bg-amber-50 p-4 text-sm text-amber-900"><ul class="list-disc pl-5">@foreach ($problems as $p)<li>{{ $p }}</li>@endforeach</ul></div>
                    @else
                        <form method="POST" action="{{ route('admin.updates.apply', $update) }}" class="space-y-3" x-data="{ busy: false }" @submit="busy = true">
                            @csrf
                            <x-checkbox name="backup_db" label="Sauvegarder aussi la base de données (recommandé)" :checked="true" />
                            <x-checkbox name="confirm" label="J'ai vérifié l'empreinte du paquet et je lance l'installation. Le site sera en maintenance pendant l'opération." required />
                            <button class="btn-primary" :disabled="busy"><span x-text="busy ? 'Installation en cours… ne fermez pas cette page' : 'Installer la version {{ $update->to_version }}'"></span></button>
                        </form>
                    @endif
                </x-section>
            @endif

            @if ($update->status === 'migrating')
                <x-section title="Finalisation">
                    <p class="text-sm text-slate-600">Les fichiers sont installés ; il reste à mettre à jour la base de données et à remettre le site en ligne. Sans action de votre part, la tâche planifiée s'en charge dans les 2 minutes.</p>
                    <a href="{{ URL::temporarySignedRoute('admin.updates.finalize', now()->addMinutes(30), $update) }}" class="btn-primary mt-3">Terminer l'installation</a>
                </x-section>
            @endif

            @if ($update->log)
                <x-section title="Journal">
                    <pre class="overflow-x-auto rounded-2xl bg-slate-900 p-4 text-xs leading-relaxed text-slate-100">{{ $update->log }}</pre>
                </x-section>
            @endif
        </div>
        <div class="space-y-4 lg:col-span-2">
            @if ($update->backup_code_path || $update->backup_db_path)
                <x-section title="Sauvegardes">
                    <div class="flex flex-wrap gap-2">
                        @if ($update->backup_code_path && is_file($update->backup_code_path))<a href="{{ route('admin.updates.download', [$update, 'code']) }}" class="btn-secondary">Code ({{ $update->from_version }})</a>@endif
                        @if ($update->backup_db_path && is_file($update->backup_db_path))<a href="{{ route('admin.updates.download', [$update, 'base']) }}" class="btn-secondary">Base de données</a>@endif
                    </div>
                    @if (in_array($update->status, ['done', 'failed'], true) && $update->backup_code_path && is_file($update->backup_code_path))
                        <form method="POST" action="{{ route('admin.updates.restore', $update) }}" class="mt-4 space-y-2 border-t border-slate-100 pt-4">
                            @csrf
                            <x-checkbox name="confirm" :label="'Remettre le code de la version '.$update->from_version" help="La base de données n'est pas modifiée : si la nouvelle version l'a fait évoluer, restaurez aussi la sauvegarde de la base (voir docs/SAUVEGARDE.md)." required />
                            <button class="btn-danger">Restaurer</button>
                        </form>
                    @endif
                </x-section>
            @endif
            @if (in_array($update->status, ['uploaded', 'verified', 'rejected'], true))
                <form method="POST" action="{{ route('admin.updates.destroy', $update) }}">@csrf @method('DELETE')<button class="btn-ghost text-red-600">Supprimer ce paquet</button></form>
            @endif
        </div>
    </div>
</x-layouts.app>

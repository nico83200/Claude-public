@php $badge = fn ($s) => ['done' => 'green', 'verified' => 'blue', 'rejected' => 'red', 'failed' => 'red', 'restored' => 'amber', 'applying' => 'amber', 'migrating' => 'amber'][$s] ?? 'slate'; @endphp
<x-layouts.app title="Mises à jour · Admin">
    <x-page-header title="Mises à jour" :subtitle="'Version installée : '.$current" />
    @include('admin._nav')

    <div class="grid gap-4 lg:grid-cols-5">
        <x-section title="Installer une nouvelle version" class="lg:col-span-3">
            @if (! $zipAvailable)
                <p class="rounded-2xl bg-red-50 p-4 text-sm text-red-800">L'extension PHP « zip » n'est pas disponible sur ce serveur : demandez son activation à votre hébergeur.</p>
            @else
                <ol class="mb-4 list-decimal space-y-1 pl-5 text-sm text-slate-600">
                    <li>Choisissez le fichier <strong>jackcie-x.y.z.zip</strong> de la nouvelle version.</li>
                    <li>Il est envoyé par petits morceaux puis vérifié (contenu, numéro de version, chemins).</li>
                    <li>Vous lancez l'installation : sauvegarde du code et de la base, remplacement des fichiers, mise à jour de la base. Le site est en maintenance une à deux minutes.</li>
                </ol>
                <div x-data="updateUpload(@js(route('admin.updates.upload')), {{ (int) $chunkBytes }})">
                    <label class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-3xl border-2 border-dashed border-slate-200 p-8 text-center transition hover:border-brand-300 hover:bg-brand-50/40" :class="busy && 'pointer-events-none opacity-60'">
                        <x-icon name="download" class="size-8 rotate-180 text-brand-600" />
                        <span class="font-medium" x-text="file ? file.name + ' (' + (file.size / 1048576).toFixed(1) + ' Mo)' : 'Choisir le fichier zip'"></span>
                        <span class="text-xs text-slate-500">Aucune limite de taille d'envoi de l'hébergeur à contourner : l'envoi se fait par morceaux.</span>
                        <input type="file" accept=".zip,application/zip" class="sr-only" @change="pick">
                    </label>
                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100" x-show="busy" x-cloak><div class="h-2 rounded-full bg-brand-600 transition-all" :style="`width: ${progress}%`"></div></div>
                    <p class="mt-2 text-sm text-red-700" x-show="error" x-text="error" x-cloak></p>
                    <button type="button" class="btn-primary mt-4" :disabled="! file || busy" @click="send()"><span x-text="busy ? 'Envoi… ' + progress + ' %' : 'Envoyer et vérifier'"></span></button>
                </div>
            @endif
            @if ($problems)
                <div class="mt-4 rounded-2xl bg-amber-50 p-4 text-sm text-amber-900"><p class="font-medium">À corriger avant d'installer :</p><ul class="mt-1 list-disc pl-5">@foreach ($problems as $p)<li>{{ $p }}</li>@endforeach</ul></div>
            @endif
        </x-section>
        <x-section title="Bon à savoir" class="lg:col-span-2">
            <ul class="space-y-2 text-sm text-slate-600">
                <li>• Le fichier <code>.env</code>, les documents et photos (<code>storage/</code>) ne sont jamais modifiés.</li>
                <li>• Une version plus ancienne que celle installée est refusée.</li>
                <li>• Les 3 dernières sauvegardes (code et base) sont conservées et téléchargeables.</li>
                <li>• En cas d'échec, l'ancienne version du code est remise automatiquement.</li>
                <li>• Vérifiez l'empreinte SHA-256 du paquet (fournie avec le zip) avant d'installer.</li>
                <li>• Par SSH, la méthode manuelle reste possible : voir <code>docs/DEPLOIEMENT.md</code>.</li>
            </ul>
        </x-section>
    </div>

    <h2 class="mt-8 mb-1 text-[17px] font-semibold">Historique des versions</h2>
    <p class="mb-3 text-sm text-slate-500">Principaux changements de chaque version et date d'installation sur ce serveur.</p>
    <div class="card divide-y divide-slate-100" data-version-history x-data="{ all: false }">
        @foreach ($versions as $i => $v)
            <div class="flex flex-col gap-1 p-4 sm:flex-row sm:gap-4" @if ($i >= 8) x-show="all" x-cloak @endif data-version="{{ $v['version'] }}">
                <div class="shrink-0 sm:w-44">
                    <p class="font-semibold text-slate-900">{{ $v['version'] }} @if ($v['current'])<x-badge color="green" class="ml-1">Installée</x-badge>@endif</p>
                    <p class="text-xs text-slate-500">
                        @if ($v['installed_at'])
                            {{ $v['initial'] ? 'Installation initiale' : 'Installée' }} le {{ $v['installed_at']->format('d/m/Y à H:i') }}@if ($v['with'])<br>avec la {{ $v['with'] }}@endif
                        @elseif ($v['initial'])
                            Installation initiale
                        @else
                            Date d'installation inconnue
                        @endif
                    </p>
                </div>
                <p class="text-sm text-slate-700">{{ $v['summary'] }}@if ($v['released'])<span class="block text-xs text-slate-400">Publiée le {{ $v['released']->format('d/m/Y') }}</span>@endif</p>
            </div>
        @endforeach
        @if ($versions->count() > 8)
            <button type="button" class="w-full p-3 text-sm font-medium text-brand-700 hover:bg-slate-50" x-show="! all" @click="all = true">Voir les {{ $versions->count() - 8 }} versions précédentes</button>
        @endif
    </div>

    <h2 class="mt-8 mb-3 text-[17px] font-semibold">Mises à jour envoyées</h2>
    @if ($updates->isEmpty())
        <p class="text-sm text-slate-500">Aucune mise à jour envoyée pour l'instant.</p>
    @else
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Date</th><th>Version</th><th>État</th><th>Par</th><th></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @foreach ($updates as $u)
                <tr>
                    <td class="text-sm">{{ $u->created_at->format('d/m/Y H:i') }}</td>
                    <td class="text-sm">{{ $u->from_version }} → <strong>{{ $u->to_version ?? '?' }}</strong></td>
                    <td><x-badge :color="$badge($u->status)">{{ \App\Models\AppUpdate::STATUSES[$u->status] }}</x-badge></td>
                    <td class="text-sm">{{ $u->user?->name }}</td>
                    <td><a href="{{ route('admin.updates.show', $u) }}" class="link text-sm">Détail</a></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    @endif
</x-layouts.app>

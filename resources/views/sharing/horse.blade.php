<x-layouts.app :title="'Partage · '.$horse->shortName()">
    <x-horse-header :horse="$horse" />
    @if ($upsell)
        <x-upsell :offer="$upsell" class="mb-4" />
    @elseunless ($sharingIncluded)<div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">Le partage n'est pas inclus dans l'offre de l'espace {{ $horse->organization->name }}.</div>@endif
    <div class="grid gap-4 lg:grid-cols-2">
        <div class="space-y-4">
            <x-section title="Personnes ayant accès">
                @forelse ($grants as $g)
                    <div class="border-t border-slate-100 py-3 first:border-0 {{ ! $g->isActive() ? 'opacity-60' : '' }}" x-data="{ edit: false }">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div><p class="font-medium">{{ $g->user->name }} <span class="text-sm font-normal text-slate-500">{{ $g->user->email }}</span></p>
                                <p class="text-xs text-slate-500">{{ $g->label }} · accordé par {{ $g->grantor?->name }} le {{ $g->created_at->format('d/m/Y') }}
                                    {{ $g->starts_at ? '· à partir du '.$g->starts_at->format('d/m/Y') : '' }} {{ $g->expires_at ? '· jusqu\'au '.$g->expires_at->format('d/m/Y') : '' }}
                                    {{ $g->revoked_at ? ($g->revoked_by === $g->user_id ? '· quitté par la personne invitée le ' : '· révoqué le ').$g->revoked_at->format('d/m/Y H:i') : '' }}</p>
                                @if ($g->paused_at && ! $g->revoked_at)<p class="mt-1 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">Suspendu par la personne invitée depuis le {{ $g->paused_at->format('d/m/Y') }}</p>@endif
                                <p class="mt-1 text-xs">{{ collect($g->permissions)->map(fn ($p) => $labels[$p] ?? $p)->implode(' · ') }}</p></div>
                            @if (! $g->revoked_at)
                                <div class="flex gap-1"><button @click="edit = ! edit" class="btn-ghost text-sm">Droits</button>
                                    <form method="POST" action="{{ route('horses.sharing.grants.revoke', [$horse, $g]) }}" data-confirm="Révoquer cet accès immédiatement ?" data-confirm-ok="Révoquer">@csrf<button class="btn-ghost text-sm text-red-600">Révoquer</button></form></div>
                            @endif
                        </div>
                        @if (! $g->revoked_at)
                        <form x-show="edit" x-cloak method="POST" action="{{ route('horses.sharing.grants.update', [$horse, $g]) }}" class="mt-2 space-y-2 rounded-lg bg-slate-50 p-3">
                            @csrf @method('PUT')
                            <x-field name="label" label="Libellé" :value="$g->label" />
                            @include('sharing._perms', ['selected' => $g->permissions, 'prefix' => 'permissions'])
                            <div class="grid grid-cols-2 gap-2"><x-field name="starts_at" type="date" label="Début" :value="$g->starts_at?->format('Y-m-d')" /><x-field name="expires_at" type="date" label="Fin" :value="$g->expires_at?->format('Y-m-d')" /></div>
                            <button class="btn-primary">Enregistrer</button>
                        </form>
                        @endif
                    </div>
                @empty <p class="text-sm text-slate-500">Personne d'autre n'a accès à ce dossier via un partage.</p> @endforelse
                @if ($invitations->isNotEmpty())
                    <p class="mt-3 text-sm font-semibold">Invitations en attente</p>
                    @foreach ($invitations as $i)<div class="flex items-center justify-between py-1 text-sm"><span>{{ $i->email }} <span class="text-xs text-slate-500">expire le {{ $i->expires_at->format('d/m/Y') }}</span></span><x-confirm-delete :action="route('horses.sharing.invitations.revoke', [$horse, $i])" label="Annuler" message="Annuler l'invitation ?" /></div>@endforeach
                @endif
                <p class="mt-3 text-xs text-slate-500">La révocation est immédiate.</p>
            </x-section>

            <x-section title="Écuries et prises en charge">
                @forelse ($assignments as $a)
                    <div class="border-t border-slate-100 py-3 text-sm first:border-0" x-data="{ edit: false }">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span><span class="font-medium">{{ $a->organization->name }}</span> — {{ $kinds[$a->kind] }} <x-badge :color="['active' => 'green', 'pending' => 'amber'][$a->status] ?? 'slate'">{{ ['pending' => 'En attente', 'active' => 'Active', 'ended' => 'Terminée', 'declined' => 'Refusée'][$a->status] }}</x-badge>
                                <span class="block text-xs text-slate-500">{{ $a->starts_at?->format('d/m/Y') }} {{ $a->ends_at ? '→ '.$a->ends_at->format('d/m/Y') : '' }}</span></span>
                            @if (in_array($a->status, ['pending', 'active']))
                                <span class="flex gap-1"><button @click="edit = ! edit" class="btn-ghost text-sm">Droits</button>
                                    <form method="POST" action="{{ route('assignments.end', $a) }}" data-confirm="Mettre fin à la prise en charge ? L'écurie perdra l'accès ; l'historique est conservé." data-confirm-ok="Mettre fin">@csrf<button class="btn-ghost text-sm text-red-600">Terminer</button></form></span>
                            @endif
                        </div>
                        @if (in_array($a->status, ['pending', 'active']))
                        <form x-show="edit" x-cloak method="POST" action="{{ route('horses.assignments.update', [$horse, $a]) }}" class="mt-2 space-y-2 rounded-lg bg-slate-50 p-3">
                            @csrf @method('PUT')
                            @include('sharing._perms', ['selected' => $a->permissions, 'prefix' => 'permissions'])
                            <button class="btn-primary">Enregistrer</button>
                        </form>
                        @endif
                    </div>
                @empty <p class="text-sm text-slate-500">Aucune écurie n'a accès à ce dossier.</p> @endforelse
            </x-section>
        </div>

        <div class="space-y-4">
            <x-section title="Inviter une personne (demi-pension, cavalier…)">
                <form method="POST" action="{{ route('horses.sharing.invite', $horse) }}" class="space-y-3">
                    @csrf
                    <x-field name="email" type="email" label="Email" required />
                    <x-field name="label" label="Libellé" value="Demi-pension" />
                    @include('sharing._perms', ['selected' => old('permissions', $presets['half_lease']), 'prefix' => 'permissions'])
                    <div class="grid grid-cols-2 gap-2"><x-field name="starts_at" type="date" label="Début de l'accès" /><x-field name="expires_at" type="date" label="Fin de l'accès" /></div>
                    <button class="btn-primary w-full" @disabled(! $sharingIncluded || $upsell)>Envoyer l'invitation</button>
                    <p class="text-xs text-slate-500">La personne n'accédera ni à vos autres chevaux, ni à votre abonnement, ni à vos factures.</p>
                </form>
            </x-section>
            <x-section title="Confier à une écurie">
                <form method="POST" action="{{ route('horses.assignments.request', $horse) }}" class="space-y-3">
                    @csrf
                    <x-field name="stable_code" label="Code de l'écurie" help="Communiqué par le gérant (page Organisation de l'écurie)." required />
                    <x-select name="kind" label="Nature" :options="$kinds" value="boarding" />
                    @include('sharing._perms', ['selected' => old('permissions', $presets['boarding']), 'prefix' => 'permissions'])
                    <button class="btn-secondary w-full">Envoyer la demande</button>
                    <p class="text-xs text-slate-500">L'écurie n'aura accès qu'aux informations cochées, même si le cheval y est hébergé. Le dossier n'est jamais dupliqué.</p>
                </form>
            </x-section>
        </div>
    </div>
</x-layouts.app>

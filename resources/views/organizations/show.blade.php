<x-layouts.app :title="$org->name">
    <x-page-header :title="$org->name" :subtitle="$org->typeLabel().' · '.$horseCount.' cheval(aux) géré(s)'">
        <a href="{{ route('organizations.create') }}" class="btn-secondary"><x-icon name="plus" /> Créer une écurie</a>
    </x-page-header>
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            @if (! $org->isPersonal())
            <x-section title="Membres">
                @foreach ($members as $m)
                    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 py-2 first:border-0" x-data="{ edit: false }">
                        <span class="text-sm"><span class="font-medium">{{ $m->user->name }}</span> <span class="text-slate-500">{{ $m->user->email }}</span><span class="block text-xs text-slate-500">{{ $m->role->name }}{{ $m->title ? ' · '.$m->title : '' }}</span></span>
                        <span class="flex gap-1">
                            @if ($canMembers && $m->user_id !== $org->owner_id)
                                <button @click="edit = ! edit" class="btn-ghost text-sm">Rôle</button>
                                <x-confirm-delete :action="route('organization.members.destroy', $m)" label="Retirer" message="Retirer ce membre ? Ses accès sont supprimés immédiatement." />
                            @elseif ($m->user_id === auth()->id() && $m->user_id !== $org->owner_id)
                                <x-confirm-delete :action="route('organization.members.destroy', $m)" label="Quitter" message="Quitter cet espace ?" />
                            @endif
                        </span>
                        @if ($canMembers && $m->user_id !== $org->owner_id)
                        <form x-show="edit" x-cloak method="POST" action="{{ route('organization.members.update', $m) }}" class="flex w-full flex-wrap gap-2 rounded-lg bg-slate-50 p-2">
                            @csrf @method('PUT')
                            <x-select name="role_id" :options="$roles->reject(fn ($r) => $r->key === 'owner')->pluck('name', 'id')" :value="$m->role_id" aria-label="Rôle" />
                            <input name="title" value="{{ $m->title }}" placeholder="Fonction (facultatif)" class="input max-w-48 py-2 text-sm" aria-label="Fonction">
                            <button class="btn-primary">OK</button>
                        </form>
                        @endif
                    </div>
                @endforeach
                @foreach ($invitations as $i)<div class="flex items-center justify-between border-t border-slate-100 py-2 text-sm"><span>{{ $i->email }} <span class="text-xs text-slate-500">({{ $i->role?->name }}) invitation en attente</span></span>@if ($canMembers)<x-confirm-delete :action="route('organization.invitations.revoke', $i)" label="Annuler" message="Annuler l'invitation ?" />@endif</div>@endforeach
                @if ($canMembers)
                <form method="POST" action="{{ route('organization.members.invite') }}" class="mt-3 flex flex-wrap gap-2 border-t border-slate-100 pt-3">
                    @csrf
                    <input type="email" name="email" placeholder="Email du membre" required class="input max-w-xs" aria-label="Email">
                    <x-select name="role_id" :options="$roles->reject(fn ($r) => $r->key === 'owner')->pluck('name', 'id')" aria-label="Rôle" />
                    <button class="btn-primary">Inviter</button>
                </form>
                @error('email')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                @endif
            </x-section>

            <x-section title="Chevaux accueillis (pension, travail, soins)">
                @forelse ($assignments as $a)
                    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 py-2 text-sm first:border-0">
                        <span><span class="font-medium">{{ $a->horse->shortName() }}</span> — {{ \App\Models\HorseOrganizationAssignment::KINDS[$a->kind] }}
                            <span class="block text-xs text-slate-500">{{ count($a->permissions) }} droit(s) accordé(s) par le propriétaire</span></span>
                        <span class="flex gap-1">
                            @if ($a->status === 'pending' && $canManage)
                                <form method="POST" action="{{ route('assignments.accept', $a) }}">@csrf<button class="btn-primary text-sm">Accepter</button></form>
                                <form method="POST" action="{{ route('assignments.decline', $a) }}">@csrf<button class="btn-ghost text-sm">Refuser</button></form>
                            @elseif ($a->status === 'active')
                                <a href="{{ route('horses.show', $a->horse) }}" class="btn-secondary text-sm">Ouvrir</a>
                                @if ($canManage)<form method="POST" action="{{ route('assignments.end', $a) }}" onsubmit="return confirm('Mettre fin à la prise en charge ?')">@csrf<button class="btn-ghost text-sm">Départ</button></form>@endif
                            @endif
                        </span>
                    </div>
                @empty <p class="text-sm text-slate-500">Aucun cheval confié par un propriétaire extérieur.</p> @endforelse
                @if ($pastAssignments->isNotEmpty())<p class="mt-3 text-xs text-slate-500">Départs récents : {{ $pastAssignments->map(fn ($a) => $a->horse->shortName().' ('.$a->ends_at?->format('d/m/Y').')')->implode(', ') }}</p>@endif
            </x-section>

            @if ($canMembers)
            <x-section title="Rôles personnalisés">
                @foreach ($customRoles as $role)
                    <details class="border-t border-slate-100 py-2 first:border-0"><summary class="cursor-pointer text-sm font-medium">{{ $role->name }}</summary>
                        <form method="POST" action="{{ route('organization.roles.update', $role) }}" class="mt-2 space-y-2">
                            @csrf @method('PUT')
                            <x-field name="name" label="Nom" :value="$role->name" />
                            @include('organizations._role-perms', ['selected' => $role->permissions->pluck('key')->all()])
                            <div class="flex gap-2"><button class="btn-primary">Enregistrer</button></div>
                        </form>
                        <x-confirm-delete :action="route('organization.roles.destroy', $role)" message="Supprimer ce rôle ?" />
                    </details>
                @endforeach
                <details class="mt-2"><summary class="cursor-pointer text-sm link">Créer un rôle</summary>
                    <form method="POST" action="{{ route('organization.roles.store') }}" class="mt-2 space-y-2">
                        @csrf
                        <x-field name="name" label="Nom du rôle" required />
                        @include('organizations._role-perms', ['selected' => ['horse.view']])
                        <button class="btn-primary">Créer</button>
                    </form>
                </details>
            </x-section>
            @endif
            @else
            <x-section title="Votre espace personnel">
                <p class="text-sm text-slate-600">Cet espace contient vos chevaux et votre abonnement. Pour gérer une écurie (membres, rôles, chevaux en pension), créez un espace écurie : vous pourrez basculer d'un espace à l'autre depuis le menu.</p>
            </x-section>
            @endif
        </div>

        <div class="space-y-4">
            @if (! $org->isPersonal())
            <x-section title="Code de l'écurie">
                <p class="rounded-lg bg-slate-100 p-3 text-center font-mono text-lg select-all">{{ $org->slug }}</p>
                <p class="mt-2 text-xs text-slate-500">À communiquer aux propriétaires pour qu'ils vous confient leur cheval (onglet Partage du cheval).</p>
            </x-section>
            @endif
            <x-section title="Offre">
                <p class="text-sm"><span class="font-semibold">{{ $ent['plan']?->name ?? 'Aucune' }}</span> — {{ \App\Models\License::STATUSES[$ent['status']] ?? 'Aucune licence' }}</p>
                <p class="mt-1 text-xs text-slate-500">Chevaux : {{ $horseCount }} / {{ $ent['limits']['max_horses'] ?? '∞' }} · Membres : {{ $members->count() }} / {{ $ent['limits']['max_members'] ?? '∞' }}</p>
                @if (in_array('billing.manage', $perms))<a href="{{ route('billing.index') }}" class="mt-2 inline-block text-sm link">Gérer l'abonnement</a>@endif
            </x-section>
            @if ($canManage)
            <x-section title="Coordonnées">
                <form method="POST" action="{{ route('organization.update') }}" class="space-y-3">
                    @csrf @method('PUT')
                    <x-field name="name" label="Nom" :value="$org->name" required />
                    <x-field name="email" type="email" label="Email" :value="$org->email" />
                    <x-field name="phone" label="Téléphone" :value="$org->phone" />
                    <x-field name="address_line" label="Adresse" :value="$org->address_line" />
                    <div class="grid grid-cols-2 gap-2"><x-field name="postal_code" label="CP" :value="$org->postal_code" /><x-field name="city" label="Ville" :value="$org->city" /></div>
                    <button class="btn-primary w-full">Enregistrer</button>
                </form>
            </x-section>
            @endif
        </div>
    </div>
</x-layouts.app>

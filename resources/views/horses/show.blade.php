@php $can = fn ($p) => in_array($p, $perms, true); @endphp
<x-layouts.app :title="$horse->shortName()">
    <x-horse-header :horse="$horse" :perms="$perms">
        @unless ($horse->isArchived())
            @if ($can('sessions.create'))<a href="{{ route('sessions.create', ['horse' => $horse->id]) }}" class="btn-primary"><x-icon name="plus" class="size-4" /> Séance</a>@endif
            @if ($can('health.edit'))<a href="{{ route('horses.care.create', $horse) }}" class="btn-secondary"><x-icon name="health" class="size-4" /> Soin</a>@endif
            @if ($can('comments.create'))<button type="button" class="btn-secondary" onclick="document.getElementById('observation').showModal()"><x-icon name="alert" class="size-4" /> Signaler</button>@endif
        @endunless
    </x-horse-header>

    @if ($sources->isNotEmpty())
        <x-section title="Informations importées à vérifier" class="mb-4 border-amber-200 bg-amber-50/40">
            @foreach ($sources as $src)
                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-amber-100 py-2 text-sm first:border-0">
                    <span><strong>{{ \App\Services\HorseSearch\HorseCandidate::FIELDS[$src->field] ?? $src->field }}</strong> : {{ $src->value }} <span class="text-xs text-slate-500">— {{ $src->source }} ({{ $src->fetched_at->format('d/m/Y') }})</span></span>
                    <span class="flex gap-1">
                        <form method="POST" action="{{ route('horses.sources.decide', [$horse, $src, 'confirm']) }}">@csrf<button class="btn-secondary text-xs">Confirmer</button></form>
                        <form method="POST" action="{{ route('horses.sources.decide', [$horse, $src, 'reject']) }}">@csrf<button class="btn-ghost text-xs">Rejeter</button></form>
                    </span>
                </div>
            @endforeach
        </x-section>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            @if ($horse->precautions || $horse->care_instructions)
                <div class="rounded-2xl border border-brand-200 bg-brand-50 p-4 text-sm">
                    @if ($horse->precautions)<p class="font-semibold text-brand-800">Précautions</p><p class="mb-2 whitespace-pre-line">{{ $horse->precautions }}</p>@endif
                    @if ($horse->care_instructions)<p class="font-semibold text-brand-800">Consignes de soins</p><p class="whitespace-pre-line">{{ $horse->care_instructions }}</p>@endif
                </div>
            @endif
            @if ($treatments->isNotEmpty())
            <x-section title="Traitements en cours">
                @foreach ($treatments as $t)<p class="border-t border-slate-100 py-2 text-sm first:border-0"><span class="font-medium">{{ $t->product }}</span><span class="block text-xs text-slate-500">{{ $t->dosage }} · {{ $t->frequency }} {{ $t->ends_on ? '· fin '.$t->ends_on->format('d/m') : '' }}</span></p>@endforeach
            </x-section>
            @endif

            @if ($events->isNotEmpty())
            <x-section title="À venir" :action="route('calendar.index', ['horse' => $horse->id])">
                @foreach ($events as $e)<a href="{{ route('calendar.show', $e) }}" class="block border-t border-slate-100 py-2 text-sm first:border-0"><span class="font-medium">{{ $e->starts_at->format('d/m H:i') }}</span> {{ $e->title }}</a>@endforeach
            </x-section>
            @endif
            <x-section title="Identité">
                <x-dl :items="[
                    'Nom officiel' => $horse->official_name,
                    'N° SIRE' => $horse->identifier('sire'),
                    'UELN' => $horse->identifier('ueln'),
                    'Transpondeur' => $horse->identifier('transponder'),
                    'Date de naissance' => $horse->birth_date?->format('d/m/Y') ?? $horse->birth_year,
                    'Taille' => $horse->height_cm ? $horse->height_cm.' cm' : null,
                    'Pays de naissance' => $horse->birth_country,
                    'Éleveur' => $horse->breeder,
                    'Discipline' => \App\Models\Horse::DISCIPLINES[$horse->main_discipline] ?? null,
                    'Niveau' => $horse->work_level,
                    'Lieu de vie' => $horse->current_location,
                    'Origine' => $horse->origin?->studbook,
                ]" />
                @if ($horse->particularities)<div class="mt-4"><p class="text-xs font-medium tracking-wide text-slate-500 uppercase">Particularités</p><p class="text-sm whitespace-pre-line">{{ $horse->particularities }}</p></div>@endif
                @if ($horse->general_notes)<div class="mt-4"><p class="text-xs font-medium tracking-wide text-slate-500 uppercase">Observations générales</p><p class="text-sm whitespace-pre-line">{{ $horse->general_notes }}</p></div>@endif
            </x-section>

            @if ($can('sessions.view'))
            <x-section title="Dernières séances" :action="route('sessions.index', ['horse' => $horse->id])">
                @forelse ($sessions as $s)
                    <a href="{{ route('sessions.show', $s) }}" class="flex justify-between border-t border-slate-100 py-2 text-sm first:border-0"><span>{{ $s->scheduled_at->format('d/m/Y H:i') }} · {{ $s->typeLabel() }}<span class="block text-xs text-slate-500">{{ $s->objective }} · {{ $s->riderLabel() }}</span></span><x-badge :color="$s->status === 'completed' ? 'green' : 'slate'">{{ \App\Models\RidingSession::STATUSES[$s->status] }}</x-badge></a>
                @empty <p class="text-sm text-slate-500">Aucune séance.</p> @endforelse
            </x-section>
            @endif

            @if ($can('health.view'))
            <x-section title="Derniers soins" :action="route('horses.care.index', $horse)">
                @forelse ($care as $c)
                    <a href="{{ route('horses.care.show', [$horse, $c]) }}" class="flex justify-between border-t border-slate-100 py-2 text-sm first:border-0"><span><span class="font-medium">{{ $c->category->name }}</span> {{ $c->reason ? '— '.$c->reason : '' }}<span class="block text-xs text-slate-500">{{ $c->professional?->fullName() }}</span></span><span class="shrink-0 pl-2 text-xs whitespace-nowrap text-slate-500">{{ $c->performed_at->format('d/m/Y') }}</span></a>
                @empty <p class="text-sm text-slate-500">Aucun soin enregistré.</p> @endforelse
            </x-section>
            @endif

            <x-section title="Photos">
                @if ($horse->photos->isEmpty())<p class="text-sm text-slate-500">Aucune photo pour le moment.</p>@endif
                <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                    @foreach ($horse->photos as $photo)
                        <div class="group relative">
                            <a href="{{ route('horses.photos.show', [$horse, $photo]) }}" target="_blank"><img src="{{ route('horses.photos.show', [$horse, $photo]) }}?thumb=1" alt="{{ $photo->caption }}" loading="lazy" class="aspect-square w-full rounded-lg object-cover"></a>
                            @if ($can('horse.edit'))
                                <div class="mt-1 flex gap-1 text-xs">
                                    @if ($horse->main_photo_path !== (string) $photo->id)<form method="POST" action="{{ route('horses.photos.main', [$horse, $photo]) }}">@csrf<button class="link">Principale</button></form>@endif
                                    <x-confirm-delete :action="route('horses.photos.destroy', [$horse, $photo])" class="text-red-600" message="Supprimer cette photo ?">Suppr.</x-confirm-delete>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
                @if ($can('horse.edit') && ! $horse->isArchived())
                    <form method="POST" action="{{ route('horses.photos.store', $horse) }}" enctype="multipart/form-data" class="mt-3">
                        @csrf
                        <label class="btn-secondary cursor-pointer"><x-icon name="camera" class="size-4" /> Ajouter une photo
                            <input type="file" name="photo" accept="image/*" required class="sr-only" onchange="this.form.requestSubmit()">
                        </label>
                    </form>
                @endif
            </x-section>
        </div>

        <div class="space-y-4">
            @if ($observations->isNotEmpty())
            <x-section title="Observations récentes">
                @foreach ($observations as $o)
                    <div class="border-t border-slate-100 py-2.5 text-sm first:border-0 first:pt-0"><x-badge :color="['info' => 'slate', 'watch' => 'amber', 'alert' => 'red'][$o->severity]">{{ \App\Models\HealthObservation::SEVERITIES[$o->severity] }}</x-badge> {{ $o->body }}<p class="mt-0.5 text-xs text-slate-500">{{ $o->author?->name }} · {{ $o->observed_at->diffForHumans() }}</p></div>
                @endforeach
            </x-section>
            @endif


            <x-section title="Propriétaires et détenteurs">
                @foreach ($horse->ownerships->sortBy('ended_on') as $o)
                    <div class="flex items-center justify-between border-t border-slate-100 py-2 text-sm first:border-0 {{ $o->ended_on && $o->ended_on->isPast() ? 'opacity-60' : '' }}">
                        <span>{{ $o->displayName() }} <span class="text-xs text-slate-500">({{ ['owner' => 'propriétaire', 'co_owner' => 'copropriétaire', 'keeper' => 'détenteur'][$o->role] }}{{ $o->share_percent ? ', '.rtrim(rtrim($o->share_percent, '0'), '.').' %' : '' }})</span>
                            <span class="block text-xs text-slate-500">depuis {{ $o->started_on?->format('d/m/Y') ?? '—' }}{{ $o->ended_on ? ' · jusqu\'au '.$o->ended_on->format('d/m/Y') : '' }}</span></span>
                        @if ($can('horse.manage') && ! $o->ended_on)<x-confirm-delete :action="route('horses.ownerships.end', [$horse, $o])" method="POST" message="Enregistrer la fin de propriété ?" class="text-xs link">Terminer</x-confirm-delete>@endif
                    </div>
                @endforeach
                @if ($can('horse.manage'))
                    <details class="mt-2 text-sm"><summary class="cursor-pointer link">Ajouter un propriétaire / détenteur</summary>
                        <form method="POST" action="{{ route('horses.ownerships.store', $horse) }}" class="mt-2 space-y-2">
                            @csrf
                            <x-field name="email" type="email" label="Email d'un compte existant" />
                            <x-field name="owner_name" label="ou nom (sans compte)" />
                            <x-select name="role" label="Rôle" :options="['owner' => 'Propriétaire', 'co_owner' => 'Copropriétaire', 'keeper' => 'Détenteur']" value="co_owner" />
                            <x-field name="share_percent" type="number" step="0.01" label="Part (%)" />
                            <button class="btn-secondary w-full">Ajouter</button>
                        </form>
                    </details>
                @endif
            </x-section>

            @if ($horse->assignments->isNotEmpty() || $horse->locations->isNotEmpty())
            <x-section title="Lieux et prise en charge">
                @foreach ($horse->assignments as $a)<p class="text-sm"><x-badge :color="$a->status === 'active' ? 'green' : 'amber'">{{ $a->status === 'active' ? 'Actif' : 'En attente' }}</x-badge> {{ \App\Models\HorseOrganizationAssignment::KINDS[$a->kind] }} — {{ $a->organization->name }}</p>@endforeach
                @foreach ($horse->locations->take(5) as $l)<p class="mt-1 text-xs text-slate-500">{{ $l->label }} : {{ $l->started_on->format('d/m/Y') }} → {{ $l->ended_on?->format('d/m/Y') ?? 'aujourd\'hui' }}</p>@endforeach
            </x-section>
            @endif

            @if ($can('horse.manage'))
            <details id="gestion" class="card group p-5 sm:p-6" x-data x-init="const o = () => { if (location.hash === '#gestion') { $el.open = true; $el.scrollIntoView({ behavior: 'smooth' }) } }; o(); window.addEventListener('hashchange', o)">
                <summary class="flex cursor-pointer list-none items-center justify-between text-[17px] font-semibold text-slate-900">Gestion du dossier <x-icon name="chevron" class="size-5 text-slate-400 transition group-open:rotate-90" /></summary>
                <div class="mt-4 space-y-5 text-sm">
                    @if ($transferTargets->isNotEmpty())
                        <form method="POST" action="{{ route('horses.transfer', $horse) }}" class="space-y-2" onsubmit="return confirm('Transférer la gestion de ce cheval ? L\'historique est conservé.')">
                            @csrf
                            <x-select name="organization_id" label="Transférer la gestion vers" :options="$transferTargets->pluck('name', 'id')" />
                            <button class="btn-secondary w-full">Transférer</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('horses.destroy', $horse) }}" class="space-y-2 rounded-2xl bg-red-50/60 p-4">
                        @csrf @method('DELETE')
                        <p class="font-semibold text-red-700">Supprimer le dossier</p>
                        <p class="text-xs text-slate-600">Le dossier sera supprimé pour tous les utilisateurs, puis définitivement effacé après 30 jours. Préférez l'archivage pour conserver l'historique.</p>
                        <x-field name="confirm_name" :label="'Tapez « '.$horse->official_name.' » pour confirmer'" />
                        <button class="btn-danger w-full">Supprimer définitivement</button>
                    </form>
                </div>
            </details>
            @endif
        </div>
    </div>

    @if ($can('comments.create'))
    <dialog id="observation" class="m-auto w-[calc(100%-2rem)] max-w-md rounded-3xl p-0 backdrop:bg-slate-900/40">
        <form method="POST" action="{{ route('horses.observations.store', $horse) }}" class="space-y-4 p-6">
            @csrf
            <div class="flex items-start justify-between gap-2">
                <div><h2 class="text-lg font-semibold">Signaler une observation</h2><p class="text-sm text-slate-500">Les personnes concernées sont prévenues.</p></div>
                <button type="button" class="btn-icon border-0" onclick="this.closest('dialog').close()" aria-label="Fermer"><x-icon name="x" /></button>
            </div>
            <x-textarea name="body" rows="3" placeholder="Boiterie, plaie, comportement inhabituel…" required aria-label="Observation" />
            <x-select name="severity" label="Niveau" :options="\App\Models\HealthObservation::SEVERITIES" value="info" />
            <button class="btn-primary w-full">Envoyer</button>
        </form>
    </dialog>
    @endif
</x-layouts.app>

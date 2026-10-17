@php $can = fn ($p) => in_array($p, $perms, true); @endphp
<x-layouts.app :title="$horse->shortName()">
    <x-horse-header :horse="$horse" :perms="$perms">
        @unless ($horse->isArchived())
            @if ($can('sessions.create'))<a href="{{ route('sessions.create', ['horse' => $horse->id]) }}" class="btn-primary"><x-icon name="plus" class="size-4" /> Séance</a>@endif
            @if ($can('health.edit'))<a href="{{ route('horses.care.create', $horse) }}" class="btn-secondary"><x-icon name="health" class="size-4" /> Soin</a>@endif
            @if ($can('comments.create'))<button type="button" class="btn-secondary" onclick="document.getElementById('observation').showModal()"><x-icon name="alert" class="size-4" /> Signaler</button>@endif
        @endunless
    </x-horse-header>


    @php
        $lastLog = $logs->first(); $w = $today['weights']; $nextDue = $today['nextDue'];
        $trend = $w->count() === 2 ? (float) $w[0]->weight_kg - (float) $w[1]->weight_kg : null;
        $qty = fn ($e) => $e->quantity !== null ? rtrim(rtrim((string) $e->quantity, '0'), '.').' '.$e->unit : '';
    @endphp
    {{-- Aujourd'hui : ce qu'on vient vérifier chaque jour, en un coup d'œil. --}}
    <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4" data-horse-today>
        <a href="{{ route('horses.logs.index', $horse) }}" class="card p-4 hover:ring-brand-300">
            <p class="text-xs text-slate-500">Dernier suivi</p>
            @if ($lastLog)
                <p class="mt-1 font-semibold {{ $lastLog->anomalies || $lastLog->general_state === 'poor' ? 'text-red-700' : 'text-slate-900' }}">{{ $lastLog->anomalies ? 'Anomalie signalée' : 'État : '.(\App\Models\DailyLog::STATE[$lastLog->general_state] ?? '—') }}</p>
                <p class="text-xs text-slate-500">{{ $lastLog->logged_at->diffForHumans() }}</p>
            @else<p class="mt-1 font-semibold text-slate-400">Aucun</p><p class="text-xs text-brand-700">Noter le suivi du jour</p>@endif
        </a>
        <a href="{{ route('horses.weight', $horse) }}" class="card p-4 hover:ring-brand-300">
            <p class="text-xs text-slate-500">Poids</p>
            @if ($w->isNotEmpty())
                <p class="mt-1 font-semibold text-slate-900">{{ number_format((float) $w[0]->weight_kg, 0, ',', ' ') }} kg @if ($trend !== null && abs($trend) >= 1)<span class="text-xs font-medium {{ abs($trend) >= 15 ? 'text-amber-700' : 'text-slate-500' }}">{{ $trend > 0 ? '+' : '−' }}{{ number_format(abs($trend), 0, ',', ' ') }}</span>@endif</p>
                <p class="text-xs text-slate-500">le {{ $w[0]->measured_on->format('d/m') }}</p>
            @else<p class="mt-1 font-semibold text-slate-400">—</p><p class="text-xs text-brand-700">Ajouter une pesée</p>@endif
        </a>
        @if ($can('health.view'))
        <a href="{{ route('horses.care.index', $horse) }}#controles" class="card p-4 hover:ring-brand-300">
            <p class="text-xs text-slate-500">Prochaine échéance</p>
            @if ($nextDue)
                <p class="mt-1 truncate font-semibold {{ $nextDue->next_check_on->isPast() ? 'text-red-700' : 'text-slate-900' }}">{{ $nextDue->category?->name }}</p>
                <p class="text-xs {{ $nextDue->next_check_on->isPast() ? 'text-red-600' : 'text-slate-500' }}">{{ $nextDue->next_check_on->isPast() ? 'en retard depuis le ' : 'le ' }}{{ $nextDue->next_check_on->format('d/m') }}</p>
            @else<p class="mt-1 font-semibold text-slate-400">Rien de prévu</p>@endif
        </a>
        @endif
        @if ($can('feeding.view'))
        <a href="{{ route('horses.feeding', $horse) }}" class="card p-4 hover:ring-brand-300">
            <p class="text-xs text-slate-500">Ration{{ $today['rations']->count() > 1 ? 's' : '' }} en cours</p>
            @forelse ($today['rations']->take(2) as $r)
                <p class="mt-1 truncate text-sm font-semibold text-slate-900">{{ $r->name }}</p>
                <p class="truncate text-xs text-slate-500">{{ $r->entries->take(3)->map(fn ($e) => trim(($e->time_of_day ? substr($e->time_of_day, 0, 5).' ' : '').$e->feed.' '.$qty($e)))->implode(' · ') }}</p>
            @empty<p class="mt-1 font-semibold text-slate-400">Aucune</p><p class="text-xs text-brand-700">Ajouter une ration</p>@endforelse
        </a>
        @endif
    </div>

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
                    <a href="{{ route('sessions.show', $s) }}" class="flex justify-between border-t border-slate-100 py-2 text-sm first:border-0"><span>{{ $s->scheduled_at->format('d/m/Y H:i') }} · {{ $s->typeLabel() }}<span class="block text-xs text-slate-500">{{ $s->objective }} · {{ $s->riderLabel() }}</span></span><x-time-status :status="$s->timeStatus()" /></a>
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
                        @php $isMain = $horse->main_photo_path === (string) $photo->id; @endphp
                        <div class="group relative" data-photo="{{ $photo->id }}">
                            <a href="{{ route('horses.photos.show', [$horse, $photo]) }}" data-lightbox="photos-{{ $horse->id }}" data-caption="{{ $photo->caption }}"><img src="{{ route('horses.photos.show', [$horse, $photo]) }}?thumb=1" alt="{{ $photo->caption }}" loading="lazy" class="aspect-square w-full rounded-lg object-cover"></a>
                            @if ($isMain)<span class="pointer-events-none absolute top-1.5 left-1.5 flex items-center gap-1 rounded-full bg-slate-900/70 px-2 py-0.5 text-[11px] font-medium text-white"><x-icon name="star" fill="currentColor" class="size-3" /> Principale</span>@endif
                            @if ($can('horse.edit'))
                                {{-- Boutons de 44 px, écartés : pas de suppression par erreur au doigt (confirmation dans l'application). --}}
                                <div class="mt-1 flex items-center justify-between">
                                    @unless ($isMain)
                                        <form method="POST" action="{{ route('horses.photos.main', [$horse, $photo]) }}">@csrf
                                            <button class="flex size-11 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 hover:text-brand-700" aria-label="Choisir comme photo principale" title="Choisir comme photo principale"><x-icon name="star" class="size-5" /></button>
                                        </form>
                                    @else
                                        <span></span>
                                    @endunless
                                    <form method="POST" action="{{ route('horses.photos.destroy', [$horse, $photo]) }}" data-confirm="Supprimer cette photo ? Elle sera définitivement effacée." data-confirm-ok="Supprimer">@csrf @method('DELETE')
                                        <button class="flex size-11 items-center justify-center rounded-full text-red-600 hover:bg-red-50" aria-label="Supprimer la photo" title="Supprimer la photo"><x-icon name="trash" class="size-5" /></button>
                                    </form>
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
                        <form method="POST" action="{{ route('horses.transfer', $horse) }}" class="space-y-2" data-confirm="Transférer la gestion de ce cheval ? L'historique est conservé." data-confirm-ok="Transférer">
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
